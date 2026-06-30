<?php
/**
 * Stage 161-200 product engine for the standalone Training Lab app.
 *
 * This layer turns the cleaned Training Lab script into a more connected product:
 * workflow state, participant mission control, review/reward operations, reporting
 * snapshots, and launch QA. It writes only to Training Lab tables that already
 * exist, primarily training_events plus existing campaign/participant/reward rows.
 */

if (!function_exists('tl_stage200_clean')) {
    function tl_stage200_clean($value, int $max = 700): string
    {
        $value = trim((string)$value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        if ($max > 0 && mb_strlen($value) > $max) $value = mb_substr($value, 0, $max);
        return $value;
    }
}

if (!function_exists('tl_stage200_actor_id')) {
    function tl_stage200_actor_id(?array $input = null): int
    {
        if (function_exists('tl_account_bridge_numeric_user_id')) {
            $user = function_exists('tl_auth_current_user') ? tl_auth_current_user() : null;
            if ($user) return tl_account_bridge_numeric_user_id($user);
        }
        $input = $input ?: [];
        return max(1, (int)($input['user_id'] ?? $input['actor_user_id'] ?? 1));
    }
}

if (!function_exists('tl_stage200_decode_json')) {
    function tl_stage200_decode_json($value): array
    {
        if (is_array($value)) return $value;
        if (!$value) return [];
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('tl_stage200_campaign_row')) {
    function tl_stage200_campaign_row(string $campaignRef = ''): ?array
    {
        $pdo = tl_db();
        if (!$pdo || !tl_table_exists('training_campaigns')) return null;
        $campaignRef = trim($campaignRef) ?: (function_exists('tl_app_default_campaign_ref') ? tl_app_default_campaign_ref() : '');
        try {
            if ($campaignRef !== '') {
                $stmt = $pdo->prepare('SELECT * FROM training_campaigns WHERE id = ? OR public_id = ? OR slug = ? ORDER BY id DESC LIMIT 1');
                $stmt->execute([ctype_digit($campaignRef) ? (int)$campaignRef : 0, $campaignRef, $campaignRef]);
                $row = $stmt->fetch();
                if ($row) return $row;
            }
            $row = $pdo->query("SELECT * FROM training_campaigns ORDER BY FIELD(status,'active','scheduled','draft','paused','completed','archived'), updated_at DESC, id DESC LIMIT 1")->fetch();
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('tl_stage200_campaign_ref')) {
    function tl_stage200_campaign_ref(?array $campaign = null): string
    {
        if (!$campaign) return '';
        return (string)($campaign['slug'] ?? $campaign['public_id'] ?? $campaign['id'] ?? '');
    }
}

if (!function_exists('tl_stage200_progress_percent')) {
    function tl_stage200_progress_percent(array $context): int
    {
        $total = max(1, count($context['tasks'] ?? []));
        $approved = 0;
        foreach (($context['tasks'] ?? []) as $task) {
            $tid = (string)($task['db_id'] ?? $task['id'] ?? '');
            foreach (($context['proofs_by_task'][$tid] ?? []) as $proof) {
                if (($proof['status'] ?? '') === 'approved') { $approved++; break; }
            }
        }
        return min(100, (int)round(($approved / $total) * 100));
    }
}

if (!function_exists('tl_stage200_next_task')) {
    function tl_stage200_next_task(array $context): ?array
    {
        foreach (($context['tasks'] ?? []) as $task) {
            $tid = (string)($task['db_id'] ?? $task['id'] ?? '');
            $latest = ($context['proofs_by_task'][$tid] ?? [])[0] ?? null;
            if (!$latest || !in_array((string)($latest['status'] ?? ''), ['approved'], true)) return $task + ['latest_proof' => $latest];
        }
        return ($context['tasks'] ?? []) ? end($context['tasks']) : null;
    }
}

if (!function_exists('tl_stage200_workflow_state')) {
    function tl_stage200_workflow_state(string $campaignRef = '', int $userId = 0): array
    {
        $actorId = $userId > 0 ? $userId : tl_stage200_actor_id();
        $campaign = tl_stage200_campaign_row($campaignRef);
        $campaignRef = tl_stage200_campaign_ref($campaign);
        $context = function_exists('tl_app_participant_context') ? tl_app_participant_context($campaignRef, $actorId) : [];
        $summary = function_exists('tl_app_flow_summary') ? tl_app_flow_summary() : [];
        $rewards = function_exists('tl_mg_stage160_user_summary') ? tl_mg_stage160_user_summary($actorId) : ['counts' => [], 'rewards' => []];
        $auth = function_exists('tl_account_bridge_current_context') ? tl_account_bridge_current_context() : [];
        $nextTask = tl_stage200_next_task($context);
        $progress = tl_stage200_progress_percent($context);
        $pendingProofs = function_exists('tl_app_pending_proofs') ? tl_app_pending_proofs(20) : [];
        $campaignCount = (int)($summary['counts']['campaigns'] ?? 0);
        $joined = !empty($context['joined']);
        $needsReview = count($pendingProofs);
        $claimable = (int)($rewards['counts']['claimable'] ?? 0);
        $steps = [
            ['key'=>'account','label'=>'Account','status'=>!empty($auth['user']) ? 'ready' : 'optional','href'=>'/account.php','detail'=>!empty($auth['user']) ? 'Training user mapped' : 'Login optional until enforcement is enabled'],
            ['key'=>'campaign','label'=>'Campaign','status'=>$campaignCount > 0 ? 'ready' : 'needed','href'=>'/app/campaign-builder.php','detail'=>$campaignCount > 0 ? 'Campaigns available' : 'Create the first campaign blueprint'],
            ['key'=>'join','label'=>'Join','status'=>$joined ? 'ready' : 'needed','href'=>'/app/participant-portal.php?campaign=' . rawurlencode($campaignRef) . '&user_id=' . $actorId,'detail'=>$joined ? 'Participant row exists' : 'Join the selected campaign'],
            ['key'=>'tasks','label'=>'Tasks','status'=>$progress >= 100 ? 'complete' : ($joined ? 'active' : 'blocked'),'href'=>'/app/task-runner.php?campaign=' . rawurlencode($campaignRef) . '&user_id=' . $actorId,'detail'=>$nextTask ? (string)($nextTask['title'] ?? 'Continue next task') : 'No active tasks found'],
            ['key'=>'review','label'=>'Review','status'=>$needsReview > 0 ? 'needs_admin' : 'clear','href'=>'/admin/review-workbench.php','detail'=>$needsReview . ' proof item(s) waiting'],
            ['key'=>'rewards','label'=>'Rewards','status'=>$claimable > 0 ? 'claimable' : 'watching','href'=>'/app/rewards.php?user_id=' . $actorId,'detail'=>$claimable . ' reward(s) available to claim'],
            ['key'=>'report','label'=>'Report','status'=>$progress >= 100 ? 'ready' : 'building','href'=>'/app/flow-board.php?campaign=' . rawurlencode($campaignRef) . '&user_id=' . $actorId,'detail'=>'Flow board shows the lifecycle trail'],
        ];
        $blockers = [];
        foreach ($steps as $step) {
            if (in_array($step['status'], ['needed','blocked','needs_admin'], true)) $blockers[] = $step;
        }
        $next = null;
        foreach ($steps as $step) {
            if (in_array($step['status'], ['needed','blocked','active','needs_admin','claimable'], true)) { $next = $step; break; }
        }
        return [
            'stage' => 'Stage 161-200 stacked app build',
            'actor_user_id' => $actorId,
            'campaign' => $campaign,
            'campaign_ref' => $campaignRef,
            'participant_context' => $context,
            'summary' => $summary,
            'rewards' => $rewards,
            'auth' => $auth,
            'steps' => $steps,
            'blockers' => $blockers,
            'next_step' => $next,
            'progress_percent' => $progress,
            'next_task' => $nextTask,
            'safe_boundaries' => [
                'standalone_training_script' => true,
                'writes_only_training_tables' => true,
                'no_page_factory' => true,
                'no_new_sql_required' => true,
                'no_payment_processing' => true,
                'no_wallet_balance_mutation' => true,
                'microgifter_rewards_adapter_gated' => true,
            ],
        ];
    }
}

if (!function_exists('tl_stage200_admin_state')) {
    function tl_stage200_admin_state(): array
    {
        $flow = function_exists('tl_app_flow_summary') ? tl_app_flow_summary() : [];
        $rewardBridge = function_exists('tl_mg_stage160_bridge_summary') ? tl_mg_stage160_bridge_summary() : [];
        $pendingProofs = function_exists('tl_app_pending_proofs') ? tl_app_pending_proofs(50) : [];
        $recentReviews = function_exists('tl_app_recent_reviews') ? tl_app_recent_reviews(20) : [];
        $recentEvents = function_exists('tl_app_recent_events') ? tl_app_recent_events(30) : [];
        $routes = tl_stage200_route_readiness();
        return [
            'stage' => 'Stage 161-200 admin operations state',
            'flow' => $flow,
            'reward_bridge' => $rewardBridge,
            'pending_proofs' => $pendingProofs,
            'recent_reviews' => $recentReviews,
            'recent_events' => $recentEvents,
            'route_readiness' => $routes,
            'operations_score' => tl_stage200_operations_score($flow, $rewardBridge, $routes),
        ];
    }
}

if (!function_exists('tl_stage200_route_readiness')) {
    function tl_stage200_route_readiness(): array
    {
        $base = dirname(__DIR__);
        $core = [
            'app_dashboard' => '/app/index.php',
            'campaign_builder' => '/app/campaign-builder.php',
            'participant_portal' => '/app/participant-portal.php',
            'task_runner' => '/app/task-runner.php',
            'flow_board' => '/app/flow-board.php',
            'rewards' => '/app/rewards.php',
            'admin_command' => '/admin/command-center.php',
            'review_workbench' => '/admin/review-workbench.php',
            'reward_bridge' => '/admin/reward-bridge.php',
            'backend_readiness' => '/admin/backend-readiness.php',
            'workflow_api' => '/api/training/workflow-state.php',
            'qa_api' => '/api/training/core-workflow-qa.php',
        ];
        $items = [];
        $ready = 0;
        foreach ($core as $key => $route) {
            $exists = is_file($base . $route);
            if ($exists) $ready++;
            $items[$key] = ['route' => $route, 'exists' => $exists];
        }
        return ['ready' => $ready, 'total' => count($core), 'score' => (int)round(($ready / max(1, count($core))) * 100), 'items' => $items];
    }
}

if (!function_exists('tl_stage200_operations_score')) {
    function tl_stage200_operations_score(array $flow = [], array $rewardBridge = [], array $routes = []): array
    {
        $checks = [];
        $counts = $flow['counts'] ?? [];
        $checks['db_connected'] = !empty($flow['connected']);
        $checks['campaigns_exist'] = (int)($counts['campaigns'] ?? 0) > 0;
        $checks['tasks_exist'] = (int)($counts['tasks'] ?? 0) > 0;
        $checks['participants_exist'] = (int)($counts['participants'] ?? 0) > 0;
        $checks['proof_flow_ready'] = (int)($counts['proofs'] ?? 0) >= 0;
        $checks['review_flow_ready'] = (int)($counts['reviews'] ?? 0) >= 0;
        $checks['reward_lifecycle_ready'] = isset($rewardBridge['counts']);
        $checks['routes_ready'] = (int)($routes['score'] ?? 0) >= 100;
        $passed = count(array_filter($checks));
        return ['score' => (int)round(($passed / max(1, count($checks))) * 100), 'passed' => $passed, 'total' => count($checks), 'checks' => $checks];
    }
}

if (!function_exists('tl_stage200_log_event')) {
    function tl_stage200_log_event(string $subjectType, ?int $subjectId, string $eventType, array $metadata, ?int $actorId = null): array
    {
        $pdo = tl_require_db();
        $actorId = $actorId ?: tl_stage200_actor_id();
        $allowed = ['campaign','task','participant','proof','review','receipt','reward_rule','reward_event','streak','system'];
        if (!in_array($subjectType, $allowed, true)) $subjectType = 'system';
        tl_log_event($pdo, $actorId, $subjectType, $subjectId, $eventType, $metadata + ['stage200_app_build' => true]);
        return ['event_type' => $eventType, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'actor_user_id' => $actorId];
    }
}

if (!function_exists('tl_stage200_save_training_note')) {
    function tl_stage200_save_training_note(array $input): array
    {
        $note = tl_stage200_clean($input['note'] ?? $input['body'] ?? '', 1200);
        if ($note === '') throw new RuntimeException('A note is required.');
        $subjectType = tl_stage200_clean($input['subject_type'] ?? 'system', 40);
        $subjectId = isset($input['subject_id']) && ctype_digit((string)$input['subject_id']) ? (int)$input['subject_id'] : null;
        $title = tl_stage200_clean($input['title'] ?? 'Training note', 160);
        return tl_stage200_log_event($subjectType, $subjectId, 'training_note_saved', [
            'title' => $title,
            'note' => $note,
            'visibility' => tl_stage200_clean($input['visibility'] ?? 'internal', 40),
            'source' => tl_stage200_clean($input['source'] ?? 'stage200_app', 80),
        ], tl_stage200_actor_id($input));
    }
}

if (!function_exists('tl_stage200_save_campaign_checkpoint')) {
    function tl_stage200_save_campaign_checkpoint(array $input): array
    {
        $pdo = tl_require_db();
        $campaign = tl_stage200_campaign_row((string)($input['campaign'] ?? $input['campaign_id'] ?? ''));
        if (!$campaign) throw new RuntimeException('Campaign not found for checkpoint.');
        $checkpoint = tl_stage200_clean($input['checkpoint'] ?? $input['status_note'] ?? 'Operator checkpoint saved.', 700);
        return tl_stage200_log_event('campaign', (int)$campaign['id'], 'campaign_checkpoint_saved', [
            'campaign_ref' => tl_stage200_campaign_ref($campaign),
            'checkpoint' => $checkpoint,
            'next_action' => tl_stage200_clean($input['next_action'] ?? '', 300),
        ], tl_stage200_actor_id($input));
    }
}

if (!function_exists('tl_stage200_mark_participant_focus')) {
    function tl_stage200_mark_participant_focus(array $input): array
    {
        $pdo = tl_require_db();
        $participantRef = tl_stage200_clean($input['participant_id'] ?? $input['participant'] ?? '', 80);
        if ($participantRef === '') throw new RuntimeException('Participant id is required.');
        $stmt = $pdo->prepare('SELECT * FROM training_participants WHERE id = ? OR public_id = ? LIMIT 1');
        $stmt->execute([ctype_digit($participantRef) ? (int)$participantRef : 0, $participantRef]);
        $participant = $stmt->fetch();
        if (!$participant) throw new RuntimeException('Participant not found.');
        $metadata = tl_stage200_decode_json($participant['metadata_json'] ?? null);
        $metadata['stage200_focus'] = [
            'level' => tl_stage200_clean($input['focus_level'] ?? 'normal', 40),
            'note' => tl_stage200_clean($input['focus_note'] ?? 'Participant focus updated.', 500),
            'updated_at' => gmdate('c'),
            'updated_by_user_id' => tl_stage200_actor_id($input),
        ];
        $status = tl_stage200_clean($input['participant_status'] ?? (string)$participant['status'], 40);
        if (!in_array($status, ['invited','active','paused','completed','removed'], true)) $status = (string)$participant['status'];
        $upd = $pdo->prepare('UPDATE training_participants SET status = ?, metadata_json = ? WHERE id = ?');
        $upd->execute([$status, json_encode($metadata, JSON_UNESCAPED_SLASHES), (int)$participant['id']]);
        tl_log_event($pdo, tl_stage200_actor_id($input), 'participant', (int)$participant['id'], 'participant_focus_updated', ['status' => $status, 'focus' => $metadata['stage200_focus']]);
        return ['participant_id' => (int)$participant['id'], 'status' => $status, 'focus' => $metadata['stage200_focus']];
    }
}

if (!function_exists('tl_stage200_create_workflow_snapshot')) {
    function tl_stage200_create_workflow_snapshot(array $input = []): array
    {
        $state = tl_stage200_workflow_state((string)($input['campaign'] ?? $input['campaign_id'] ?? ''), max(0, (int)($input['user_id'] ?? 0)));
        $admin = tl_stage200_admin_state();
        $snapshot = [
            'workflow_progress' => $state['progress_percent'] ?? 0,
            'next_step' => $state['next_step']['key'] ?? null,
            'blocker_count' => count($state['blockers'] ?? []),
            'operations_score' => $admin['operations_score']['score'] ?? 0,
            'reward_counts' => $state['rewards']['counts'] ?? [],
            'route_score' => $admin['route_readiness']['score'] ?? 0,
        ];
        tl_stage200_log_event('system', null, 'workflow_snapshot_created', $snapshot, tl_stage200_actor_id($input));
        return $snapshot + ['created_at' => gmdate('c')];
    }
}

if (!function_exists('tl_stage200_run_core_qa')) {
    function tl_stage200_run_core_qa(array $input = []): array
    {
        $state = tl_stage200_workflow_state((string)($input['campaign'] ?? $input['campaign_id'] ?? ''), max(0, (int)($input['user_id'] ?? 0)));
        $admin = tl_stage200_admin_state();
        $checks = [
            'direct_extract_routes' => ($admin['route_readiness']['score'] ?? 0) >= 100,
            'db_connected' => !empty($state['summary']['connected']),
            'tables_present' => empty(array_filter($state['summary']['table_status'] ?? [], fn($ok) => !$ok)),
            'account_context_available' => isset($state['auth']['roles']),
            'campaign_flow_available' => (int)($state['summary']['counts']['campaigns'] ?? 0) >= 0,
            'participant_flow_available' => isset($state['participant_context']['tasks']),
            'reward_lifecycle_available' => isset($state['rewards']['claim_flow']),
            'safe_boundaries_declared' => !empty($state['safe_boundaries']),
        ];
        $score = (int)round((count(array_filter($checks)) / max(1, count($checks))) * 100);
        $result = ['score' => $score, 'checks' => $checks, 'run_at' => gmdate('c'), 'accepted' => $score >= 100];
        if (!empty($input['log_event']) || !empty($input['persist'])) {
            try {
                tl_stage200_log_event('system', null, 'core_workflow_qa_run', $result, tl_stage200_actor_id($input));
            } catch (Throwable $e) {
                $result['log_warning'] = $e->getMessage();
            }
        }
        return $result;
    }
}

if (!function_exists('tl_stage200_summary')) {
    function tl_stage200_summary(): array
    {
        return [
            'stage' => 'Stage 161-200 stacked app builds',
            'builds' => [
                'Build 1: Core workflow engine and product state API',
                'Build 2: Participant mission control and task run improvements',
                'Build 3: Review operations and coach/admin note loop',
                'Build 4: Reward lifecycle operations and claim tracking polish',
                'Build 5: Backend readiness, QA snapshots, and design-asset prep shell',
            ],
            'workflow_state' => tl_stage200_workflow_state(),
            'admin_state' => tl_stage200_admin_state(),
            'safe_boundaries' => [
                'no_new_sql_required' => true,
                'no_new_page_factory' => true,
                'uses_existing_core_pages' => true,
                'all_writes_training_tables_only' => true,
                'microgifter_real_issue_adapter_gated' => true,
            ],
        ];
    }
}
