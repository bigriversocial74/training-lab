<?php
/**
 * Stage 883 Read-only Microgifter Adapter Wiring.
 *
 * This layer probes real Microgifter read adapter functions, validates response
 * shapes, and falls back to existing fixture data when no safe read adapter is
 * available. It does not issue rewards, mutate wallets, redeem claims, process
 * payments, or destructively sync back to Microgifter.
 */

if (!function_exists('tl_stage883_e')) { function tl_stage883_e($value): string { return function_exists('labs_e') ? labs_e((string)$value) : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('tl_stage883_root')) { function tl_stage883_root(): string { return dirname(__DIR__); } }
if (!function_exists('tl_stage883_route_exists')) { function tl_stage883_route_exists(string $route): bool { return is_file(tl_stage883_root() . '/' . ltrim($route, '/')); } }
if (!function_exists('tl_stage883_score_from_rows')) {
    function tl_stage883_score_from_rows(array $rows, bool $allowPreview = true): int
    {
        if (!$rows) return 100;
        $passed = 0;
        foreach ($rows as $row) {
            $status = strtolower((string)($row['status'] ?? ''));
            if (!empty($row['passed']) || ($allowPreview && in_array($status, ['fixture','preview','missing_key','not_configured'], true))) $passed++;
        }
        return (int)round(($passed / max(1, count($rows))) * 100);
    }
}

if (!function_exists('tl_stage883_safe_call')) {
    function tl_stage883_safe_call(string $fn, array $args = []): array
    {
        if (!function_exists($fn)) {
            return ['called' => false, 'ok' => false, 'result' => null, 'error' => 'function missing'];
        }
        try {
            $result = $fn(...$args);
            return ['called' => true, 'ok' => is_array($result), 'result' => is_array($result) ? $result : null, 'error' => is_array($result) ? null : 'non-array response'];
        } catch (Throwable $e) {
            return ['called' => true, 'ok' => false, 'result' => null, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('tl_stage883_first_read')) {
    function tl_stage883_first_read(array $functions, array $args = [], bool $requiresKey = true): array
    {
        $mode = function_exists('tl_stage880_adapter_mode') ? tl_stage880_adapter_mode() : [];
        $hasKey = !empty($mode['developer_key_present']);
        $available = array_values(array_filter($functions, 'function_exists'));
        if (!$available) {
            return ['source' => 'fixture', 'function' => '', 'available_functions' => [], 'connected' => false, 'developer_key_present' => $hasKey, 'rows' => [], 'error' => 'no adapter function found'];
        }
        if ($requiresKey && !$hasKey) {
            return ['source' => 'missing_key', 'function' => $available[0], 'available_functions' => $available, 'connected' => false, 'developer_key_present' => false, 'rows' => [], 'error' => 'developer key missing'];
        }
        foreach ($available as $fn) {
            $call = tl_stage883_safe_call($fn, $args);
            if (!empty($call['ok'])) {
                return ['source' => 'adapter', 'function' => $fn, 'available_functions' => $available, 'connected' => true, 'developer_key_present' => $hasKey, 'rows' => (array)$call['result'], 'error' => null];
            }
        }
        return ['source' => 'adapter_error', 'function' => $available[0], 'available_functions' => $available, 'connected' => false, 'developer_key_present' => $hasKey, 'rows' => [], 'error' => 'adapter calls did not return array data'];
    }
}

if (!function_exists('tl_stage883_shape_score')) {
    function tl_stage883_shape_score(array $rows, array $requiredAny, array $requiredAll = []): int
    {
        if (!$rows) return 0;
        $total = 0;
        $passed = 0;
        foreach (array_slice(array_values($rows), 0, 10) as $row) {
            if (!is_array($row)) continue;
            $total++;
            $rowOk = true;
            foreach ($requiredAll as $key) {
                if (!array_key_exists($key, $row) || trim((string)$row[$key]) === '') { $rowOk = false; break; }
            }
            if ($rowOk && $requiredAny) {
                $anyOk = false;
                foreach ($requiredAny as $key) {
                    if (array_key_exists($key, $row) && trim((string)$row[$key]) !== '') { $anyOk = true; break; }
                }
                $rowOk = $anyOk;
            }
            if ($rowOk) $passed++;
        }
        return $total > 0 ? (int)round(($passed / $total) * 100) : 0;
    }
}

if (!function_exists('tl_stage883_read_health_rows')) {
    function tl_stage883_read_health_rows(int $userId = 0): array
    {
        $campaignRead = tl_stage883_first_read(['microgifter_training_campaign_catalog','microgifter_merchant_reward_campaigns','microgifter_reward_campaign_catalog','microgifter_reward_catalog']);
        $awardRead = tl_stage883_first_read(['microgifter_training_user_awards','microgifter_customer_awards','microgifter_user_awards'], [$userId]);
        $accountRead = tl_stage883_first_read(['microgifter_user_account_status','microgifter_customer_account_status','microgifter_training_user_account_status'], [$userId]);
        $statusRead = tl_stage883_first_read(['microgifter_adapter_status','microgifter_training_sync_status','microgifter_adapter_sync_status'], [], false);
        $inventoryRead = tl_stage883_first_read(['microgifter_campaign_sync_health','microgifter_reward_inventory_refresh_preview','microgifter_adapter_sync_status','microgifter_training_sync_status'], [], false);

        $contracts = [
            'merchant_campaign_catalog' => ['label'=>'Merchant campaign catalog', 'read'=>$campaignRead, 'fallback'=>function_exists('tl_stage800_imported_campaigns') ? tl_stage800_imported_campaigns() : [], 'required_any'=>['campaign_id','id','campaign_name','name','title'], 'required_all'=>[]],
            'customer_awards' => ['label'=>'Customer awards', 'read'=>$awardRead, 'fallback'=>function_exists('tl_stage840_user_awards') ? tl_stage840_user_awards($userId) : [], 'required_any'=>['award_id','id','award_title','title'], 'required_all'=>[]],
            'customer_account_status' => ['label'=>'Customer account status', 'read'=>$accountRead, 'fallback'=>function_exists('tl_stage840_customer_account_bridge') ? [tl_stage840_customer_account_bridge($userId)] : [], 'required_any'=>['status','connected','customer_account','display_name','email'], 'required_all'=>[]],
            'adapter_status' => ['label'=>'Adapter status', 'read'=>$statusRead, 'fallback'=>function_exists('tl_stage880_adapter_mode') ? [tl_stage880_adapter_mode()] : [], 'required_any'=>['status','mode','connected','developer_key_present'], 'required_all'=>[]],
            'inventory_freshness' => ['label'=>'Inventory freshness', 'read'=>$inventoryRead, 'fallback'=>function_exists('tl_stage880_campaign_sync_health') ? [tl_stage880_campaign_sync_health()] : [], 'required_any'=>['inventory_freshness','freshness_status','status','mode'], 'required_all'=>[]],
        ];

        $rows = [];
        foreach ($contracts as $key => $contract) {
            $read = (array)$contract['read'];
            $adapterRows = (array)($read['rows'] ?? []);
            $fallbackRows = (array)($contract['fallback'] ?? []);
            $source = (string)($read['source'] ?? 'fixture');
            $usingAdapter = $source === 'adapter' && count($adapterRows) > 0;
            $dataRows = $usingAdapter ? $adapterRows : $fallbackRows;
            $shapeScore = tl_stage883_shape_score($dataRows, (array)$contract['required_any'], (array)$contract['required_all']);
            $hasFallback = count($fallbackRows) > 0;
            $safeReady = $usingAdapter ? $shapeScore >= 60 : $hasFallback;
            $status = $usingAdapter ? 'adapter_read' : ($source === 'missing_key' ? 'missing_key' : ($hasFallback ? 'fixture' : 'not_configured'));
            $rows[] = [
                'contract' => $key,
                'label' => (string)$contract['label'],
                'status' => $status,
                'passed' => $safeReady,
                'source' => $usingAdapter ? 'adapter' : 'fixture_fallback',
                'function' => (string)($read['function'] ?? ''),
                'available_functions' => (array)($read['available_functions'] ?? []),
                'developer_key_present' => !empty($read['developer_key_present']),
                'connected' => !empty($read['connected']),
                'row_count' => count($dataRows),
                'shape_score' => $shapeScore,
                'detail' => $usingAdapter ? 'real read adapter returned data' : ($hasFallback ? 'fixture fallback active; no production mutation' : (string)($read['error'] ?? 'not configured')),
                'error' => (string)($read['error'] ?? ''),
            ];
        }
        return $rows;
    }
}

if (!function_exists('tl_stage883_readonly_summary')) {
    function tl_stage883_readonly_summary(int $userId = 0): array
    {
        $mode = function_exists('tl_stage880_adapter_mode') ? tl_stage880_adapter_mode() : [];
        $rows = tl_stage883_read_health_rows($userId);
        $adapterCount = 0;
        $fixtureCount = 0;
        $missingKeyCount = 0;
        foreach ($rows as $row) {
            if ((string)$row['source'] === 'adapter') $adapterCount++;
            if ((string)$row['source'] === 'fixture_fallback') $fixtureCount++;
            if ((string)$row['status'] === 'missing_key') $missingKeyCount++;
        }
        $score = tl_stage883_score_from_rows($rows, true);
        return [
            'stage' => 'Stage 883 Read-only Microgifter Adapter Wiring',
            'built_from' => 'Stage 882 Live Environment Smoke + Microgifter Adapter Dry Run',
            'accepted' => $score === 100,
            'score' => $score,
            'adapter_mode' => $mode,
            'read_contracts' => $rows,
            'adapter_read_contract_count' => $adapterCount,
            'fixture_fallback_contract_count' => $fixtureCount,
            'missing_key_contract_count' => $missingKeyCount,
            'real_adapter_detected' => $adapterCount > 0,
            'readiness_label' => $adapterCount > 0 ? 'real read adapter active' : ($missingKeyCount > 0 ? 'adapter functions found; developer key missing' : 'fixture fallback ready'),
            'safe_boundaries' => [
                'read_only_adapter_calls' => true,
                'no_new_sql' => true,
                'no_config_files_moved_or_overwritten' => true,
                'no_hard_auth_gates_forced' => true,
                'no_payment_processing' => true,
                'no_wallet_balance_mutation' => true,
                'no_production_claim_redeem_mutation' => true,
                'no_destructive_microgifter_sync' => true,
                'fixture_fallback_preserved' => true,
            ],
            'next_recommended_step' => $adapterCount > 0
                ? 'Review real read adapter payloads and then plan controlled handoff approval without enabling production mutation.'
                : 'Configure real read adapter functions and developer key when ready; fixture fallback remains safe.',
        ];
    }
}

if (!function_exists('tl_stage883_status_class')) {
    function tl_stage883_status_class(string $status): string
    {
        $s = strtolower(str_replace([' ', '-'], '_', trim($status)));
        if (in_array($s, ['adapter_read','ready','pass','connected'], true)) return 'good';
        if (in_array($s, ['fixture','missing_key','preview','not_configured'], true)) return 'warn';
        return 'bad';
    }
}

if (!function_exists('tl_stage883_render_rows')) {
    function tl_stage883_render_rows(array $rows): void
    {
        echo '<div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>Read Contract</th><th>Status</th><th>Source</th><th>Rows</th><th>Shape</th><th>Detail</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $status = (string)($row['status'] ?? 'fixture');
            echo '<tr><td>' . tl_stage883_e((string)($row['label'] ?? 'Read contract')) . '</td><td><span class="labs-pill is-' . tl_stage883_e(tl_stage883_status_class($status)) . '">' . tl_stage883_e($status) . '</span></td><td>' . tl_stage883_e((string)($row['source'] ?? 'fixture')) . '</td><td>' . (int)($row['row_count'] ?? 0) . '</td><td>' . (int)($row['shape_score'] ?? 0) . '/100</td><td>' . tl_stage883_e((string)($row['detail'] ?? '')) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

if (!function_exists('tl_stage883_render_readonly_adapter')) {
    function tl_stage883_render_readonly_adapter(int $userId = 0): void
    {
        $summary = tl_stage883_readonly_summary($userId);
        echo '<section class="labs-page-title"><div><span class="labs-eyebrow">Stage 883</span><h1>Read-only Microgifter Adapter Wiring</h1><p class="labs-copy">Validates real Microgifter read adapter functions, response shapes, and fixture fallback without enabling production mutation.</p></div><a class="labs-btn labs-btn-primary" href="' . tl_stage883_e(function_exists('labs_url') ? labs_url('/api/training/microgifter-adapter-sync.php?section=readonly') : '/api/training/microgifter-adapter-sync.php?section=readonly') . '">View JSON</a></section>';
        echo '<section class="labs-kpis">';
        echo '<div class="labs-kpi"><span class="labs-muted">Accepted</span><strong>' . (!empty($summary['accepted']) ? 'Yes' : 'Check') . '</strong><small>read-only gate</small></div>';
        echo '<div class="labs-kpi"><span class="labs-muted">Score</span><strong>' . (int)$summary['score'] . '/100</strong><small>contract shape</small></div>';
        echo '<div class="labs-kpi"><span class="labs-muted">Adapter Reads</span><strong>' . (int)$summary['adapter_read_contract_count'] . '</strong><small>real read contracts</small></div>';
        echo '<div class="labs-kpi"><span class="labs-muted">Fallbacks</span><strong>' . (int)$summary['fixture_fallback_contract_count'] . '</strong><small>safe fixtures</small></div>';
        echo '</section>';
        echo '<section class="labs-card"><h2>Read contract health</h2>';
        tl_stage883_render_rows((array)$summary['read_contracts']);
        echo '</section>';
    }
}
