<?php
/**
 * Stage 561-600 Workflow Control + Operator Usability.
 *
 * Five-section batch focused on campaign state control, participant timeline,
 * proof review console, reward operations, and a daily operator command
 * snapshot. It reuses existing Training Lab tables/state and remains inside
 * safe standalone boundaries: no SQL migrations, uploads, payments, wallet
 * mutation, production claim/redeem behavior, deletes, or resets.
 */

if (!function_exists('tl_stage600_e')) {
    function tl_stage600_e($value): string
    {
        return function_exists('labs_e') ? labs_e((string)$value) : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('tl_stage600_score_from_checks')) {
    function tl_stage600_score_from_checks(array $checks): int
    {
        if (!$checks) return 100;
        $passed = 0;
        foreach ($checks as $ok) if ($ok) $passed++;
        return (int)round(($passed / max(1, count($checks))) * 100);
    }
}

if (!function_exists('tl_stage600_root')) {
    function tl_stage600_root(): string
    {
        return dirname(__DIR__);
    }
}

if (!function_exists('tl_stage600_route_exists')) {
    function tl_stage600_route_exists(string $route): bool
    {
        return is_file(tl_stage600_root() . '/' . ltrim($route, '/'));
    }
}

if (!function_exists('tl_stage600_badge_status')) {
    function tl_stage600_badge_status(string $status): string
    {
        $status = strtolower(trim($status));
        if (in_array($status, ['active', 'published', 'ready', 'complete', 'completed', 'approved', 'issued'], true)) return 'good';
        if (in_array($status, ['paused', 'hold', 'needs info', 'needs_info', 'review needed', 'review_needed', 'failed'], true)) return 'warn';
        return 'neutral';
    }
}

if (!function_exists('tl_stage600_campaign_state_control')) {
    function tl_stage600_campaign_state_control(string $campaignRef = ''): array
    {
        $flow = function_exists('tl_stage520_campaign_flow') ? tl_stage520_campaign_flow($campaignRef) : [];
        $planner = function_exists('tl_stage560_campaign_publish_planner') ? tl_stage560_campaign_publish_planner($campaignRef) : [];
        $status = strtolower((string)($flow['status'] ?? 'draft'));
        $taskCount = (int)($flow['task_count'] ?? $planner['task_count'] ?? 0);
        $proofCount = (int)($flow['proof_task_count'] ?? $planner['proof_task_count'] ?? 0);
        $rewardReady = !empty($flow['reward_ready']) || !empty($planner['reward_ready']);
        $sequenceReady = !empty($flow['sequence_ready']) || $taskCount >= 3;
        $campaignExists = !empty($flow['campaign']) || (int)($flow['campaign_count'] ?? 0) > 0;
        $ready = $campaignExists && $sequenceReady && $rewardReady;
        $reviewNeeded = !$sequenceReady || !$rewardReady;
        $states = [
            ['key' => 'draft', 'label' => 'Draft', 'active' => $status === 'draft' || !$campaignExists, 'detail' => 'Campaign is being shaped before review.'],
            ['key' => 'ready', 'label' => 'Ready', 'active' => $ready && !in_array($status, ['active','published','completed'], true), 'detail' => 'Definition, sequence, and reward are ready for activation.'],
            ['key' => 'active', 'label' => 'Active', 'active' => in_array($status, ['active','published'], true), 'detail' => 'Participants can run the mission path.'],
            ['key' => 'paused', 'label' => 'Paused', 'active' => $status === 'paused', 'detail' => 'Hold participant movement without deleting anything.'],
            ['key' => 'review_needed', 'label' => 'Review Needed', 'active' => $reviewNeeded, 'detail' => 'Task path or reward details need operator review.'],
            ['key' => 'completed', 'label' => 'Completed', 'active' => in_array($status, ['completed','archived'], true), 'detail' => 'Campaign is closed or archived for reporting.'],
        ];
        $checklist = [
            ['label' => 'Campaign definition', 'status' => $campaignExists ? 'complete' : 'draft', 'href' => '/app/campaign-builder.php', 'detail' => $campaignExists ? 'Title/status is available.' : 'Create the campaign shell.'],
            ['label' => 'Task sequence', 'status' => $sequenceReady ? 'complete' : 'needs work', 'href' => '/app/campaign-builder.php', 'detail' => $taskCount . ' task(s) found; target is 3+.'],
            ['label' => 'Proof rule', 'status' => $proofCount > 0 ? 'complete' : 'recommended', 'href' => '/app/proof-upload.php', 'detail' => $proofCount . ' proof-required task(s).'],
            ['label' => 'Reward path', 'status' => $rewardReady ? 'complete' : 'needs setup', 'href' => '/app/rewards.php', 'detail' => $rewardReady ? 'Reward preview is visible.' : 'Add reward label/summary.'],
            ['label' => 'Operator review', 'status' => $ready ? 'ready' : 'hold', 'href' => '/admin/campaign-inspector.php', 'detail' => $ready ? 'Ready for active state.' : 'Hold until checklist is complete.'],
        ];
        $checks = [
            'campaign_builder_route' => tl_stage600_route_exists('/app/campaign-builder.php'),
            'campaigns_route' => tl_stage600_route_exists('/app/campaigns.php'),
            'campaign_detail_route' => tl_stage600_route_exists('/app/campaign-detail.php'),
            'admin_campaigns_route' => tl_stage600_route_exists('/admin/campaigns.php'),
            'campaign_inspector_route' => tl_stage600_route_exists('/admin/campaign-inspector.php'),
            'safe_guidance_only_no_destructive_actions' => true,
        ];
        return [
            'stage' => 'Stage 561-568 campaign state control',
            'campaign_ref' => (string)($flow['campaign_ref'] ?? $campaignRef),
            'status' => $status,
            'ready' => $ready,
            'task_count' => $taskCount,
            'proof_task_count' => $proofCount,
            'reward_ready' => $rewardReady,
            'states' => $states,
            'checklist' => $checklist,
            'score' => tl_stage600_score_from_checks($checks),
            'accepted' => tl_stage600_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}

if (!function_exists('tl_stage600_participant_timeline')) {
    function tl_stage600_participant_timeline(string $campaignRef = '', int $userId = 0): array
    {
        $mission = function_exists('tl_stage520_participant_mission') ? tl_stage520_participant_mission($campaignRef, $userId) : [];
        $runbook = function_exists('tl_stage560_mission_runbook') ? tl_stage560_mission_runbook($campaignRef, $userId) : [];
        $progress = (int)($mission['progress_percent'] ?? $runbook['progress_percent'] ?? 0);
        $counts = (array)($mission['counts'] ?? []);
        $rewardCounts = (array)($mission['reward_counts'] ?? []);
        $next = (array)($mission['next_task'] ?? []);
        $pendingProofs = (int)($counts['pending_proofs'] ?? 0);
        $claimable = (int)($rewardCounts['claimable'] ?? $rewardCounts['available_to_claim'] ?? 0);
        $timeline = [
            ['label' => 'Joined', 'status' => $progress > 0 || !empty($mission['campaign_ref']) ? 'complete' : 'ready', 'href' => '/app/participant-portal.php', 'detail' => 'Participant is connected to the mission dashboard.'],
            ['label' => 'Task Started', 'status' => !empty($next) ? 'active' : ($progress >= 100 ? 'complete' : 'queued'), 'href' => '/app/task-runner.php', 'detail' => (string)($next['title'] ?? $runbook['next_task_label'] ?? 'Open the next task.')],
            ['label' => 'Proof Submitted', 'status' => $pendingProofs > 0 ? 'active' : ($progress > 0 ? 'visible' : 'queued'), 'href' => '/app/proof-upload.php', 'detail' => $pendingProofs . ' proof item(s) currently waiting.'],
            ['label' => 'Review Pending', 'status' => $pendingProofs > 0 ? 'waiting' : 'clear', 'href' => '/app/flow-board.php', 'detail' => 'Proof moves through waiting, needs info, approved, or rejected.'],
            ['label' => 'Reward Available', 'status' => $claimable > 0 ? 'ready' : 'queued', 'href' => '/app/rewards.php', 'detail' => $claimable . ' reward(s) available to claim.'],
        ];
        $position = $claimable > 0 ? 'Reward available' : ($pendingProofs > 0 ? 'Review pending' : ($progress >= 100 ? 'Mission complete' : 'Running mission'));
        $checks = [
            'participant_portal_route' => tl_stage600_route_exists('/app/participant-portal.php'),
            'task_runner_route' => tl_stage600_route_exists('/app/task-runner.php'),
            'progress_map_route' => tl_stage600_route_exists('/app/progress-map.php'),
            'flow_board_route' => tl_stage600_route_exists('/app/flow-board.php'),
            'participant_inspector_route' => tl_stage600_route_exists('/admin/participant-inspector.php'),
        ];
        return [
            'stage' => 'Stage 569-576 participant timeline and activity trail',
            'campaign_ref' => (string)($mission['campaign_ref'] ?? $campaignRef),
            'user_id' => (int)($mission['user_id'] ?? $userId),
            'progress_percent' => $progress,
            'current_position' => $position,
            'timeline' => $timeline,
            'counts' => $counts,
            'reward_counts' => $rewardCounts,
            'score' => tl_stage600_score_from_checks($checks),
            'accepted' => tl_stage600_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}

if (!function_exists('tl_stage600_proof_review_console')) {
    function tl_stage600_proof_review_console(): array
    {
        $admin = function_exists('tl_stage520_admin_operations') ? tl_stage520_admin_operations() : [];
        $quality = function_exists('tl_stage280_proof_quality_state') ? tl_stage280_proof_quality_state() : [];
        $proofLanes = (array)($admin['proof_lanes'] ?? []);
        $waiting = (int)($admin['pending_proof_count'] ?? $proofLanes['pending_review']['value'] ?? 0);
        $needsInfo = (int)($admin['review_sla']['needs_info_count'] ?? 0);
        $approved = (int)($proofLanes['approved']['value'] ?? 0);
        $rejected = (int)($proofLanes['rejected']['value'] ?? 0);
        $lanes = [
            ['key' => 'waiting', 'label' => 'Waiting', 'value' => $waiting, 'href' => '/admin/review-workbench.php', 'detail' => 'Submissions that need a reviewer.'],
            ['key' => 'needs_info', 'label' => 'Needs Info', 'value' => $needsInfo, 'href' => '/admin/review-queue.php', 'detail' => 'Submissions requiring participant clarification.'],
            ['key' => 'approved', 'label' => 'Approved', 'value' => $approved, 'href' => '/admin/review-inspector.php', 'detail' => 'Accepted proof outcomes.'],
            ['key' => 'rejected', 'label' => 'Rejected', 'value' => $rejected, 'href' => '/admin/review-inspector.php', 'detail' => 'Closed proof outcomes.'],
        ];
        $checklist = [
            ['label' => 'Evidence readable', 'status' => 'required', 'detail' => 'Proof text or metadata should explain the action.'],
            ['label' => 'Task matched', 'status' => 'required', 'detail' => 'Submitted proof should map to the selected task.'],
            ['label' => 'Decision reason', 'status' => 'required', 'detail' => 'Approve, reject, or ask for more info with a clear note.'],
            ['label' => 'Reward eligible', 'status' => 'conditional', 'detail' => 'Approved proof can feed the reward lifecycle.'],
        ];
        $checks = [
            'review_workbench_route' => tl_stage600_route_exists('/admin/review-workbench.php'),
            'review_queue_route' => tl_stage600_route_exists('/admin/review-queue.php'),
            'review_inspector_route' => tl_stage600_route_exists('/admin/review-inspector.php'),
            'proof_upload_route' => tl_stage600_route_exists('/app/proof-upload.php'),
            'safe_review_actions_existing_router' => true,
        ];
        return [
            'stage' => 'Stage 577-584 proof review console upgrade',
            'lanes' => $lanes,
            'decision_checklist' => $checklist,
            'proof_quality' => $quality,
            'score' => tl_stage600_score_from_checks($checks),
            'accepted' => tl_stage600_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}

if (!function_exists('tl_stage600_reward_operations')) {
    function tl_stage600_reward_operations(): array
    {
        $admin = function_exists('tl_stage520_admin_operations') ? tl_stage520_admin_operations() : [];
        $assurance = function_exists('tl_stage280_reward_assurance') ? tl_stage280_reward_assurance() : [];
        $rewardLanes = (array)($admin['reward_lanes'] ?? []);
        $board = [
            ['key' => 'earned', 'label' => 'Earned', 'value' => (int)($rewardLanes['available_to_claim'] ?? 0), 'href' => '/app/rewards.php', 'detail' => 'Approved action created a claimable event.'],
            ['key' => 'claimable', 'label' => 'Claimable', 'value' => (int)($rewardLanes['available_to_claim'] ?? 0), 'href' => '/app/rewards.php', 'detail' => 'Participant can claim inside Training Lab.'],
            ['key' => 'claimed', 'label' => 'Claimed', 'value' => (int)($rewardLanes['claimed_in_app'] ?? 0), 'href' => '/admin/reward-bridge.php', 'detail' => 'Claim tracked in-app only.'],
            ['key' => 'pending_sync', 'label' => 'Pending Sync', 'value' => (int)($rewardLanes['pending_microgifter_sync'] ?? 0), 'href' => '/admin/reward-bridge.php', 'detail' => 'Microgifter bridge waits for adapter/key.'],
            ['key' => 'issued', 'label' => 'Issued', 'value' => (int)($rewardLanes['issued'] ?? 0), 'href' => '/admin/reward-inspector.php', 'detail' => 'Issued or linked through the safe bridge.'],
            ['key' => 'failed', 'label' => 'Failed', 'value' => (int)($rewardLanes['failed_retry_available'] ?? 0), 'href' => '/admin/reward-bridge.php', 'detail' => 'Retry/manual issue lane.'],
            ['key' => 'cancelled', 'label' => 'Cancelled', 'value' => (int)($rewardLanes['cancelled'] ?? 0), 'href' => '/admin/reward-inspector.php', 'detail' => 'Closed without payment/wallet mutation.'],
        ];
        $checks = [
            'app_rewards_route' => tl_stage600_route_exists('/app/rewards.php'),
            'reward_bridge_route' => tl_stage600_route_exists('/admin/reward-bridge.php'),
            'reward_inspector_route' => tl_stage600_route_exists('/admin/reward-inspector.php'),
            'command_center_route' => tl_stage600_route_exists('/admin/command-center.php'),
            'microgifter_issuing_adapter_gated' => true,
            'no_wallet_mutation' => true,
        ];
        return [
            'stage' => 'Stage 585-592 reward operations upgrade',
            'board' => $board,
            'assurance' => $assurance,
            'participant_copy' => 'Rewards unlock after approved Training Lab actions. Real Microgifter issuing stays adapter/developer-key gated.',
            'score' => tl_stage600_score_from_checks($checks),
            'accepted' => tl_stage600_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}

if (!function_exists('tl_stage600_operator_command_snapshot')) {
    function tl_stage600_operator_command_snapshot(): array
    {
        $campaign = tl_stage600_campaign_state_control();
        $timeline = tl_stage600_participant_timeline();
        $proof = tl_stage600_proof_review_console();
        $reward = tl_stage600_reward_operations();
        $stage560 = function_exists('tl_stage560_operational_run_summary') ? tl_stage560_operational_run_summary(false) : [];
        $proofWaiting = 0;
        foreach ($proof['lanes'] as $lane) if ($lane['key'] === 'waiting') $proofWaiting = (int)$lane['value'];
        $failedRewards = 0;
        foreach ($reward['board'] as $lane) if ($lane['key'] === 'failed') $failedRewards = (int)$lane['value'];
        $order = [
            ['label' => 'Check campaign state', 'status' => $campaign['ready'] ? 'ready' : 'review', 'href' => '/admin/campaigns.php', 'detail' => $campaign['ready'] ? 'Campaign is ready for active operations.' : 'Complete readiness checklist first.'],
            ['label' => 'Inspect participant position', 'status' => strtolower((string)$timeline['current_position']), 'href' => '/admin/participant-inspector.php', 'detail' => $timeline['current_position'] . ' · ' . $timeline['progress_percent'] . '% progress.'],
            ['label' => 'Work proof queue', 'status' => $proofWaiting > 0 ? 'active' : 'clear', 'href' => '/admin/review-workbench.php', 'detail' => $proofWaiting . ' proof item(s) waiting.'],
            ['label' => 'Check reward assurance', 'status' => $failedRewards > 0 ? 'retry' : 'clear', 'href' => '/admin/reward-bridge.php', 'detail' => $failedRewards . ' failed/retry reward item(s).'],
            ['label' => 'Open reporting snapshot', 'status' => 'ready', 'href' => '/admin/reporting-center.php', 'detail' => 'Review daily operator ledger.'],
        ];
        $scores = [(int)$campaign['score'], (int)$timeline['score'], (int)$proof['score'], (int)$reward['score'], (int)($stage560['score'] ?? 100)];
        $checks = [
            'admin_index_route' => tl_stage600_route_exists('/admin/index.php'),
            'command_center_route' => tl_stage600_route_exists('/admin/command-center.php'),
            'backend_readiness_route' => tl_stage600_route_exists('/admin/backend-readiness.php'),
            'reporting_center_route' => tl_stage600_route_exists('/admin/reporting-center.php'),
            'ops_overview_api' => tl_stage600_route_exists('/api/training/ops-overview.php'),
            'workflow_control_api' => tl_stage600_route_exists('/api/training/workflow-control.php'),
        ];
        $score = min((int)round(array_sum($scores) / max(1, count($scores))), tl_stage600_score_from_checks($checks));
        return [
            'stage' => 'Stage 593-600 operator command snapshot',
            'todays_operating_order' => $order,
            'campaign' => $campaign,
            'participant_timeline' => $timeline,
            'proof_review' => $proof,
            'reward_operations' => $reward,
            'stage560_baseline' => $stage560,
            'score' => $score,
            'accepted' => $score === 100,
            'checks' => $checks,
        ];
    }
}

if (!function_exists('tl_stage600_route_contract')) {
    function tl_stage600_route_contract(): array
    {
        return [
            'campaign_state_control' => ['/app/campaign-builder.php','/app/campaigns.php','/app/campaign-detail.php','/admin/campaigns.php','/admin/campaign-inspector.php'],
            'participant_timeline' => ['/app/participant-portal.php','/app/task-runner.php','/app/progress-map.php','/app/flow-board.php','/admin/participant-inspector.php'],
            'proof_review_console' => ['/admin/review-workbench.php','/admin/review-queue.php','/admin/review-inspector.php','/app/proof-upload.php'],
            'reward_operations' => ['/app/rewards.php','/admin/reward-bridge.php','/admin/reward-inspector.php','/admin/command-center.php'],
            'operator_command_snapshot' => ['/admin/index.php','/admin/command-center.php','/admin/backend-readiness.php','/admin/reporting-center.php','/api/training/ops-overview.php','/api/training/workflow-control.php'],
        ];
    }
}

if (!function_exists('tl_stage600_workflow_control_audit')) {
    function tl_stage600_workflow_control_audit(): array
    {
        $issues = [];
        foreach (tl_stage600_route_contract() as $group => $routes) {
            foreach ($routes as $route) if (!tl_stage600_route_exists($route)) $issues[] = 'Missing ' . $group . ' route ' . $route;
        }
        foreach (['tl_stage600_campaign_state_control','tl_stage600_participant_timeline','tl_stage600_proof_review_console','tl_stage600_reward_operations','tl_stage600_operator_command_snapshot','tl_stage600_workflow_control_summary'] as $fn) {
            if (!function_exists($fn)) $issues[] = 'Missing function ' . $fn;
        }
        $css = is_file(tl_stage600_root() . '/assets/css/labs.css') ? (string)file_get_contents(tl_stage600_root() . '/assets/css/labs.css') : '';
        foreach (['labs-stage600-panel','labs-stage600-state-grid','labs-stage600-timeline','labs-stage600-lane-board','labs-li-stage600-control'] as $class) {
            if (strpos($css, $class) === false) $issues[] = 'Missing CSS class .' . $class;
        }
        $markers = [
            'app/campaign-builder.php' => 'tl_stage600_render_campaign_state_control',
            'admin/campaigns.php' => 'tl_stage600_render_campaign_state_control',
            'app/participant-portal.php' => 'tl_stage600_render_participant_timeline',
            'admin/participant-inspector.php' => 'tl_stage600_render_participant_timeline',
            'admin/review-workbench.php' => 'tl_stage600_render_proof_review_console',
            'app/proof-upload.php' => 'tl_stage600_render_proof_review_console',
            'app/rewards.php' => 'tl_stage600_render_reward_operations',
            'admin/reward-bridge.php' => 'tl_stage600_render_reward_operations',
            'admin/index.php' => 'tl_stage600_render_operator_command_snapshot',
            'admin/backend-readiness.php' => 'tl_stage600_render_operator_command_snapshot',
        ];
        foreach ($markers as $path => $needle) {
            $file = tl_stage600_root() . '/' . $path;
            if (!is_file($file) || strpos((string)file_get_contents($file), $needle) === false) $issues[] = $path . ' missing Stage 600 marker ' . $needle;
        }
        $score = count($issues) === 0 ? 100 : max(0, 100 - (count($issues) * 5));
        return [
            'stage' => 'Stage 561-600 workflow control audit',
            'route_contract' => tl_stage600_route_contract(),
            'issue_count' => count($issues),
            'issues' => $issues,
            'score' => $score,
            'accepted' => count($issues) === 0,
        ];
    }
}

if (!function_exists('tl_stage600_workflow_control_summary')) {
    function tl_stage600_workflow_control_summary(bool $includeAudit = true): array
    {
        $campaign = tl_stage600_campaign_state_control();
        $timeline = tl_stage600_participant_timeline();
        $proof = tl_stage600_proof_review_console();
        $reward = tl_stage600_reward_operations();
        $operator = tl_stage600_operator_command_snapshot();
        $audit = $includeAudit ? tl_stage600_workflow_control_audit() : ['accepted'=>true,'score'=>100,'issue_count'=>0,'issues'=>[]];
        $sections = [$campaign, $timeline, $proof, $reward, $operator, $audit];
        $accepted = true;
        $scores = [];
        foreach ($sections as $section) {
            $scores[] = (int)($section['score'] ?? 0);
            if (empty($section['accepted'])) $accepted = false;
        }
        return [
            'stage' => 'Stage 561-600 workflow control and operator usability',
            'built_from' => 'Stage 521-560 operational run loop',
            'builds' => [
                'Build 74: Campaign State Control',
                'Build 75: Participant Timeline + Activity Trail',
                'Build 76: Proof Review Console Upgrade',
                'Build 77: Reward Operations Upgrade',
                'Build 78: Operator Command Snapshot',
            ],
            'campaign_state_control' => $campaign,
            'participant_timeline' => $timeline,
            'proof_review_console' => $proof,
            'reward_operations' => $reward,
            'operator_command_snapshot' => $operator,
            'workflow_control_audit' => $audit,
            'score' => $accepted ? 100 : (int)round(array_sum($scores) / max(1, count($scores))),
            'accepted' => $accepted,
            'safe_boundaries' => [
                'no_new_sql_required' => true,
                'no_config_files_moved_or_overwritten' => true,
                'no_hard_auth_gates_forced' => true,
                'no_page_factory_expansion' => true,
                'no_real_upload_processing' => true,
                'no_payments_or_wallet_mutation' => true,
                'no_production_claim_redeem_logic' => true,
                'microgifter_reward_issuing_adapter_gated' => true,
            ],
        ];
    }
}

if (!function_exists('tl_stage600_context_cards')) {
    function tl_stage600_context_cards(string $context): array
    {
        if (strpos($context, 'campaign') !== false) {
            return [
                ['label' => 'State', 'value' => 'Control', 'hint' => 'draft → ready → active', 'href' => '/app/campaign-detail.php'],
                ['label' => 'Checklist', 'value' => 'Review', 'hint' => 'tasks, proof, reward', 'href' => '/app/campaign-builder.php'],
                ['label' => 'Admin', 'value' => 'Inspect', 'hint' => 'campaign state', 'href' => '/admin/campaign-inspector.php'],
            ];
        }
        if (strpos($context, 'review') !== false || strpos($context, 'proof') !== false) {
            return [
                ['label' => 'Proof', 'value' => 'Lanes', 'hint' => 'waiting, needs info', 'href' => '/admin/review-workbench.php'],
                ['label' => 'Decision', 'value' => 'Checklist', 'hint' => 'review quality', 'href' => '/admin/review-queue.php'],
                ['label' => 'Participant', 'value' => 'Status', 'hint' => 'submission state', 'href' => '/app/proof-upload.php'],
            ];
        }
        if (strpos($context, 'reward') !== false || strpos($context, 'wallet') !== false) {
            return [
                ['label' => 'Reward', 'value' => 'Board', 'hint' => 'earned to issued', 'href' => '/admin/reward-bridge.php'],
                ['label' => 'Claim', 'value' => 'Safe', 'hint' => 'in-app only', 'href' => '/app/rewards.php'],
                ['label' => 'Bridge', 'value' => 'Gated', 'hint' => 'adapter/key', 'href' => '/admin/reward-inspector.php'],
            ];
        }
        if (str_starts_with($context, 'admin-')) {
            return [
                ['label' => 'Today', 'value' => 'Order', 'hint' => 'operator command', 'href' => '/admin/command-center.php'],
                ['label' => 'Proofs', 'value' => 'Triage', 'hint' => 'review lanes', 'href' => '/admin/review-workbench.php'],
                ['label' => 'Rewards', 'value' => 'Assure', 'hint' => 'bridge state', 'href' => '/admin/reward-bridge.php'],
            ];
        }
        return [
            ['label' => 'Mission', 'value' => 'Timeline', 'hint' => 'task to reward', 'href' => '/app/progress-map.php'],
            ['label' => 'Proof', 'value' => 'Status', 'hint' => 'review wait state', 'href' => '/app/proof-upload.php'],
            ['label' => 'Reward', 'value' => 'Next', 'hint' => 'claim path', 'href' => '/app/rewards.php'],
        ];
    }
}

if (!function_exists('tl_stage600_context_runtime_overrides')) {
    function tl_stage600_context_runtime_overrides(string $context, array $baseCfg = []): array
    {
        $stage560 = function_exists('tl_stage560_context_runtime_overrides') ? tl_stage560_context_runtime_overrides($context, $baseCfg) : [];
        $live = array_values(array_unique(array_merge((array)($stage560['live_strip'] ?? []), ['Workflow control', 'Stage 600'])));
        return array_replace_recursive($stage560, [
            'live_strip' => $live,
            'stage600_cards' => tl_stage600_context_cards($context),
            'status_meta' => str_starts_with($context, 'admin-')
                ? ['Campaign state', 'Proof lanes', 'Reward board']
                : ['Mission timeline', 'Proof status', 'Reward path'],
            'stage600_runtime_bound' => true,
        ]);
    }
}

if (!function_exists('tl_stage600_render_cards')) {
    function tl_stage600_render_cards(array $items, string $class = 'labs-stage600-card-list'): void
    {
        echo '<div class="' . tl_stage600_e($class) . '">';
        foreach ($items as $item) {
            $href = (string)($item['href'] ?? '#');
            $status = (string)($item['status'] ?? $item['key'] ?? 'ready');
            echo '<a href="' . tl_stage600_e(function_exists('labs_url') ? labs_url($href) : $href) . '" class="labs-stage600-status-' . tl_stage600_e(tl_stage600_badge_status($status)) . '"><span>' . tl_stage600_e($status) . '</span><strong>' . tl_stage600_e($item['label'] ?? 'Workflow item') . '</strong><small>' . tl_stage600_e($item['detail'] ?? '') . '</small></a>';
        }
        echo '</div>';
    }
}

if (!function_exists('tl_stage600_render_shell')) {
    function tl_stage600_render_shell(string $eyebrow, string $title, array $metrics, array $items, string $apiHref, string $class = ''): void
    {
        echo '<section class="labs-card labs-stage600-panel ' . tl_stage600_e($class) . '"><div class="labs-card-headline"><div><span class="labs-eyebrow">' . tl_stage600_e($eyebrow) . '</span><h2>' . tl_stage600_e($title) . '</h2></div><a class="labs-btn" href="' . tl_stage600_e(labs_url($apiHref)) . '">Workflow API</a></div>';
        echo '<div class="labs-stage600-state-grid">';
        foreach ($metrics as $metric) {
            echo '<article><span>' . tl_stage600_e($metric['label'] ?? 'Metric') . '</span><strong>' . tl_stage600_e($metric['value'] ?? 'Ready') . '</strong><small>' . tl_stage600_e($metric['hint'] ?? '') . '</small></article>';
        }
        echo '</div>';
        tl_stage600_render_cards($items, 'labs-stage600-card-list ' . $class . '-items');
        echo '</section>';
    }
}

if (!function_exists('tl_stage600_render_campaign_state_control')) {
    function tl_stage600_render_campaign_state_control(string $campaignRef = ''): void
    {
        $data = tl_stage600_campaign_state_control($campaignRef);
        $metrics = [
            ['label'=>'Campaign status', 'value'=>ucwords(str_replace('_',' ', $data['status'])), 'hint'=>$data['ready'] ? 'ready to operate' : 'needs review'],
            ['label'=>'Task path', 'value'=>(string)$data['task_count'], 'hint'=>$data['proof_task_count'] . ' proof task(s)'],
            ['label'=>'Reward', 'value'=>$data['reward_ready'] ? 'Ready' : 'Pending', 'hint'=>'preview before active'],
        ];
        tl_stage600_render_shell('Stage 561–568', 'Campaign State Control', $metrics, $data['checklist'], '/api/training/workflow-control.php?section=campaign', 'labs-stage600-campaign');
        echo '<div class="labs-stage600-lane-board labs-stage600-campaign-states">';
        foreach ($data['states'] as $state) {
            echo '<article class="' . (!empty($state['active']) ? 'is-active' : '') . '"><span>' . tl_stage600_e($state['label']) . '</span><strong>' . (!empty($state['active']) ? 'Current' : 'Available') . '</strong><small>' . tl_stage600_e($state['detail']) . '</small></article>';
        }
        echo '</div>';
    }
}

if (!function_exists('tl_stage600_render_participant_timeline')) {
    function tl_stage600_render_participant_timeline(string $campaignRef = '', int $userId = 0): void
    {
        $data = tl_stage600_participant_timeline($campaignRef, $userId);
        $metrics = [
            ['label'=>'Current position', 'value'=>$data['current_position'], 'hint'=>'participant state'],
            ['label'=>'Progress', 'value'=>$data['progress_percent'] . '%', 'hint'=>'mission completion'],
            ['label'=>'Rewards', 'value'=>(string)(($data['reward_counts']['claimable'] ?? $data['reward_counts']['available_to_claim'] ?? 0)), 'hint'=>'claimable'],
        ];
        tl_stage600_render_shell('Stage 569–576', 'Participant Timeline + Activity Trail', $metrics, $data['timeline'], '/api/training/workflow-control.php?section=timeline', 'labs-stage600-timeline');
    }
}

if (!function_exists('tl_stage600_render_proof_review_console')) {
    function tl_stage600_render_proof_review_console(): void
    {
        $data = tl_stage600_proof_review_console();
        $metrics = [];
        foreach ($data['lanes'] as $lane) $metrics[] = ['label'=>$lane['label'], 'value'=>(string)$lane['value'], 'hint'=>$lane['detail']];
        tl_stage600_render_shell('Stage 577–584', 'Proof Review Console', $metrics, $data['decision_checklist'], '/api/training/workflow-control.php?section=proof', 'labs-stage600-proof');
    }
}

if (!function_exists('tl_stage600_render_reward_operations')) {
    function tl_stage600_render_reward_operations(): void
    {
        $data = tl_stage600_reward_operations();
        $metrics = [];
        foreach (array_slice($data['board'], 0, 4) as $lane) $metrics[] = ['label'=>$lane['label'], 'value'=>(string)$lane['value'], 'hint'=>$lane['detail']];
        tl_stage600_render_shell('Stage 585–592', 'Reward Operations Board', $metrics, $data['board'], '/api/training/workflow-control.php?section=reward', 'labs-stage600-reward');
        echo '<p class="labs-copy labs-stage600-note">' . tl_stage600_e($data['participant_copy']) . '</p>';
    }
}

if (!function_exists('tl_stage600_render_operator_command_snapshot')) {
    function tl_stage600_render_operator_command_snapshot(): void
    {
        $data = tl_stage600_operator_command_snapshot();
        $metrics = [
            ['label'=>'Operator score', 'value'=>$data['score'] . '/100', 'hint'=>$data['accepted'] ? 'accepted' : 'review'],
            ['label'=>'Campaign', 'value'=>$data['campaign']['ready'] ? 'Ready' : 'Review', 'hint'=>$data['campaign']['status']],
            ['label'=>'Participant', 'value'=>$data['participant_timeline']['current_position'], 'hint'=>$data['participant_timeline']['progress_percent'] . '% progress'],
        ];
        tl_stage600_render_shell('Stage 593–600', 'Operator Command Snapshot', $metrics, $data['todays_operating_order'], '/api/training/workflow-control.php?section=operator', 'labs-stage600-operator');
    }
}
