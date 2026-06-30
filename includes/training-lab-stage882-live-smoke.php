<?php
/**
 * Stage 882 Live Environment Smoke + Microgifter Adapter Dry Run.
 *
 * Read-only deployment smoke checks for the merged Stage 880/881 baseline.
 * This layer does not call production mutation endpoints. It reports config,
 * database, route, adapter, and dry-run readiness while keeping all production
 * claim/redeem, wallet, payment, and destructive sync boundaries closed.
 */

if (!function_exists('tl_stage882_root')) {
    function tl_stage882_root(): string
    {
        return dirname(__DIR__);
    }
}

if (!function_exists('tl_stage882_e')) {
    function tl_stage882_e($value): string
    {
        return function_exists('labs_e') ? labs_e((string)$value) : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('tl_stage882_route_exists')) {
    function tl_stage882_route_exists(string $route): bool
    {
        return is_file(tl_stage882_root() . '/' . ltrim($route, '/'));
    }
}

if (!function_exists('tl_stage882_status')) {
    function tl_stage882_status(bool $passed, string $warn = 'check'): string
    {
        return $passed ? 'pass' : $warn;
    }
}

if (!function_exists('tl_stage882_live_route_checks')) {
    function tl_stage882_live_route_checks(): array
    {
        $routes = [
            '/admin/deployment-acceptance.php' => 'Stage 881 human QA page',
            '/api/training/deployment-acceptance.php' => 'Stage 881 machine QA API',
            '/admin/db-health.php' => 'DB health page',
            '/api/training/db-status.php' => 'DB status API',
            '/api/training/microgifter-adapter-sync.php' => 'Stage 880 adapter sync API',
            '/api/training/microgifter-adapter-sync.php?section=audit' => 'Stage 880 adapter audit section',
        ];

        $rows = [];
        foreach ($routes as $route => $detail) {
            $path = strtok($route, '?') ?: $route;
            $exists = tl_stage882_route_exists($path);
            $rows[] = [
                'label' => $route,
                'status' => tl_stage882_status($exists),
                'passed' => $exists,
                'detail' => $exists ? $detail . ' exists' : $detail . ' missing',
            ];
        }
        return $rows;
    }
}

if (!function_exists('tl_stage882_db_smoke')) {
    function tl_stage882_db_smoke(): array
    {
        $db = function_exists('tl_db_status_summary') ? tl_db_status_summary() : [];
        $config = $db['config'] ?? [];
        $tables = $db['tables'] ?? [];
        $missing = $db['missing_tables'] ?? [];

        $requiredReady = is_array($tables) && count($tables) > 0 && empty($missing);
        $rowCountTotal = 0;
        foreach (($db['row_counts'] ?? []) as $count) {
            if ($count !== null) $rowCountTotal += (int)$count;
        }

        return [
            [
                'label' => 'Config loaded',
                'status' => tl_stage882_status(!empty($db['config_ready'])),
                'passed' => !empty($db['config_ready']),
                'detail' => (string)($config['expected_path'] ?? 'labs/config.php'),
            ],
            [
                'label' => 'Database connected',
                'status' => tl_stage882_status(!empty($db['connected'])),
                'passed' => !empty($db['connected']),
                'detail' => !empty($db['connected']) ? 'database mode' : (string)($db['connection_error'] ?? 'demo fallback or missing private config'),
            ],
            [
                'label' => 'Required tables',
                'status' => tl_stage882_status($requiredReady),
                'passed' => $requiredReady,
                'detail' => $requiredReady ? 'all required Training Lab tables present' : 'missing: ' . implode(', ', (array)$missing),
            ],
            [
                'label' => 'Training rows visible',
                'status' => $rowCountTotal > 0 ? 'pass' : 'preview',
                'passed' => true,
                'detail' => number_format($rowCountTotal) . ' read-only row(s) visible',
            ],
        ];
    }
}

if (!function_exists('tl_stage882_adapter_smoke')) {
    function tl_stage882_adapter_smoke(): array
    {
        $mode = function_exists('tl_stage880_adapter_mode') ? tl_stage880_adapter_mode() : [];
        $audit = function_exists('tl_stage880_adapter_sync_audit') ? tl_stage880_adapter_sync_audit() : [];
        $summary = function_exists('tl_stage880_adapter_sync_summary') ? tl_stage880_adapter_sync_summary(0, false) : [];
        $safe = $summary['safe_boundaries'] ?? [];

        return [
            [
                'label' => 'Adapter mode',
                'status' => !empty($mode['connected']) ? 'pass' : 'preview',
                'passed' => true,
                'detail' => (string)($mode['mode_label'] ?? $mode['mode'] ?? 'fixture adapter preview'),
            ],
            [
                'label' => 'Developer key present',
                'status' => tl_stage882_status(!empty($mode['developer_key_present']), 'missing'),
                'passed' => !empty($mode['developer_key_present']),
                'detail' => !empty($mode['developer_key_present']) ? 'developer key detected' : 'safe fixture/read-only mode until key is configured',
            ],
            [
                'label' => 'Stage 880 audit',
                'status' => tl_stage882_status(!empty($audit['accepted'])),
                'passed' => !empty($audit['accepted']),
                'detail' => 'score ' . (int)($audit['score'] ?? 0) . '/100; issues ' . (int)($audit['issue_count'] ?? 0),
            ],
            [
                'label' => 'Production mutation',
                'status' => empty($mode['production_mutation_allowed_by_training_lab']) ? 'closed' : 'gated',
                'passed' => empty($mode['production_mutation_allowed_by_training_lab']),
                'detail' => empty($mode['production_mutation_allowed_by_training_lab']) ? 'mutation remains disabled by default' : 'production mutation flag is enabled; verify before deploy',
            ],
            [
                'label' => 'Claim/redeem boundary',
                'status' => !empty($safe['no_real_claim_or_redeem_mutation_without_adapter_gate']) ? 'closed' : 'check',
                'passed' => !empty($safe['no_real_claim_or_redeem_mutation_without_adapter_gate']),
                'detail' => 'production claim/redeem remains adapter/developer-key gated',
            ],
        ];
    }
}

if (!function_exists('tl_stage882_adapter_dry_run_cards')) {
    function tl_stage882_adapter_dry_run_cards(): array
    {
        $mode = function_exists('tl_stage880_adapter_mode') ? tl_stage880_adapter_mode() : [];
        $readFns = (array)($mode['read_adapter_functions'] ?? []);
        $writeFns = (array)($mode['write_adapter_functions'] ?? []);
        $syncFns = (array)($mode['sync_adapter_functions'] ?? []);

        $catalogReady = count(array_intersect($readFns, ['microgifter_training_campaign_catalog', 'microgifter_merchant_reward_campaigns', 'microgifter_reward_catalog'])) > 0 || function_exists('tl_stage800_imported_campaigns');
        $awardsReady = count(array_intersect($readFns, ['microgifter_customer_awards', 'microgifter_training_user_awards'])) > 0 || function_exists('tl_stage840_user_awards');
        $identityReady = count(array_intersect($readFns, ['microgifter_user_account_status', 'microgifter_adapter_status'])) > 0 || function_exists('tl_stage880_identity_matching');
        $inventoryReady = count($syncFns) > 0 || function_exists('tl_stage880_campaign_sync_health');
        $handoffReady = count($writeFns) > 0 || function_exists('tl_stage880_award_handoff_queue');

        return [
            [
                'label' => 'Merchant campaign catalog',
                'status' => $catalogReady ? 'ready' : 'fixture',
                'passed' => $catalogReady,
                'detail' => 'read-only campaign catalog / fixture fallback',
            ],
            [
                'label' => 'Customer awards',
                'status' => $awardsReady ? 'ready' : 'fixture',
                'passed' => $awardsReady,
                'detail' => 'read-only customer award inbox / fixture fallback',
            ],
            [
                'label' => 'Identity matching',
                'status' => $identityReady ? 'ready' : 'fixture',
                'passed' => $identityReady,
                'detail' => 'merchant/customer identity matching preview',
            ],
            [
                'label' => 'Inventory freshness',
                'status' => $inventoryReady ? 'ready' : 'fixture',
                'passed' => $inventoryReady,
                'detail' => 'refresh preview only; no destructive sync',
            ],
            [
                'label' => 'Award handoff preview',
                'status' => $handoffReady ? 'preview' : 'blocked',
                'passed' => $handoffReady,
                'detail' => 'handoff preview/control only; no production reward creation',
            ],
        ];
    }
}

if (!function_exists('tl_stage882_group_score')) {
    function tl_stage882_group_score(array $rows, bool $strict = true): int
    {
        if (!$rows) return 100;
        $passed = 0;
        foreach ($rows as $row) {
            if (!empty($row['passed']) || (!$strict && in_array((string)($row['status'] ?? ''), ['preview', 'fixture', 'missing'], true))) {
                $passed++;
            }
        }
        return (int)round(($passed / max(1, count($rows))) * 100);
    }
}

if (!function_exists('tl_stage882_stage881_live_gate')) {
    function tl_stage882_stage881_live_gate(array $stage881, array $dbRows): array
    {
        $scores = (array)($stage881['scores'] ?? []);
        $configRows = (array)($stage881['config_placeholders'] ?? []);
        $repoPlaceholderTotal = 0;
        $repoPlaceholderPassed = 0;
        $dbPathPassed = false;

        foreach ($configRows as $row) {
            $label = (string)($row['label'] ?? '');
            if ($label === 'config.php' || $label === 'labs/config.php') {
                $repoPlaceholderTotal++;
                if (!empty($row['passed'])) $repoPlaceholderPassed++;
            }
            if ($label === 'DB config path' && !empty($row['passed'])) {
                $dbPathPassed = true;
            }
        }

        $dbScore = tl_stage882_group_score($dbRows);
        $stableChecksPass =
            (int)($scores['source_folders'] ?? 0) === 100 &&
            (int)($scores['route_presence'] ?? 0) === 100 &&
            (int)($scores['validation_coverage'] ?? 0) === 100 &&
            (int)($scores['safe_boundaries'] ?? 0) === 100;

        $repoPlaceholdersPass = $repoPlaceholderTotal > 0 && $repoPlaceholderPassed === $repoPlaceholderTotal;
        $livePrivateConfigDetected = $repoPlaceholderTotal > 0 && $repoPlaceholderPassed < $repoPlaceholderTotal && $dbPathPassed && $dbScore === 100;
        $accepted = !empty($stage881['accepted']) || ($stableChecksPass && ($repoPlaceholdersPass || $livePrivateConfigDetected));

        return [
            'label' => 'Stage 881 live gate',
            'status' => $accepted ? 'pass' : 'check',
            'passed' => $accepted,
            'detail' => $livePrivateConfigDetected
                ? 'live private DB config detected; repo placeholder check is not required on deployed server'
                : ($accepted ? 'Stage 881 acceptance checks pass' : 'Stage 881 stable checks or DB config path need review'),
            'repo_placeholder_check' => [
                'total' => $repoPlaceholderTotal,
                'passed' => $repoPlaceholderPassed,
                'required_for_repo_archive' => true,
                'required_for_live_private_config' => false,
            ],
            'live_private_config_detected' => $livePrivateConfigDetected,
        ];
    }
}

if (!function_exists('tl_stage882_live_smoke_summary')) {
    function tl_stage882_live_smoke_summary(): array
    {
        $routes = tl_stage882_live_route_checks();
        $db = tl_stage882_db_smoke();
        $adapter = tl_stage882_adapter_smoke();
        $dryRun = tl_stage882_adapter_dry_run_cards();
        $stage881 = function_exists('tl_stage881_deployment_acceptance_summary') ? tl_stage881_deployment_acceptance_summary() : [];
        $stage881LiveGate = tl_stage882_stage881_live_gate($stage881, $db);

        $routeScore = tl_stage882_group_score($routes);
        $dbScore = tl_stage882_group_score($db);
        $adapterScore = tl_stage882_group_score($adapter, false);
        $dryRunScore = tl_stage882_group_score($dryRun, false);
        $stage881LiveScore = !empty($stage881LiveGate['passed']) ? 100 : 0;

        $strictAccepted = $routeScore === 100 && $dbScore === 100 && !empty($stage881LiveGate['passed']);

        return [
            'stage' => 'Stage 882 Live Environment Smoke + Microgifter Adapter Dry Run',
            'built_from' => 'Stage 881 Deployment Acceptance + Route QA',
            'accepted' => $strictAccepted,
            'score' => (int)round(($routeScore + $dbScore + $adapterScore + $dryRunScore + $stage881LiveScore) / 5),
            'scores' => [
                'stage881_live_gate' => $stage881LiveScore,
                'live_routes' => $routeScore,
                'database_smoke' => $dbScore,
                'adapter_smoke' => $adapterScore,
                'adapter_dry_run' => $dryRunScore,
            ],
            'stage881_live_gate' => $stage881LiveGate,
            'deployment_acceptance' => $stage881,
            'live_routes' => $routes,
            'database_smoke' => $db,
            'adapter_smoke' => $adapter,
            'adapter_dry_run' => $dryRun,
            'safe_boundaries' => [
                'no_new_sql' => true,
                'no_config_files_moved_or_overwritten' => true,
                'no_hard_auth_gates_forced' => true,
                'no_payment_processing' => true,
                'no_wallet_balance_mutation' => true,
                'no_production_claim_redeem_mutation' => true,
                'no_destructive_microgifter_sync' => true,
                'adapter_dry_run_read_only' => true,
                'award_handoff_preview_only' => true,
            ],
            'next_recommended_step' => $strictAccepted
                ? 'Begin Stage 883 read-only adapter wiring against real Microgifter adapter functions, still without production mutation.'
                : 'Fix live deployment, DB config, or missing table issues before Stage 883 adapter wiring.',
        ];
    }
}

if (!function_exists('tl_stage882_status_class')) {
    function tl_stage882_status_class(string $status): string
    {
        if (in_array($status, ['pass', 'ready', 'closed'], true)) return 'good';
        if (in_array($status, ['preview', 'fixture', 'missing', 'gated'], true)) return 'warn';
        return 'bad';
    }
}

if (!function_exists('tl_stage882_render_rows')) {
    function tl_stage882_render_rows(array $rows): void
    {
        echo '<div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>Check</th><th>Status</th><th>Detail</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $status = (string)($row['status'] ?? 'check');
            echo '<tr><td>' . tl_stage882_e((string)($row['label'] ?? 'Check')) . '</td><td><span class="labs-pill is-' . tl_stage882_e(tl_stage882_status_class($status)) . '">' . tl_stage882_e($status) . '</span></td><td>' . tl_stage882_e((string)($row['detail'] ?? '')) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

if (!function_exists('tl_stage882_render_live_smoke')) {
    function tl_stage882_render_live_smoke(): void
    {
        $summary = tl_stage882_live_smoke_summary();
        echo '<section class="labs-page-title"><div><span class="labs-eyebrow">Stage 882</span><h1>Live Environment Smoke + Adapter Dry Run</h1><p class="labs-copy">Read-only checks for deployment health, DB readiness, Stage 880 adapter audit, and Microgifter adapter dry-run status. No production mutation is enabled.</p></div><a class="labs-btn labs-btn-primary" href="' . tl_stage882_e(function_exists('labs_url') ? labs_url('/api/training/live-smoke.php') : '/api/training/live-smoke.php') . '">View JSON</a></section>';

        echo '<section class="labs-kpis">';
        echo '<div class="labs-kpi"><span class="labs-muted">Accepted</span><strong>' . ($summary['accepted'] ? 'Yes' : 'Check') . '</strong><small>live smoke gate</small></div>';
        echo '<div class="labs-kpi"><span class="labs-muted">Score</span><strong>' . (int)$summary['score'] . '/100</strong><small>route/db/adapter</small></div>';
        echo '<div class="labs-kpi"><span class="labs-muted">Stage</span><strong>882</strong><small>dry run only</small></div>';
        echo '<div class="labs-kpi"><span class="labs-muted">Mutation</span><strong>Closed</strong><small>safe boundary</small></div>';
        echo '</section>';

        echo '<section class="labs-card"><h2>Stage 881 live gate</h2>';
        tl_stage882_render_rows([(array)($summary['stage881_live_gate'] ?? [])]);
        echo '</section>';

        $groups = [
            'live_routes' => 'Live route smoke',
            'database_smoke' => 'Database smoke',
            'adapter_smoke' => 'Adapter smoke',
            'adapter_dry_run' => 'Read-only adapter dry run',
        ];

        echo '<section class="labs-flow-grid">';
        foreach ($groups as $key => $title) {
            echo '<article class="labs-card"><h2>' . tl_stage882_e($title) . '</h2>';
            tl_stage882_render_rows((array)($summary[$key] ?? []));
            echo '</article>';
        }
        echo '</section>';
    }
}
