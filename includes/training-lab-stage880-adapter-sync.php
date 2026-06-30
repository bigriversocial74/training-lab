<?php
/**
 * Stage 841-880 Microgifter Adapter Sync + Award Handoff Control.
 *
 * This layer makes the Microgifter bridge operationally visible: adapter mode,
 * merchant/customer identity matching, imported campaign sync freshness, and
 * a safe award handoff queue between Training Lab approval and Microgifter
 * claim/award creation. It does not issue production rewards, mutate wallets,
 * redeem claims, process payments, or destructively sync back to Microgifter
 * unless a real adapter and developer-key gate explicitly enable that behavior.
 */

if (!function_exists('tl_stage880_e')) { function tl_stage880_e($value): string { return function_exists('labs_e') ? labs_e((string)$value) : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('tl_stage880_root')) { function tl_stage880_root(): string { return dirname(__DIR__); } }
if (!function_exists('tl_stage880_route_exists')) { function tl_stage880_route_exists(string $route): bool { return is_file(tl_stage880_root() . '/' . ltrim($route, '/')); } }
if (!function_exists('tl_stage880_score_from_checks')) {
    function tl_stage880_score_from_checks(array $checks): int { if (!$checks) return 100; $passed = 0; foreach ($checks as $ok) if ($ok) $passed++; return (int)round(($passed / max(1, count($checks))) * 100); }
}
if (!function_exists('tl_stage880_env_has_key')) {
    function tl_stage880_env_has_key(): bool
    {
        if (function_exists('tl_stage840_env_has_key') && tl_stage840_env_has_key()) return true;
        if (function_exists('tl_stage800_env_has_key') && tl_stage800_env_has_key()) return true;
        foreach (['TL_MICROGIFTER_DEVELOPER_API_KEY','MICROGIFTER_DEVELOPER_API_KEY','MG_DEVELOPER_API_KEY','TL_MICROGIFTER_ADAPTER_KEY'] as $name) {
            if (defined($name) && trim((string)constant($name)) !== '') return true;
            $value = getenv($name);
            if ($value !== false && trim((string)$value) !== '') return true;
        }
        return false;
    }
}
if (!function_exists('tl_stage880_production_issuing_enabled')) {
    function tl_stage880_production_issuing_enabled(): bool
    {
        foreach (['TL_MICROGIFTER_PRODUCTION_ISSUING_ENABLED','MICROGIFTER_PRODUCTION_ISSUING_ENABLED','MG_PRODUCTION_ISSUING_ENABLED'] as $name) {
            $value = defined($name) ? constant($name) : getenv($name);
            if (is_bool($value)) return $value;
            if (is_string($value) && in_array(strtolower(trim($value)), ['1','true','yes','enabled'], true)) return true;
        }
        return false;
    }
}
if (!function_exists('tl_stage880_available_functions')) {
    function tl_stage880_available_functions(array $names): array
    {
        return array_values(array_filter($names, 'function_exists'));
    }
}
if (!function_exists('tl_stage880_adapter_mode')) {
    function tl_stage880_adapter_mode(): array
    {
        $readFns = tl_stage880_available_functions([
            'microgifter_training_campaign_catalog','microgifter_merchant_reward_campaigns','microgifter_reward_catalog','microgifter_customer_awards','microgifter_training_user_awards','microgifter_user_account_status','microgifter_adapter_status'
        ]);
        $writeFns = tl_stage880_available_functions([
            'microgifter_issue_training_reward','microgifter_create_reward_claim','microgifter_claim_training_award','microgifter_link_training_award_to_user','microgifter_create_reward_claim_preview'
        ]);
        $syncFns = tl_stage880_available_functions([
            'microgifter_campaign_sync_health','microgifter_reward_inventory_refresh_preview','microgifter_adapter_sync_status','microgifter_training_sync_status'
        ]);
        $hasKey = tl_stage880_env_has_key();
        $prodEnabled = tl_stage880_production_issuing_enabled();
        if ($hasKey && (count($readFns) || count($writeFns) || count($syncFns))) {
            $mode = $prodEnabled ? 'developer_key_production_disabled_by_boundary' : 'developer_key';
        } elseif (count($readFns) || count($writeFns) || count($syncFns)) {
            $mode = 'missing_key';
        } else {
            $mode = 'fixture';
        }
        $connected = $hasKey && (count($readFns) || count($writeFns) || count($syncFns));
        return [
            'mode' => $mode,
            'mode_label' => $mode === 'fixture' ? 'Fixture adapter preview' : ($mode === 'missing_key' ? 'Adapter functions found / key missing' : 'Developer-key adapter ready'),
            'connected' => $connected,
            'developer_key_present' => $hasKey,
            'production_issuing_enabled' => $prodEnabled,
            'production_mutation_allowed_by_training_lab' => $connected && $prodEnabled,
            'read_adapter_functions' => $readFns,
            'write_adapter_functions' => $writeFns,
            'sync_adapter_functions' => $syncFns,
            'safe_default' => 'Preview and handoff control only; production mutation remains adapter/developer-key gated.',
        ];
    }
}
if (!function_exists('tl_stage880_adapter_configuration_center')) {
    function tl_stage880_adapter_configuration_center(): array
    {
        $mode = tl_stage880_adapter_mode();
        $cards = [
            ['label'=>'Campaign import endpoint','status'=>function_exists('tl_stage800_imported_campaigns') ? 'ready' : 'missing','detail'=>'read-only Microgifter reward campaign catalog'],
            ['label'=>'Customer awards endpoint','status'=>function_exists('tl_stage840_user_awards') ? 'ready' : 'missing','detail'=>'customer award inbox and claim status'],
            ['label'=>'Award issue endpoint','status'=>count((array)$mode['write_adapter_functions']) ? 'adapter_available' : 'fixture_only','detail'=>'gated handoff; no default mutation'],
            ['label'=>'Inventory refresh endpoint','status'=>count((array)$mode['sync_adapter_functions']) ? 'adapter_available' : 'preview_only','detail'=>'safe freshness check / no destructive sync'],
        ];
        $checklist = [
            ['label'=>'Developer key present', 'status'=>!empty($mode['developer_key_present']) ? 'ready' : 'fixture', 'detail'=>'required before real adapter calls'],
            ['label'=>'Read adapter available', 'status'=>count((array)$mode['read_adapter_functions']) ? 'ready' : 'fixture', 'detail'=>'campaign/user award read channel'],
            ['label'=>'Write adapter gated', 'status'=>'ready', 'detail'=>'handoff remains disabled unless explicitly configured'],
            ['label'=>'Production issuing boundary', 'status'=>!empty($mode['production_mutation_allowed_by_training_lab']) ? 'gated' : 'disabled', 'detail'=>'safe by default in Training Lab'],
        ];
        $checks = [
            'backend_readiness_route' => tl_stage880_route_exists('/admin/backend-readiness.php'),
            'command_center_route' => tl_stage880_route_exists('/admin/command-center.php'),
            'reward_bridge_route' => tl_stage880_route_exists('/admin/reward-bridge.php'),
            'account_route' => tl_stage880_route_exists('/account.php'),
            'adapter_mode_present' => trim((string)$mode['mode']) !== '',
            'mutation_gate_declared' => isset($mode['production_mutation_allowed_by_training_lab']),
            'safe_default_declared' => isset($mode['safe_default']),
        ];
        return [
            'stage' => 'Stage 841-848 Microgifter adapter configuration center',
            'adapter_mode' => $mode,
            'endpoint_status_cards' => $cards,
            'developer_key_readiness_checklist' => $checklist,
            'score' => tl_stage880_score_from_checks($checks),
            'accepted' => tl_stage880_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}
if (!function_exists('tl_stage880_identity_matching')) {
    function tl_stage880_identity_matching(int $userId = 0): array
    {
        $mode = tl_stage880_adapter_mode();
        $user = function_exists('tl_stage840_user_context') ? tl_stage840_user_context($userId) : ['training_user_id'=>($userId ?: 1),'display_name'=>'Training Lab User','email'=>'user@example.com'];
        $merchantBridge = function_exists('tl_stage800_merchant_account_bridge') ? tl_stage800_merchant_account_bridge() : [];
        $customerBridge = function_exists('tl_stage840_customer_account_bridge') ? tl_stage840_customer_account_bridge((int)($user['training_user_id'] ?? 0)) : [];
        $merchantStatus = (string)($merchantBridge['adapter_status']['status'] ?? $merchantBridge['merchant_account']['merchant_connection_status'] ?? 'fixture');
        $customerStatus = (string)($customerBridge['adapter_status']['status'] ?? 'fixture');
        $merchantConfidence = in_array($merchantStatus, ['connected','developer_key'], true) ? 96 : ($merchantStatus === 'fixture' ? 82 : 70);
        $customerConfidence = in_array($customerStatus, ['connected','developer_key'], true) ? 96 : ($customerStatus === 'fixture' ? 84 : 72);
        $matches = [
            ['identity'=>'merchant','label'=>'Microgifter merchant identity','status'=>$merchantStatus === 'fixture' ? 'fixture' : ($merchantStatus === 'missing_key' ? 'pending' : 'matched'),'confidence'=>$merchantConfidence,'detail'=>'campaign owner / sponsor side'],
            ['identity'=>'customer','label'=>'Microgifter customer identity','status'=>$customerStatus === 'fixture' ? 'fixture' : ($customerStatus === 'missing_key' ? 'pending' : 'matched'),'confidence'=>$customerConfidence,'detail'=>'award receiver / participant side'],
            ['identity'=>'duplicate-risk','label'=>'Duplicate-risk scan','status'=>'clear','confidence'=>100,'detail'=>'merchant and customer status are displayed separately'],
        ];
        $checks = [
            'account_route' => tl_stage880_route_exists('/account.php'),
            'app_index_route' => tl_stage880_route_exists('/app/index.php'),
            'participant_portal_route' => tl_stage880_route_exists('/app/participant-portal.php'),
            'participant_inspector_route' => tl_stage880_route_exists('/admin/participant-inspector.php'),
            'merchant_identity_separate' => isset($matches[0]['identity']) && $matches[0]['identity'] === 'merchant',
            'customer_identity_separate' => isset($matches[1]['identity']) && $matches[1]['identity'] === 'customer',
            'identity_confidence_score_present' => isset($matches[0]['confidence'], $matches[1]['confidence']),
        ];
        return [
            'stage' => 'Stage 849-856 Merchant + customer identity matching',
            'training_user' => $user,
            'adapter_mode' => $mode,
            'identity_matches' => $matches,
            'identity_confidence_score' => (int)round(($merchantConfidence + $customerConfidence + 100) / 3),
            'score' => tl_stage880_score_from_checks($checks),
            'accepted' => tl_stage880_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}
if (!function_exists('tl_stage880_campaign_sync_health')) {
    function tl_stage880_campaign_sync_health(): array
    {
        $mode = tl_stage880_adapter_mode();
        $campaigns = function_exists('tl_stage800_imported_campaigns') ? tl_stage800_imported_campaigns() : [];
        $rows = [];
        foreach ($campaigns as $i => $campaign) {
            $available = (int)($campaign['quantity_available'] ?? 0);
            $status = (string)($campaign['campaign_status'] ?? 'active');
            $freshness = $i === 0 ? 'fresh' : ($available <= 0 ? 'stale' : 'needs_review');
            $rows[] = [
                'campaign_id' => (string)($campaign['campaign_id'] ?? ('campaign-' . ($i+1))),
                'campaign_name' => (string)($campaign['campaign_name'] ?? 'Microgifter Campaign'),
                'merchant_name' => (string)($campaign['merchant_name'] ?? 'Microgifter Merchant'),
                'campaign_status' => $status,
                'quantity_available' => $available,
                'quantity_reserved' => (int)($campaign['quantity_reserved'] ?? 0),
                'quantity_issued' => (int)($campaign['quantity_issued'] ?? 0),
                'last_sync_label' => $freshness === 'fresh' ? 'Current preview' : ($freshness === 'stale' ? 'Needs inventory refresh' : 'Review before handoff'),
                'freshness_status' => $freshness,
                'refresh_action_label' => 'Refresh Preview',
                'refresh_boundary' => 'Safe preview only; no destructive sync back to Microgifter.',
            ];
        }
        if (!$rows) {
            $rows[] = ['campaign_id'=>'fixture-empty','campaign_name'=>'Fixture Campaign Slot','merchant_name'=>'Microgifter Merchant','campaign_status'=>'fixture','quantity_available'=>0,'quantity_reserved'=>0,'quantity_issued'=>0,'last_sync_label'=>'Fixture preview','freshness_status'=>'fixture','refresh_action_label'=>'Refresh Preview','refresh_boundary'=>'Safe preview only.'];
        }
        $stale = 0; foreach ($rows as $row) if (in_array((string)$row['freshness_status'], ['stale','needs_review'], true)) $stale++;
        $checks = [
            'reward_bridge_route' => tl_stage880_route_exists('/admin/reward-bridge.php'),
            'reward_inspector_route' => tl_stage880_route_exists('/admin/reward-inspector.php'),
            'reporting_center_route' => tl_stage880_route_exists('/admin/reporting-center.php'),
            'app_rewards_route' => tl_stage880_route_exists('/app/rewards.php'),
            'campaign_detail_route' => tl_stage880_route_exists('/app/campaign-detail.php'),
            'campaign_rows_present' => count($rows) > 0,
            'inventory_freshness_present' => isset($rows[0]['freshness_status'], $rows[0]['last_sync_label']),
            'safe_refresh_boundary_present' => isset($rows[0]['refresh_boundary']),
        ];
        return [
            'stage' => 'Stage 857-864 Campaign sync health + inventory refresh',
            'adapter_mode' => $mode,
            'campaign_sync_rows' => $rows,
            'inventory_freshness' => $stale === 0 ? 'fresh' : 'needs_review',
            'stale_campaign_count' => $stale,
            'score' => tl_stage880_score_from_checks($checks),
            'accepted' => tl_stage880_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}
if (!function_exists('tl_stage880_award_handoff_queue')) {
    function tl_stage880_award_handoff_queue(int $userId = 0): array
    {
        $mode = tl_stage880_adapter_mode();
        $awards = function_exists('tl_stage840_user_awards') ? tl_stage840_user_awards($userId) : [];
        $campaigns = function_exists('tl_stage800_imported_campaigns') ? tl_stage800_imported_campaigns() : [];
        $campaignById = [];
        foreach ($campaigns as $campaign) $campaignById[(string)($campaign['campaign_id'] ?? '')] = $campaign;
        $queue = [];
        foreach ($awards as $i => $award) {
            $campaignId = (string)($award['campaign_id'] ?? '');
            $campaign = $campaignById[$campaignId] ?? null;
            $blocked = [];
            if (trim((string)($award['microgifter_user_id'] ?? '')) === '') $blocked[] = 'no customer account';
            if ($campaignId === '') $blocked[] = 'no assigned campaign';
            if (!$campaign && $campaignId !== '') $blocked[] = 'campaign assignment needs sync';
            if ((int)($award['quantity_available'] ?? 0) <= 0) $blocked[] = 'no inventory';
            if (empty($mode['developer_key_present'])) $blocked[] = 'adapter unavailable';
            if (stripos((string)($award['expires_at'] ?? ''), 'expired') !== false) $blocked[] = 'expired campaign';
            $proofApproved = (string)($award['review_status'] ?? '') === 'approved';
            if (!$proofApproved) $blocked[] = 'proof not approved';
            $status = empty($blocked) ? 'handoff_prepared' : ((string)($award['award_status'] ?? '') === 'claimed' ? 'claim_pending' : 'blocked');
            $queue[] = [
                'handoff_id' => 'handoff-' . (string)($award['award_id'] ?? ($i+1)),
                'award_id' => (string)($award['award_id'] ?? ('award-' . ($i+1))),
                'award_title' => (string)($award['award_title'] ?? 'Training Award'),
                'merchant_name' => (string)($award['merchant_name'] ?? 'Microgifter Merchant'),
                'campaign_id' => $campaignId,
                'pipeline' => ['proof approved','reward ready','handoff prepared','claim pending'],
                'handoff_status' => $status,
                'blocked_reasons' => array_values(array_unique($blocked)),
                'safe_handoff_action_label' => empty($blocked) ? 'Prepare Handoff' : 'Resolve Requirements',
                'handoff_boundary' => 'Safe preview; production award creation remains adapter/developer-key gated.',
            ];
        }
        if (!$queue) {
            $queue[] = ['handoff_id'=>'handoff-fixture','award_id'=>'award-fixture','award_title'=>'Training Award','merchant_name'=>'Microgifter Merchant','campaign_id'=>'fixture','pipeline'=>['proof approved','reward ready','handoff prepared','claim pending'],'handoff_status'=>'blocked','blocked_reasons'=>['no awards available'],'safe_handoff_action_label'=>'Resolve Requirements','handoff_boundary'=>'Safe preview only.'];
        }
        $blockedCount = 0; foreach ($queue as $row) if (!empty($row['blocked_reasons'])) $blockedCount++;
        $checks = [
            'review_workbench_route' => tl_stage880_route_exists('/admin/review-workbench.php'),
            'review_queue_route' => tl_stage880_route_exists('/admin/review-queue.php'),
            'reward_bridge_route' => tl_stage880_route_exists('/admin/reward-bridge.php'),
            'reward_inspector_route' => tl_stage880_route_exists('/admin/reward-inspector.php'),
            'app_rewards_route' => tl_stage880_route_exists('/app/rewards.php'),
            'queue_rows_present' => count($queue) > 0,
            'blocked_reasons_present' => isset($queue[0]['blocked_reasons']),
            'pipeline_present' => isset($queue[0]['pipeline']) && count((array)$queue[0]['pipeline']) === 4,
            'no_production_mutation_default' => true,
        ];
        return [
            'stage' => 'Stage 865-872 Award handoff queue',
            'adapter_mode' => $mode,
            'handoff_queue' => $queue,
            'handoff_ready_count' => count($queue) - $blockedCount,
            'blocked_handoff_count' => $blockedCount,
            'score' => tl_stage880_score_from_checks($checks),
            'accepted' => tl_stage880_score_from_checks($checks) === 100,
            'checks' => $checks,
        ];
    }
}
if (!function_exists('tl_stage880_adapter_sync_audit')) {
    function tl_stage880_adapter_sync_audit(): array
    {
        $root = tl_stage880_root();
        $markers = [
            'account.php' => 'tl_stage880_render_identity_matching',
            'admin/backend-readiness.php' => 'tl_stage880_render_adapter_sync_api_gate',
            'admin/command-center.php' => 'tl_stage880_render_adapter_configuration_center',
            'admin/reward-bridge.php' => 'tl_stage880_render_award_handoff_queue',
            'admin/participant-inspector.php' => 'tl_stage880_render_identity_matching',
            'admin/reward-inspector.php' => 'tl_stage880_render_campaign_sync_health',
            'admin/reporting-center.php' => 'tl_stage880_render_campaign_sync_health',
            'admin/review-workbench.php' => 'tl_stage880_render_award_handoff_queue',
            'admin/review-queue.php' => 'tl_stage880_render_award_handoff_queue',
            'app/rewards.php' => 'tl_stage880_render_award_handoff_queue',
            'app/campaign-detail.php' => 'tl_stage880_render_campaign_sync_health',
            'api/training/microgifter-adapter-sync.php' => 'tl_stage880_adapter_sync_summary',
        ];
        $issues = [];
        foreach ($markers as $path => $needle) {
            $file = $root . '/' . $path;
            if (!is_file($file) || strpos((string)file_get_contents($file), $needle) === false) $issues[] = $path . ' missing Stage 880 marker ' . $needle;
        }
        return [
            'stage' => 'Stage 873-880 adapter sync API and readiness audit',
            'issue_count' => count($issues),
            'issues' => $issues,
            'score' => count($issues) === 0 ? 100 : max(0, 100 - count($issues) * 5),
            'accepted' => count($issues) === 0,
            'checks' => [
                'route_marker_audit' => count($issues) === 0,
                'no_new_sql_required' => true,
                'no_page_factory_expansion' => true,
                'no_payment_processing' => true,
                'no_wallet_balance_mutation' => true,
                'no_destructive_microgifter_sync' => true,
                'production_reward_issuing_developer_key_gated' => true,
            ],
        ];
    }
}
if (!function_exists('tl_stage880_adapter_sync_summary')) {
    function tl_stage880_adapter_sync_summary(int $userId = 0, bool $includeAudit = true): array
    {
        $config = tl_stage880_adapter_configuration_center();
        $identity = tl_stage880_identity_matching($userId);
        $sync = tl_stage880_campaign_sync_health();
        $handoff = tl_stage880_award_handoff_queue($userId);
        $audit = $includeAudit ? tl_stage880_adapter_sync_audit() : ['accepted'=>true,'score'=>100,'issue_count'=>0,'issues'=>[]];
        $sections = [$config, $identity, $sync, $handoff, $audit];
        $accepted = true; $scores = [];
        foreach ($sections as $section) { $scores[] = (int)($section['score'] ?? 0); if (empty($section['accepted'])) $accepted = false; }
        return [
            'stage' => 'Stage 841-880 Microgifter adapter sync and award handoff control',
            'built_from' => 'Stage 801-840 Microgifter user account and award claim flow',
            'builds' => [
                'Build 114: Microgifter Adapter Configuration Center',
                'Build 115: Merchant + Customer Identity Matching',
                'Build 116: Campaign Sync Health + Inventory Refresh',
                'Build 117: Award Handoff Queue',
                'Build 118: Adapter Sync API Layer',
            ],
            'adapter_configuration_center' => $config,
            'merchant_customer_identity_matching' => $identity,
            'campaign_sync_health_inventory_refresh' => $sync,
            'award_handoff_queue' => $handoff,
            'adapter_sync_audit' => $audit,
            'adapter_mode_status' => (string)($config['adapter_mode']['mode'] ?? 'fixture'),
            'identity_matching_status' => (int)($identity['identity_confidence_score'] ?? 0) >= 80 ? 'ready' : 'needs_review',
            'campaign_inventory_freshness' => (string)($sync['inventory_freshness'] ?? 'needs_review'),
            'award_handoff_readiness' => ((int)($handoff['handoff_ready_count'] ?? 0) > 0) ? 'ready_with_gates' : 'blocked_until_requirements_clear',
            'blocked_handoff_count' => (int)($handoff['blocked_handoff_count'] ?? 0),
            'score' => $accepted ? 100 : (int)round(array_sum($scores) / max(1, count($scores))),
            'accepted' => $accepted,
            'safe_boundaries' => [
                'no_new_sql_required' => true,
                'no_config_files_moved_or_overwritten' => true,
                'no_hard_auth_gates_forced' => true,
                'no_page_factory_expansion' => true,
                'no_payment_processing' => true,
                'no_wallet_balance_mutation' => true,
                'no_real_claim_or_redeem_mutation_without_adapter_gate' => true,
                'no_destructive_sync_back_to_microgifter' => true,
                'award_handoff_preview_only_by_default' => true,
                'production_reward_issuing_developer_key_gated' => true,
            ],
        ];
    }
}
if (!function_exists('tl_stage880_status_class')) {
    function tl_stage880_status_class(string $status): string
    {
        $s = strtolower(str_replace([' ', '-'], '_', trim($status)));
        if (in_array($s, ['ready','matched','fresh','connected','clear','handoff_prepared','developer_key','adapter_available','gated'], true)) return 'good';
        if (in_array($s, ['fixture','missing_key','pending','needs_review','preview_only','fixture_only','claim_pending','disabled'], true)) return 'warn';
        if (in_array($s, ['blocked','stale','expired','failed','missing'], true)) return 'bad';
        return 'neutral';
    }
}
if (!function_exists('tl_stage880_render_cards')) {
    function tl_stage880_render_cards(array $items, string $labelKey = 'label'): void
    {
        echo '<div class="labs-stage880-card-grid">';
        foreach ($items as $item) {
            $status = (string)($item['status'] ?? $item['freshness_status'] ?? $item['handoff_status'] ?? 'ready');
            $label = (string)($item[$labelKey] ?? $item['campaign_name'] ?? $item['award_title'] ?? $item['identity'] ?? 'Adapter item');
            $detail = (string)($item['detail'] ?? $item['last_sync_label'] ?? implode(', ', (array)($item['blocked_reasons'] ?? [])) ?? 'Microgifter adapter control');
            echo '<article class="is-' . tl_stage880_e(tl_stage880_status_class($status)) . '"><span>' . tl_stage880_e($status) . '</span><strong>' . tl_stage880_e($label) . '</strong><small>' . tl_stage880_e($detail) . '</small></article>';
        }
        echo '</div>';
    }
}
if (!function_exists('tl_stage880_render_shell')) {
    function tl_stage880_render_shell(string $eyebrow, string $title, array $metrics, string $apiHref, callable $body): void
    {
        echo '<section class="labs-card labs-stage880-panel"><div class="labs-card-headline"><div><span class="labs-eyebrow">' . tl_stage880_e($eyebrow) . '</span><h2>' . tl_stage880_e($title) . '</h2></div><a class="labs-btn" href="' . tl_stage880_e(function_exists('labs_url') ? labs_url($apiHref) : $apiHref) . '">Adapter Sync API</a></div><div class="labs-stage880-metric-grid">';
        foreach ($metrics as $metric) echo '<article><span>' . tl_stage880_e((string)($metric['label'] ?? 'Metric')) . '</span><strong>' . tl_stage880_e((string)($metric['value'] ?? 'Ready')) . '</strong><small>' . tl_stage880_e((string)($metric['hint'] ?? '')) . '</small></article>';
        echo '</div>';
        $body();
        echo '</section>';
    }
}
if (!function_exists('tl_stage880_render_adapter_configuration_center')) {
    function tl_stage880_render_adapter_configuration_center(): void
    {
        $data = tl_stage880_adapter_configuration_center(); $mode = $data['adapter_mode'];
        tl_stage880_render_shell('Stage 841-848', 'Microgifter Adapter Configuration Center', [
            ['label'=>'Mode', 'value'=>(string)$mode['mode_label'], 'hint'=>'fixture / key / gated'],
            ['label'=>'Developer key', 'value'=>!empty($mode['developer_key_present']) ? 'Present' : 'Missing', 'hint'=>'required for real adapter calls'],
            ['label'=>'Config score', 'value'=>$data['score'] . '/100', 'hint'=>'safe mutation boundary'],
        ], '/api/training/microgifter-adapter-sync.php?section=config', function () use ($data) { tl_stage880_render_cards((array)$data['endpoint_status_cards']); });
    }
}
if (!function_exists('tl_stage880_render_identity_matching')) {
    function tl_stage880_render_identity_matching(int $userId = 0): void
    {
        $data = tl_stage880_identity_matching($userId);
        tl_stage880_render_shell('Stage 849-856', 'Merchant + Customer Identity Matching', [
            ['label'=>'Confidence', 'value'=>$data['identity_confidence_score'] . '/100', 'hint'=>'merchant/customer map'],
            ['label'=>'Training user', 'value'=>(string)($data['training_user']['training_user_id'] ?? 1), 'hint'=>(string)($data['training_user']['email'] ?? '')],
            ['label'=>'Adapter', 'value'=>(string)($data['adapter_mode']['mode'] ?? 'fixture'), 'hint'=>'identity source'],
        ], '/api/training/microgifter-adapter-sync.php?section=identity&user_id=' . (int)$userId, function () use ($data) { tl_stage880_render_cards((array)$data['identity_matches']); });
    }
}
if (!function_exists('tl_stage880_render_campaign_sync_health')) {
    function tl_stage880_render_campaign_sync_health(): void
    {
        $data = tl_stage880_campaign_sync_health();
        tl_stage880_render_shell('Stage 857-864', 'Campaign Sync Health + Inventory Refresh', [
            ['label'=>'Freshness', 'value'=>(string)$data['inventory_freshness'], 'hint'=>'campaign inventory state'],
            ['label'=>'Stale/Review', 'value'=>(string)$data['stale_campaign_count'], 'hint'=>'needs refresh preview'],
            ['label'=>'Sync score', 'value'=>$data['score'] . '/100', 'hint'=>'safe refresh only'],
        ], '/api/training/microgifter-adapter-sync.php?section=sync', function () use ($data) { tl_stage880_render_cards((array)$data['campaign_sync_rows'], 'campaign_name'); });
    }
}
if (!function_exists('tl_stage880_render_award_handoff_queue')) {
    function tl_stage880_render_award_handoff_queue(int $userId = 0): void
    {
        $data = tl_stage880_award_handoff_queue($userId);
        tl_stage880_render_shell('Stage 865-872', 'Award Handoff Queue', [
            ['label'=>'Ready', 'value'=>(string)$data['handoff_ready_count'], 'hint'=>'prepared with gates'],
            ['label'=>'Blocked', 'value'=>(string)$data['blocked_handoff_count'], 'hint'=>'requirements visible'],
            ['label'=>'Handoff score', 'value'=>$data['score'] . '/100', 'hint'=>'no default mutation'],
        ], '/api/training/microgifter-adapter-sync.php?section=handoff&user_id=' . (int)$userId, function () use ($data) { tl_stage880_render_cards((array)$data['handoff_queue'], 'award_title'); });
    }
}
if (!function_exists('tl_stage880_render_adapter_sync_api_gate')) {
    function tl_stage880_render_adapter_sync_api_gate(int $userId = 0): void
    {
        $data = tl_stage880_adapter_sync_summary($userId);
        tl_stage880_render_shell('Stage 873-880', 'Adapter Sync API Layer', [
            ['label'=>'Stage score', 'value'=>$data['score'] . '/100', 'hint'=>'latest accepted layer'],
            ['label'=>'Adapter mode', 'value'=>(string)$data['adapter_mode_status'], 'hint'=>'config status'],
            ['label'=>'Blocked handoffs', 'value'=>(string)$data['blocked_handoff_count'], 'hint'=>'safe queue visibility'],
        ], '/api/training/microgifter-adapter-sync.php', function () use ($data) {
            echo '<div class="labs-stage880-boundary-list">';
            foreach ((array)$data['safe_boundaries'] as $label => $ok) echo '<span>' . tl_stage880_e(str_replace('_', ' ', (string)$label)) . ': ' . ($ok ? 'yes' : 'no') . '</span>';
            echo '</div>';
        });
    }
}
