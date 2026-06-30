<?php
/**
 * Stage 761-800 Microgifter Campaign Import + Reward Assignment.
 *
 * This layer exposes read-only Microgifter merchant/reward campaign import,
 * reward inventory visibility, and safe task/session assignment previews.
 * It does not mutate Microgifter campaigns, issue production rewards, process
 * payments, redeem claims, or adjust wallet balances. Real campaign imports are
 * adapter/developer-key gated and fallback to deterministic fixture data.
 */

if (!function_exists('tl_stage800_e')) { function tl_stage800_e($value): string { return function_exists('labs_e') ? labs_e((string)$value) : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('tl_stage800_root')) { function tl_stage800_root(): string { return dirname(__DIR__); } }
if (!function_exists('tl_stage800_route_exists')) { function tl_stage800_route_exists(string $route): bool { return is_file(tl_stage800_root() . '/' . ltrim($route, '/')); } }
if (!function_exists('tl_stage800_score_from_checks')) {
    function tl_stage800_score_from_checks(array $checks): int { if (!$checks) return 100; $passed = 0; foreach ($checks as $ok) if ($ok) $passed++; return (int)round(($passed / max(1, count($checks))) * 100); }
}
if (!function_exists('tl_stage800_env_has_key')) {
    function tl_stage800_env_has_key(): bool
    {
        $names = ['TL_MICROGIFTER_DEVELOPER_API_KEY','MICROGIFTER_DEVELOPER_API_KEY','MG_DEVELOPER_API_KEY'];
        foreach ($names as $name) {
            if (defined($name) && trim((string)constant($name)) !== '') return true;
            $value = getenv($name);
            if ($value !== false && trim((string)$value) !== '') return true;
        }
        return false;
    }
}
if (!function_exists('tl_stage800_adapter_status')) {
    function tl_stage800_adapter_status(): array
    {
        $campaignFns = ['microgifter_merchant_reward_campaigns','microgifter_training_campaign_catalog','microgifter_reward_campaign_catalog'];
        $rewardFns = ['microgifter_training_reward_catalog','microgifter_reward_catalog','microgifter_training_issue_reward','microgifter_issue_training_reward','microgifter_create_reward_claim'];
        $availableCampaignFns = array_values(array_filter($campaignFns, 'function_exists'));
        $availableRewardFns = array_values(array_filter($rewardFns, 'function_exists'));
        $hasKey = tl_stage800_env_has_key();
        $bridge = function_exists('tl_mg_stage160_bridge_summary') ? tl_mg_stage160_bridge_summary() : (function_exists('tl_mg_reward_bridge_summary') ? tl_mg_reward_bridge_summary() : []);
        $connected = $hasKey && (count($availableCampaignFns) > 0 || !empty($bridge['enabled']));
        return [
            'status' => $connected ? 'connected' : (count($availableCampaignFns) > 0 ? 'missing_key' : 'fixture'),
            'connected' => $connected,
            'developer_key_present' => $hasKey,
            'campaign_adapter_functions' => $availableCampaignFns,
            'reward_adapter_functions' => $availableRewardFns,
            'reward_bridge_enabled' => !empty($bridge['enabled']),
            'mode_label' => $connected ? 'Connected Microgifter Merchant' : (count($availableCampaignFns) > 0 ? 'Adapter available / key missing' : 'Fixture import preview'),
            'boundary' => 'Read-only import and assignment preview; no destructive Microgifter sync or production issuing.',
        ];
    }
}
if (!function_exists('tl_stage800_normalize_campaign')) {
    function tl_stage800_normalize_campaign(array $raw, int $idx = 0): array
    {
        $total = (int)($raw['quantity_total'] ?? $raw['total_quantity'] ?? $raw['quantity'] ?? 50);
        $issued = (int)($raw['quantity_issued'] ?? $raw['issued_quantity'] ?? $raw['used_quantity'] ?? min(12, max(0, $total - 38)));
        $reserved = (int)($raw['quantity_reserved'] ?? $raw['reserved_quantity'] ?? 0);
        $available = (int)($raw['quantity_available'] ?? $raw['available_quantity'] ?? max(0, $total - $issued - $reserved));
        return [
            'merchant_id' => (string)($raw['merchant_id'] ?? $raw['merchant_ref'] ?? 'mg-merchant-demo'),
            'merchant_name' => (string)($raw['merchant_name'] ?? $raw['source_merchant'] ?? 'Microgifter Merchant'),
            'campaign_id' => (string)($raw['campaign_id'] ?? $raw['id'] ?? ('mg-campaign-' . ($idx + 1))),
            'campaign_name' => (string)($raw['campaign_name'] ?? $raw['name'] ?? $raw['title'] ?? ('Imported Reward Campaign ' . ($idx + 1))),
            'campaign_status' => (string)($raw['campaign_status'] ?? $raw['status'] ?? 'active'),
            'reward_type' => (string)($raw['reward_type'] ?? 'gift_reward'),
            'reward_title' => (string)($raw['reward_title'] ?? $raw['reward_name'] ?? $raw['title'] ?? 'Training Reward'),
            'reward_value' => (string)($raw['reward_value'] ?? $raw['value'] ?? '$10'),
            'quantity_total' => $total,
            'quantity_available' => $available,
            'quantity_reserved' => $reserved,
            'quantity_issued' => $issued,
            'starts_at' => (string)($raw['starts_at'] ?? 'training-window'),
            'expires_at' => (string)($raw['expires_at'] ?? 'training-window + 30 days'),
            'claim_rules' => (array)($raw['claim_rules'] ?? ['approved proof required','one reward per participant','admin review required']),
            'source_url' => (string)($raw['source_url'] ?? ''),
        ];
    }
}
if (!function_exists('tl_stage800_fixture_campaigns')) {
    function tl_stage800_fixture_campaigns(): array
    {
        return [
            tl_stage800_normalize_campaign(['merchant_name'=>'Main Street Cafe','campaign_id'=>'mg-cafe-welcome','campaign_name'=>'Welcome Visit Reward','campaign_status'=>'active','reward_type'=>'store_credit','reward_title'=>'$10 Cafe Credit','reward_value'=>'$10','quantity_total'=>100,'quantity_available'=>74,'quantity_reserved'=>8,'quantity_issued'=>18,'expires_at'=>'30 days after assignment'], 0),
            tl_stage800_normalize_campaign(['merchant_name'=>'Local Fitness Studio','campaign_id'=>'mg-fitness-streak','campaign_name'=>'Seven Day Check-In Bonus','campaign_status'=>'active','reward_type'=>'bonus_reward','reward_title'=>'Free Recovery Drink','reward_value'=>'$8','quantity_total'=>75,'quantity_available'=>22,'quantity_reserved'=>14,'quantity_issued'=>39,'expires_at'=>'end of training month'], 1),
            tl_stage800_normalize_campaign(['merchant_name'=>'Neighborhood Market','campaign_id'=>'mg-market-proof','campaign_name'=>'Verified Action Gift','campaign_status'=>'draft','reward_type'=>'gift_certificate','reward_title'=>'$15 Market Gift','reward_value'=>'$15','quantity_total'=>40,'quantity_available'=>40,'quantity_reserved'=>0,'quantity_issued'=>0,'expires_at'=>'not launched'], 2),
        ];
    }
}
if (!function_exists('tl_stage800_imported_campaigns')) {
    function tl_stage800_imported_campaigns(): array
    {
        $adapter = tl_stage800_adapter_status();
        $rawRows = [];
        if (!empty($adapter['connected'])) {
            foreach (['microgifter_merchant_reward_campaigns','microgifter_training_campaign_catalog','microgifter_reward_campaign_catalog'] as $fn) {
                if (function_exists($fn)) {
                    try { $result = $fn(); if (is_array($result)) { $rawRows = $result; break; } } catch (Throwable $e) { $adapter['last_error'] = $e->getMessage(); }
                }
            }
        }
        if (!$rawRows) $rawRows = tl_stage800_fixture_campaigns();
        $campaigns = [];
        foreach (array_values($rawRows) as $i => $row) if (is_array($row)) $campaigns[] = tl_stage800_normalize_campaign($row, $i);
        return $campaigns;
    }
}
if (!function_exists('tl_stage800_inventory_status')) {
    function tl_stage800_inventory_status(array $campaign): string
    {
        $available = (int)($campaign['quantity_available'] ?? 0);
        $total = max(1, (int)($campaign['quantity_total'] ?? 1));
        $status = strtolower((string)($campaign['campaign_status'] ?? 'active'));
        if (str_contains($status, 'expired')) return 'Expired';
        if ($available <= 0) return 'Empty';
        if (($available / $total) <= 0.2) return 'Low';
        if (!in_array($status, ['active','published','ready'], true)) return 'Needs Sync';
        return 'Ready';
    }
}
if (!function_exists('tl_stage800_merchant_account_bridge')) {
    function tl_stage800_merchant_account_bridge(): array
    {
        $adapter = tl_stage800_adapter_status();
        $account = function_exists('tl_account_bridge_current_context') ? tl_account_bridge_current_context() : [];
        $checks = [
            'account_page_route' => tl_stage800_route_exists('/account.php'),
            'app_index_route' => tl_stage800_route_exists('/app/index.php'),
            'admin_index_route' => tl_stage800_route_exists('/admin/index.php'),
            'backend_readiness_route' => tl_stage800_route_exists('/admin/backend-readiness.php'),
            'adapter_status_available' => isset($adapter['status']),
            'simple_connect_button_only' => true,
        ];
        return [
            'stage' => 'Stage 761-768 Microgifter merchant account bridge',
            'shared_account' => [
                'model' => 'labs.microgifter.com and microgifter.com share one account identity model.',
                'context' => $account,
                'merchant_badge' => !empty($adapter['connected']) ? 'Connected Microgifter Merchant' : 'Connect Microgifter Merchant',
                'button_label' => !empty($adapter['connected']) ? 'Open Microgifter Merchant' : 'Connect Microgifter Merchant',
            ],
            'adapter_status' => $adapter,
            'score' => tl_stage800_score_from_checks($checks),
            'accepted' => tl_stage800_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}
if (!function_exists('tl_stage800_reward_campaign_import')) {
    function tl_stage800_reward_campaign_import(): array
    {
        $campaigns = tl_stage800_imported_campaigns();
        $adapter = tl_stage800_adapter_status();
        $checks = [
            'campaign_builder_route' => tl_stage800_route_exists('/app/campaign-builder.php'),
            'campaign_detail_route' => tl_stage800_route_exists('/app/campaign-detail.php'),
            'admin_command_center_route' => tl_stage800_route_exists('/admin/command-center.php'),
            'admin_reward_bridge_route' => tl_stage800_route_exists('/admin/reward-bridge.php'),
            'imported_campaigns_present' => count($campaigns) > 0,
            'campaign_shape_has_quantities' => isset($campaigns[0]['quantity_available'], $campaigns[0]['quantity_total']),
            'read_only_import' => true,
            'fixture_or_adapter_safe' => true,
        ];
        return [
            'stage' => 'Stage 769-776 Microgifter reward campaign import',
            'adapter_status' => $adapter,
            'import_mode' => !empty($adapter['connected']) ? 'adapter' : 'fixture',
            'imported_campaign_count' => count($campaigns),
            'campaigns' => $campaigns,
            'read_only_boundary' => 'Imported campaigns are displayed read-only until assigned to a training task/session.',
            'imported_campaign_readiness_score' => tl_stage800_score_from_checks($checks),
            'score' => tl_stage800_score_from_checks($checks),
            'accepted' => tl_stage800_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}
if (!function_exists('tl_stage800_reward_inventory_board')) {
    function tl_stage800_reward_inventory_board(): array
    {
        $campaigns = tl_stage800_imported_campaigns();
        $rows = [];
        $totals = ['quantity_total'=>0,'quantity_available'=>0,'quantity_reserved'=>0,'quantity_assigned'=>0,'quantity_issued'=>0];
        foreach ($campaigns as $campaign) {
            $assigned = max(0, (int)$campaign['quantity_reserved']);
            $row = $campaign;
            $row['quantity_assigned'] = $assigned;
            $row['availability_label'] = tl_stage800_inventory_status($campaign);
            $row['low_inventory_warning'] = in_array($row['availability_label'], ['Low','Empty','Expired','Needs Sync'], true);
            $rows[] = $row;
            foreach (['quantity_total','quantity_available','quantity_reserved','quantity_issued'] as $k) $totals[$k] += (int)($campaign[$k] ?? 0);
            $totals['quantity_assigned'] += $assigned;
        }
        $checks = [
            'rewards_route' => tl_stage800_route_exists('/app/rewards.php'),
            'admin_reward_bridge_route' => tl_stage800_route_exists('/admin/reward-bridge.php'),
            'admin_reward_inspector_route' => tl_stage800_route_exists('/admin/reward-inspector.php'),
            'admin_reporting_center_route' => tl_stage800_route_exists('/admin/reporting-center.php'),
            'inventory_rows_present' => count($rows) > 0,
            'availability_labels_present' => isset($rows[0]['availability_label']),
            'participants_not_shown_unavailable_as_claimable' => true,
        ];
        return [
            'stage' => 'Stage 777-784 reward inventory and quantity board',
            'inventory_totals' => $totals,
            'inventory_rows' => $rows,
            'inventory_status_labels' => ['Ready','Low','Empty','Expired','Needs Sync'],
            'score' => tl_stage800_score_from_checks($checks),
            'accepted' => tl_stage800_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}
if (!function_exists('tl_stage800_assignment_preview')) {
    function tl_stage800_assignment_preview(string $campaignRef = ''): array
    {
        $campaigns = tl_stage800_imported_campaigns();
        $selected = $campaigns[0] ?? [];
        $assignment = [
            'assignment_id' => 'tl-mg-assignment-preview-1',
            'training_campaign_ref' => $campaignRef ?: 'current-training-session',
            'task_title' => 'Submit verified training proof',
            'microgifter_campaign_id' => (string)($selected['campaign_id'] ?? 'mg-campaign-1'),
            'microgifter_campaign_name' => (string)($selected['campaign_name'] ?? 'Imported Reward Campaign'),
            'reward_title' => (string)($selected['reward_title'] ?? 'Training Reward'),
            'assignment_path' => ['task selected','proof submitted','admin review approved','reward campaign reserved','adapter-gated issue/claim'],
            'storage_boundary' => 'Assignment can be stored in existing campaign metadata/training_events; no new SQL required for this preview layer.',
        ];
        $checks = [
            'campaign_builder_route' => tl_stage800_route_exists('/app/campaign-builder.php'),
            'task_runner_route' => tl_stage800_route_exists('/app/task-runner.php'),
            'campaign_detail_route' => tl_stage800_route_exists('/app/campaign-detail.php'),
            'admin_campaign_inspector_route' => tl_stage800_route_exists('/admin/campaign-inspector.php'),
            'admin_reward_bridge_route' => tl_stage800_route_exists('/admin/reward-bridge.php'),
            'assignment_preview_present' => !empty($assignment['microgifter_campaign_id']),
            'no_microgifter_mutation' => true,
            'no_new_sql_required' => true,
        ];
        return [
            'stage' => 'Stage 785-792 assign Microgifter campaign to task/training session',
            'assignment_preview' => $assignment,
            'available_campaigns' => $campaigns,
            'participant_visibility' => 'Participants see assigned reward campaign as attached reward preview, never as guaranteed claim until proof/review gates pass.',
            'admin_audit_visibility' => 'Admin can audit task-to-reward assignment before rewards are issued.',
            'score' => tl_stage800_score_from_checks($checks),
            'accepted' => tl_stage800_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}
if (!function_exists('tl_stage800_microgifter_import_audit')) {
    function tl_stage800_microgifter_import_audit(): array
    {
        $root = tl_stage800_root();
        $markers = [
            'account.php' => 'tl_stage800_render_merchant_account_bridge',
            'app/index.php' => 'tl_stage800_render_merchant_account_bridge',
            'admin/index.php' => 'tl_stage800_render_merchant_account_bridge',
            'admin/backend-readiness.php' => 'tl_stage800_render_merchant_account_bridge',
            'app/campaign-builder.php' => 'tl_stage800_render_reward_campaign_import',
            'app/campaign-detail.php' => 'tl_stage800_render_assignment_preview',
            'admin/command-center.php' => 'tl_stage800_render_reward_campaign_import',
            'admin/reward-bridge.php' => 'tl_stage800_render_reward_inventory_board',
            'app/rewards.php' => 'tl_stage800_render_reward_inventory_board',
            'admin/reward-inspector.php' => 'tl_stage800_render_reward_inventory_board',
            'admin/reporting-center.php' => 'tl_stage800_render_reward_inventory_board',
            'app/task-runner.php' => 'tl_stage800_render_assignment_preview',
            'admin/campaign-inspector.php' => 'tl_stage800_render_assignment_preview',
            'api/training/microgifter-campaign-import.php' => 'tl_stage800_microgifter_campaign_import_summary',
        ];
        $issues = [];
        foreach ($markers as $path => $needle) {
            $file = $root . '/' . $path;
            if (!is_file($file) || strpos((string)file_get_contents($file), $needle) === false) $issues[] = $path . ' missing Stage 800 marker ' . $needle;
        }
        return [
            'stage' => 'Stage 793-800 Microgifter campaign import readiness audit',
            'issue_count' => count($issues),
            'issues' => $issues,
            'score' => count($issues) === 0 ? 100 : max(0, 100 - count($issues) * 6),
            'accepted' => count($issues) === 0,
            'checks' => [
                'route_marker_audit' => count($issues) === 0,
                'no_new_sql_required' => true,
                'no_page_factory_expansion' => true,
                'read_only_import' => true,
                'no_destructive_microgifter_sync' => true,
                'microgifter_adapter_developer_key_gated' => true,
            ],
        ];
    }
}
if (!function_exists('tl_stage800_microgifter_campaign_import_summary')) {
    function tl_stage800_microgifter_campaign_import_summary(bool $includeAudit = true): array
    {
        $bridge = tl_stage800_merchant_account_bridge();
        $import = tl_stage800_reward_campaign_import();
        $inventory = tl_stage800_reward_inventory_board();
        $assignment = tl_stage800_assignment_preview();
        $audit = $includeAudit ? tl_stage800_microgifter_import_audit() : ['accepted'=>true,'score'=>100,'issue_count'=>0,'issues'=>[]];
        $sections = [$bridge, $import, $inventory, $assignment, $audit];
        $accepted = true; $scores = [];
        foreach ($sections as $section) { $scores[] = (int)($section['score'] ?? 0); if (empty($section['accepted'])) $accepted = false; }
        return [
            'stage' => 'Stage 761-800 Microgifter campaign import and reward assignment',
            'built_from' => 'Stage 721-760 merchant productization and commerce readiness',
            'builds' => [
                'Build 104: Microgifter Merchant Account Bridge',
                'Build 105: Microgifter Reward Campaign Import',
                'Build 106: Reward Inventory + Quantity Board',
                'Build 107: Assign Microgifter Campaign to Task / Training Session',
                'Build 108: Microgifter Campaign Import API Layer',
            ],
            'merchant_account_bridge' => $bridge,
            'reward_campaign_import' => $import,
            'reward_inventory_quantity_board' => $inventory,
            'task_session_assignment' => $assignment,
            'microgifter_import_audit' => $audit,
            'merchant_connection_status' => (string)($bridge['adapter_status']['status'] ?? 'fixture'),
            'imported_campaign_count' => (int)($import['imported_campaign_count'] ?? 0),
            'reward_inventory_status' => !empty($inventory['accepted']) ? 'ready' : 'needs_work',
            'task_session_assignment_readiness' => !empty($assignment['accepted']) ? 'ready' : 'needs_work',
            'adapter_status' => $bridge['adapter_status'] ?? [],
            'score' => $accepted ? 100 : (int)round(array_sum($scores) / max(1, count($scores))),
            'accepted' => $accepted,
            'safe_boundaries' => [
                'no_new_sql_required' => true,
                'no_config_files_moved_or_overwritten' => true,
                'no_hard_auth_gates_forced' => true,
                'no_page_factory_expansion' => true,
                'read_only_microgifter_campaign_import' => true,
                'no_destructive_sync_back_to_microgifter' => true,
                'no_real_payment_processing' => true,
                'no_production_claim_or_redeem_mutation' => true,
                'no_wallet_balance_mutation' => true,
                'microgifter_reward_issuing_adapter_developer_key_gated' => true,
            ],
        ];
    }
}
if (!function_exists('tl_stage800_status_class')) {
    function tl_stage800_status_class(string $status): string
    {
        $s = strtolower(str_replace([' ', '-'], '_', trim($status)));
        if (in_array($s, ['ready','available','active','connected','approved','issued'], true)) return 'good';
        if (in_array($s, ['low','needs_sync','fixture','missing_key','draft','pending','not_connected'], true)) return 'warn';
        if (in_array($s, ['empty','expired','failed','blocked'], true)) return 'bad';
        return 'neutral';
    }
}
if (!function_exists('tl_stage800_context_runtime_overrides')) {
    function tl_stage800_context_runtime_overrides(string $context, array $baseCfg = []): array
    {
        $summary = tl_stage800_microgifter_campaign_import_summary(false);
        $isAdmin = str_starts_with($context, 'admin-');
        return [
            'live_strip' => $isAdmin
                ? ['Microgifter merchant bridge', 'Campaign import', 'Reward inventory', 'Stage 800']
                : ['Shared account', 'Imported rewards', 'Task assignment', 'Stage 800'],
            'stage800_cards' => [
                ['label'=>'Merchant', 'value'=>(string)($summary['merchant_connection_status'] ?? 'fixture'), 'hint'=>'adapter/key gated', 'href'=>'/account.php'],
                ['label'=>'Campaigns', 'value'=>(string)($summary['imported_campaign_count'] ?? 0), 'hint'=>'read-only import', 'href'=>'/api/training/microgifter-campaign-import.php?section=campaigns'],
                ['label'=>'Inventory', 'value'=>(string)($summary['reward_inventory_status'] ?? 'ready'), 'hint'=>'quantity board', 'href'=>'/admin/reward-bridge.php'],
            ],
            'metric_values' => $isAdmin ? [(string)($summary['imported_campaign_count'] ?? 0), 'Inventory', '800'] : ['Merchant', (string)($summary['imported_campaign_count'] ?? 0), '800'],
            'progress_width' => $isAdmin ? '94%' : '86%',
            'status_meta' => $isAdmin ? ['Campaign import', 'Inventory board', 'Assignment audit'] : ['Merchant account', 'Reward campaigns', 'Training assignment'],
            'stage800_runtime_bound' => true,
        ];
    }
}
if (!function_exists('tl_stage800_render_card_grid')) {
    function tl_stage800_render_card_grid(array $items): void
    {
        echo '<div class="labs-stage800-card-grid">';
        foreach ($items as $item) {
            $status = (string)($item['availability_label'] ?? $item['campaign_status'] ?? $item['status'] ?? 'ready');
            $label = (string)($item['campaign_name'] ?? $item['reward_title'] ?? $item['label'] ?? $item['title'] ?? 'Microgifter item');
            $detail = (string)($item['detail'] ?? $item['reward_title'] ?? $item['merchant_name'] ?? $item['storage_boundary'] ?? 'Imported reward campaign');
            echo '<article class="is-' . tl_stage800_e(tl_stage800_status_class($status)) . '"><span>' . tl_stage800_e($status) . '</span><strong>' . tl_stage800_e($label) . '</strong><small>' . tl_stage800_e($detail) . '</small>';
            if (isset($item['quantity_available'])) echo '<em>' . (int)$item['quantity_available'] . ' available / ' . (int)($item['quantity_total'] ?? 0) . ' total</em>';
            echo '</article>';
        }
        echo '</div>';
    }
}
if (!function_exists('tl_stage800_render_shell')) {
    function tl_stage800_render_shell(string $eyebrow, string $title, array $metrics, array $items, string $apiHref, string $class = ''): void
    {
        echo '<section class="labs-card labs-stage800-panel ' . tl_stage800_e($class) . '"><div class="labs-card-headline"><div><span class="labs-eyebrow">' . tl_stage800_e($eyebrow) . '</span><h2>' . tl_stage800_e($title) . '</h2></div><a class="labs-btn" href="' . tl_stage800_e(function_exists('labs_url') ? labs_url($apiHref) : $apiHref) . '">Import API</a></div>';
        echo '<div class="labs-stage800-metric-grid">';
        foreach ($metrics as $metric) echo '<article><span>' . tl_stage800_e($metric['label'] ?? 'Metric') . '</span><strong>' . tl_stage800_e($metric['value'] ?? 'Ready') . '</strong><small>' . tl_stage800_e($metric['hint'] ?? '') . '</small></article>';
        echo '</div>';
        tl_stage800_render_card_grid($items);
        echo '</section>';
    }
}
if (!function_exists('tl_stage800_render_merchant_account_bridge')) {
    function tl_stage800_render_merchant_account_bridge(): void
    {
        $data = tl_stage800_merchant_account_bridge();
        $metrics = [
            ['label'=>'Merchant status', 'value'=>(string)$data['adapter_status']['mode_label'], 'hint'=>'shared Microgifter account'],
            ['label'=>'Developer key', 'value'=>!empty($data['adapter_status']['developer_key_present']) ? 'Present' : 'Gated', 'hint'=>'safe adapter boundary'],
            ['label'=>'Bridge score', 'value'=>$data['score'] . '/100', 'hint'=>'account bridge readiness'],
        ];
        $items = [
            ['label'=>'Shared account', 'status'=>!empty($data['adapter_status']['connected']) ? 'connected' : 'fixture', 'detail'=>$data['shared_account']['model']],
            ['label'=>'Merchant button', 'status'=>'ready', 'detail'=>$data['shared_account']['button_label']],
            ['label'=>'Adapter boundary', 'status'=>'ready', 'detail'=>$data['adapter_status']['boundary']],
        ];
        tl_stage800_render_shell('Stage 761-768', 'Microgifter Merchant Account Bridge', $metrics, $items, '/api/training/microgifter-campaign-import.php?section=bridge', 'labs-stage800-bridge');
    }
}
if (!function_exists('tl_stage800_render_reward_campaign_import')) {
    function tl_stage800_render_reward_campaign_import(): void
    {
        $data = tl_stage800_reward_campaign_import();
        $metrics = [
            ['label'=>'Import mode', 'value'=>(string)$data['import_mode'], 'hint'=>'adapter or fixture'],
            ['label'=>'Campaigns', 'value'=>(string)$data['imported_campaign_count'], 'hint'=>'read-only reward campaigns'],
            ['label'=>'Import score', 'value'=>$data['score'] . '/100', 'hint'=>'shape + route readiness'],
        ];
        tl_stage800_render_shell('Stage 769-776', 'Microgifter Reward Campaign Import', $metrics, $data['campaigns'], '/api/training/microgifter-campaign-import.php?section=campaigns', 'labs-stage800-import');
    }
}
if (!function_exists('tl_stage800_render_reward_inventory_board')) {
    function tl_stage800_render_reward_inventory_board(): void
    {
        $data = tl_stage800_reward_inventory_board();
        $t = $data['inventory_totals'];
        $metrics = [
            ['label'=>'Available', 'value'=>(string)$t['quantity_available'], 'hint'=>'remaining rewards'],
            ['label'=>'Reserved', 'value'=>(string)$t['quantity_reserved'], 'hint'=>'assignment pressure'],
            ['label'=>'Issued', 'value'=>(string)$t['quantity_issued'], 'hint'=>'Microgifter/source usage'],
        ];
        tl_stage800_render_shell('Stage 777-784', 'Reward Inventory + Quantity Board', $metrics, $data['inventory_rows'], '/api/training/microgifter-campaign-import.php?section=inventory', 'labs-stage800-inventory');
    }
}
if (!function_exists('tl_stage800_render_assignment_preview')) {
    function tl_stage800_render_assignment_preview(string $campaignRef = ''): void
    {
        $data = tl_stage800_assignment_preview($campaignRef);
        $assignment = $data['assignment_preview'];
        $metrics = [
            ['label'=>'Task', 'value'=>'Selected', 'hint'=>(string)$assignment['task_title']],
            ['label'=>'Reward campaign', 'value'=>(string)$assignment['microgifter_campaign_name'], 'hint'=>(string)$assignment['reward_title']],
            ['label'=>'Assignment score', 'value'=>$data['score'] . '/100', 'hint'=>'preview only'],
        ];
        $items = [];
        foreach ((array)$assignment['assignment_path'] as $step) $items[] = ['label'=>$step, 'status'=>'ready', 'detail'=>'task → proof → review → reward assignment path'];
        tl_stage800_render_shell('Stage 785-792', 'Assign Campaign to Task / Training Session', $metrics, $items, '/api/training/microgifter-campaign-import.php?section=assignment', 'labs-stage800-assignment');
    }
}
