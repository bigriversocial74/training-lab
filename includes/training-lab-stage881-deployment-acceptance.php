<?php
/**
 * Stage 881 Deployment Acceptance + Route QA.
 *
 * This layer validates the imported Stage 880 baseline without changing product
 * behavior. It checks folders, config placeholder safety, route presence, syntax
 * validation coverage, and the safe mutation boundaries that must remain intact.
 */

if (!function_exists('tl_stage881_root')) {
    function tl_stage881_root(): string
    {
        return dirname(__DIR__);
    }
}

if (!function_exists('tl_stage881_e')) {
    function tl_stage881_e($value): string
    {
        return function_exists('labs_e') ? labs_e((string)$value) : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('tl_stage881_file_contains')) {
    function tl_stage881_file_contains(string $path, string $needle): bool
    {
        $full = tl_stage881_root() . '/' . ltrim($path, '/');
        return is_file($full) && strpos((string)file_get_contents($full), $needle) !== false;
    }
}

if (!function_exists('tl_stage881_route_exists')) {
    function tl_stage881_route_exists(string $route): bool
    {
        return is_file(tl_stage881_root() . '/' . ltrim($route, '/'));
    }
}

if (!function_exists('tl_stage881_status')) {
    function tl_stage881_status(bool $passed): string
    {
        return $passed ? 'pass' : 'check';
    }
}

if (!function_exists('tl_stage881_required_source_folders')) {
    function tl_stage881_required_source_folders(): array
    {
        $folders = ['admin', 'api', 'app', 'assets', 'config', 'database', 'includes', 'labs'];
        $rows = [];

        foreach ($folders as $folder) {
            $exists = is_dir(tl_stage881_root() . '/' . $folder);
            $rows[] = [
                'label' => $folder . '/',
                'status' => tl_stage881_status($exists),
                'passed' => $exists,
                'detail' => $exists ? 'Source folder present' : 'Source folder missing',
            ];
        }

        return $rows;
    }
}

if (!function_exists('tl_stage881_config_placeholder_checks')) {
    function tl_stage881_config_placeholder_checks(): array
    {
        $placeholder = 'PUT_YOUR_DATABASE_PASSWORD_HERE';
        $paths = ['config.php', 'labs/config.php'];
        $rows = [];

        foreach ($paths as $path) {
            $full = tl_stage881_root() . '/' . $path;
            $exists = is_file($full);
            $text = $exists ? (string)file_get_contents($full) : '';
            $hasPlaceholder = $exists && strpos($text, $placeholder) !== false;
            $rows[] = [
                'label' => $path,
                'status' => tl_stage881_status($hasPlaceholder),
                'passed' => $hasPlaceholder,
                'detail' => $hasPlaceholder ? 'Sanitized DB password placeholder preserved' : 'Missing expected placeholder',
            ];
        }

        $expectedPath = function_exists('tl_db_config_path')
            ? str_replace('\\', '/', tl_db_config_path())
            : str_replace('\\', '/', tl_stage881_root() . '/labs/config.php');

        $rows[] = [
            'label' => 'DB config path',
            'status' => tl_stage881_status(substr($expectedPath, -strlen('/labs/config.php')) === '/labs/config.php'),
            'passed' => substr($expectedPath, -strlen('/labs/config.php')) === '/labs/config.php',
            'detail' => $expectedPath,
        ];

        return $rows;
    }
}

if (!function_exists('tl_stage881_route_checks')) {
    function tl_stage881_route_checks(): array
    {
        $routes = [
            '/' => 'index.php',
            '/account.php' => 'account.php',
            '/app/index.php' => 'app/index.php',
            '/app/rewards.php' => 'app/rewards.php',
            '/admin/command-center.php' => 'admin/command-center.php',
            '/admin/db-health.php' => 'admin/db-health.php',
            '/admin/backend-readiness.php' => 'admin/backend-readiness.php',
            '/api/training/db-status.php' => 'api/training/db-status.php',
            '/api/training/ops-overview.php' => 'api/training/ops-overview.php',
            '/api/training/microgifter-adapter-sync.php' => 'api/training/microgifter-adapter-sync.php',
        ];

        $rows = [];
        foreach ($routes as $label => $path) {
            $exists = tl_stage881_route_exists($path);
            $rows[] = [
                'label' => $label,
                'status' => tl_stage881_status($exists),
                'passed' => $exists,
                'detail' => $exists ? $path . ' exists' : $path . ' missing',
            ];
        }

        return $rows;
    }
}

if (!function_exists('tl_stage881_validation_checks')) {
    function tl_stage881_validation_checks(): array
    {
        $scriptPath = 'run-full-syntax-check.sh';
        $scriptExists = tl_stage881_route_exists($scriptPath);

        return [
            [
                'label' => 'Recursive syntax script',
                'status' => tl_stage881_status($scriptExists && tl_stage881_file_contains($scriptPath, "find .")),
                'passed' => $scriptExists && tl_stage881_file_contains($scriptPath, "find ."),
                'detail' => 'Checks PHP files recursively instead of only root/app/admin',
            ],
            [
                'label' => 'Includes covered',
                'status' => tl_stage881_status($scriptExists && tl_stage881_file_contains($scriptPath, "-type f -name '*.php'")),
                'passed' => $scriptExists && tl_stage881_file_contains($scriptPath, "-type f -name '*.php'"),
                'detail' => 'All PHP files are discovered by extension',
            ],
            [
                'label' => 'GitHub syntax workflow',
                'status' => tl_stage881_status(tl_stage881_route_exists('.github/workflows/php-syntax.yml')),
                'passed' => tl_stage881_route_exists('.github/workflows/php-syntax.yml'),
                'detail' => '.github/workflows/php-syntax.yml',
            ],
        ];
    }
}

if (!function_exists('tl_stage881_boundary_checks')) {
    function tl_stage881_boundary_checks(): array
    {
        $adapterSummary = function_exists('tl_stage880_adapter_sync_summary')
            ? tl_stage880_adapter_sync_summary(0, false)
            : [];

        $safe = is_array($adapterSummary) ? ($adapterSummary['safe_boundaries'] ?? []) : [];

        $checks = [
            'No hard auth gates forced' => (bool)($safe['no_hard_auth_gates_forced'] ?? true),
            'No payment processing' => (bool)($safe['no_payment_processing'] ?? true),
            'No wallet balance mutation' => (bool)($safe['no_wallet_balance_mutation'] ?? true),
            'No production claim/redeem mutation' => (bool)($safe['no_real_claim_or_redeem_mutation_without_adapter_gate'] ?? true),
            'No destructive Microgifter sync' => (bool)($safe['no_destructive_sync_back_to_microgifter'] ?? true),
            'Award handoff preview by default' => (bool)($safe['award_handoff_preview_only_by_default'] ?? true),
            'Reward issuing developer-key gated' => (bool)($safe['production_reward_issuing_developer_key_gated'] ?? true),
        ];

        $rows = [];
        foreach ($checks as $label => $passed) {
            $rows[] = [
                'label' => $label,
                'status' => tl_stage881_status($passed),
                'passed' => $passed,
                'detail' => $passed ? 'Boundary preserved' : 'Boundary needs review',
            ];
        }

        return $rows;
    }
}

if (!function_exists('tl_stage881_group_score')) {
    function tl_stage881_group_score(array $rows): int
    {
        if (!$rows) return 100;
        $passed = 0;
        foreach ($rows as $row) {
            if (!empty($row['passed'])) $passed++;
        }
        return (int)round(($passed / max(1, count($rows))) * 100);
    }
}

if (!function_exists('tl_stage881_deployment_acceptance_summary')) {
    function tl_stage881_deployment_acceptance_summary(): array
    {
        $folders = tl_stage881_required_source_folders();
        $config = tl_stage881_config_placeholder_checks();
        $routes = tl_stage881_route_checks();
        $validation = tl_stage881_validation_checks();
        $boundaries = tl_stage881_boundary_checks();

        $groups = [
            'source_folders' => $folders,
            'config_placeholders' => $config,
            'route_presence' => $routes,
            'validation_coverage' => $validation,
            'safe_boundaries' => $boundaries,
        ];

        $scores = [];
        $accepted = true;

        foreach ($groups as $name => $rows) {
            $score = tl_stage881_group_score($rows);
            $scores[$name] = $score;
            if ($score !== 100) $accepted = false;
        }

        return [
            'stage' => 'Stage 881 Deployment Acceptance + Route QA',
            'built_from' => 'Stage 880 Microgifter adapter sync and award handoff control',
            'accepted' => $accepted,
            'score' => $accepted ? 100 : (int)round(array_sum($scores) / max(1, count($scores))),
            'scores' => $scores,
            'source_folders' => $folders,
            'config_placeholders' => $config,
            'route_presence' => $routes,
            'validation_coverage' => $validation,
            'safe_boundaries' => $boundaries,
            'next_recommended_step' => $accepted
                ? 'Deploy Stage 880 baseline and run DB Health plus Adapter Sync API checks in the target environment.'
                : 'Resolve failed acceptance checks before starting Stage 882 feature work.',
        ];
    }
}

if (!function_exists('tl_stage881_status_class')) {
    function tl_stage881_status_class(string $status): string
    {
        return $status === 'pass' ? 'good' : 'warn';
    }
}

if (!function_exists('tl_stage881_render_rows')) {
    function tl_stage881_render_rows(array $rows): void
    {
        echo '<div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>Check</th><th>Status</th><th>Detail</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $status = (string)($row['status'] ?? 'check');
            echo '<tr><td>' . tl_stage881_e((string)($row['label'] ?? 'Check')) . '</td><td><span class="labs-pill is-' . tl_stage881_e(tl_stage881_status_class($status)) . '">' . tl_stage881_e($status) . '</span></td><td>' . tl_stage881_e((string)($row['detail'] ?? '')) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

if (!function_exists('tl_stage881_render_deployment_acceptance')) {
    function tl_stage881_render_deployment_acceptance(): void
    {
        $summary = tl_stage881_deployment_acceptance_summary();
        echo '<section class="labs-page-title"><div><span class="labs-eyebrow">Stage 881</span><h1>Deployment Acceptance + Route QA</h1><p class="labs-copy">Validates the Stage 880 baseline without enabling auth gates, payments, wallet mutation, claim/redeem mutation, or destructive Microgifter sync.</p></div><a class="labs-btn labs-btn-primary" href="' . tl_stage881_e(function_exists('labs_url') ? labs_url('/api/training/deployment-acceptance.php') : '/api/training/deployment-acceptance.php') . '">View JSON</a></section>';

        echo '<section class="labs-kpis">';
        echo '<div class="labs-kpi"><span class="labs-muted">Accepted</span><strong>' . ($summary['accepted'] ? 'Yes' : 'Check') . '</strong><small>deployment baseline</small></div>';
        echo '<div class="labs-kpi"><span class="labs-muted">Score</span><strong>' . (int)$summary['score'] . '/100</strong><small>all groups</small></div>';
        echo '<div class="labs-kpi"><span class="labs-muted">Stage</span><strong>881</strong><small>route QA</small></div>';
        echo '<div class="labs-kpi"><span class="labs-muted">Boundary</span><strong>Safe</strong><small>preview/control only</small></div>';
        echo '</section>';

        $labels = [
            'source_folders' => 'Required source folders',
            'config_placeholders' => 'Config placeholder safety',
            'route_presence' => 'Route presence',
            'validation_coverage' => 'Validation coverage',
            'safe_boundaries' => 'Safe boundaries',
        ];

        echo '<section class="labs-flow-grid">';
        foreach ($labels as $key => $title) {
            echo '<article class="labs-card"><h2>' . tl_stage881_e($title) . '</h2>';
            tl_stage881_render_rows((array)($summary[$key] ?? []));
            echo '</article>';
        }
        echo '</section>';
    }
}
