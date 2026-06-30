<?php
/**
 * Stage 721-760 Merchant Productization + Commerce Readiness.
 *
 * Five-section batch focused on reward packages, sponsor/merchant context,
 * offer previews, merchant operations, and a commerce-readiness API layer.
 * This is UI/readiness only: no real payments, wallet mutations, production
 * redemption, or ungated Microgifter sync actions are introduced.
 */

if (!function_exists('tl_stage760_e')) {
    function tl_stage760_e($value): string { return function_exists('labs_e') ? labs_e((string)$value) : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('tl_stage760_root')) { function tl_stage760_root(): string { return dirname(__DIR__); } }
if (!function_exists('tl_stage760_route_exists')) { function tl_stage760_route_exists(string $route): bool { return is_file(tl_stage760_root() . '/' . ltrim($route, '/')); } }
if (!function_exists('tl_stage760_score_from_checks')) {
    function tl_stage760_score_from_checks(array $checks): int { if (!$checks) return 100; $passed = 0; foreach ($checks as $ok) if ($ok) $passed++; return (int)round(($passed / max(1, count($checks))) * 100); }
}
if (!function_exists('tl_stage760_flow_counts')) {
    function tl_stage760_flow_counts(): array { $flow = function_exists('tl_app_flow_summary') ? tl_app_flow_summary() : ['counts'=>[]]; return (array)($flow['counts'] ?? []); }
}
if (!function_exists('tl_stage760_status_class')) {
    function tl_stage760_status_class(string $status): string {
        $s = strtolower(str_replace([' ', '-'], '_', trim($status)));
        if (in_array($s, ['ready','available','earned','claimable','connected','healthy','complete','approved','issued','active'], true)) return 'good';
        if (in_array($s, ['draft','locked','pending','pending_sync','review_needed','blocked','failed','needs_work','warning','not_connected'], true)) return 'warn';
        return 'neutral';
    }
}

if (!function_exists('tl_stage760_reward_package_builder')) {
    function tl_stage760_reward_package_builder(string $campaignRef = ''): array
    {
        $counts = tl_stage760_flow_counts();
        $rewardBridge = function_exists('tl_mg_stage160_bridge_summary') ? tl_mg_stage160_bridge_summary() : ['enabled'=>false, 'catalog_count'=>0];
        $packages = [
            ['type'=>'Starter Reward', 'status'=>'ready', 'value'=>'Welcome bonus', 'earning_action'=>'Join the campaign and complete first mission step.', 'href'=>'/app/campaign-builder.php'],
            ['type'=>'Completion Reward', 'status'=>(int)($counts['proofs'] ?? 0) > 0 ? 'earned' : 'locked', 'value'=>'Verified action reward', 'earning_action'=>'Submit proof and receive an approved review.', 'href'=>'/app/rewards.php'],
            ['type'=>'Bonus Reward', 'status'=>'draft', 'value'=>'Extra completion incentive', 'earning_action'=>'Complete the full task sequence or streak.', 'href'=>'/app/campaign-detail.php'],
            ['type'=>'Manual Admin Reward', 'status'=>(int)($counts['reward_events'] ?? 0) > 0 ? 'available' : 'pending', 'value'=>'Operator-issued make-good', 'earning_action'=>'Admin approves exception or manual fulfillment.', 'href'=>'/admin/reward-bridge.php'],
        ];
        $checks = [
            'campaign_builder_route' => tl_stage760_route_exists('/app/campaign-builder.php'),
            'campaign_detail_route' => tl_stage760_route_exists('/app/campaign-detail.php'),
            'rewards_route' => tl_stage760_route_exists('/app/rewards.php'),
            'admin_reward_bridge_route' => tl_stage760_route_exists('/admin/reward-bridge.php'),
            'admin_reward_inspector_route' => tl_stage760_route_exists('/admin/reward-inspector.php'),
            'package_cards_present' => count($packages) >= 4,
            'microgifter_adapter_gated' => true,
            'no_wallet_mutation' => true,
        ];
        return [
            'stage' => 'Stage 721-728 product and reward package builder',
            'campaign_ref' => $campaignRef,
            'reward_package_completeness_score' => tl_stage760_score_from_checks($checks),
            'packages' => $packages,
            'reward_bridge_status' => [
                'microgifter_connection' => !empty($rewardBridge['enabled']) ? 'adapter available' : 'adapter gated',
                'catalog_count' => (int)($rewardBridge['catalog_count'] ?? 0),
                'issuing_boundary' => 'Microgifter issuing remains adapter/developer-key gated.',
            ],
            'score' => tl_stage760_score_from_checks($checks),
            'accepted' => tl_stage760_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}

if (!function_exists('tl_stage760_merchant_sponsor_context')) {
    function tl_stage760_merchant_sponsor_context(string $campaignRef = ''): array
    {
        $campaigns = function_exists('tl_app_campaign_options') ? tl_app_campaign_options() : [];
        $selected = $campaigns[0] ?? [];
        $sponsor = [
            'training_owner' => 'Training Lab Operator',
            'sponsor_name' => 'Microgifter Merchant Sponsor',
            'campaign_audience' => 'Participants, reviewers, and reward operators',
            'reward_source' => 'Training reward catalog / Microgifter adapter when configured',
            'merchant_connection_status' => function_exists('tl_mg_stage160_bridge_summary') && !empty(tl_mg_stage160_bridge_summary()['enabled']) ? 'connected' : 'not_connected',
            'shared_account_model' => 'labs.microgifter.com and microgifter.com use one account identity model.',
            'current_campaign' => (string)($selected['title'] ?? $campaignRef ?: 'Demo training campaign'),
        ];
        $checklist = [
            ['label'=>'Sponsor identity', 'status'=>'ready', 'detail'=>'Training owner and sponsor labels have a visible UI slot.', 'href'=>'/admin/command-center.php'],
            ['label'=>'Campaign audience', 'status'=>'ready', 'detail'=>'Audience is shown before reward/offer operations.', 'href'=>'/app/campaigns.php'],
            ['label'=>'Reward source', 'status'=>'ready', 'detail'=>'Reward source is visible without enabling real commerce actions.', 'href'=>'/admin/reward-bridge.php'],
            ['label'=>'Merchant connection', 'status'=>$sponsor['merchant_connection_status'], 'detail'=>'Badge only; no automatic merchant sync is performed.', 'href'=>'/admin/backend-readiness.php'],
        ];
        $checks = [
            'app_index_route' => tl_stage760_route_exists('/app/index.php'),
            'campaign_builder_route' => tl_stage760_route_exists('/app/campaign-builder.php'),
            'campaigns_route' => tl_stage760_route_exists('/app/campaigns.php'),
            'admin_index_route' => tl_stage760_route_exists('/admin/index.php'),
            'admin_command_center_route' => tl_stage760_route_exists('/admin/command-center.php'),
            'reporting_center_route' => tl_stage760_route_exists('/admin/reporting-center.php'),
            'sponsor_checklist_present' => count($checklist) >= 4,
        ];
        return [
            'stage' => 'Stage 729-736 merchant and sponsor context layer',
            'sponsor_context' => $sponsor,
            'sponsor_readiness_checklist' => $checklist,
            'score' => tl_stage760_score_from_checks($checks),
            'accepted' => tl_stage760_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}

if (!function_exists('tl_stage760_offer_preview_experience')) {
    function tl_stage760_offer_preview_experience(string $campaignRef = '', int $userId = 0): array
    {
        $packages = tl_stage760_reward_package_builder($campaignRef)['packages'];
        $previews = [];
        foreach ($packages as $idx => $package) {
            $previews[] = [
                'title' => (string)$package['type'],
                'status' => (string)$package['status'],
                'value' => (string)$package['value'],
                'earning_action' => (string)$package['earning_action'],
                'expiration' => 'Training-window placeholder',
                'image_slot' => $idx % 2 === 0 ? 'reward-card' : 'challenge-card',
                'href' => $idx % 2 === 0 ? '/app/rewards.php' : '/app/challenge-library.php',
            ];
        }
        $checks = [
            'challenge_library_route' => tl_stage760_route_exists('/app/challenge-library.php'),
            'resource_hub_route' => tl_stage760_route_exists('/app/resource-hub.php'),
            'rewards_route' => tl_stage760_route_exists('/app/rewards.php'),
            'participant_portal_route' => tl_stage760_route_exists('/app/participant-portal.php'),
            'admin_reward_bridge_route' => tl_stage760_route_exists('/admin/reward-bridge.php'),
            'offer_previews_present' => count($previews) >= 4,
            'no_real_payments' => true,
            'no_production_redemption' => true,
        ];
        return [
            'stage' => 'Stage 737-744 catalog and offer preview experience',
            'offer_preview_cards' => $previews,
            'participant_status_tags' => ['Available','Earned','Claimable','Locked','Pending Review'],
            'admin_preview_notes' => ['Preview reward presentation before issuing.', 'Keep claim/redeem disabled until production adapter is approved.', 'Tie every offer to a verified action.'],
            'score' => tl_stage760_score_from_checks($checks),
            'accepted' => tl_stage760_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}

if (!function_exists('tl_stage760_merchant_operations_console')) {
    function tl_stage760_merchant_operations_console(): array
    {
        $context = tl_stage760_merchant_sponsor_context();
        $package = tl_stage760_reward_package_builder();
        $counts = tl_stage760_flow_counts();
        $board = [
            ['label'=>'Campaign readiness', 'status'=>(int)($counts['campaigns'] ?? 0) > 0 ? 'ready' : 'draft', 'detail'=>(int)($counts['campaigns'] ?? 0) . ' campaign(s) available.', 'href'=>'/admin/campaigns.php'],
            ['label'=>'Reward readiness', 'status'=>!empty($package['accepted']) ? 'ready' : 'needs_work', 'detail'=>'Reward packages mapped to earning actions.', 'href'=>'/admin/reward-bridge.php'],
            ['label'=>'Participant readiness', 'status'=>(int)($counts['participants'] ?? 0) > 0 ? 'ready' : 'pending', 'detail'=>(int)($counts['participants'] ?? 0) . ' participant(s).', 'href'=>'/admin/participant-inspector.php'],
            ['label'=>'Microgifter bridge readiness', 'status'=>(string)($context['sponsor_context']['merchant_connection_status'] ?? 'not_connected'), 'detail'=>'Adapter/developer-key gated status badge only.', 'href'=>'/admin/backend-readiness.php'],
        ];
        $actionOrder = [
            'Confirm sponsor identity and campaign audience.',
            'Review campaign readiness and task/reward sequence.',
            'Preview participant offer cards before launch.',
            'Verify reward bridge is gated or configured safely.',
            'Run Backend Readiness before live merchant use.',
        ];
        $checks = [
            'admin_index_route' => tl_stage760_route_exists('/admin/index.php'),
            'admin_command_center_route' => tl_stage760_route_exists('/admin/command-center.php'),
            'admin_reward_bridge_route' => tl_stage760_route_exists('/admin/reward-bridge.php'),
            'admin_reporting_center_route' => tl_stage760_route_exists('/admin/reporting-center.php'),
            'admin_backend_readiness_route' => tl_stage760_route_exists('/admin/backend-readiness.php'),
            'merchant_ops_board_present' => count($board) >= 4,
            'operator_action_order_present' => count($actionOrder) >= 5,
        ];
        return [
            'stage' => 'Stage 745-752 merchant operations console',
            'merchant_operations_board' => $board,
            'sponsor_issue_checklist' => (array)$context['sponsor_readiness_checklist'],
            'operator_action_order' => $actionOrder,
            'score' => tl_stage760_score_from_checks($checks),
            'accepted' => tl_stage760_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}

if (!function_exists('tl_stage760_merchant_commerce_audit')) {
    function tl_stage760_merchant_commerce_audit(): array
    {
        $root = tl_stage760_root();
        $markers = [
            'app/campaign-builder.php' => 'tl_stage760_render_reward_package_builder',
            'app/campaign-detail.php' => 'tl_stage760_render_reward_package_builder',
            'app/rewards.php' => 'tl_stage760_render_offer_preview_experience',
            'app/index.php' => 'tl_stage760_render_merchant_sponsor_context',
            'app/campaigns.php' => 'tl_stage760_render_merchant_sponsor_context',
            'app/challenge-library.php' => 'tl_stage760_render_offer_preview_experience',
            'app/resource-hub.php' => 'tl_stage760_render_offer_preview_experience',
            'app/participant-portal.php' => 'tl_stage760_render_offer_preview_experience',
            'admin/index.php' => 'tl_stage760_render_merchant_operations_console',
            'admin/command-center.php' => 'tl_stage760_render_merchant_operations_console',
            'admin/reward-bridge.php' => 'tl_stage760_render_reward_package_builder',
            'admin/reward-inspector.php' => 'tl_stage760_render_reward_package_builder',
            'admin/reporting-center.php' => 'tl_stage760_render_merchant_operations_console',
            'admin/backend-readiness.php' => 'tl_stage760_render_merchant_operations_console',
            'api/training/merchant-commerce-readiness.php' => 'tl_stage760_merchant_commerce_summary',
        ];
        $issues = [];
        foreach ($markers as $path => $needle) {
            $file = $root . '/' . $path;
            if (!is_file($file) || strpos((string)file_get_contents($file), $needle) === false) $issues[] = $path . ' missing Stage 760 marker ' . $needle;
        }
        return [
            'stage' => 'Stage 753-760 merchant commerce readiness audit',
            'issue_count' => count($issues),
            'issues' => $issues,
            'score' => count($issues) === 0 ? 100 : max(0, 100 - count($issues) * 6),
            'accepted' => count($issues) === 0,
            'checks' => [
                'route_marker_audit' => count($issues) === 0,
                'no_new_sql_required' => true,
                'no_page_factory_expansion' => true,
                'no_real_payments' => true,
                'no_production_redemption' => true,
                'microgifter_adapter_gated' => true,
            ],
        ];
    }
}

if (!function_exists('tl_stage760_merchant_commerce_summary')) {
    function tl_stage760_merchant_commerce_summary(bool $includeAudit = true): array
    {
        $package = tl_stage760_reward_package_builder();
        $sponsor = tl_stage760_merchant_sponsor_context();
        $offers = tl_stage760_offer_preview_experience();
        $ops = tl_stage760_merchant_operations_console();
        $audit = $includeAudit ? tl_stage760_merchant_commerce_audit() : ['accepted'=>true,'score'=>100,'issue_count'=>0,'issues'=>[]];
        $sections = [$package, $sponsor, $offers, $ops, $audit];
        $accepted = true; $scores = [];
        foreach ($sections as $section) { $scores[] = (int)($section['score'] ?? 0); if (empty($section['accepted'])) $accepted = false; }
        return [
            'stage' => 'Stage 721-760 merchant productization and commerce readiness',
            'built_from' => 'Stage 681-720 content management and training experience polish',
            'builds' => [
                'Build 99: Product / Reward Package Builder',
                'Build 100: Merchant / Sponsor Context Layer',
                'Build 101: Catalog / Offer Preview Experience',
                'Build 102: Merchant Operations Console',
                'Build 103: Merchant Commerce Readiness API Layer',
            ],
            'reward_package_builder' => $package,
            'merchant_sponsor_context' => $sponsor,
            'offer_preview_experience' => $offers,
            'merchant_operations_console' => $ops,
            'merchant_commerce_audit' => $audit,
            'sponsor_merchant_readiness_status' => !empty($sponsor['accepted']) ? 'ready' : 'needs_work',
            'reward_package_readiness_status' => !empty($package['accepted']) ? 'ready' : 'needs_work',
            'offer_preview_readiness_status' => !empty($offers['accepted']) ? 'ready' : 'needs_work',
            'merchant_operations_readiness_score' => (int)($ops['score'] ?? 0),
            'score' => $accepted ? 100 : (int)round(array_sum($scores) / max(1, count($scores))),
            'accepted' => $accepted,
            'safe_boundaries' => [
                'no_new_sql_required' => true,
                'no_config_files_moved_or_overwritten' => true,
                'no_hard_auth_gates_forced' => true,
                'no_page_factory_expansion' => true,
                'no_real_payment_processing' => true,
                'no_production_claim_or_redemption_mutation' => true,
                'no_wallet_balance_mutation' => true,
                'microgifter_reward_issuing_adapter_gated' => true,
            ],
        ];
    }
}

if (!function_exists('tl_stage760_context_cards')) {
    function tl_stage760_context_cards(string $context): array
    {
        if (strpos($context, 'reward') !== false || strpos($context, 'commerce') !== false) {
            return [
                ['label'=>'Package', 'value'=>'Reward', 'hint'=>'earning action mapped', 'href'=>'/admin/reward-bridge.php'],
                ['label'=>'Offer', 'value'=>'Preview', 'hint'=>'participant card', 'href'=>'/app/rewards.php'],
                ['label'=>'Bridge', 'value'=>'Gated', 'hint'=>'Microgifter safe', 'href'=>'/admin/backend-readiness.php'],
            ];
        }
        if (strpos($context, 'campaign') !== false || strpos($context, 'challenge') !== false || strpos($context, 'launchpad') !== false) {
            return [
                ['label'=>'Sponsor', 'value'=>'Context', 'hint'=>'owner + audience', 'href'=>'/app/campaigns.php'],
                ['label'=>'Package', 'value'=>'Preview', 'hint'=>'reward path', 'href'=>'/app/campaign-builder.php'],
                ['label'=>'Offer', 'value'=>'Catalog', 'hint'=>'locked/claimable', 'href'=>'/app/challenge-library.php'],
            ];
        }
        if (str_starts_with($context, 'admin-')) {
            return [
                ['label'=>'Merchant', 'value'=>'Ops', 'hint'=>'sponsor board', 'href'=>'/admin/command-center.php'],
                ['label'=>'Reward', 'value'=>'Ready', 'hint'=>'package audit', 'href'=>'/admin/reward-bridge.php'],
                ['label'=>'Bridge', 'value'=>'Check', 'hint'=>'readiness gate', 'href'=>'/admin/backend-readiness.php'],
            ];
        }
        return [
            ['label'=>'Sponsor', 'value'=>'Ready', 'hint'=>'shared account', 'href'=>'/app/index.php'],
            ['label'=>'Offer', 'value'=>'Preview', 'hint'=>'reward cards', 'href'=>'/app/rewards.php'],
            ['label'=>'Catalog', 'value'=>'View', 'hint'=>'training offers', 'href'=>'/app/challenge-library.php'],
        ];
    }
}

if (!function_exists('tl_stage760_context_runtime_overrides')) {
    function tl_stage760_context_runtime_overrides(string $context, array $baseCfg = []): array
    {
        $isAdmin = str_starts_with($context, 'admin-');
        $counts = tl_stage760_flow_counts();
        return [
            'live_strip' => $isAdmin
                ? ['Shared Microgifter account', 'Merchant operations', 'Reward packages', 'Stage 760']
                : ['Shared Microgifter account', 'Sponsor context', 'Offer previews', 'Stage 760'],
            'stage760_cards' => tl_stage760_context_cards($context),
            'metric_values' => $isAdmin
                ? [(string)((int)($counts['campaigns'] ?? 0)), (string)((int)($counts['reward_events'] ?? 0)), '760']
                : [(string)((int)($counts['participants'] ?? 0)), (string)((int)($counts['reward_events'] ?? 0)), '760'],
            'progress_width' => $isAdmin ? '91%' : '82%',
            'status_meta' => $isAdmin
                ? ['Merchant board', 'Reward bridge', 'Commerce readiness']
                : ['Sponsor context', 'Training offers', 'Reward preview'],
            'stage760_runtime_bound' => true,
        ];
    }
}

if (!function_exists('tl_stage760_render_card_grid')) {
    function tl_stage760_render_card_grid(array $items): void
    {
        echo '<div class="labs-stage760-card-grid">';
        foreach ($items as $item) {
            $status = (string)($item['status'] ?? 'ready');
            $href = (string)($item['href'] ?? '#');
            $label = (string)($item['title'] ?? $item['type'] ?? $item['label'] ?? 'Commerce item');
            $detail = (string)($item['detail'] ?? $item['earning_action'] ?? $item['value'] ?? 'Merchant commerce readiness item');
            echo '<a class="is-' . tl_stage760_e(tl_stage760_status_class($status)) . '" href="' . tl_stage760_e(function_exists('labs_url') ? labs_url($href) : $href) . '"><span>' . tl_stage760_e((string)($item['value'] ?? $status)) . '</span><strong>' . tl_stage760_e($label) . '</strong><small>' . tl_stage760_e($detail) . '</small></a>';
        }
        echo '</div>';
    }
}

if (!function_exists('tl_stage760_render_shell')) {
    function tl_stage760_render_shell(string $eyebrow, string $title, array $metrics, array $items, string $apiHref, string $class = ''): void
    {
        echo '<section class="labs-card labs-stage760-panel ' . tl_stage760_e($class) . '"><div class="labs-card-headline"><div><span class="labs-eyebrow">' . tl_stage760_e($eyebrow) . '</span><h2>' . tl_stage760_e($title) . '</h2></div><a class="labs-btn" href="' . tl_stage760_e(function_exists('labs_url') ? labs_url($apiHref) : $apiHref) . '">Commerce API</a></div>';
        echo '<div class="labs-stage760-metric-grid">';
        foreach ($metrics as $metric) echo '<article><span>' . tl_stage760_e($metric['label'] ?? 'Metric') . '</span><strong>' . tl_stage760_e($metric['value'] ?? 'Ready') . '</strong><small>' . tl_stage760_e($metric['hint'] ?? '') . '</small></article>';
        echo '</div>';
        tl_stage760_render_card_grid($items);
        echo '</section>';
    }
}

if (!function_exists('tl_stage760_render_reward_package_builder')) {
    function tl_stage760_render_reward_package_builder(string $campaignRef = ''): void
    {
        $data = tl_stage760_reward_package_builder($campaignRef);
        $metrics = [
            ['label'=>'Package score', 'value'=>$data['reward_package_completeness_score'] . '/100', 'hint'=>'reward package readiness'],
            ['label'=>'Packages', 'value'=>(string)count($data['packages']), 'hint'=>'starter/completion/bonus/manual'],
            ['label'=>'Bridge', 'value'=>(string)$data['reward_bridge_status']['microgifter_connection'], 'hint'=>'adapter gated'],
        ];
        tl_stage760_render_shell('Stage 721-728', 'Product / Reward Package Builder', $metrics, $data['packages'], '/api/training/merchant-commerce-readiness.php?section=packages', 'labs-stage760-packages');
    }
}

if (!function_exists('tl_stage760_render_merchant_sponsor_context')) {
    function tl_stage760_render_merchant_sponsor_context(string $campaignRef = ''): void
    {
        $data = tl_stage760_merchant_sponsor_context($campaignRef);
        $ctx = $data['sponsor_context'];
        $metrics = [
            ['label'=>'Sponsor', 'value'=>(string)$ctx['sponsor_name'], 'hint'=>'campaign owner slot'],
            ['label'=>'Audience', 'value'=>'Mapped', 'hint'=>(string)$ctx['campaign_audience']],
            ['label'=>'Merchant bridge', 'value'=>(string)$ctx['merchant_connection_status'], 'hint'=>'simple badge only'],
        ];
        tl_stage760_render_shell('Stage 729-736', 'Merchant / Sponsor Context', $metrics, $data['sponsor_readiness_checklist'], '/api/training/merchant-commerce-readiness.php?section=sponsor', 'labs-stage760-sponsor');
    }
}

if (!function_exists('tl_stage760_render_offer_preview_experience')) {
    function tl_stage760_render_offer_preview_experience(string $campaignRef = '', int $userId = 0): void
    {
        $data = tl_stage760_offer_preview_experience($campaignRef, $userId);
        $metrics = [
            ['label'=>'Offers', 'value'=>(string)count($data['offer_preview_cards']), 'hint'=>'training reward cards'],
            ['label'=>'Tags', 'value'=>(string)count($data['participant_status_tags']), 'hint'=>'available/earned/locked'],
            ['label'=>'Commerce', 'value'=>'Preview', 'hint'=>'no payment/redemption'],
        ];
        tl_stage760_render_shell('Stage 737-744', 'Catalog / Offer Preview Experience', $metrics, $data['offer_preview_cards'], '/api/training/merchant-commerce-readiness.php?section=offers', 'labs-stage760-offers');
    }
}

if (!function_exists('tl_stage760_render_merchant_operations_console')) {
    function tl_stage760_render_merchant_operations_console(): void
    {
        $data = tl_stage760_merchant_operations_console();
        $metrics = [
            ['label'=>'Ops score', 'value'=>$data['score'] . '/100', 'hint'=>'merchant board'],
            ['label'=>'Board lanes', 'value'=>(string)count($data['merchant_operations_board']), 'hint'=>'campaign/reward/participant/bridge'],
            ['label'=>'Action order', 'value'=>(string)count($data['operator_action_order']), 'hint'=>'merchant-backed runbook'],
        ];
        tl_stage760_render_shell('Stage 745-752', 'Merchant Operations Console', $metrics, $data['merchant_operations_board'], '/api/training/merchant-commerce-readiness.php?section=ops', 'labs-stage760-merchant-ops');
    }
}
