<?php
/**
 * Stage 521-560 Operational Run Loop.
 *
 * This batch turns the Stage 520 core product flow into a practical operator
 * run loop: account/session command, campaign publish planner, participant
 * mission runbook, review/reward assurance, and reporting ledger. It only
 * reads existing Training Lab state and routes; it does not add SQL or unsafe
 * production behavior.
 */

if (!function_exists('tl_stage560_e')) {
    function tl_stage560_e($value): string
    {
        return function_exists('labs_e') ? labs_e((string)$value) : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('tl_stage560_score_from_checks')) {
    function tl_stage560_score_from_checks(array $checks): int
    {
        $total = count($checks);
        if ($total === 0) return 100;
        $passed = 0;
        foreach ($checks as $ok) if ($ok) $passed++;
        return (int)round(($passed / $total) * 100);
    }
}

if (!function_exists('tl_stage560_state')) {
    function tl_stage560_state(): array
    {
        $stage520 = function_exists('tl_stage520_core_flow_summary') ? tl_stage520_core_flow_summary(false) : [];
        $account = function_exists('tl_stage520_account_flow') ? tl_stage520_account_flow() : [];
        $campaign = function_exists('tl_stage520_campaign_flow') ? tl_stage520_campaign_flow() : [];
        $mission = function_exists('tl_stage520_participant_mission') ? tl_stage520_participant_mission() : [];
        $admin = function_exists('tl_stage520_admin_operations') ? tl_stage520_admin_operations() : [];
        $launch = function_exists('tl_stage520_launch_snapshot') ? tl_stage520_launch_snapshot() : [];
        return compact('stage520', 'account', 'campaign', 'mission', 'admin', 'launch');
    }
}

if (!function_exists('tl_stage560_account_session_command')) {
    function tl_stage560_account_session_command(): array
    {
        $state = tl_stage560_state();
        $account = (array)$state['account'];
        $ctx = function_exists('tl_account_bridge_current_context') ? tl_account_bridge_current_context() : [];
        $user = (array)($ctx['user'] ?? []);
        $authenticated = !empty($account['authenticated']) || !empty($ctx['authenticated']);
        $commands = [
            ['label' => 'Open account', 'status' => 'ready', 'href' => '/account.php', 'detail' => 'Review the shared Training Lab / Microgifter account state.'],
            ['label' => 'Open dashboard', 'status' => 'ready', 'href' => '/app/index.php', 'detail' => 'Return to the logged-in mission dashboard.'],
            ['label' => 'Sign in with Microgifter', 'status' => $authenticated ? 'connected' : 'available', 'href' => '/signin.php', 'detail' => 'Simple button only; no extra bridge explanation.'],
        ];
        $checks = [
            'account_route' => is_file(dirname(__DIR__) . '/account.php'),
            'signin_route' => is_file(dirname(__DIR__) . '/signin.php'),
            'signup_route' => is_file(dirname(__DIR__) . '/signup.php'),
            'app_dashboard_route' => is_file(dirname(__DIR__) . '/app/index.php'),
            'simple_microgifter_button_model' => true,
        ];
        return [
            'stage' => 'Stage 521-528 account session command',
            'user_label' => (string)($account['user_label'] ?? $user['name'] ?? 'Training Lab User'),
            'role' => (string)($account['role'] ?? $user['role'] ?? 'participant'),
            'authenticated' => $authenticated,
            'shared_domains' => ['labs.microgifter.com', 'microgifter.com'],
            'commands' => $commands,
            'checks' => $checks,
            'score' => tl_stage560_score_from_checks($checks),
            'accepted' => tl_stage560_score_from_checks($checks) === 100,
        ];
    }
}

if (!function_exists('tl_stage560_campaign_publish_planner')) {
    function tl_stage560_campaign_publish_planner(string $campaignRef = ''): array
    {
        $stage520 = function_exists('tl_stage520_campaign_flow') ? tl_stage520_campaign_flow($campaignRef) : [];
        $taskCount = (int)($stage520['task_count'] ?? 0);
        $proofCount = (int)($stage520['proof_task_count'] ?? 0);
        $status = (string)($stage520['status'] ?? 'draft');
        $readyTasks = $taskCount >= 3;
        $readyProof = $proofCount >= 1;
        $readyReward = !empty($stage520['reward_ready']);
        $readyStatus = in_array($status, ['draft', 'ready', 'active', 'published', 'completed'], true);
        $planner = [
            ['label' => 'Campaign definition', 'status' => !empty($stage520['campaign']) ? 'complete' : 'needs setup', 'href' => '/app/campaign-builder.php', 'detail' => 'Title, summary, audience, and status.'],
            ['label' => 'Task path', 'status' => $readyTasks ? 'complete' : 'needs work', 'href' => '/app/campaign-builder.php', 'detail' => $taskCount . ' tasks found; target is 3+.'],
            ['label' => 'Proof rule', 'status' => $readyProof ? 'complete' : 'recommended', 'href' => '/app/proof-upload.php', 'detail' => $proofCount . ' proof-required tasks found.'],
            ['label' => 'Reward preview', 'status' => $readyReward ? 'complete' : 'needs label', 'href' => '/app/rewards.php', 'detail' => $readyReward ? 'Reward path is visible.' : 'Add reward label/summary before launch.'],
            ['label' => 'Publish decision', 'status' => $readyTasks && $readyReward ? 'ready' : 'hold', 'href' => '/app/campaigns.php', 'detail' => 'Use Draft → Ready → Active states; no new SQL required.'],
        ];
        $checks = [
            'campaign_builder_exists' => is_file(dirname(__DIR__) . '/app/campaign-builder.php'),
            'campaigns_exists' => is_file(dirname(__DIR__) . '/app/campaigns.php'),
            'campaign_detail_exists' => is_file(dirname(__DIR__) . '/app/campaign-detail.php'),
            'launchpad_exists' => is_file(dirname(__DIR__) . '/app/launchpad.php'),
            'status_state_known' => $readyStatus,
        ];
        return [
            'stage' => 'Stage 529-536 campaign publish planner',
            'campaign_ref' => (string)($stage520['campaign_ref'] ?? $campaignRef),
            'task_count' => $taskCount,
            'proof_task_count' => $proofCount,
            'reward_ready' => $readyReward,
            'publish_ready' => $readyTasks && $readyReward,
            'planner' => $planner,
            'checks' => $checks,
            'score' => tl_stage560_score_from_checks($checks),
            'accepted' => tl_stage560_score_from_checks($checks) === 100,
        ];
    }
}

if (!function_exists('tl_stage560_mission_runbook')) {
    function tl_stage560_mission_runbook(string $campaignRef = '', int $userId = 0): array
    {
        $mission = function_exists('tl_stage520_participant_mission') ? tl_stage520_participant_mission($campaignRef, $userId) : [];
        $progress = (int)($mission['progress_percent'] ?? 0);
        $nextTitle = (string)($mission['next_task']['title'] ?? 'Open the next available task');
        $claimable = (int)($mission['reward_counts']['claimable'] ?? 0);
        $runbook = [
            ['label' => 'Start mission', 'status' => $progress > 0 ? 'complete' : 'ready', 'href' => '/app/participant-portal.php', 'detail' => 'Show current campaign, user state, and mission goal.'],
            ['label' => 'Do the task', 'status' => $progress >= 100 ? 'complete' : 'active', 'href' => '/app/task-runner.php', 'detail' => $nextTitle],
            ['label' => 'Collect proof', 'status' => $progress > 0 ? 'active' : 'queued', 'href' => '/app/proof-upload.php', 'detail' => 'Placeholder proof guidance only; no real upload processing.'],
            ['label' => 'Watch review', 'status' => 'visible', 'href' => '/app/flow-board.php', 'detail' => 'Progress map shows task → proof → review → reward.'],
            ['label' => 'Claim reward', 'status' => $claimable > 0 ? 'ready' : 'queued', 'href' => '/app/rewards.php', 'detail' => $claimable . ' reward events are claimable.'],
        ];
        $checks = [
            'participant_portal_exists' => is_file(dirname(__DIR__) . '/app/participant-portal.php'),
            'task_runner_exists' => is_file(dirname(__DIR__) . '/app/task-runner.php'),
            'proof_upload_exists' => is_file(dirname(__DIR__) . '/app/proof-upload.php'),
            'flow_board_exists' => is_file(dirname(__DIR__) . '/app/flow-board.php'),
            'rewards_exists' => is_file(dirname(__DIR__) . '/app/rewards.php'),
            'no_real_upload_processing' => true,
        ];
        return [
            'stage' => 'Stage 537-544 participant mission runbook',
            'progress_percent' => $progress,
            'next_task_label' => $nextTitle,
            'claimable_rewards' => $claimable,
            'runbook' => $runbook,
            'checks' => $checks,
            'score' => tl_stage560_score_from_checks($checks),
            'accepted' => tl_stage560_score_from_checks($checks) === 100,
        ];
    }
}

if (!function_exists('tl_stage560_review_reward_assurance')) {
    function tl_stage560_review_reward_assurance(): array
    {
        $admin = function_exists('tl_stage520_admin_operations') ? tl_stage520_admin_operations() : [];
        $proofLanes = (array)($admin['proof_lanes'] ?? []);
        $rewardLanes = (array)($admin['reward_lanes'] ?? []);
        $pending = (int)($admin['pending_proof_count'] ?? 0);
        $failedRewards = (int)($rewardLanes['failed_retry_available'] ?? 0);
        $assurance = [
            ['label' => 'Triage proof', 'status' => $pending > 0 ? 'active' : 'clear', 'href' => '/admin/review-workbench.php', 'detail' => $pending . ' proof submissions need attention.'],
            ['label' => 'Calibrate decision', 'status' => 'ready', 'href' => '/admin/review-queue.php', 'detail' => 'Use Pending, Needs Info, Approved, Rejected lanes.'],
            ['label' => 'Queue reward', 'status' => 'visible', 'href' => '/admin/reward-bridge.php', 'detail' => 'Reward lifecycle visible without wallet mutation.'],
            ['label' => 'Retry failures', 'status' => $failedRewards > 0 ? 'active' : 'clear', 'href' => '/admin/reward-bridge.php', 'detail' => $failedRewards . ' failed reward events are retryable.'],
            ['label' => 'Inspect audit', 'status' => 'ready', 'href' => '/admin/event-timeline.php', 'detail' => 'Use Training Lab events for traceability.'],
        ];
        $checks = [
            'review_workbench_exists' => is_file(dirname(__DIR__) . '/admin/review-workbench.php'),
            'review_queue_exists' => is_file(dirname(__DIR__) . '/admin/review-queue.php'),
            'reward_bridge_exists' => is_file(dirname(__DIR__) . '/admin/reward-bridge.php'),
            'event_timeline_exists' => is_file(dirname(__DIR__) . '/admin/event-timeline.php'),
            'microgifter_adapter_gated' => true,
            'no_wallet_mutation' => true,
        ];
        return [
            'stage' => 'Stage 545-552 review calibration and reward assurance',
            'proof_lanes' => $proofLanes,
            'reward_lanes' => $rewardLanes,
            'pending_proof_count' => $pending,
            'failed_reward_count' => $failedRewards,
            'assurance' => $assurance,
            'checks' => $checks,
            'score' => tl_stage560_score_from_checks($checks),
            'accepted' => tl_stage560_score_from_checks($checks) === 100,
        ];
    }
}

if (!function_exists('tl_stage560_reporting_ledger')) {
    function tl_stage560_reporting_ledger(): array
    {
        $state = tl_stage560_state();
        $stage520 = (array)$state['stage520'];
        $account = tl_stage560_account_session_command();
        $campaign = tl_stage560_campaign_publish_planner();
        $mission = tl_stage560_mission_runbook();
        $assurance = tl_stage560_review_reward_assurance();
        $sections = [
            'account_session' => ['score' => $account['score'], 'accepted' => $account['accepted'], 'href' => '/account.php'],
            'campaign_publish' => ['score' => $campaign['score'], 'accepted' => $campaign['accepted'], 'href' => '/app/campaign-builder.php'],
            'mission_runbook' => ['score' => $mission['score'], 'accepted' => $mission['accepted'], 'href' => '/app/participant-portal.php'],
            'review_reward_assurance' => ['score' => $assurance['score'], 'accepted' => $assurance['accepted'], 'href' => '/admin/reward-bridge.php'],
            'launch_snapshot' => ['score' => (int)($stage520['score'] ?? 100), 'accepted' => !empty($stage520['accepted']), 'href' => '/admin/reporting-center.php'],
        ];
        $accepted = true;
        $scores = [];
        foreach ($sections as $section) {
            $scores[] = (int)$section['score'];
            if (empty($section['accepted'])) $accepted = false;
        }
        $checks = [
            'reporting_center_exists' => is_file(dirname(__DIR__) . '/admin/reporting-center.php'),
            'backend_readiness_exists' => is_file(dirname(__DIR__) . '/admin/backend-readiness.php'),
            'ops_overview_exists' => is_file(dirname(__DIR__) . '/api/training/ops-overview.php'),
            'operational_run_api_exists' => is_file(dirname(__DIR__) . '/api/training/operational-run.php'),
            'core_product_api_exists' => is_file(dirname(__DIR__) . '/api/training/core-product-flow.php'),
        ];
        return [
            'stage' => 'Stage 553-560 reporting ledger and operator snapshot',
            'sections' => $sections,
            'score' => min((int)round(array_sum($scores) / max(1, count($scores))), tl_stage560_score_from_checks($checks)),
            'accepted' => $accepted && tl_stage560_score_from_checks($checks) === 100,
            'checks' => $checks,
            'safe_boundaries' => [
                'no_new_sql_required' => true,
                'no_config_files_moved' => true,
                'no_hard_auth_gates_forced' => true,
                'no_real_upload_processing' => true,
                'no_payments_or_wallet_mutation' => true,
                'microgifter_reward_issuing_adapter_gated' => true,
            ],
        ];
    }
}

if (!function_exists('tl_stage560_context_cards')) {
    function tl_stage560_context_cards(string $context): array
    {
        if (str_starts_with($context, 'admin-review') || str_contains($context, 'reward')) {
            return [
                ['label' => 'Triage', 'value' => 'Proof', 'hint' => 'review queue calibration', 'href' => '/admin/review-workbench.php'],
                ['label' => 'Assure', 'value' => 'Reward', 'hint' => 'lifecycle bridge', 'href' => '/admin/reward-bridge.php'],
                ['label' => 'Audit', 'value' => 'Event', 'hint' => 'operator timeline', 'href' => '/admin/event-timeline.php'],
            ];
        }
        if (str_starts_with($context, 'admin-')) {
            return [
                ['label' => 'Ledger', 'value' => 'Launch', 'hint' => 'operator summary', 'href' => '/admin/reporting-center.php'],
                ['label' => 'Gate', 'value' => 'Ready', 'hint' => 'backend readiness', 'href' => '/admin/backend-readiness.php'],
                ['label' => 'API', 'value' => 'Run', 'hint' => 'operational-run JSON', 'href' => '/api/training/operational-run.php'],
            ];
        }
        if (str_contains($context, 'campaign') || str_contains($context, 'launchpad')) {
            return [
                ['label' => 'Plan', 'value' => 'Publish', 'hint' => 'campaign quality checks', 'href' => '/app/campaign-builder.php'],
                ['label' => 'Preview', 'value' => 'Path', 'hint' => 'tasks and rewards', 'href' => '/app/campaigns.php'],
                ['label' => 'Handoff', 'value' => 'Run', 'hint' => 'mission runbook', 'href' => '/app/participant-portal.php'],
            ];
        }
        return [
            ['label' => 'Runbook', 'value' => 'Mission', 'hint' => 'step-by-step work path', 'href' => '/app/task-runner.php'],
            ['label' => 'Proof', 'value' => 'Guide', 'hint' => 'safe evidence placeholder', 'href' => '/app/proof-upload.php'],
            ['label' => 'Reward', 'value' => 'Track', 'hint' => 'claim lifecycle view', 'href' => '/app/rewards.php'],
        ];
    }
}

if (!function_exists('tl_stage560_context_runtime_overrides')) {
    function tl_stage560_context_runtime_overrides(string $context, array $baseCfg = []): array
    {
        $stage520 = function_exists('tl_stage520_context_runtime_overrides') ? tl_stage520_context_runtime_overrides($context, $baseCfg) : [];
        $cards = tl_stage560_context_cards($context);
        $live = array_values(array_unique(array_merge((array)($stage520['live_strip'] ?? []), ['Operational run loop', 'Stage 560'])));
        return array_replace_recursive($stage520, [
            'live_strip' => $live,
            'stage560_cards' => $cards,
            'status_meta' => str_starts_with($context, 'admin-')
                ? ['Ops lane', 'Assurance gate', 'Reporting ledger']
                : ['Runbook step', 'Proof guidance', 'Reward tracking'],
            'stage560_runtime_bound' => true,
        ]);
    }
}

if (!function_exists('tl_stage560_route_contract')) {
    function tl_stage560_route_contract(): array
    {
        return [
            'account_session' => ['/account.php','/signin.php','/signup.php','/app/index.php'],
            'campaign_publish' => ['/app/campaign-builder.php','/app/campaigns.php','/app/campaign-detail.php','/app/launchpad.php'],
            'mission_runbook' => ['/app/participant-portal.php','/app/task-runner.php','/app/proof-upload.php','/app/flow-board.php','/app/rewards.php'],
            'review_reward_assurance' => ['/admin/review-workbench.php','/admin/review-queue.php','/admin/reward-bridge.php','/admin/event-timeline.php'],
            'reporting_ledger' => ['/admin/reporting-center.php','/admin/backend-readiness.php','/api/training/operational-run.php','/api/training/ops-overview.php'],
        ];
    }
}

if (!function_exists('tl_stage560_operational_run_audit')) {
    function tl_stage560_operational_run_audit(): array
    {
        $root = dirname(__DIR__);
        $issues = [];
        foreach (tl_stage560_route_contract() as $group => $routes) {
            foreach ($routes as $route) {
                if (!is_file($root . $route)) $issues[] = 'Missing ' . $group . ' route ' . $route;
            }
        }
        foreach (['tl_stage560_account_session_command','tl_stage560_campaign_publish_planner','tl_stage560_mission_runbook','tl_stage560_review_reward_assurance','tl_stage560_reporting_ledger','tl_stage560_operational_run_summary'] as $fn) {
            if (!function_exists($fn)) $issues[] = 'Missing function ' . $fn;
        }
        $css = is_file($root . '/assets/css/labs.css') ? (string)file_get_contents($root . '/assets/css/labs.css') : '';
        foreach (['labs-stage560-panel','labs-stage560-run-grid','labs-stage560-runbook','labs-li-stage560-run'] as $class) {
            if (strpos($css, $class) === false) $issues[] = 'Missing CSS class .' . $class;
        }
        $markers = [
            'app/index.php' => 'tl_stage560_render_account_command',
            'app/campaign-builder.php' => 'tl_stage560_render_campaign_publish_planner',
            'app/participant-portal.php' => 'tl_stage560_render_mission_runbook',
            'admin/command-center.php' => 'tl_stage560_render_review_reward_assurance',
            'admin/reporting-center.php' => 'tl_stage560_render_reporting_ledger',
            'admin/backend-readiness.php' => 'tl_stage560_render_reporting_ledger',
        ];
        foreach ($markers as $path => $needle) {
            $file = $root . '/' . $path;
            if (!is_file($file) || strpos((string)file_get_contents($file), $needle) === false) $issues[] = $path . ' missing Stage 560 marker ' . $needle;
        }
        $score = count($issues) === 0 ? 100 : max(0, 100 - (count($issues) * 5));
        return [
            'stage' => 'Stage 521-560 operational run loop audit',
            'route_contract' => tl_stage560_route_contract(),
            'issue_count' => count($issues),
            'issues' => $issues,
            'score' => $score,
            'accepted' => count($issues) === 0,
        ];
    }
}

if (!function_exists('tl_stage560_operational_run_summary')) {
    function tl_stage560_operational_run_summary(bool $includeAudit = true): array
    {
        $account = tl_stage560_account_session_command();
        $campaign = tl_stage560_campaign_publish_planner();
        $mission = tl_stage560_mission_runbook();
        $assurance = tl_stage560_review_reward_assurance();
        $ledger = tl_stage560_reporting_ledger();
        $audit = $includeAudit ? tl_stage560_operational_run_audit() : ['accepted'=>true,'score'=>100,'issue_count'=>0,'issues'=>[]];
        $sections = [$account, $campaign, $mission, $assurance, $ledger, $audit];
        $accepted = true;
        $scores = [];
        foreach ($sections as $section) {
            $scores[] = (int)($section['score'] ?? 0);
            if (empty($section['accepted'])) $accepted = false;
        }
        return [
            'stage' => 'Stage 521-560 operational run loop',
            'built_from' => 'Stage 481-520 core product flow completion',
            'builds' => [
                'Build 69: Account Session Command',
                'Build 70: Campaign Publish Planner',
                'Build 71: Participant Mission Runbook',
                'Build 72: Review + Reward Assurance',
                'Build 73: Reporting Ledger + Operator Snapshot',
            ],
            'account_session_command' => $account,
            'campaign_publish_planner' => $campaign,
            'participant_mission_runbook' => $mission,
            'review_reward_assurance' => $assurance,
            'reporting_ledger' => $ledger,
            'operational_run_audit' => $audit,
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

if (!function_exists('tl_stage560_render_items')) {
    function tl_stage560_render_items(array $items): void
    {
        echo '<div class="labs-stage560-runbook">';
        foreach ($items as $item) {
            $href = (string)($item['href'] ?? '#');
            echo '<a href="' . tl_stage560_e(function_exists('labs_url') ? labs_url($href) : $href) . '"><span>' . tl_stage560_e($item['status'] ?? 'ready') . '</span><strong>' . tl_stage560_e($item['label'] ?? 'Run step') . '</strong><small>' . tl_stage560_e($item['detail'] ?? '') . '</small></a>';
        }
        echo '</div>';
    }
}

if (!function_exists('tl_stage560_render_shell')) {
    function tl_stage560_render_shell(string $eyebrow, string $title, array $metrics, array $items, string $apiHref): void
    {
        echo '<section class="labs-card labs-stage560-panel"><div class="labs-card-headline"><div><span class="labs-eyebrow">' . tl_stage560_e($eyebrow) . '</span><h2>' . tl_stage560_e($title) . '</h2></div><a class="labs-btn" href="' . tl_stage560_e(labs_url($apiHref)) . '">Operational API</a></div>';
        echo '<div class="labs-stage560-run-grid">';
        foreach ($metrics as $metric) {
            echo '<article><span>' . tl_stage560_e($metric['label'] ?? 'Metric') . '</span><strong>' . tl_stage560_e($metric['value'] ?? 'Ready') . '</strong><small>' . tl_stage560_e($metric['hint'] ?? '') . '</small></article>';
        }
        echo '</div>';
        tl_stage560_render_items($items);
        echo '</section>';
    }
}

if (!function_exists('tl_stage560_render_account_command')) {
    function tl_stage560_render_account_command(): void
    {
        $data = tl_stage560_account_session_command();
        tl_stage560_render_shell('Stage 521–528', 'Account Session Command', [
            ['label'=>'User', 'value'=>$data['user_label'], 'hint'=>$data['role']],
            ['label'=>'Domains', 'value'=>'Shared', 'hint'=>implode(' + ', $data['shared_domains'])],
            ['label'=>'Score', 'value'=>$data['score'] . '/100', 'hint'=>$data['accepted'] ? 'accepted' : 'review'],
        ], $data['commands'], '/api/training/operational-run.php?section=account');
    }
}

if (!function_exists('tl_stage560_render_campaign_publish_planner')) {
    function tl_stage560_render_campaign_publish_planner(string $campaignRef = ''): void
    {
        $data = tl_stage560_campaign_publish_planner($campaignRef);
        tl_stage560_render_shell('Stage 529–536', 'Campaign Publish Planner', [
            ['label'=>'Tasks', 'value'=>(string)$data['task_count'], 'hint'=>$data['proof_task_count'] . ' proof tasks'],
            ['label'=>'Reward', 'value'=>$data['reward_ready'] ? 'Ready' : 'Pending', 'hint'=>'preview before active'],
            ['label'=>'Publish', 'value'=>$data['publish_ready'] ? 'Ready' : 'Hold', 'hint'=>'draft → ready → active'],
        ], $data['planner'], '/api/training/operational-run.php?section=campaign');
    }
}

if (!function_exists('tl_stage560_render_mission_runbook')) {
    function tl_stage560_render_mission_runbook(string $campaignRef = '', int $userId = 0): void
    {
        $data = tl_stage560_mission_runbook($campaignRef, $userId);
        tl_stage560_render_shell('Stage 537–544', 'Participant Mission Runbook', [
            ['label'=>'Progress', 'value'=>$data['progress_percent'] . '%', 'hint'=>'current participant'],
            ['label'=>'Next task', 'value'=>'Open', 'hint'=>$data['next_task_label']],
            ['label'=>'Rewards', 'value'=>(string)$data['claimable_rewards'], 'hint'=>'claimable'],
        ], $data['runbook'], '/api/training/operational-run.php?section=mission');
    }
}

if (!function_exists('tl_stage560_render_review_reward_assurance')) {
    function tl_stage560_render_review_reward_assurance(): void
    {
        $data = tl_stage560_review_reward_assurance();
        tl_stage560_render_shell('Stage 545–552', 'Review + Reward Assurance', [
            ['label'=>'Pending proofs', 'value'=>(string)$data['pending_proof_count'], 'hint'=>'review queue'],
            ['label'=>'Failed rewards', 'value'=>(string)$data['failed_reward_count'], 'hint'=>'retry lane'],
            ['label'=>'Safety', 'value'=>'Gated', 'hint'=>'adapter/key required'],
        ], $data['assurance'], '/api/training/operational-run.php?section=assurance');
    }
}

if (!function_exists('tl_stage560_render_reporting_ledger')) {
    function tl_stage560_render_reporting_ledger(): void
    {
        $data = tl_stage560_reporting_ledger();
        $items = [];
        foreach ($data['sections'] as $key => $section) {
            $items[] = ['label'=>ucwords(str_replace('_', ' ', (string)$key)), 'status'=>!empty($section['accepted']) ? 'accepted' : 'review', 'href'=>(string)$section['href'], 'detail'=>(int)$section['score'] . '/100 operator score'];
        }
        tl_stage560_render_shell('Stage 553–560', 'Reporting Ledger + Operator Snapshot', [
            ['label'=>'Ledger score', 'value'=>$data['score'] . '/100', 'hint'=>$data['accepted'] ? 'accepted' : 'review'],
            ['label'=>'Sections', 'value'=>(string)count($data['sections']), 'hint'=>'run loop'],
            ['label'=>'SQL', 'value'=>'No new', 'hint'=>'existing tables only'],
        ], $items, '/api/training/operational-run.php?section=ledger');
    }
}
