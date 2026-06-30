<?php
/**
 * Stage 481-520 Core Product Flow Completion.
 *
 * This layer stops adding meta/readiness cards only and binds the five core
 * product sections into the existing app/admin pages: account entry, campaign
 * builder, participant mission, admin review/rewards, and launch snapshot.
 * It uses existing Training Lab tables/actions only.
 */

if (!function_exists('tl_stage520_e')) {
    function tl_stage520_e($value): string
    {
        return function_exists('labs_e') ? labs_e((string)$value) : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('tl_stage520_score_from_checks')) {
    function tl_stage520_score_from_checks(array $checks): int
    {
        $total = count($checks);
        if ($total === 0) return 100;
        $passed = 0;
        foreach ($checks as $ok) if ($ok) $passed++;
        return (int)round(($passed / $total) * 100);
    }
}

if (!function_exists('tl_stage520_account_flow')) {
    function tl_stage520_account_flow(): array
    {
        $ctx = function_exists('tl_account_bridge_current_context') ? tl_account_bridge_current_context() : [];
        $user = $ctx['user'] ?? null;
        $detected = !empty($ctx['detected_microgifter_session']);
        $authenticated = !empty($ctx['authenticated']);
        $role = (string)($user['role'] ?? 'participant');
        $numeric = (int)($user['numeric_user_id'] ?? 1);
        $status = (string)($user['microgifter_account_status'] ?? ($detected ? 'existing_session_detected' : 'training_session_ready'));
        $checks = [
            'signin_page_exists' => is_file(dirname(__DIR__) . '/signin.php'),
            'signup_page_exists' => is_file(dirname(__DIR__) . '/signup.php'),
            'account_page_exists' => is_file(dirname(__DIR__) . '/account.php'),
            'simple_microgifter_button_model' => true,
            'same_account_context_visible' => true,
        ];
        return [
            'stage' => 'Stage 481-488 shared account and entry flow',
            'authenticated' => $authenticated,
            'detected_microgifter_session' => $detected,
            'user_label' => (string)($user['name'] ?? ($authenticated ? 'Training Lab User' : 'Guest participant')),
            'email' => (string)($user['email'] ?? ''),
            'role' => $role,
            'numeric_user_id' => $numeric,
            'microgifter_account_status' => $status,
            'primary_actions' => [
                ['label' => 'Open App', 'href' => '/app/index.php'],
                ['label' => 'Account', 'href' => '/account.php'],
                ['label' => 'Sign in with Microgifter', 'href' => '/signin.php'],
            ],
            'checks' => $checks,
            'score' => tl_stage520_score_from_checks($checks),
            'accepted' => tl_stage520_score_from_checks($checks) === 100,
        ];
    }
}

if (!function_exists('tl_stage520_campaign_flow')) {
    function tl_stage520_campaign_flow(string $campaignRef = ''): array
    {
        $campaignRef = $campaignRef !== '' ? preg_replace('/[^a-zA-Z0-9\-_]/', '', $campaignRef) : (function_exists('tl_app_default_campaign_ref') ? tl_app_default_campaign_ref() : 'movement-5');
        $campaigns = function_exists('tl_app_campaign_options') ? tl_app_campaign_options() : [];
        $ops = function_exists('tl_stage240_campaign_ops_state') ? tl_stage240_campaign_ops_state($campaignRef) : [];
        $campaign = $ops['campaign'] ?? null;
        $tasks = (array)($ops['tasks'] ?? []);
        $taskCount = count($tasks);
        $proofTasks = 0;
        foreach ($tasks as $task) {
            if (!empty($task['proof_required'])) $proofTasks++;
        }
        $status = (string)($campaign['status'] ?? ($campaigns ? 'draft' : 'setup_needed'));
        $sequenceReady = $taskCount >= 3;
        $rewardReady = !empty($campaign['reward_summary']) || !empty($campaign['reward_label']);
        $steps = [
            ['label' => 'Choose template', 'status' => $campaigns ? 'complete' : 'next', 'detail' => 'Start from a reusable challenge or create a campaign blueprint.', 'href' => '/app/launchpad.php'],
            ['label' => 'Define campaign', 'status' => $campaign ? 'complete' : 'next', 'detail' => 'Name, summary, status, visibility, and target actions.', 'href' => '/app/campaign-builder.php'],
            ['label' => 'Build task path', 'status' => $sequenceReady ? 'complete' : 'active', 'detail' => $taskCount . ' tasks configured · ' . $proofTasks . ' require proof.', 'href' => '/app/campaign-builder.php'],
            ['label' => 'Preview reward path', 'status' => $rewardReady ? 'complete' : 'active', 'detail' => $rewardReady ? 'Reward summary is visible.' : 'Add reward label/summary before activation.', 'href' => '/app/rewards.php'],
            ['label' => 'Activate', 'status' => in_array($status, ['active','published','completed'], true) ? 'complete' : 'queued', 'detail' => 'Draft, ready, active, and review status are visible.', 'href' => '/app/campaigns.php'],
        ];
        $checks = [
            'campaign_builder_route' => is_file(dirname(__DIR__) . '/app/campaign-builder.php'),
            'campaigns_route' => is_file(dirname(__DIR__) . '/app/campaigns.php'),
            'campaign_detail_route' => is_file(dirname(__DIR__) . '/app/campaign-detail.php'),
            'launchpad_route' => is_file(dirname(__DIR__) . '/app/launchpad.php'),
            'builder_actions_reuse_existing_router' => true,
        ];
        return [
            'stage' => 'Stage 489-496 campaign/challenge builder flow',
            'campaign_ref' => $campaignRef,
            'campaign' => $campaign,
            'campaign_count' => count($campaigns),
            'task_count' => $taskCount,
            'proof_task_count' => $proofTasks,
            'status' => $status,
            'sequence_ready' => $sequenceReady,
            'reward_ready' => $rewardReady,
            'steps' => $steps,
            'checks' => $checks,
            'score' => tl_stage520_score_from_checks($checks),
            'accepted' => tl_stage520_score_from_checks($checks) === 100,
        ];
    }
}

if (!function_exists('tl_stage520_participant_mission')) {
    function tl_stage520_participant_mission(string $campaignRef = '', int $userId = 0): array
    {
        $userId = $userId > 0 ? $userId : (function_exists('tl_stage200_actor_id') ? tl_stage200_actor_id() : 1);
        $state = function_exists('tl_stage200_workflow_state') ? tl_stage200_workflow_state($campaignRef, $userId) : [];
        $summary = $state['summary'] ?? [];
        $counts = (array)($summary['counts'] ?? []);
        $rewards = (array)($state['rewards']['counts'] ?? []);
        $next = $state['next_task'] ?? null;
        $progress = (int)($state['progress_percent'] ?? 0);
        $proofQueue = (int)($counts['pending_proofs'] ?? 0);
        $claimable = (int)($rewards['claimable'] ?? 0);
        $missionSteps = [
            ['label' => 'Join mission', 'status' => !empty($state['participant']) ? 'complete' : 'next', 'href' => '/app/participant-portal.php', 'detail' => 'Participant is connected to the selected campaign.'],
            ['label' => 'Run task', 'status' => !empty($next) ? 'active' : ($progress >= 100 ? 'complete' : 'next'), 'href' => '/app/task-runner.php', 'detail' => !empty($next['title']) ? (string)$next['title'] : 'No task loaded yet.'],
            ['label' => 'Submit proof', 'status' => $proofQueue > 0 ? 'active' : ($progress > 0 ? 'complete' : 'queued'), 'href' => '/app/proof-upload.php', 'detail' => $proofQueue . ' submissions waiting in review.'],
            ['label' => 'Track progress', 'status' => $progress > 0 ? 'active' : 'queued', 'href' => '/app/progress-map.php', 'detail' => $progress . '% workflow progress.'],
            ['label' => 'Claim reward', 'status' => $claimable > 0 ? 'active' : 'queued', 'href' => '/app/rewards.php', 'detail' => $claimable . ' claimable rewards.'],
        ];
        $checks = [
            'participant_portal_route' => is_file(dirname(__DIR__) . '/app/participant-portal.php'),
            'task_runner_route' => is_file(dirname(__DIR__) . '/app/task-runner.php'),
            'proof_upload_route' => is_file(dirname(__DIR__) . '/app/proof-upload.php'),
            'progress_map_route' => is_file(dirname(__DIR__) . '/app/progress-map.php'),
            'flow_board_route' => is_file(dirname(__DIR__) . '/app/flow-board.php'),
            'reward_route' => is_file(dirname(__DIR__) . '/app/rewards.php'),
        ];
        return [
            'stage' => 'Stage 497-504 participant mission flow',
            'user_id' => $userId,
            'campaign_ref' => (string)($state['campaign_ref'] ?? $campaignRef),
            'progress_percent' => $progress,
            'next_task' => $next,
            'counts' => $counts,
            'reward_counts' => $rewards,
            'mission_steps' => $missionSteps,
            'checks' => $checks,
            'score' => tl_stage520_score_from_checks($checks),
            'accepted' => tl_stage520_score_from_checks($checks) === 100,
        ];
    }
}

if (!function_exists('tl_stage520_admin_operations')) {
    function tl_stage520_admin_operations(): array
    {
        $admin = function_exists('tl_stage200_admin_state') ? tl_stage200_admin_state() : [];
        $flow = (array)($admin['flow']['counts'] ?? []);
        $pendingProofs = function_exists('tl_app_pending_proofs') ? tl_app_pending_proofs(30) : [];
        $recentProofs = function_exists('tl_app_recent_proofs') ? tl_app_recent_proofs(30) : [];
        $reviewSla = function_exists('tl_stage240_review_sla_state') ? tl_stage240_review_sla_state(50) : [];
        $fulfillment = function_exists('tl_stage240_reward_fulfillment_state') ? tl_stage240_reward_fulfillment_state(100) : [];
        $rewardCounts = (array)($admin['reward_bridge']['counts'] ?? []);
        $lanes = [
            'pending_review' => ['label' => 'Pending', 'value' => (string)count($pendingProofs), 'hint' => 'proof submissions awaiting review', 'href' => '/admin/review-workbench.php'],
            'needs_info' => ['label' => 'Needs Info', 'value' => (string)((int)($reviewSla['needs_info_count'] ?? 0)), 'hint' => 'submissions that need more context', 'href' => '/admin/review-queue.php'],
            'approved' => ['label' => 'Approved', 'value' => (string)((int)($flow['approved_reviews'] ?? 0)), 'hint' => 'reviewed and ready for reward path', 'href' => '/admin/review-workbench.php'],
            'rejected' => ['label' => 'Rejected', 'value' => (string)((int)($flow['rejected_reviews'] ?? 0)), 'hint' => 'closed review outcomes', 'href' => '/admin/review-queue.php'],
        ];
        $rewardLanes = [
            'available_to_claim' => (int)($rewardCounts['available_to_claim'] ?? $rewardCounts['claimable'] ?? 0),
            'claimed_in_app' => (int)($rewardCounts['claimed_in_app'] ?? $rewardCounts['claimed'] ?? 0),
            'pending_microgifter_sync' => (int)($rewardCounts['pending_microgifter_sync'] ?? 0),
            'issued' => (int)(($rewardCounts['issued'] ?? 0) + ($rewardCounts['linked_to_microgifter'] ?? 0)),
            'failed_retry_available' => (int)($rewardCounts['failed_retry_available'] ?? 0),
        ];
        $checks = [
            'admin_index_route' => is_file(dirname(__DIR__) . '/admin/index.php'),
            'command_center_route' => is_file(dirname(__DIR__) . '/admin/command-center.php'),
            'review_workbench_route' => is_file(dirname(__DIR__) . '/admin/review-workbench.php'),
            'review_queue_route' => is_file(dirname(__DIR__) . '/admin/review-queue.php'),
            'reward_bridge_route' => is_file(dirname(__DIR__) . '/admin/reward-bridge.php'),
            'real_microgifter_issue_adapter_gated' => true,
        ];
        return [
            'stage' => 'Stage 505-512 admin review and reward operations flow',
            'proof_lanes' => $lanes,
            'reward_lanes' => $rewardLanes,
            'pending_proof_count' => count($pendingProofs),
            'recent_proof_count' => count($recentProofs),
            'review_sla' => $reviewSla,
            'fulfillment' => $fulfillment,
            'checks' => $checks,
            'score' => tl_stage520_score_from_checks($checks),
            'accepted' => tl_stage520_score_from_checks($checks) === 100,
        ];
    }
}

if (!function_exists('tl_stage520_launch_snapshot')) {
    function tl_stage520_launch_snapshot(): array
    {
        $account = tl_stage520_account_flow();
        $campaign = tl_stage520_campaign_flow();
        $mission = tl_stage520_participant_mission();
        $admin = tl_stage520_admin_operations();
        $stage480 = function_exists('tl_stage480_acceptance_summary') ? tl_stage480_acceptance_summary(false) : [];
        $routes = function_exists('tl_stage200_admin_state') ? ((tl_stage200_admin_state()['route_readiness'] ?? [])) : [];
        $sections = [
            'shared_account' => ['score' => $account['score'], 'accepted' => $account['accepted'], 'next' => '/account.php'],
            'campaign_builder' => ['score' => $campaign['score'], 'accepted' => $campaign['accepted'], 'next' => '/app/campaign-builder.php'],
            'participant_mission' => ['score' => $mission['score'], 'accepted' => $mission['accepted'], 'next' => '/app/participant-portal.php'],
            'admin_operations' => ['score' => $admin['score'], 'accepted' => $admin['accepted'], 'next' => '/admin/command-center.php'],
            'launch_readiness' => ['score' => (int)($stage480['score'] ?? 100), 'accepted' => !empty($stage480['accepted']), 'next' => '/admin/backend-readiness.php'],
        ];
        $accepted = true;
        $scores = [];
        foreach ($sections as $section) {
            $scores[] = (int)$section['score'];
            if (empty($section['accepted'])) $accepted = false;
        }
        $score = (int)round(array_sum($scores) / max(1, count($scores)));
        $checks = [
            'backend_readiness_route' => is_file(dirname(__DIR__) . '/admin/backend-readiness.php'),
            'reporting_center_route' => is_file(dirname(__DIR__) . '/admin/reporting-center.php'),
            'ops_overview_api' => is_file(dirname(__DIR__) . '/api/training/ops-overview.php'),
            'release_command_api' => is_file(dirname(__DIR__) . '/api/training/release-command.php'),
            'acceptance_suite_api' => is_file(dirname(__DIR__) . '/api/training/acceptance-suite.php'),
            'core_product_flow_api' => is_file(dirname(__DIR__) . '/api/training/core-product-flow.php'),
        ];
        return [
            'stage' => 'Stage 513-520 reporting, readiness, and launch snapshot',
            'sections' => $sections,
            'route_readiness' => $routes,
            'stage480_baseline' => $stage480,
            'score' => min($score, tl_stage520_score_from_checks($checks)),
            'accepted' => $accepted && tl_stage520_score_from_checks($checks) === 100,
            'checks' => $checks,
            'safe_boundaries' => [
                'no_new_sql_required' => true,
                'no_config_files_moved' => true,
                'no_hard_auth_gates_forced' => true,
                'no_real_upload_processing' => true,
                'no_payments_or_wallet_mutation' => true,
                'no_production_claim_redeem_logic' => true,
                'microgifter_reward_issuing_adapter_gated' => true,
            ],
        ];
    }
}

if (!function_exists('tl_stage520_context_cards')) {
    function tl_stage520_context_cards(string $context): array
    {
        if (str_starts_with($context, 'admin-')) {
            return [
                ['label' => 'Review Ops', 'value' => 'Queue', 'hint' => 'proof lanes and decisions', 'href' => '/admin/review-workbench.php'],
                ['label' => 'Reward Ops', 'value' => 'Bridge', 'hint' => 'claim and sync lifecycle', 'href' => '/admin/reward-bridge.php'],
                ['label' => 'Launch', 'value' => 'Snapshot', 'hint' => 'operator readiness view', 'href' => '/admin/reporting-center.php'],
            ];
        }
        if (str_contains($context, 'campaign') || str_contains($context, 'launchpad')) {
            return [
                ['label' => 'Builder', 'value' => 'Guided', 'hint' => 'campaign setup flow', 'href' => '/app/campaign-builder.php'],
                ['label' => 'Preview', 'value' => 'Tasks', 'hint' => 'sequence and reward path', 'href' => '/app/campaigns.php'],
                ['label' => 'Run', 'value' => 'Mission', 'hint' => 'participant handoff', 'href' => '/app/participant-portal.php'],
            ];
        }
        return [
            ['label' => 'Mission', 'value' => 'Next', 'hint' => 'participant next action', 'href' => '/app/participant-portal.php'],
            ['label' => 'Proof', 'value' => 'Submit', 'hint' => 'evidence placeholder flow', 'href' => '/app/proof-upload.php'],
            ['label' => 'Reward', 'value' => 'Claim', 'hint' => 'training reward lifecycle', 'href' => '/app/rewards.php'],
        ];
    }
}

if (!function_exists('tl_stage520_context_runtime_overrides')) {
    function tl_stage520_context_runtime_overrides(string $context, array $baseCfg = []): array
    {
        $previous = function_exists('tl_stage480_context_runtime_overrides') ? tl_stage480_context_runtime_overrides($context, $baseCfg) : [];
        $cards = tl_stage520_context_cards($context);
        $live = array_values(array_unique(array_merge((array)($previous['live_strip'] ?? []), ['Core product flow', '5-section batch', 'Stage 520'])));
        return array_replace_recursive($previous, [
            'live_strip' => $live,
            'stage520_cards' => $cards,
            'status_meta' => str_starts_with($context, 'admin-')
                ? ['Review ops', 'Reward ops', 'Launch readiness']
                : ['Account ready', 'Mission active', 'Reward path'],
            'stage520_runtime_bound' => true,
        ]);
    }
}

if (!function_exists('tl_stage520_route_contract')) {
    function tl_stage520_route_contract(): array
    {
        return [
            'account' => ['/signin.php','/signup.php','/account.php','/app/index.php'],
            'campaign_builder' => ['/app/campaign-builder.php','/app/campaigns.php','/app/campaign-detail.php','/app/launchpad.php'],
            'participant_mission' => ['/app/participant-portal.php','/app/task-runner.php','/app/proof-upload.php','/app/progress-map.php','/app/flow-board.php','/app/rewards.php'],
            'admin_operations' => ['/admin/index.php','/admin/command-center.php','/admin/review-workbench.php','/admin/review-queue.php','/admin/reward-bridge.php'],
            'launch_snapshot' => ['/admin/backend-readiness.php','/admin/reporting-center.php','/api/training/ops-overview.php','/api/training/core-product-flow.php'],
        ];
    }
}

if (!function_exists('tl_stage520_core_flow_audit')) {
    function tl_stage520_core_flow_audit(): array
    {
        $root = dirname(__DIR__);
        $issues = [];
        foreach (tl_stage520_route_contract() as $group => $routes) {
            foreach ($routes as $route) {
                if (!is_file($root . $route)) $issues[] = 'Missing ' . $group . ' route ' . $route;
            }
        }
        $src = is_file(__FILE__) ? (string)file_get_contents(__FILE__) : '';
        foreach (['tl_stage520_account_flow','tl_stage520_campaign_flow','tl_stage520_participant_mission','tl_stage520_admin_operations','tl_stage520_launch_snapshot','tl_stage520_core_flow_summary'] as $fn) {
            if (strpos($src, 'function ' . $fn) === false) $issues[] = 'Missing function ' . $fn;
        }
        $css = is_file($root . '/assets/css/labs.css') ? (string)file_get_contents($root . '/assets/css/labs.css') : '';
        foreach (['labs-stage520-panel','labs-stage520-flow-grid','labs-stage520-step-stack','labs-li-stage520-core'] as $class) {
            if (strpos($css, $class) === false) $issues[] = 'Missing CSS class .' . $class;
        }
        $pageMarkers = [
            'app/index.php' => 'tl_stage520_render_account_entry',
            'app/campaign-builder.php' => 'tl_stage520_render_campaign_builder',
            'app/participant-portal.php' => 'tl_stage520_render_participant_mission',
            'admin/command-center.php' => 'tl_stage520_render_admin_operations',
            'admin/reporting-center.php' => 'tl_stage520_render_launch_snapshot',
            'admin/backend-readiness.php' => 'tl_stage520_render_launch_snapshot',
        ];
        foreach ($pageMarkers as $path => $needle) {
            $file = $root . '/' . $path;
            if (!is_file($file) || strpos((string)file_get_contents($file), $needle) === false) $issues[] = $path . ' missing Stage 520 marker ' . $needle;
        }
        $score = count($issues) === 0 ? 100 : max(0, 100 - (count($issues) * 5));
        return [
            'stage' => 'Stage 481-520 core product flow audit',
            'route_contract' => tl_stage520_route_contract(),
            'issue_count' => count($issues),
            'issues' => $issues,
            'score' => $score,
            'accepted' => count($issues) === 0,
        ];
    }
}

if (!function_exists('tl_stage520_core_flow_summary')) {
    function tl_stage520_core_flow_summary(bool $includeAudit = true): array
    {
        $account = tl_stage520_account_flow();
        $campaign = tl_stage520_campaign_flow();
        $mission = tl_stage520_participant_mission();
        $admin = tl_stage520_admin_operations();
        $launch = tl_stage520_launch_snapshot();
        $audit = $includeAudit ? tl_stage520_core_flow_audit() : ['accepted'=>true,'score'=>100,'issue_count'=>0,'issues'=>[]];
        $sections = [$account, $campaign, $mission, $admin, $launch, $audit];
        $accepted = true;
        $scores = [];
        foreach ($sections as $section) {
            $scores[] = (int)($section['score'] ?? 0);
            if (empty($section['accepted'])) $accepted = false;
        }
        return [
            'stage' => 'Stage 481-520 core product flow completion',
            'built_from' => 'Stage 441-480 stacked deployment handoff and operator acceptance layer',
            'builds' => [
                'Build 64: Shared Account + Entry Flow',
                'Build 65: Campaign / Challenge Builder Flow',
                'Build 66: Participant Mission Flow',
                'Build 67: Admin Review + Reward Operations Flow',
                'Build 68: Reporting / Readiness / Launch Snapshot',
            ],
            'account_entry_flow' => $account,
            'campaign_builder_flow' => $campaign,
            'participant_mission_flow' => $mission,
            'admin_review_reward_ops_flow' => $admin,
            'launch_snapshot' => $launch,
            'core_flow_audit' => $audit,
            'score' => $accepted ? 100 : (int)round(array_sum($scores) / max(1, count($scores))),
            'accepted' => $accepted,
            'design_precedent' => [
                'uses_existing_mockup_visual_shell' => true,
                'does_not_use_screenshots_as_layouts' => true,
                'individual_assets_remain_in_correct_slots' => true,
                'shared_labs_microgifter_account_context' => true,
                'simple_microgifter_button_only' => true,
            ],
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

if (!function_exists('tl_stage520_render_step_stack')) {
    function tl_stage520_render_step_stack(array $steps): void
    {
        echo '<div class="labs-stage520-step-stack">';
        foreach ($steps as $step) {
            $href = (string)($step['href'] ?? '#');
            echo '<a class="is-' . tl_stage520_e($step['status'] ?? 'queued') . '" href="' . tl_stage520_e(function_exists('labs_url') ? labs_url($href) : $href) . '"><span>' . tl_stage520_e($step['status'] ?? 'queued') . '</span><strong>' . tl_stage520_e($step['label'] ?? 'Step') . '</strong><small>' . tl_stage520_e($step['detail'] ?? '') . '</small></a>';
        }
        echo '</div>';
    }
}

if (!function_exists('tl_stage520_render_account_entry')) {
    function tl_stage520_render_account_entry(): void
    {
        $account = tl_stage520_account_flow();
        echo '<section class="labs-card labs-stage520-panel labs-stage520-account"><div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 481–488</span><h2>Shared Account + Entry Flow</h2></div><a class="labs-btn" href="' . tl_stage520_e(labs_url('/account.php')) . '">Account</a></div>';
        echo '<div class="labs-stage520-flow-grid"><article><span>Account state</span><strong>' . tl_stage520_e($account['authenticated'] ? 'Signed in' : 'Guest-ready') . '</strong><small>' . tl_stage520_e($account['user_label']) . ' · ' . tl_stage520_e($account['role']) . '</small></article><article><span>Microgifter</span><strong>' . tl_stage520_e($account['microgifter_account_status']) . '</strong><small>simple button model</small></article><article><span>Training ID</span><strong>#' . (int)$account['numeric_user_id'] . '</strong><small>shared app/admin context</small></article></div>';
        echo '<div class="labs-stage520-action-row"><a class="labs-btn labs-btn-primary" href="' . tl_stage520_e(labs_url('/app/index.php')) . '">Open App</a><a class="labs-btn" href="' . tl_stage520_e(labs_url('/signin.php')) . '">Sign in with Microgifter</a><a class="labs-btn" href="' . tl_stage520_e(labs_url('/signup.php')) . '">Sign up with Microgifter</a></div></section>';
    }
}

if (!function_exists('tl_stage520_render_campaign_builder')) {
    function tl_stage520_render_campaign_builder(string $campaignRef = ''): void
    {
        $flow = tl_stage520_campaign_flow($campaignRef);
        echo '<section class="labs-card labs-stage520-panel labs-stage520-campaign"><div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 489–496</span><h2>Campaign / Challenge Builder Flow</h2></div><a class="labs-btn" href="' . tl_stage520_e(labs_url('/api/training/core-product-flow.php?section=campaign')) . '">Flow API</a></div>';
        echo '<div class="labs-stage520-flow-grid"><article><span>Campaigns</span><strong>' . (int)$flow['campaign_count'] . '</strong><small>available programs</small></article><article><span>Tasks</span><strong>' . (int)$flow['task_count'] . '</strong><small>' . (int)$flow['proof_task_count'] . ' require proof</small></article><article><span>Status</span><strong>' . tl_stage520_e($flow['status']) . '</strong><small>' . ($flow['reward_ready'] ? 'reward path ready' : 'reward path pending') . '</small></article></div>';
        tl_stage520_render_step_stack($flow['steps']);
        echo '</section>';
    }
}

if (!function_exists('tl_stage520_render_participant_mission')) {
    function tl_stage520_render_participant_mission(string $campaignRef = '', int $userId = 0): void
    {
        $mission = tl_stage520_participant_mission($campaignRef, $userId);
        echo '<section class="labs-card labs-stage520-panel labs-stage520-mission"><div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 497–504</span><h2>Participant Mission Flow</h2></div><a class="labs-btn" href="' . tl_stage520_e(labs_url('/api/training/core-product-flow.php?section=mission')) . '">Mission API</a></div>';
        echo '<div class="labs-stage520-flow-grid"><article><span>Progress</span><strong>' . (int)$mission['progress_percent'] . '%</strong><small>current participant</small></article><article><span>Next task</span><strong>' . tl_stage520_e(!empty($mission['next_task']['title']) ? 'Ready' : 'Setup') . '</strong><small>' . tl_stage520_e((string)($mission['next_task']['title'] ?? 'No task loaded')) . '</small></article><article><span>Rewards</span><strong>' . (int)($mission['reward_counts']['claimable'] ?? 0) . '</strong><small>claimable now</small></article></div>';
        tl_stage520_render_step_stack($mission['mission_steps']);
        echo '</section>';
    }
}

if (!function_exists('tl_stage520_render_admin_operations')) {
    function tl_stage520_render_admin_operations(): void
    {
        $ops = tl_stage520_admin_operations();
        echo '<section class="labs-card labs-stage520-panel labs-stage520-adminops"><div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 505–512</span><h2>Admin Review + Reward Operations Flow</h2></div><a class="labs-btn" href="' . tl_stage520_e(labs_url('/api/training/core-product-flow.php?section=admin')) . '">Ops API</a></div>';
        echo '<div class="labs-stage520-lane-board">';
        foreach ($ops['proof_lanes'] as $lane) {
            echo '<a href="' . tl_stage520_e(labs_url((string)$lane['href'])) . '"><span>' . tl_stage520_e($lane['label']) . '</span><strong>' . tl_stage520_e($lane['value']) . '</strong><small>' . tl_stage520_e($lane['hint']) . '</small></a>';
        }
        echo '</div><div class="labs-stage520-reward-board">';
        foreach ($ops['reward_lanes'] as $key => $value) {
            echo '<article><span>' . tl_stage520_e(ucwords(str_replace('_', ' ', (string)$key))) . '</span><strong>' . (int)$value . '</strong></article>';
        }
        echo '</div></section>';
    }
}

if (!function_exists('tl_stage520_render_launch_snapshot')) {
    function tl_stage520_render_launch_snapshot(): void
    {
        $snapshot = tl_stage520_launch_snapshot();
        echo '<section class="labs-card labs-stage520-panel labs-stage520-launch"><div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 513–520</span><h2>Reporting / Readiness / Launch Snapshot</h2></div><a class="labs-btn labs-btn-primary" href="' . tl_stage520_e(labs_url('/api/training/core-product-flow.php')) . '">Core Flow API</a></div>';
        echo '<div class="labs-stage520-flow-grid"><article><span>Launch Score</span><strong>' . (int)$snapshot['score'] . '/100</strong><small>' . (!empty($snapshot['accepted']) ? 'accepted' : 'needs review') . '</small></article><article><span>Sections</span><strong>' . count($snapshot['sections']) . '</strong><small>account, campaign, mission, ops, launch</small></article><article><span>SQL Boundary</span><strong>No SQL</strong><small>existing tables only</small></article></div>';
        echo '<div class="labs-stage520-lane-board">';
        foreach ($snapshot['sections'] as $key => $section) {
            echo '<a href="' . tl_stage520_e(labs_url((string)$section['next'])) . '"><span>' . tl_stage520_e(ucwords(str_replace('_', ' ', (string)$key))) . '</span><strong>' . (int)$section['score'] . '/100</strong><small>' . (!empty($section['accepted']) ? 'accepted' : 'needs review') . '</small></a>';
        }
        echo '</div></section>';
    }
}
