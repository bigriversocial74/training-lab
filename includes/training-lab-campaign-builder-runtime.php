<?php
/**
 * Section 19 runtime hardening for the merchant campaign builder.
 *
 * This layer keeps the original builder read model intact while routing the
 * mutable task/reward operations through stricter prerequisite, due-date,
 * dependency-cleanup, and established reward-schema contracts.
 */
require_once __DIR__ . '/training-lab-campaign-builder.php';

if (!function_exists('tl_campaign_builder_runtime_timezone')) {
    function tl_campaign_builder_runtime_timezone(array $campaign): string
    {
        $settings = tl_campaign_builder_json($campaign['settings_json'] ?? null);
        $builder = is_array($settings['builder'] ?? null) ? $settings['builder'] : [];
        $timezone = trim((string)($campaign['timezone'] ?? $builder['timezone'] ?? 'America/Phoenix'));
        try {
            new DateTimeZone($timezone);
            return $timezone;
        } catch (Throwable $e) {
            return 'America/Phoenix';
        }
    }
}

if (!function_exists('tl_campaign_builder_runtime_due_at')) {
    function tl_campaign_builder_runtime_due_at($value, string $timezone): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : tl_campaign_builder_normalize_datetime($value, $timezone);
    }
}

if (!function_exists('tl_campaign_builder_runtime_prerequisite')) {
    function tl_campaign_builder_runtime_prerequisite(PDO $pdo, array $user, int $campaignId, int $position, int $taskId, $value): ?int
    {
        $prerequisiteId = max(0, (int)$value);
        if ($prerequisiteId === 0) return null;
        if ($taskId > 0 && $prerequisiteId === $taskId) {
            throw new TlHttpException('A task cannot require itself.', 422, 'campaign_builder_prerequisite_self');
        }
        $prerequisite = tl_campaign_builder_task_row($pdo, $user, $prerequisiteId, true);
        if ((int)$prerequisite['campaign_id'] !== $campaignId || (string)$prerequisite['status'] === 'archived') {
            throw new TlHttpException('Prerequisite must be an available task in the same campaign.', 422, 'campaign_builder_prerequisite_invalid');
        }
        if ((int)$prerequisite['position_no'] >= $position) {
            throw new TlHttpException('Prerequisite must appear before the task in the campaign path.', 422, 'campaign_builder_prerequisite_order_invalid');
        }
        return $prerequisiteId;
    }
}

if (!function_exists('tl_campaign_builder_runtime_settings')) {
    function tl_campaign_builder_runtime_settings(array $existing, array $input, int $proofRequired, ?int $prerequisiteId, string $timezone, array $actor, bool $created): array
    {
        $dueAt = tl_campaign_builder_runtime_due_at($input['due_at'] ?? '', $timezone);
        $closeAfterDue = tl_campaign_builder_bool($input['close_after_due'] ?? false);
        $proofInstructions = tl_campaign_builder_clean($input['proof_instructions'] ?? '', 1200, $proofRequired === 1, 'Proof instructions');
        $settings = $existing;
        $settings['due_at'] = $dueAt;
        $settings['close_after_due'] = $closeAfterDue;
        $settings['prerequisite_task_id'] = $prerequisiteId;
        $settings['builder'] = array_merge(is_array($settings['builder'] ?? null) ? $settings['builder'] : [], [
            'proof_instructions'=>$proofInstructions,
            'due_at'=>$dueAt,
            'close_after_due'=>$closeAfterDue,
            'prerequisite_task_id'=>$prerequisiteId,
            ($created ? 'created_by' : 'updated_by')=>$actor['user_id'],
            ($created ? 'created_at' : 'updated_at')=>gmdate('c'),
        ]);
        return $settings;
    }
}

if (!function_exists('tl_campaign_builder_add_task_v2')) {
    function tl_campaign_builder_add_task_v2(array $user, array $input): array
    {
        $pdo = tl_require_db();
        $actor = tl_campaign_builder_actor($user);
        $pdo->beginTransaction();
        try {
            $campaign = tl_campaign_builder_owned_campaign($pdo, $user, (string)($input['campaign'] ?? ''), true);
            if ((string)$campaign['status'] === 'archived') throw new TlHttpException('Archived campaigns are immutable.', 409, 'campaign_builder_archived_immutable');
            $title = tl_campaign_builder_clean($input['title'] ?? '', 180, true, 'Task title');
            $instructions = tl_campaign_builder_clean($input['instructions'] ?? '', 5000, true, 'Task instructions');
            $taskType = tl_action_enum($input['task_type'] ?? 'checklist', ['checklist','movement','photo_proof','video_proof','text_reflection','quiz','custom'], 'checklist');
            $proofRequired = tl_campaign_builder_bool($input['proof_required'] ?? false) ? 1 : 0;
            $duration = max(1, min(1440, (int)($input['expected_duration_minutes'] ?? 15)));
            $max = $pdo->prepare('SELECT COALESCE(MAX(position_no),0) FROM training_campaign_tasks WHERE campaign_id = ? FOR UPDATE');
            $max->execute([(int)$campaign['id']]);
            $position = (int)$max->fetchColumn() + 1;
            $prerequisiteId = tl_campaign_builder_runtime_prerequisite($pdo, $user, (int)$campaign['id'], $position, 0, $input['prerequisite_task_id'] ?? 0);
            $settings = tl_campaign_builder_runtime_settings([], $input, $proofRequired, $prerequisiteId, tl_campaign_builder_runtime_timezone($campaign), $actor, true);
            $stmt = $pdo->prepare('INSERT INTO training_campaign_tasks (public_id, campaign_id, position_no, day_no, task_type, title, instructions, proof_required, expected_duration_minutes, status, settings_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([tl_uuid(),(int)$campaign['id'],$position,$position,$taskType,$title,$instructions,$proofRequired,$duration,'active',json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
            $taskId = (int)$pdo->lastInsertId();
            tl_log_event($pdo, $actor['user_id'], 'task', $taskId, 'campaign_builder_task_created', ['campaign_id'=>(int)$campaign['id'],'position'=>$position,'prerequisite_task_id'=>$prerequisiteId]);
            $pdo->commit();
            return ['campaign_ref'=>(string)$campaign['public_id'],'task_id'=>$taskId,'position'=>$position];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_campaign_builder_update_task_v2')) {
    function tl_campaign_builder_update_task_v2(array $user, array $input): array
    {
        $pdo = tl_require_db();
        $actor = tl_campaign_builder_actor($user);
        $taskId = max(1, (int)($input['task_id'] ?? 0));
        $pdo->beginTransaction();
        try {
            $task = tl_campaign_builder_task_row($pdo, $user, $taskId, true);
            $campaign = tl_campaign_builder_owned_campaign($pdo, $user, (string)$task['campaign_id'], true);
            if ((string)$campaign['status'] === 'archived') throw new TlHttpException('Archived campaigns are immutable.', 409, 'campaign_builder_archived_immutable');
            $title = tl_campaign_builder_clean($input['title'] ?? $task['title'], 180, true, 'Task title');
            $instructions = tl_campaign_builder_clean($input['instructions'] ?? $task['instructions'], 5000, true, 'Task instructions');
            $taskType = tl_action_enum($input['task_type'] ?? $task['task_type'], ['checklist','movement','photo_proof','video_proof','text_reflection','quiz','custom'], 'checklist');
            $proofRequired = tl_campaign_builder_bool($input['proof_required'] ?? false) ? 1 : 0;
            $duration = max(1, min(1440, (int)($input['expected_duration_minutes'] ?? $task['expected_duration_minutes'] ?? 15)));
            $status = tl_action_enum($input['task_status'] ?? $task['status'], ['active','hidden','archived'], 'active');
            $prerequisiteId = tl_campaign_builder_runtime_prerequisite($pdo, $user, (int)$campaign['id'], (int)$task['position_no'], $taskId, $input['prerequisite_task_id'] ?? 0);
            $settings = tl_campaign_builder_runtime_settings(tl_campaign_builder_json($task['settings_json'] ?? null), $input, $proofRequired, $prerequisiteId, tl_campaign_builder_runtime_timezone($campaign), $actor, false);
            $stmt = $pdo->prepare('UPDATE training_campaign_tasks SET title = ?, instructions = ?, task_type = ?, proof_required = ?, expected_duration_minutes = ?, status = ?, settings_json = ? WHERE id = ? AND campaign_id = ?');
            $stmt->execute([$title,$instructions,$taskType,$proofRequired,$duration,$status,json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),$taskId,(int)$campaign['id']]);
            $updatedTasks = tl_campaign_builder_tasks($pdo, (int)$campaign['id']);
            $rules = tl_campaign_builder_reward_rules($pdo, (int)$campaign['id']);
            $readiness = tl_campaign_builder_readiness($campaign, $updatedTasks, $rules);
            if (in_array((string)$campaign['status'], ['active','scheduled'], true) || (string)$campaign['visibility'] === 'published') {
                if (!$readiness['ready']) throw new TlHttpException('A published campaign cannot be left in an incomplete task state: ' . implode(', ', $readiness['failed']) . '.', 422, 'campaign_builder_live_task_not_ready');
            }
            tl_log_event($pdo, $actor['user_id'], 'task', $taskId, 'campaign_builder_task_updated', ['campaign_id'=>(int)$campaign['id'],'status'=>$status,'prerequisite_task_id'=>$prerequisiteId]);
            $pdo->commit();
            return ['campaign_ref'=>(string)$campaign['public_id'],'task_id'=>$taskId,'status'=>$status,'readiness'=>$readiness];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_campaign_builder_clear_prerequisite_references')) {
    function tl_campaign_builder_clear_prerequisite_references(PDO $pdo, int $campaignId, int $removedTaskId): int
    {
        $stmt = $pdo->prepare('SELECT id, settings_json FROM training_campaign_tasks WHERE campaign_id = ? AND id <> ? FOR UPDATE');
        $stmt->execute([$campaignId, $removedTaskId]);
        $update = $pdo->prepare('UPDATE training_campaign_tasks SET settings_json = ? WHERE id = ? AND campaign_id = ?');
        $count = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $settings = tl_campaign_builder_json($row['settings_json'] ?? null);
            $builder = is_array($settings['builder'] ?? null) ? $settings['builder'] : [];
            if ((int)($settings['prerequisite_task_id'] ?? 0) !== $removedTaskId && (int)($builder['prerequisite_task_id'] ?? 0) !== $removedTaskId) continue;
            $settings['prerequisite_task_id'] = null;
            $builder['prerequisite_task_id'] = null;
            $settings['builder'] = $builder;
            $update->execute([json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),(int)$row['id'],$campaignId]);
            $count++;
        }
        return $count;
    }
}

if (!function_exists('tl_campaign_builder_delete_task_v2')) {
    function tl_campaign_builder_delete_task_v2(array $user, array $input): array
    {
        $pdo = tl_require_db();
        $actor = tl_campaign_builder_actor($user);
        $taskId = max(1, (int)($input['task_id'] ?? 0));
        $pdo->beginTransaction();
        try {
            $task = tl_campaign_builder_task_row($pdo, $user, $taskId, true);
            $campaign = tl_campaign_builder_owned_campaign($pdo, $user, (string)$task['campaign_id'], true);
            if ((string)$campaign['status'] === 'archived') throw new TlHttpException('Archived campaigns are immutable.', 409, 'campaign_builder_archived_immutable');
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM training_campaign_tasks WHERE campaign_id = ? AND status = 'active'");
            $countStmt->execute([(int)$campaign['id']]);
            if ((string)$task['status'] === 'active' && (int)$countStmt->fetchColumn() <= 1) throw new TlHttpException('A campaign must keep at least one active task.', 409, 'campaign_builder_last_task');
            $proofStmt = $pdo->prepare('SELECT COUNT(*) FROM training_proof_submissions WHERE task_id = ?');
            $proofStmt->execute([$taskId]);
            $referenced = (int)$proofStmt->fetchColumn() > 0;
            $cleared = tl_campaign_builder_clear_prerequisite_references($pdo, (int)$campaign['id'], $taskId);
            if ($referenced) {
                $stmt = $pdo->prepare("UPDATE training_campaign_tasks SET status = 'archived' WHERE id = ? AND campaign_id = ?");
                $stmt->execute([$taskId,(int)$campaign['id']]);
                $result = 'archived';
            } else {
                $stmt = $pdo->prepare('DELETE FROM training_campaign_tasks WHERE id = ? AND campaign_id = ?');
                $stmt->execute([$taskId,(int)$campaign['id']]);
                $result = 'deleted';
            }
            $remaining = tl_campaign_builder_tasks($pdo, (int)$campaign['id']);
            $positionStmt = $pdo->prepare('UPDATE training_campaign_tasks SET position_no = ?, day_no = ? WHERE id = ? AND campaign_id = ?');
            $position = 0;
            foreach ($remaining as $row) {
                if ((int)$row['id'] === $taskId && $result === 'archived') continue;
                $position++;
                $positionStmt->execute([$position,$position,(int)$row['id'],(int)$campaign['id']]);
            }
            tl_log_event($pdo, $actor['user_id'], 'task', $taskId, 'campaign_builder_task_removed', ['campaign_id'=>(int)$campaign['id'],'result'=>$result,'prerequisites_cleared'=>$cleared]);
            $pdo->commit();
            return ['campaign_ref'=>(string)$campaign['public_id'],'task_id'=>$taskId,'result'=>$result,'prerequisites_cleared'=>$cleared];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_campaign_builder_attach_reward_v2')) {
    function tl_campaign_builder_attach_reward_v2(array $user, array $input): array
    {
        $pdo = tl_require_db();
        $actor = tl_campaign_builder_actor($user);
        $pdo->beginTransaction();
        try {
            $campaign = tl_campaign_builder_owned_campaign($pdo, $user, (string)($input['campaign'] ?? ''), true);
            if ((string)$campaign['status'] === 'archived') throw new TlHttpException('Archived campaigns are immutable.', 409, 'campaign_builder_archived_immutable');
            $sourceRuleId = max(0, (int)($input['source_rule_id'] ?? 0));
            $source = null;
            if ($sourceRuleId > 0) {
                $sql = 'SELECT r.*, c.owner_user_id FROM training_reward_rules r INNER JOIN training_campaigns c ON c.id = r.campaign_id WHERE r.id = ?';
                $params = [$sourceRuleId];
                if (!$actor['is_admin']) { $sql .= ' AND c.owner_user_id = ?'; $params[] = $actor['user_id']; }
                $sql .= ' LIMIT 1 FOR UPDATE';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $source = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if (!$source) throw new TlHttpException('Reward rule was not found in your merchant workspace.', 404, 'campaign_builder_reward_not_found');
            }
            $label = tl_campaign_builder_clean($input['reward_label'] ?? ($source['reward_label'] ?? 'Training completion'), 255, true, 'Reward label');
            $type = tl_action_enum($input['reward_type'] ?? ($source['reward_type'] ?? 'badge'), ['badge','microgift','entitlement','wallet_credit_preview','custom'], 'badge');
            $value = max(0, min(100000000, (int)($input['reward_value_cents'] ?? $source['reward_value_cents'] ?? 0)));
            $currency = strtoupper(tl_campaign_builder_clean($input['currency'] ?? ($source['currency'] ?? 'USD'), 3));
            if (!preg_match('/^[A-Z]{3}$/', $currency)) throw new TlHttpException('Currency must be a three-letter code.', 422, 'campaign_builder_currency_invalid');
            $requestedTrigger = (string)($input['trigger_type'] ?? $source['trigger_type'] ?? 'sequence_completed');
            if ($requestedTrigger === 'campaign_completion') $requestedTrigger = 'sequence_completed';
            $trigger = tl_action_enum($requestedTrigger, ['action_count','sequence_completed','streak_days','manual'], 'sequence_completed');
            $threshold = in_array($trigger, ['sequence_completed','manual'], true) ? 1 : max(1, min(10000, (int)($input['threshold_count'] ?? $source['threshold_count'] ?? 1)));
            $settings = tl_campaign_builder_json($source['settings_json'] ?? null);
            $settings['builder'] = array_merge(is_array($settings['builder'] ?? null) ? $settings['builder'] : [], ['attached_by'=>$actor['user_id'],'attached_at'=>gmdate('c'),'source_rule_id'=>$sourceRuleId ?: null]);
            $stmt = $pdo->prepare("INSERT INTO training_reward_rules (public_id, campaign_id, rule_name, trigger_type, threshold_count, reward_type, reward_label, reward_value_cents, currency, linked_microgift_template_id, linked_catalog_product_id, status, settings_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)");
            $stmt->execute([tl_uuid(),(int)$campaign['id'],$label . ' eligibility',$trigger,$threshold,$type,$label,$value,$currency,$source['linked_microgift_template_id'] ?? null,$source['linked_catalog_product_id'] ?? null,json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
            $ruleId = (int)$pdo->lastInsertId();
            $summary = $pdo->prepare('UPDATE training_campaigns SET reward_summary = ? WHERE id = ?');
            $summary->execute([$label,(int)$campaign['id']]);
            tl_log_event($pdo, $actor['user_id'], 'reward_rule', $ruleId, 'campaign_builder_reward_attached', ['campaign_id'=>(int)$campaign['id'],'reward_type'=>$type,'trigger_type'=>$trigger]);
            $pdo->commit();
            return ['campaign_ref'=>(string)$campaign['public_id'],'reward_rule_id'=>$ruleId,'trigger_type'=>$trigger];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_campaign_builder_duplicate_v2')) {
    function tl_campaign_builder_duplicate_v2(array $user, array $input): array
    {
        $pdo = tl_require_db();
        $source = tl_campaign_builder_owned_campaign($pdo, $user, (string)($input['campaign'] ?? ''));
        $sourceTasks = array_values(array_filter(tl_campaign_builder_tasks($pdo, (int)$source['id']), static fn(array $task): bool => (string)$task['status'] !== 'archived'));
        $created = tl_campaign_builder_duplicate($user, $input);
        $new = tl_campaign_builder_owned_campaign($pdo, $user, (string)$created['campaign_id']);
        $newTasks = tl_campaign_builder_tasks($pdo, (int)$new['id']);
        $idMap = [];
        foreach ($sourceTasks as $index=>$task) if (isset($newTasks[$index])) $idMap[(int)$task['id']] = (int)$newTasks[$index]['id'];
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare('UPDATE training_campaign_tasks SET settings_json = ? WHERE id = ? AND campaign_id = ?');
            foreach ($sourceTasks as $index=>$task) {
                if (!isset($newTasks[$index])) continue;
                $settings = tl_campaign_builder_json($task['settings_json'] ?? null);
                $oldPrerequisite = (int)($settings['prerequisite_task_id'] ?? $settings['builder']['prerequisite_task_id'] ?? 0);
                $newPrerequisite = $oldPrerequisite > 0 ? ($idMap[$oldPrerequisite] ?? null) : null;
                $settings['prerequisite_task_id'] = $newPrerequisite;
                $settings['builder'] = array_merge(is_array($settings['builder'] ?? null) ? $settings['builder'] : [], ['prerequisite_task_id'=>$newPrerequisite,'duplicated_from_task_id'=>(int)$task['id']]);
                $update->execute([json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),(int)$newTasks[$index]['id'],(int)$new['id']]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        return $created + ['campaign_ref'=>(string)$new['public_id']];
    }
}
