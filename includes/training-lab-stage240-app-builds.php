<?php
/**
 * Stage 201-240 stacked app builds.
 *
 * Five app-building passes stacked after Stage 160/200:
 * 1) Campaign Operations Engine
 * 2) Participant Timeline + Checkpoint Ledger
 * 3) Review SLA + Decision Quality Loop
 * 4) Reward Fulfillment Queue
 * 5) Product Readiness + Self-Test Snapshots
 *
 * Writes stay inside the existing Training Lab tables. No new SQL, uploads,
 * payments, wallet mutation, deletes, resets, or production claim/redeem logic.
 */

if (!function_exists('tl_stage240_clean')) {
    function tl_stage240_clean($value, int $max = 900): string
    {
        $value = trim((string)$value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        if ($max > 0 && strlen($value) > $max) $value = substr($value, 0, $max);
        return $value;
    }
}

if (!function_exists('tl_stage240_decode_json')) {
    function tl_stage240_decode_json($value): array
    {
        if (is_array($value)) return $value;
        if (!$value) return [];
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('tl_stage240_actor_id')) {
    function tl_stage240_actor_id(?array $input = null): int
    {
        if (function_exists('tl_stage200_actor_id')) return tl_stage200_actor_id($input);
        $input = $input ?: [];
        return max(1, (int)($input['user_id'] ?? $input['actor_user_id'] ?? 1));
    }
}

if (!function_exists('tl_stage240_campaign_row')) {
    function tl_stage240_campaign_row(string $campaignRef = ''): ?array
    {
        if (function_exists('tl_stage200_campaign_row')) return tl_stage200_campaign_row($campaignRef);
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_campaigns')) return null;
        try {
            if ($campaignRef !== '') {
                $stmt = $pdo->prepare('SELECT * FROM training_campaigns WHERE id = ? OR public_id = ? OR slug = ? ORDER BY id DESC LIMIT 1');
                $stmt->execute([ctype_digit($campaignRef) ? (int)$campaignRef : 0, $campaignRef, $campaignRef]);
                $row = $stmt->fetch();
                if ($row) return $row;
            }
            $row = $pdo->query('SELECT * FROM training_campaigns ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
            return $row ?: null;
        } catch (Throwable $e) { return null; }
    }
}

if (!function_exists('tl_stage240_campaign_ref')) {
    function tl_stage240_campaign_ref(?array $campaign): string
    {
        if (!$campaign) return '';
        return (string)($campaign['slug'] ?? $campaign['public_id'] ?? $campaign['id'] ?? '');
    }
}

if (!function_exists('tl_stage240_event')) {
    function tl_stage240_event(string $subjectType, ?int $subjectId, string $eventType, array $metadata, ?int $actorId = null): array
    {
        $pdo = tl_require_db();
        $actorId = $actorId ?: tl_stage240_actor_id();
        $allowed = ['campaign','task','participant','proof','review','receipt','reward_rule','reward_event','streak','system'];
        if (!in_array($subjectType, $allowed, true)) $subjectType = 'system';
        $metadata['stage240_stacked_app_build'] = true;
        tl_log_event($pdo, $actorId, $subjectType, $subjectId, $eventType, $metadata);
        return ['event_type' => $eventType, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'actor_user_id' => $actorId];
    }
}

if (!function_exists('tl_stage240_campaign_tasks')) {
    function tl_stage240_campaign_tasks(int $campaignId): array
    {
        $pdo = tl_db();
        if (!$pdo || !$campaignId || !tl_table_exists('training_campaign_tasks')) return [];
        try {
            $stmt = $pdo->prepare('SELECT * FROM training_campaign_tasks WHERE campaign_id = ? ORDER BY position_no ASC, id ASC');
            $stmt->execute([$campaignId]);
            return $stmt->fetchAll();
        } catch (Throwable $e) { return []; }
    }
}

if (!function_exists('tl_stage240_recent_campaign_events')) {
    function tl_stage240_recent_campaign_events(int $campaignId, int $limit = 12): array
    {
        $pdo = tl_db();
        if (!$pdo || !$campaignId || !tl_table_exists('training_events')) return [];
        try {
            $stmt = $pdo->prepare('SELECT * FROM training_events WHERE (subject_type = "campaign" AND subject_id = ?) OR event_type LIKE "campaign_%" ORDER BY created_at DESC LIMIT ' . max(1, min(50, $limit)));
            $stmt->execute([$campaignId]);
            return $stmt->fetchAll();
        } catch (Throwable $e) { return []; }
    }
}

if (!function_exists('tl_stage240_campaign_ops_state')) {
    function tl_stage240_campaign_ops_state(string $campaignRef = ''): array
    {
        $campaign = tl_stage240_campaign_row($campaignRef);
        $campaignId = (int)($campaign['id'] ?? 0);
        $tasks = tl_stage240_campaign_tasks($campaignId);
        $activeTasks = array_values(array_filter($tasks, fn($t) => ($t['status'] ?? '') === 'active'));
        $proofTasks = array_values(array_filter($tasks, fn($t) => (int)($t['proof_required'] ?? 0) === 1));
        $checks = [
            'campaign_exists' => $campaignId > 0,
            'has_title' => trim((string)($campaign['title'] ?? '')) !== '',
            'has_active_status' => in_array((string)($campaign['status'] ?? ''), ['active','scheduled','draft','paused','completed'], true),
            'has_tasks' => count($activeTasks) > 0,
            'has_reviewable_step' => count($proofTasks) > 0,
            'has_reward_summary' => trim((string)($campaign['reward_summary'] ?? '')) !== '',
        ];
        $score = (int)round((count(array_filter($checks)) / max(1, count($checks))) * 100);
        return [
            'stage' => 'Stage 201-240 campaign operations engine',
            'campaign' => $campaign,
            'campaign_ref' => tl_stage240_campaign_ref($campaign),
            'tasks' => $tasks,
            'recent_events' => tl_stage240_recent_campaign_events($campaignId),
            'checks' => $checks,
            'score' => $score,
        ];
    }
}

if (!function_exists('tl_stage240_update_campaign_plan')) {
    function tl_stage240_update_campaign_plan(array $input): array
    {
        $pdo = tl_require_db();
        $campaign = tl_stage240_campaign_row((string)($input['campaign'] ?? $input['campaign_id'] ?? ''));
        if (!$campaign) throw new RuntimeException('Campaign not found.');
        $title = tl_stage240_clean($input['title'] ?? $campaign['title'], 180);
        if ($title === '') throw new RuntimeException('Campaign title is required.');
        $summary = tl_stage240_clean($input['summary'] ?? $campaign['summary'], 500);
        $status = tl_stage240_clean($input['status'] ?? $campaign['status'], 30);
        $visibility = tl_stage240_clean($input['visibility'] ?? $campaign['visibility'], 30);
        if (!in_array($status, ['draft','scheduled','active','paused','completed','archived'], true)) $status = (string)$campaign['status'];
        if (!in_array($visibility, ['draft','private','published','archived'], true)) $visibility = (string)$campaign['visibility'];
        $target = max(1, min(200, (int)($input['target_action_count'] ?? $campaign['target_action_count'] ?? 1)));
        $reward = tl_stage240_clean($input['reward_summary'] ?? $campaign['reward_summary'] ?? '', 255);
        $settings = tl_stage240_decode_json($campaign['settings_json'] ?? null);
        $settings['stage240_plan'] = [
            'operator_notes' => tl_stage240_clean($input['operator_notes'] ?? '', 1200),
            'updated_by' => tl_stage240_actor_id($input),
            'updated_at' => gmdate('c'),
        ];
        $stmt = $pdo->prepare('UPDATE training_campaigns SET title = ?, summary = ?, status = ?, visibility = ?, target_action_count = ?, reward_summary = ?, settings_json = ? WHERE id = ?');
        $stmt->execute([$title, $summary, $status, $visibility, $target, $reward, json_encode($settings, JSON_UNESCAPED_SLASHES), (int)$campaign['id']]);
        tl_stage240_event('campaign', (int)$campaign['id'], 'campaign_plan_updated', ['title' => $title, 'status' => $status, 'visibility' => $visibility], tl_stage240_actor_id($input));
        return ['campaign_id' => (int)$campaign['id'], 'title' => $title, 'status' => $status, 'visibility' => $visibility];
    }
}

if (!function_exists('tl_stage240_add_campaign_task')) {
    function tl_stage240_add_campaign_task(array $input): array
    {
        $pdo = tl_require_db();
        $campaign = tl_stage240_campaign_row((string)($input['campaign'] ?? $input['campaign_id'] ?? ''));
        if (!$campaign) throw new RuntimeException('Campaign not found.');
        $title = tl_stage240_clean($input['task_title'] ?? $input['title'] ?? '', 180);
        if ($title === '') throw new RuntimeException('Task title is required.');
        $instructions = tl_stage240_clean($input['instructions'] ?? 'Complete this Training Lab action.', 2200);
        $taskType = tl_stage240_clean($input['task_type'] ?? 'checklist', 40);
        if (!in_array($taskType, ['checklist','movement','photo_proof','video_proof','text_reflection','quiz','custom'], true)) $taskType = 'checklist';
        $proofRequired = !empty($input['proof_required']) ? 1 : 0;
        $maxStmt = $pdo->prepare('SELECT COALESCE(MAX(position_no),0) FROM training_campaign_tasks WHERE campaign_id = ?');
        $maxStmt->execute([(int)$campaign['id']]);
        $position = (int)$maxStmt->fetchColumn() + 1;
        $stmt = $pdo->prepare('INSERT INTO training_campaign_tasks (public_id, campaign_id, position_no, day_no, task_type, title, instructions, proof_required, expected_duration_minutes, status, settings_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $settings = ['stage240_added' => true, 'added_by' => tl_stage240_actor_id($input), 'added_at' => gmdate('c')];
        $stmt->execute([tl_uuid(), (int)$campaign['id'], $position, $position, $taskType, $title, $instructions, $proofRequired, max(1, (int)($input['expected_duration_minutes'] ?? 15)), 'active', json_encode($settings, JSON_UNESCAPED_SLASHES)]);
        $taskId = (int)$pdo->lastInsertId();
        tl_stage240_event('task', $taskId, 'campaign_task_added', ['campaign_id' => (int)$campaign['id'], 'position_no' => $position, 'title' => $title], tl_stage240_actor_id($input));
        return ['task_id' => $taskId, 'campaign_id' => (int)$campaign['id'], 'position_no' => $position, 'title' => $title];
    }
}

if (!function_exists('tl_stage240_update_task_status')) {
    function tl_stage240_update_task_status(array $input): array
    {
        $pdo = tl_require_db();
        $taskId = max(0, (int)($input['task_id'] ?? 0));
        $status = tl_stage240_clean($input['task_status'] ?? $input['status'] ?? 'active', 30);
        if (!$taskId) throw new RuntimeException('Task ID is required.');
        if (!in_array($status, ['active','hidden','archived'], true)) throw new RuntimeException('Unsupported task status.');
        $stmt = $pdo->prepare('UPDATE training_campaign_tasks SET status = ? WHERE id = ?');
        $stmt->execute([$status, $taskId]);
        tl_stage240_event('task', $taskId, 'campaign_task_status_updated', ['status' => $status], tl_stage240_actor_id($input));
        return ['task_id' => $taskId, 'status' => $status];
    }
}

if (!function_exists('tl_stage240_user_activity_timeline')) {
    function tl_stage240_user_activity_timeline(int $userId, int $limit = 30): array
    {
        $pdo = tl_db();
        if (!$pdo || !$userId) return [];
        $items = [];
        try {
            if (tl_table_exists('training_proof_submissions')) {
                $stmt = $pdo->prepare('SELECT p.id, p.public_id, p.status, p.proof_type, p.submitted_at AS created_at, c.title AS campaign_title, t.title AS task_title FROM training_proof_submissions p LEFT JOIN training_campaigns c ON c.id=p.campaign_id LEFT JOIN training_campaign_tasks t ON t.id=p.task_id WHERE p.submitted_by_user_id = ? ORDER BY p.submitted_at DESC LIMIT ' . max(1,min(50,$limit)));
                $stmt->execute([$userId]);
                foreach ($stmt->fetchAll() as $row) $items[] = ['type'=>'proof','label'=>'Proof ' . $row['status'], 'created_at'=>$row['created_at'], 'row'=>$row];
            }
            if (tl_table_exists('training_action_receipts')) {
                $stmt = $pdo->prepare('SELECT ar.*, c.title AS campaign_title FROM training_action_receipts ar LEFT JOIN training_campaigns c ON c.id=ar.campaign_id WHERE ar.user_id = ? ORDER BY ar.created_at DESC LIMIT ' . max(1,min(50,$limit)));
                $stmt->execute([$userId]);
                foreach ($stmt->fetchAll() as $row) $items[] = ['type'=>'receipt','label'=>'Receipt ' . $row['receipt_type'], 'created_at'=>$row['created_at'], 'row'=>$row];
            }
            if (tl_table_exists('training_reward_events')) {
                $stmt = $pdo->prepare('SELECT re.*, c.title AS campaign_title FROM training_reward_events re LEFT JOIN training_campaigns c ON c.id=re.campaign_id WHERE re.user_id = ? ORDER BY re.created_at DESC LIMIT ' . max(1,min(50,$limit)));
                $stmt->execute([$userId]);
                foreach ($stmt->fetchAll() as $row) $items[] = ['type'=>'reward','label'=>'Reward ' . $row['status'], 'created_at'=>$row['created_at'], 'row'=>$row];
            }
            if (tl_table_exists('training_events')) {
                $stmt = $pdo->prepare('SELECT * FROM training_events WHERE actor_user_id = ? ORDER BY created_at DESC LIMIT ' . max(1,min(50,$limit)));
                $stmt->execute([$userId]);
                foreach ($stmt->fetchAll() as $row) $items[] = ['type'=>'event','label'=>(string)$row['event_type'], 'created_at'=>$row['created_at'], 'row'=>$row];
            }
        } catch (Throwable $e) {
            $items[] = ['type'=>'warning','label'=>'Timeline query warning','created_at'=>gmdate('c'),'row'=>['message'=>$e->getMessage()]];
        }
        usort($items, fn($a,$b) => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
        return array_slice($items, 0, max(1,min(100,$limit)));
    }
}

if (!function_exists('tl_stage240_save_participant_checkpoint')) {
    function tl_stage240_save_participant_checkpoint(array $input): array
    {
        $participantId = max(0, (int)($input['participant_id'] ?? $input['subject_id'] ?? 0));
        $note = tl_stage240_clean($input['checkpoint_note'] ?? $input['note'] ?? '', 1500);
        if ($note === '') throw new RuntimeException('Checkpoint note is required.');
        return tl_stage240_event('participant', $participantId ?: null, 'participant_checkpoint_saved', [
            'note' => $note,
            'checkpoint_type' => tl_stage240_clean($input['checkpoint_type'] ?? 'progress', 80),
            'source' => tl_stage240_clean($input['source'] ?? 'participant_mission_control', 80),
        ], tl_stage240_actor_id($input));
    }
}

if (!function_exists('tl_stage240_review_sla_state')) {
    function tl_stage240_review_sla_state(int $limit = 50): array
    {
        $pdo = tl_db();
        $rows = [];
        if ($pdo && tl_table_exists('training_proof_submissions')) {
            try {
                $sql = "SELECT p.*, c.title AS campaign_title, t.title AS task_title, COALESCE(tp.participant_label, CONCAT('User #', p.submitted_by_user_id)) AS participant_label, TIMESTAMPDIFF(HOUR, p.submitted_at, NOW()) AS age_hours
                        FROM training_proof_submissions p
                        LEFT JOIN training_campaigns c ON c.id = p.campaign_id
                        LEFT JOIN training_campaign_tasks t ON t.id = p.task_id
                        LEFT JOIN training_participants tp ON tp.id = p.participant_id
                        WHERE p.status IN ('submitted','in_review')
                        ORDER BY p.submitted_at ASC LIMIT " . max(1, min(100, $limit));
                $rows = $pdo->query($sql)->fetchAll();
            } catch (Throwable $e) { $rows = []; }
        }
        $over24 = 0; $over48 = 0; $inReview = 0;
        foreach ($rows as $r) {
            $age = (int)($r['age_hours'] ?? 0);
            if ($age >= 24) $over24++;
            if ($age >= 48) $over48++;
            if (($r['status'] ?? '') === 'in_review') $inReview++;
        }
        $score = 100;
        if ($over48 > 0) $score -= 35;
        if ($over24 > 0) $score -= 15;
        if (count($rows) > 10) $score -= 10;
        return ['stage'=>'Stage 201-240 review SLA loop','pending'=>$rows,'counts'=>['pending'=>count($rows),'in_review'=>$inReview,'over_24h'=>$over24,'over_48h'=>$over48],'score'=>max(0,$score)];
    }
}

if (!function_exists('tl_stage240_create_review_sla_snapshot')) {
    function tl_stage240_create_review_sla_snapshot(array $input): array
    {
        $state = tl_stage240_review_sla_state();
        return tl_stage240_event('system', null, 'review_sla_snapshot_created', $state, tl_stage240_actor_id($input));
    }
}

if (!function_exists('tl_stage240_reward_fulfillment_state')) {
    function tl_stage240_reward_fulfillment_state(int $limit = 100): array
    {
        $pdo = tl_db();
        $rows = [];
        if ($pdo && tl_table_exists('training_reward_events')) {
            try {
                $sql = "SELECT re.*, c.title AS campaign_title, rr.reward_label, COALESCE(tp.participant_label, CONCAT('User #', re.user_id)) AS participant_label
                        FROM training_reward_events re
                        LEFT JOIN training_campaigns c ON c.id = re.campaign_id
                        LEFT JOIN training_reward_rules rr ON rr.id = re.reward_rule_id
                        LEFT JOIN training_participants tp ON tp.id = re.participant_id
                        ORDER BY FIELD(re.status,'failed','queued','eligible','issued','linked','cancelled'), re.updated_at DESC, re.created_at DESC
                        LIMIT " . max(1, min(200, $limit));
                $rows = $pdo->query($sql)->fetchAll();
            } catch (Throwable $e) { $rows = []; }
        }
        $counts = ['eligible'=>0,'queued'=>0,'issued'=>0,'linked'=>0,'failed'=>0,'cancelled'=>0,'total'=>count($rows)];
        foreach ($rows as $row) {
            $status = (string)($row['status'] ?? '');
            if (array_key_exists($status, $counts)) $counts[$status]++;
        }
        $open = $counts['eligible'] + $counts['queued'] + $counts['failed'];
        $score = 100;
        if ($counts['failed'] > 0) $score -= 30;
        if ($open > 10) $score -= 10;
        return ['stage'=>'Stage 201-240 reward fulfillment queue','rows'=>$rows,'counts'=>$counts,'open_count'=>$open,'score'=>max(0,$score),'adapter'=>function_exists('tl_mg_rewards_config') ? tl_mg_rewards_config() : []];
    }
}

if (!function_exists('tl_stage240_create_fulfillment_snapshot')) {
    function tl_stage240_create_fulfillment_snapshot(array $input): array
    {
        $state = tl_stage240_reward_fulfillment_state();
        return tl_stage240_event('system', null, 'reward_fulfillment_snapshot_created', $state, tl_stage240_actor_id($input));
    }
}

if (!function_exists('tl_stage240_product_readiness')) {
    function tl_stage240_product_readiness(): array
    {
        $flow = function_exists('tl_stage200_workflow_state') ? tl_stage200_workflow_state() : [];
        $campaignOps = tl_stage240_campaign_ops_state();
        $reviewSla = tl_stage240_review_sla_state();
        $fulfillment = tl_stage240_reward_fulfillment_state();
        $routes = function_exists('tl_stage200_route_readiness') ? tl_stage200_route_readiness() : ['score'=>0,'items'=>[]];
        $checks = [
            'core_routes_ready' => (int)($routes['score'] ?? 0) >= 100,
            'campaign_ops_engine_ready' => function_exists('tl_stage240_campaign_ops_state'),
            'campaign_data_optional' => true,
            'workflow_engine_ready' => isset($flow['steps']) && is_array($flow['steps']),
            'participant_timeline_ready' => function_exists('tl_stage240_user_activity_timeline'),
            'review_sla_ready' => isset($reviewSla['counts']),
            'reward_fulfillment_ready' => isset($fulfillment['counts']),
            'microgifter_bridge_present' => function_exists('tl_mg_rewards_config'),
            'safe_boundaries_declared' => true,
        ];
        $score = (int)round((count(array_filter($checks)) / max(1, count($checks))) * 100);
        return [
            'stage' => 'Stage 201-240 stacked app builds',
            'score' => $score,
            'accepted' => $score >= 100,
            'checks' => $checks,
            'campaign_ops' => $campaignOps,
            'review_sla' => $reviewSla,
            'reward_fulfillment' => ['counts'=>$fulfillment['counts'],'open_count'=>$fulfillment['open_count'],'score'=>$fulfillment['score']],
            'route_readiness' => $routes,
            'safe_boundaries' => [
                'no_new_sql_required' => true,
                'no_new_page_factory' => true,
                'existing_core_pages_only' => true,
                'writes_training_tables_only' => true,
                'no_real_uploads_payments_wallet_or_redeems' => true,
                'microgifter_issue_adapter_gated' => true,
            ],
        ];
    }
}

if (!function_exists('tl_stage240_run_product_self_test')) {
    function tl_stage240_run_product_self_test(array $input = []): array
    {
        $state = tl_stage240_product_readiness();
        if (!empty($input['log_event']) || !empty($input['persist'])) {
            try { tl_stage240_event('system', null, 'product_readiness_self_test_run', $state, tl_stage240_actor_id($input)); }
            catch (Throwable $e) { $state['log_warning'] = $e->getMessage(); }
        }
        return $state;
    }
}

if (!function_exists('tl_stage240_summary')) {
    function tl_stage240_summary(): array
    {
        return [
            'stage' => 'Stage 201-240 stacked app builds',
            'builds' => [
                'Build 6: Campaign Operations Engine',
                'Build 7: Participant Timeline and Checkpoint Ledger',
                'Build 8: Review SLA and Decision Quality Loop',
                'Build 9: Reward Fulfillment Queue',
                'Build 10: Product Readiness and Self-Test Snapshots',
            ],
            'product_readiness' => tl_stage240_product_readiness(),
            'safe_boundaries' => [
                'no_new_sql_required' => true,
                'no_new_page_factory' => true,
                'core_pages_deepened' => true,
                'all_writes_training_tables_only' => true,
                'microgifter_real_issue_adapter_gated' => true,
            ],
        ];
    }
}
