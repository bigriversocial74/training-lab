<?php
/**
 * Merchant-owned campaign and task builder.
 *
 * This service writes only Training Lab campaign, task, reward-rule, and audit
 * tables. It does not upload files, send email, issue rewards, mutate wallets,
 * process payments, or call Microgifter.
 */
require_once __DIR__ . '/training-lab-actions.php';
require_once __DIR__ . '/training-lab-product-shell.php';

if (!function_exists('tl_campaign_builder_actor')) {
    function tl_campaign_builder_actor(array $user): array
    {
        $role = function_exists('tl_product_role') ? tl_product_role($user) : (string)($user['role'] ?? 'participant');
        if (!in_array($role, ['manager', 'admin'], true)) {
            throw new TlHttpException('Merchant-manager access is required.', 403, 'campaign_builder_manager_required');
        }
        return [
            'user_id' => function_exists('tl_security_numeric_user_id') ? tl_security_numeric_user_id($user) : max(1, (int)($user['numeric_user_id'] ?? $user['id'] ?? 1)),
            'role' => $role,
            'is_admin' => $role === 'admin',
        ];
    }
}

if (!function_exists('tl_campaign_builder_clean')) {
    function tl_campaign_builder_clean($value, int $max = 500, bool $required = false, string $label = 'Value'): string
    {
        return tl_action_clean($value, $max, $required, $label);
    }
}

if (!function_exists('tl_campaign_builder_json')) {
    function tl_campaign_builder_json($value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('tl_campaign_builder_bool')) {
    function tl_campaign_builder_bool($value): bool
    {
        if (is_bool($value)) return $value;
        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('tl_campaign_builder_columns')) {
    function tl_campaign_builder_columns(PDO $pdo, string $table): array
    {
        static $cache = [];
        $allowed = ['training_campaigns', 'training_campaign_tasks', 'training_reward_rules'];
        if (!in_array($table, $allowed, true)) return [];
        if (isset($cache[$table])) return $cache[$table];
        try {
            $rows = $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return $cache[$table] = array_values(array_filter(array_map(static fn(array $row): string => (string)($row['Field'] ?? ''), $rows)));
        } catch (Throwable $e) {
            return $cache[$table] = [];
        }
    }
}

if (!function_exists('tl_campaign_builder_normalize_datetime')) {
    function tl_campaign_builder_normalize_datetime($value, string $timezone): ?string
    {
        $value = trim((string)$value);
        if ($value === '') return null;
        try {
            $zone = new DateTimeZone($timezone !== '' ? $timezone : 'America/Phoenix');
            return (new DateTimeImmutable($value, $zone))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            throw new TlHttpException('A campaign date or time is invalid.', 422, 'campaign_builder_datetime_invalid');
        }
    }
}

if (!function_exists('tl_campaign_builder_owned_campaign')) {
    function tl_campaign_builder_owned_campaign(PDO $pdo, array $user, string $campaignRef, bool $forUpdate = false): array
    {
        $actor = tl_campaign_builder_actor($user);
        $campaignRef = tl_campaign_builder_clean($campaignRef, 180, true, 'Campaign reference');
        $sql = 'SELECT * FROM training_campaigns WHERE (id = ? OR public_id = ? OR slug = ?)';
        $params = [ctype_digit($campaignRef) ? (int)$campaignRef : 0, $campaignRef, $campaignRef];
        if (!$actor['is_admin']) {
            $sql .= ' AND owner_user_id = ?';
            $params[] = $actor['user_id'];
        }
        $sql .= ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$campaign) throw new TlHttpException('Campaign was not found in your merchant workspace.', 404, 'campaign_builder_campaign_not_found');
        return $campaign;
    }
}

if (!function_exists('tl_campaign_builder_list_campaigns')) {
    function tl_campaign_builder_list_campaigns(array $user, int $limit = 100): array
    {
        $pdo = tl_require_db();
        $actor = tl_campaign_builder_actor($user);
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT c.*,
            (SELECT COUNT(*) FROM training_campaign_tasks t WHERE t.campaign_id = c.id AND t.status <> \'archived\') AS task_count,
            (SELECT COUNT(*) FROM training_participants p WHERE p.campaign_id = c.id AND p.status <> \'removed\') AS participant_count,
            (SELECT COUNT(*) FROM training_reward_rules r WHERE r.campaign_id = c.id AND r.status = \'active\') AS active_reward_count
            FROM training_campaigns c';
        $params = [];
        if (!$actor['is_admin']) {
            $sql .= ' WHERE c.owner_user_id = ?';
            $params[] = $actor['user_id'];
        }
        $sql .= ' ORDER BY CASE c.status WHEN \'active\' THEN 0 WHEN \'scheduled\' THEN 1 WHEN \'draft\' THEN 2 WHEN \'paused\' THEN 3 WHEN \'completed\' THEN 4 ELSE 5 END, c.updated_at DESC, c.id DESC LIMIT ' . $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('tl_campaign_builder_tasks')) {
    function tl_campaign_builder_tasks(PDO $pdo, int $campaignId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM training_campaign_tasks WHERE campaign_id = ? ORDER BY position_no ASC, id ASC');
        $stmt->execute([$campaignId]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($tasks as &$task) $task['builder_settings'] = tl_campaign_builder_json($task['settings_json'] ?? null)['builder'] ?? [];
        unset($task);
        return $tasks;
    }
}

if (!function_exists('tl_campaign_builder_reward_rules')) {
    function tl_campaign_builder_reward_rules(PDO $pdo, int $campaignId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM training_reward_rules WHERE campaign_id = ? ORDER BY CASE status WHEN \'active\' THEN 0 WHEN \'paused\' THEN 1 WHEN \'draft\' THEN 2 ELSE 3 END, id DESC');
        $stmt->execute([$campaignId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('tl_campaign_builder_available_reward_rules')) {
    function tl_campaign_builder_available_reward_rules(PDO $pdo, array $user, int $excludeCampaignId = 0): array
    {
        $actor = tl_campaign_builder_actor($user);
        $sql = 'SELECT r.*, c.title AS campaign_title, c.owner_user_id FROM training_reward_rules r INNER JOIN training_campaigns c ON c.id = r.campaign_id WHERE r.status IN (\'active\',\'paused\',\'draft\')';
        $params = [];
        if (!$actor['is_admin']) {
            $sql .= ' AND c.owner_user_id = ?';
            $params[] = $actor['user_id'];
        }
        if ($excludeCampaignId > 0) {
            $sql .= ' AND r.campaign_id <> ?';
            $params[] = $excludeCampaignId;
        }
        $sql .= ' ORDER BY c.updated_at DESC, r.id DESC LIMIT 100';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('tl_campaign_builder_readiness')) {
    function tl_campaign_builder_readiness(array $campaign, array $tasks, array $rules): array
    {
        $settings = tl_campaign_builder_json($campaign['settings_json'] ?? null);
        $builder = is_array($settings['builder'] ?? null) ? $settings['builder'] : [];
        $activeTasks = array_values(array_filter($tasks, static fn(array $task): bool => (string)($task['status'] ?? '') === 'active'));
        $taskChecks = [];
        foreach ($activeTasks as $task) {
            $taskSettings = is_array($task['builder_settings'] ?? null) ? $task['builder_settings'] : (tl_campaign_builder_json($task['settings_json'] ?? null)['builder'] ?? []);
            $valid = trim((string)($task['title'] ?? '')) !== '' && trim((string)($task['instructions'] ?? '')) !== '';
            if (!empty($task['proof_required'])) $valid = $valid && trim((string)($taskSettings['proof_instructions'] ?? '')) !== '';
            $taskChecks[(string)($task['public_id'] ?? $task['id'])] = $valid;
        }
        $startsAt = (string)($campaign['starts_at'] ?? $builder['starts_at'] ?? '');
        $endsAt = (string)($campaign['ends_at'] ?? $builder['ends_at'] ?? '');
        $scheduleValid = $startsAt === '' || $endsAt === '' || strtotime($endsAt) > strtotime($startsAt);
        $checks = [
            'title' => trim((string)($campaign['title'] ?? '')) !== '',
            'summary' => trim((string)($campaign['summary'] ?? '')) !== '',
            'description' => trim((string)($campaign['description'] ?? '')) !== '',
            'audience' => trim((string)($builder['audience'] ?? '')) !== '',
            'capacity' => (int)($campaign['max_participants'] ?? $builder['capacity'] ?? 0) > 0,
            'schedule' => $scheduleValid,
            'tasks' => count($activeTasks) > 0 && !in_array(false, $taskChecks, true),
            'reward_rule' => count(array_filter($rules, static fn(array $rule): bool => (string)($rule['status'] ?? '') === 'active')) > 0,
        ];
        $failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
        return [
            'ready' => $failed === [],
            'score' => (int)round((count($checks) - count($failed)) / max(1, count($checks)) * 100),
            'checks' => $checks,
            'failed' => $failed,
            'task_checks' => $taskChecks,
        ];
    }
}

if (!function_exists('tl_campaign_builder_dashboard')) {
    function tl_campaign_builder_dashboard(array $user, string $campaignRef = ''): array
    {
        $pdo = tl_require_db();
        $campaigns = tl_campaign_builder_list_campaigns($user);
        $campaign = null;
        if ($campaignRef !== '') {
            try { $campaign = tl_campaign_builder_owned_campaign($pdo, $user, $campaignRef); }
            catch (Throwable $e) { $campaign = null; }
        }
        if (!$campaign && $campaigns) $campaign = $campaigns[0];
        $tasks = $campaign ? tl_campaign_builder_tasks($pdo, (int)$campaign['id']) : [];
        $rules = $campaign ? tl_campaign_builder_reward_rules($pdo, (int)$campaign['id']) : [];
        $settings = $campaign ? tl_campaign_builder_json($campaign['settings_json'] ?? null) : [];
        return [
            'campaigns' => $campaigns,
            'campaign' => $campaign,
            'tasks' => $tasks,
            'reward_rules' => $rules,
            'available_reward_rules' => $campaign ? tl_campaign_builder_available_reward_rules($pdo, $user, (int)$campaign['id']) : [],
            'settings' => is_array($settings['builder'] ?? null) ? $settings['builder'] : [],
            'readiness' => $campaign ? tl_campaign_builder_readiness($campaign, $tasks, $rules) : ['ready'=>false,'score'=>0,'checks'=>[],'failed'=>['campaign']],
        ];
    }
}

if (!function_exists('tl_campaign_builder_create')) {
    function tl_campaign_builder_create(array $user, array $input): array
    {
        $actor = tl_campaign_builder_actor($user);
        $title = tl_campaign_builder_clean($input['title'] ?? '', 180, true, 'Campaign title');
        $created = tl_create_campaign([
            'title' => $title,
            'summary' => tl_campaign_builder_clean($input['summary'] ?? 'New proof-based training campaign.', 500),
            'description' => tl_campaign_builder_clean($input['description'] ?? 'Complete the campaign tasks, submit proof, and earn the configured reward.', 5000),
            'campaign_type' => tl_action_enum($input['campaign_type'] ?? 'custom', ['movement','learning','onboarding','service','sales','wellness','custom'], 'custom'),
            'visibility' => 'private',
            'status' => 'draft',
            'target_action_count' => 1,
            'reward_label' => tl_campaign_builder_clean($input['reward_label'] ?? 'Training completion', 255),
            'reward_type' => 'badge',
            'reward_value_cents' => 0,
            'owner_user_id' => $actor['user_id'],
            'created_by_user_id' => $actor['user_id'],
            'tasks' => [[
                'title' => 'First training action',
                'instructions' => 'Describe the first participant action.',
                'task_type' => 'checklist',
                'proof_required' => 0,
                'expected_duration_minutes' => 15,
            ]],
        ]);
        $pdo = tl_require_db();
        $campaign = tl_campaign_builder_owned_campaign($pdo, $user, (string)$created['campaign_id'], true);
        $settings = tl_campaign_builder_json($campaign['settings_json'] ?? null);
        $settings['builder'] = [
            'audience' => tl_campaign_builder_clean($input['audience'] ?? 'Invited participants', 255),
            'capacity' => max(1, min(100000, (int)($input['capacity'] ?? 25))),
            'timezone' => tl_campaign_builder_clean($input['timezone'] ?? 'America/Phoenix', 80),
            'enrollment_mode' => 'invitation_or_open',
            'allow_late_submissions' => false,
            'participant_instructions' => '',
            'created_by' => $actor['user_id'],
            'created_at' => gmdate('c'),
        ];
        $stmt = $pdo->prepare('UPDATE training_campaigns SET settings_json = ? WHERE id = ?');
        $stmt->execute([json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), (int)$campaign['id']]);
        tl_log_event($pdo, $actor['user_id'], 'campaign', (int)$campaign['id'], 'campaign_builder_created', ['slug'=>$created['slug']]);
        return $created + ['campaign_ref'=>(string)$created['public_id']];
    }
}

if (!function_exists('tl_campaign_builder_save_campaign')) {
    function tl_campaign_builder_save_campaign(array $user, array $input): array
    {
        $pdo = tl_require_db();
        $actor = tl_campaign_builder_actor($user);
        $pdo->beginTransaction();
        try {
            $campaign = tl_campaign_builder_owned_campaign($pdo, $user, (string)($input['campaign'] ?? ''), true);
            if ((string)$campaign['status'] === 'archived') throw new TlHttpException('Archived campaigns are immutable. Duplicate this campaign to continue.', 409, 'campaign_builder_archived_immutable');
            $title = tl_campaign_builder_clean($input['title'] ?? $campaign['title'], 180, true, 'Campaign title');
            $summary = tl_campaign_builder_clean($input['summary'] ?? $campaign['summary'], 500, true, 'Campaign summary');
            $description = tl_campaign_builder_clean($input['description'] ?? $campaign['description'], 5000, true, 'Campaign description');
            $type = tl_action_enum($input['campaign_type'] ?? $campaign['campaign_type'], ['movement','learning','onboarding','service','sales','wellness','custom'], 'custom');
            $status = tl_action_enum($input['status'] ?? $campaign['status'], ['draft','scheduled','active','paused','completed','archived'], 'draft');
            $visibility = tl_action_enum($input['visibility'] ?? $campaign['visibility'], ['draft','private','published','archived'], 'private');
            $target = max(1, min(200, (int)($input['target_action_count'] ?? $campaign['target_action_count'] ?? 1)));
            $rewardSummary = tl_campaign_builder_clean($input['reward_summary'] ?? $campaign['reward_summary'], 255);
            $timezone = tl_campaign_builder_clean($input['timezone'] ?? 'America/Phoenix', 80);
            try { new DateTimeZone($timezone); } catch (Throwable $e) { throw new TlHttpException('Select a valid timezone.', 422, 'campaign_builder_timezone_invalid'); }
            $startsAt = tl_campaign_builder_normalize_datetime($input['starts_at'] ?? '', $timezone);
            $endsAt = tl_campaign_builder_normalize_datetime($input['ends_at'] ?? '', $timezone);
            if ($startsAt && $endsAt && strtotime($endsAt) <= strtotime($startsAt)) throw new TlHttpException('Campaign end time must be after its start time.', 422, 'campaign_builder_schedule_invalid');
            $capacity = max(1, min(100000, (int)($input['capacity'] ?? 25)));
            $settings = tl_campaign_builder_json($campaign['settings_json'] ?? null);
            $settings['builder'] = array_merge(is_array($settings['builder'] ?? null) ? $settings['builder'] : [], [
                'audience' => tl_campaign_builder_clean($input['audience'] ?? '', 255, true, 'Audience'),
                'capacity' => $capacity,
                'timezone' => $timezone,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'enrollment_mode' => tl_action_enum($input['enrollment_mode'] ?? 'invitation_or_open', ['invitation_only','open','invitation_or_open'], 'invitation_or_open'),
                'allow_late_submissions' => tl_campaign_builder_bool($input['allow_late_submissions'] ?? false),
                'participant_instructions' => tl_campaign_builder_clean($input['participant_instructions'] ?? '', 2200),
                'updated_by' => $actor['user_id'],
                'updated_at' => gmdate('c'),
            ]);
            $columns = tl_campaign_builder_columns($pdo, 'training_campaigns');
            $updates = [
                'title'=>$title,'summary'=>$summary,'description'=>$description,'campaign_type'=>$type,
                'status'=>$status,'visibility'=>$visibility,'target_action_count'=>$target,
                'reward_summary'=>$rewardSummary,'settings_json'=>json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ];
            if (in_array('starts_at', $columns, true)) $updates['starts_at'] = $startsAt;
            if (in_array('ends_at', $columns, true)) $updates['ends_at'] = $endsAt;
            if (in_array('timezone', $columns, true)) $updates['timezone'] = $timezone;
            if (in_array('max_participants', $columns, true)) $updates['max_participants'] = $capacity;
            $set = [];
            $params = [];
            foreach ($updates as $column => $value) { if (in_array($column, $columns, true)) { $set[] = '`' . $column . '` = ?'; $params[] = $value; } }
            if (!$set) throw new RuntimeException('Campaign schema is unavailable for builder updates.');
            $params[] = (int)$campaign['id'];
            $stmt = $pdo->prepare('UPDATE training_campaigns SET ' . implode(', ', $set) . ' WHERE id = ?');
            $stmt->execute($params);
            $updated = tl_campaign_builder_owned_campaign($pdo, $user, (string)$campaign['id']);
            $tasks = tl_campaign_builder_tasks($pdo, (int)$campaign['id']);
            $rules = tl_campaign_builder_reward_rules($pdo, (int)$campaign['id']);
            $readiness = tl_campaign_builder_readiness($updated, $tasks, $rules);
            if (($status === 'active' || $status === 'scheduled' || $visibility === 'published') && !$readiness['ready']) {
                throw new TlHttpException('Campaign cannot be published until every readiness check passes: ' . implode(', ', $readiness['failed']) . '.', 422, 'campaign_builder_not_ready');
            }
            tl_log_event($pdo, $actor['user_id'], 'campaign', (int)$campaign['id'], 'campaign_builder_updated', ['status'=>$status,'visibility'=>$visibility,'readiness_score'=>$readiness['score']]);
            $pdo->commit();
            return ['campaign_id'=>(int)$campaign['id'],'campaign_ref'=>(string)$campaign['public_id'],'readiness'=>$readiness];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_campaign_builder_duplicate')) {
    function tl_campaign_builder_duplicate(array $user, array $input): array
    {
        $pdo = tl_require_db();
        $source = tl_campaign_builder_owned_campaign($pdo, $user, (string)($input['campaign'] ?? ''));
        $tasks = tl_campaign_builder_tasks($pdo, (int)$source['id']);
        $rules = tl_campaign_builder_reward_rules($pdo, (int)$source['id']);
        $primaryRule = $rules[0] ?? [];
        $copyTasks = [];
        foreach ($tasks as $task) {
            if ((string)($task['status'] ?? '') === 'archived') continue;
            $copyTasks[] = [
                'title'=>(string)$task['title'], 'instructions'=>(string)$task['instructions'], 'task_type'=>(string)$task['task_type'],
                'proof_required'=>(int)$task['proof_required'], 'expected_duration_minutes'=>(int)$task['expected_duration_minutes'],
            ];
        }
        if (!$copyTasks) $copyTasks[] = ['title'=>'First training action','instructions'=>'Describe the participant action.','task_type'=>'checklist','proof_required'=>0,'expected_duration_minutes'=>15];
        $actor = tl_campaign_builder_actor($user);
        $created = tl_create_campaign([
            'title'=>tl_campaign_builder_clean($input['title'] ?? ((string)$source['title'] . ' Copy'), 180, true, 'Campaign title'),
            'summary'=>(string)($source['summary'] ?? ''), 'description'=>(string)($source['description'] ?? ''),
            'campaign_type'=>(string)($source['campaign_type'] ?? 'custom'), 'visibility'=>'private', 'status'=>'draft',
            'target_action_count'=>count($copyTasks), 'reward_label'=>(string)($primaryRule['reward_label'] ?? $source['reward_summary'] ?? 'Training completion'),
            'reward_type'=>(string)($primaryRule['reward_type'] ?? 'badge'), 'reward_value_cents'=>(int)($primaryRule['reward_value_cents'] ?? 0),
            'currency'=>(string)($primaryRule['currency'] ?? 'USD'), 'owner_user_id'=>$actor['user_id'], 'created_by_user_id'=>$actor['user_id'], 'tasks'=>$copyTasks,
        ]);
        $new = tl_campaign_builder_owned_campaign($pdo, $user, (string)$created['campaign_id']);
        $settings = tl_campaign_builder_json($source['settings_json'] ?? null);
        $settings['builder']['duplicated_from'] = (string)$source['public_id'];
        $settings['builder']['duplicated_by'] = $actor['user_id'];
        $settings['builder']['duplicated_at'] = gmdate('c');
        $stmt = $pdo->prepare('UPDATE training_campaigns SET settings_json = ? WHERE id = ?');
        $stmt->execute([json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), (int)$new['id']]);
        tl_log_event($pdo, $actor['user_id'], 'campaign', (int)$new['id'], 'campaign_builder_duplicated', ['source_campaign_id'=>(int)$source['id']]);
        return $created + ['campaign_ref'=>(string)$new['public_id']];
    }
}

if (!function_exists('tl_campaign_builder_archive')) {
    function tl_campaign_builder_archive(array $user, array $input): array
    {
        $pdo = tl_require_db();
        $actor = tl_campaign_builder_actor($user);
        $pdo->beginTransaction();
        try {
            $campaign = tl_campaign_builder_owned_campaign($pdo, $user, (string)($input['campaign'] ?? ''), true);
            $stmt = $pdo->prepare("UPDATE training_campaigns SET status = 'archived', visibility = 'archived' WHERE id = ?");
            $stmt->execute([(int)$campaign['id']]);
            tl_log_event($pdo, $actor['user_id'], 'campaign', (int)$campaign['id'], 'campaign_builder_archived', []);
            $pdo->commit();
            return ['campaign_id'=>(int)$campaign['id'],'status'=>'archived'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_campaign_builder_task_row')) {
    function tl_campaign_builder_task_row(PDO $pdo, array $user, int $taskId, bool $forUpdate = false): array
    {
        $actor = tl_campaign_builder_actor($user);
        $sql = 'SELECT t.*, c.owner_user_id, c.status AS campaign_status, c.public_id AS campaign_public_id FROM training_campaign_tasks t INNER JOIN training_campaigns c ON c.id = t.campaign_id WHERE t.id = ?';
        $params = [$taskId];
        if (!$actor['is_admin']) { $sql .= ' AND c.owner_user_id = ?'; $params[] = $actor['user_id']; }
        $sql .= ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$task) throw new TlHttpException('Task was not found in your merchant workspace.', 404, 'campaign_builder_task_not_found');
        return $task;
    }
}

if (!function_exists('tl_campaign_builder_add_task')) {
    function tl_campaign_builder_add_task(array $user, array $input): array
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
            $proofInstructions = tl_campaign_builder_clean($input['proof_instructions'] ?? '', 1200, $proofRequired === 1, 'Proof instructions');
            $duration = max(1, min(1440, (int)($input['expected_duration_minutes'] ?? 15)));
            $max = $pdo->prepare('SELECT COALESCE(MAX(position_no),0) FROM training_campaign_tasks WHERE campaign_id = ? FOR UPDATE');
            $max->execute([(int)$campaign['id']]);
            $position = (int)$max->fetchColumn() + 1;
            $settings = ['builder'=>[
                'proof_instructions'=>$proofInstructions,
                'due_at'=>tl_campaign_builder_clean($input['due_at'] ?? '', 80),
                'close_after_due'=>tl_campaign_builder_bool($input['close_after_due'] ?? false),
                'prerequisite_task_id'=>max(0, (int)($input['prerequisite_task_id'] ?? 0)) ?: null,
                'created_by'=>$actor['user_id'],'created_at'=>gmdate('c'),
            ]];
            $stmt = $pdo->prepare('INSERT INTO training_campaign_tasks (public_id, campaign_id, position_no, day_no, task_type, title, instructions, proof_required, expected_duration_minutes, status, settings_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([tl_uuid(), (int)$campaign['id'], $position, $position, $taskType, $title, $instructions, $proofRequired, $duration, 'active', json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
            $taskId = (int)$pdo->lastInsertId();
            tl_log_event($pdo, $actor['user_id'], 'task', $taskId, 'campaign_builder_task_created', ['campaign_id'=>(int)$campaign['id'],'position'=>$position]);
            $pdo->commit();
            return ['campaign_ref'=>(string)$campaign['public_id'],'task_id'=>$taskId,'position'=>$position];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_campaign_builder_update_task')) {
    function tl_campaign_builder_update_task(array $user, array $input): array
    {
        $pdo = tl_require_db();
        $actor = tl_campaign_builder_actor($user);
        $taskId = max(1, (int)($input['task_id'] ?? 0));
        $pdo->beginTransaction();
        try {
            $task = tl_campaign_builder_task_row($pdo, $user, $taskId, true);
            if ((string)$task['campaign_status'] === 'archived') throw new TlHttpException('Archived campaigns are immutable.', 409, 'campaign_builder_archived_immutable');
            $title = tl_campaign_builder_clean($input['title'] ?? $task['title'], 180, true, 'Task title');
            $instructions = tl_campaign_builder_clean($input['instructions'] ?? $task['instructions'], 5000, true, 'Task instructions');
            $taskType = tl_action_enum($input['task_type'] ?? $task['task_type'], ['checklist','movement','photo_proof','video_proof','text_reflection','quiz','custom'], 'checklist');
            $proofRequired = tl_campaign_builder_bool($input['proof_required'] ?? false) ? 1 : 0;
            $duration = max(1, min(1440, (int)($input['expected_duration_minutes'] ?? $task['expected_duration_minutes'] ?? 15)));
            $status = tl_action_enum($input['task_status'] ?? $task['status'], ['active','hidden','archived'], 'active');
            $settings = tl_campaign_builder_json($task['settings_json'] ?? null);
            $settings['builder'] = array_merge(is_array($settings['builder'] ?? null) ? $settings['builder'] : [], [
                'proof_instructions'=>tl_campaign_builder_clean($input['proof_instructions'] ?? '', 1200, $proofRequired === 1, 'Proof instructions'),
                'due_at'=>tl_campaign_builder_clean($input['due_at'] ?? '', 80),
                'close_after_due'=>tl_campaign_builder_bool($input['close_after_due'] ?? false),
                'prerequisite_task_id'=>max(0, (int)($input['prerequisite_task_id'] ?? 0)) ?: null,
                'updated_by'=>$actor['user_id'],'updated_at'=>gmdate('c'),
            ]);
            $stmt = $pdo->prepare('UPDATE training_campaign_tasks SET title = ?, instructions = ?, task_type = ?, proof_required = ?, expected_duration_minutes = ?, status = ?, settings_json = ? WHERE id = ?');
            $stmt->execute([$title,$instructions,$taskType,$proofRequired,$duration,$status,json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),$taskId]);
            tl_log_event($pdo, $actor['user_id'], 'task', $taskId, 'campaign_builder_task_updated', ['campaign_id'=>(int)$task['campaign_id'],'status'=>$status]);
            $pdo->commit();
            return ['campaign_ref'=>(string)$task['campaign_public_id'],'task_id'=>$taskId,'status'=>$status];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_campaign_builder_normalize_task_order')) {
    function tl_campaign_builder_normalize_task_order($value): array
    {
        $parts = is_array($value) ? $value : preg_split('/[\s,]+/', (string)$value, -1, PREG_SPLIT_NO_EMPTY);
        $ids = [];
        foreach ($parts ?: [] as $part) {
            $id = (int)$part;
            if ($id > 0 && !in_array($id, $ids, true)) $ids[] = $id;
        }
        return $ids;
    }
}

if (!function_exists('tl_campaign_builder_reorder_tasks')) {
    function tl_campaign_builder_reorder_tasks(array $user, array $input): array
    {
        $pdo = tl_require_db();
        $actor = tl_campaign_builder_actor($user);
        $ids = tl_campaign_builder_normalize_task_order($input['task_order'] ?? []);
        if (!$ids) throw new TlHttpException('Provide at least one task in the new order.', 422, 'campaign_builder_task_order_required');
        $pdo->beginTransaction();
        try {
            $campaign = tl_campaign_builder_owned_campaign($pdo, $user, (string)($input['campaign'] ?? ''), true);
            $tasks = tl_campaign_builder_tasks($pdo, (int)$campaign['id']);
            $ownedIds = array_map(static fn(array $task): int => (int)$task['id'], $tasks);
            foreach ($ids as $id) if (!in_array($id, $ownedIds, true)) throw new TlHttpException('Task order contains an invalid task.', 422, 'campaign_builder_task_order_invalid');
            foreach ($ownedIds as $id) if (!in_array($id, $ids, true)) $ids[] = $id;
            $stmt = $pdo->prepare('UPDATE training_campaign_tasks SET position_no = ?, day_no = ? WHERE id = ? AND campaign_id = ?');
            foreach ($ids as $index => $id) $stmt->execute([$index + 1, $index + 1, $id, (int)$campaign['id']]);
            tl_log_event($pdo, $actor['user_id'], 'campaign', (int)$campaign['id'], 'campaign_builder_tasks_reordered', ['task_ids'=>$ids]);
            $pdo->commit();
            return ['campaign_ref'=>(string)$campaign['public_id'],'task_order'=>$ids];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_campaign_builder_delete_task')) {
    function tl_campaign_builder_delete_task(array $user, array $input): array
    {
        $pdo = tl_require_db();
        $actor = tl_campaign_builder_actor($user);
        $taskId = max(1, (int)($input['task_id'] ?? 0));
        $pdo->beginTransaction();
        try {
            $task = tl_campaign_builder_task_row($pdo, $user, $taskId, true);
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM training_campaign_tasks WHERE campaign_id = ? AND status = \'active\'');
            $countStmt->execute([(int)$task['campaign_id']]);
            $activeCount = (int)$countStmt->fetchColumn();
            if ((string)$task['status'] === 'active' && $activeCount <= 1) throw new TlHttpException('A campaign must keep at least one active task.', 409, 'campaign_builder_last_task');
            $proofStmt = $pdo->prepare('SELECT COUNT(*) FROM training_proof_submissions WHERE task_id = ?');
            $proofStmt->execute([$taskId]);
            $referenced = (int)$proofStmt->fetchColumn() > 0;
            if ($referenced) {
                $stmt = $pdo->prepare("UPDATE training_campaign_tasks SET status = 'archived' WHERE id = ?");
                $stmt->execute([$taskId]);
                $result = 'archived';
            } else {
                $stmt = $pdo->prepare('DELETE FROM training_campaign_tasks WHERE id = ?');
                $stmt->execute([$taskId]);
                $result = 'deleted';
            }
            tl_log_event($pdo, $actor['user_id'], 'task', $taskId, 'campaign_builder_task_removed', ['campaign_id'=>(int)$task['campaign_id'],'result'=>$result]);
            $pdo->commit();
            return ['campaign_ref'=>(string)$task['campaign_public_id'],'task_id'=>$taskId,'result'=>$result];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_campaign_builder_attach_reward')) {
    function tl_campaign_builder_attach_reward(array $user, array $input): array
    {
        $pdo = tl_require_db();
        $actor = tl_campaign_builder_actor($user);
        $pdo->beginTransaction();
        try {
            $campaign = tl_campaign_builder_owned_campaign($pdo, $user, (string)($input['campaign'] ?? ''), true);
            $sourceRuleId = max(0, (int)($input['source_rule_id'] ?? 0));
            $source = null;
            if ($sourceRuleId > 0) {
                $sql = 'SELECT r.*, c.owner_user_id FROM training_reward_rules r INNER JOIN training_campaigns c ON c.id = r.campaign_id WHERE r.id = ?';
                $params = [$sourceRuleId];
                if (!$actor['is_admin']) { $sql .= ' AND c.owner_user_id = ?'; $params[] = $actor['user_id']; }
                $sql .= ' LIMIT 1 FOR UPDATE';
                $stmt = $pdo->prepare($sql); $stmt->execute($params); $source = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if (!$source) throw new TlHttpException('Reward rule was not found in your merchant workspace.', 404, 'campaign_builder_reward_not_found');
            }
            $label = tl_campaign_builder_clean($input['reward_label'] ?? ($source['reward_label'] ?? 'Training completion'), 255, true, 'Reward label');
            $type = tl_action_enum($input['reward_type'] ?? ($source['reward_type'] ?? 'badge'), ['badge','microgift','entitlement','wallet_credit_preview','custom'], 'badge');
            $value = max(0, min(100000000, (int)($input['reward_value_cents'] ?? $source['reward_value_cents'] ?? 0)));
            $currency = strtoupper(tl_campaign_builder_clean($input['currency'] ?? ($source['currency'] ?? 'USD'), 3));
            if (!preg_match('/^[A-Z]{3}$/', $currency)) $currency = 'USD';
            $trigger = tl_action_enum($input['trigger_type'] ?? ($source['trigger_type'] ?? 'action_count'), ['action_count','campaign_completion','manual','custom'], 'campaign_completion');
            $threshold = max(1, min(10000, (int)($input['threshold_count'] ?? $source['threshold_count'] ?? 1)));
            $settings = tl_campaign_builder_json($source['settings_json'] ?? null);
            $settings['builder'] = ['attached_by'=>$actor['user_id'],'attached_at'=>gmdate('c'),'source_rule_id'=>$sourceRuleId ?: null];
            $stmt = $pdo->prepare('INSERT INTO training_reward_rules (public_id, campaign_id, rule_name, trigger_type, threshold_count, reward_type, reward_label, reward_value_cents, currency, linked_microgift_template_id, linked_catalog_product_id, status, settings_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([tl_uuid(),(int)$campaign['id'],$label . ' eligibility',$trigger,$threshold,$type,$label,$value,$currency,$source['linked_microgift_template_id'] ?? null,$source['linked_catalog_product_id'] ?? null,'active',json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
            $ruleId = (int)$pdo->lastInsertId();
            $summary = $pdo->prepare('UPDATE training_campaigns SET reward_summary = ? WHERE id = ?');
            $summary->execute([$label,(int)$campaign['id']]);
            tl_log_event($pdo, $actor['user_id'], 'reward_rule', $ruleId, 'campaign_builder_reward_attached', ['campaign_id'=>(int)$campaign['id'],'reward_type'=>$type]);
            $pdo->commit();
            return ['campaign_ref'=>(string)$campaign['public_id'],'reward_rule_id'=>$ruleId];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
