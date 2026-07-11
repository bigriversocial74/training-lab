<?php
/**
 * Read-only production deployment readiness for Training Lab.
 *
 * This service inspects runtime, configuration, schema, product acceptance, and
 * delivery-gate state. It never writes rows, changes configuration, runs a
 * worker, issues a reward, or contacts Microgifter.
 */
require_once __DIR__ . '/training-lab-db.php';
require_once __DIR__ . '/training-lab-security.php';
require_once __DIR__ . '/training-lab-product-acceptance.php';

if (!function_exists('tl_production_readiness_check')) {
    function tl_production_readiness_check(
        string $key,
        string $label,
        bool $passed,
        string $detail,
        string $category = 'runtime'
    ): array {
        return compact('key', 'label', 'passed', 'detail', 'category');
    }
}

if (!function_exists('tl_production_bool_setting')) {
    function tl_production_bool_setting(array $config, string $configKey, string $envKey, bool $default = false): bool
    {
        $env = getenv($envKey);
        if ($env !== false && $env !== '') {
            return tl_security_bool($env, $default);
        }
        return tl_security_bool($config[$configKey] ?? $default, $default);
    }
}

if (!function_exists('tl_production_delivery_gate_snapshot')) {
    function tl_production_delivery_gate_snapshot(array $config): array
    {
        $map = [
            'reward_handoff_processing_enabled' => 'TL_REWARD_HANDOFF_PROCESSING_ENABLED',
            'reward_handoff_worker_enabled' => 'TL_REWARD_HANDOFF_WORKER_ENABLED',
            'reward_delivery_reconciliation_enabled' => 'TL_REWARD_DELIVERY_RECONCILIATION_ENABLED',
            'microgifter_reward_lookup_enabled' => 'TL_MICROGIFTER_REWARD_LOOKUP_ENABLED',
            'stage895_live_acceptance_enabled' => 'TL_STAGE895_LIVE_ACCEPTANCE_ENABLED',
            'stage896_limited_pilot_enabled' => 'TL_STAGE896_LIMITED_PILOT_ENABLED',
            'microgifter_pilot_issue_enabled' => 'TL_MICROGIFTER_PILOT_ISSUE_ENABLED',
            'stage897_controlled_batch_enabled' => 'TL_STAGE897_CONTROLLED_BATCH_ENABLED',
            'stage898_worker_canary_enabled' => 'TL_STAGE898_WORKER_CANARY_ENABLED',
            'stage899_limited_scheduler_enabled' => 'TL_STAGE899_LIMITED_SCHEDULER_ENABLED',
        ];
        $snapshot = [];
        foreach ($map as $key => $envKey) {
            $snapshot[$key] = tl_production_bool_setting($config, $key, $envKey, false);
        }
        return $snapshot;
    }
}

if (!function_exists('tl_production_readiness_report')) {
    function tl_production_readiness_report(): array
    {
        $checks = [];
        $requiredExtensions = ['json', 'mbstring', 'openssl', 'pdo', 'pdo_mysql'];
        $checks[] = tl_production_readiness_check(
            'php_version',
            'PHP 8.2 or newer',
            PHP_VERSION_ID >= 80200,
            'Detected PHP ' . PHP_VERSION,
            'runtime'
        );
        foreach ($requiredExtensions as $extension) {
            $checks[] = tl_production_readiness_check(
                'extension_' . $extension,
                'PHP extension: ' . $extension,
                extension_loaded($extension),
                extension_loaded($extension) ? 'Loaded.' : 'Missing.',
                'runtime'
            );
        }

        $diagnostics = tl_db_config_diagnostics();
        $configReady = tl_db_config_ready();
        $checks[] = tl_production_readiness_check(
            'private_config',
            'Private /labs/config.php',
            $configReady,
            $configReady
                ? 'Active private configuration loaded without exposing values.'
                : 'Create or repair the private /labs/config.php before deployment acceptance.',
            'configuration'
        );

        $config = tl_security_config();
        $debugEnabled = tl_production_bool_setting($config, 'debug', 'TL_DEBUG', false);
        $demoEnabled = tl_production_bool_setting($config, 'allow_demo_session_login', 'TL_ALLOW_DEMO_LOGIN', false);
        $existingAuth = tl_production_bool_setting($config, 'use_existing_microgifter_auth', 'TL_USE_EXISTING_MICROGIFTER_AUTH', true);
        $paymentsEnabled = tl_production_bool_setting($config, 'payments_enabled', 'TL_PAYMENTS_ENABLED', false);
        $claimRedeemEnabled = tl_production_bool_setting($config, 'claim_redeem_enabled', 'TL_CLAIM_REDEEM_ENABLED', false);
        $proofRecordsOnly = tl_production_bool_setting($config, 'proof_records_only_no_real_uploads', 'TL_PROOF_RECORDS_ONLY', true);

        $configurationChecks = [
            ['debug_disabled', 'Debug mode disabled', !$debugEnabled, $debugEnabled ? 'Disable TL_DEBUG and config debug.' : 'Disabled.'],
            ['demo_login_disabled', 'Demo login disabled', !$demoEnabled, $demoEnabled ? 'Disable demo session login.' : 'Disabled.'],
            ['shared_auth_enabled', 'Shared Microgifter auth enabled', $existingAuth, $existingAuth ? 'Enabled.' : 'Enable existing Microgifter authentication.'],
            ['payments_disabled', 'Training Lab payments disabled', !$paymentsEnabled, $paymentsEnabled ? 'Disable payments_enabled.' : 'Disabled.'],
            ['claim_redeem_disabled', 'Training Lab claim/redeem disabled', !$claimRedeemEnabled, $claimRedeemEnabled ? 'Disable claim_redeem_enabled.' : 'Disabled.'],
            ['proof_records_only', 'Proof records only', $proofRecordsOnly, $proofRecordsOnly ? 'Real file upload processing remains disabled.' : 'Restore proof-record-only mode.'],
        ];
        foreach ($configurationChecks as [$key, $label, $passed, $detail]) {
            $checks[] = tl_production_readiness_check($key, $label, $passed, $detail, 'configuration');
        }

        $product = tl_product_acceptance_report();
        $checks[] = tl_production_readiness_check(
            'product_acceptance',
            'Repository and schema acceptance',
            !empty($product['ready']),
            !empty($product['ready'])
                ? 'All product acceptance checks passed.'
                : count((array)($product['failed'] ?? [])) . ' product acceptance check(s) are blocked.',
            'product'
        );

        $deliveryGates = tl_production_delivery_gate_snapshot($config);
        foreach ($deliveryGates as $key => $enabled) {
            $checks[] = tl_production_readiness_check(
                'gate_' . $key,
                ucwords(str_replace('_', ' ', $key)),
                !$enabled,
                $enabled
                    ? 'Enabled. Return this gate to its prior state before a product-only deployment.'
                    : 'Disabled.',
                'delivery_gates'
            );
        }

        $requiredTools = [
            'bin/build-release-package.php',
            'bin/verify-release-package.php',
            'bin/live-acceptance.php',
            'docs/PRODUCTION-DEPLOYMENT-LIVE-ACCEPTANCE-V1.md',
            'tests/production-deployment-live-acceptance-contract-test.php',
        ];
        foreach ($requiredTools as $tool) {
            $present = is_file(dirname(__DIR__) . '/' . $tool);
            $checks[] = tl_production_readiness_check(
                'tool_' . md5($tool),
                'Release asset',
                $present,
                $tool,
                'release_tools'
            );
        }

        $failed = array_values(array_filter($checks, static fn(array $check): bool => !$check['passed']));
        $categories = [];
        foreach ($checks as $check) {
            $category = (string)$check['category'];
            $categories[$category] ??= ['passed' => 0, 'total' => 0, 'percent' => 0];
            $categories[$category]['total']++;
            if ($check['passed']) $categories[$category]['passed']++;
        }
        foreach ($categories as &$category) {
            $category['percent'] = $category['total'] > 0
                ? (int)round($category['passed'] / $category['total'] * 100)
                : 0;
        }
        unset($category);

        return [
            'ready' => count($failed) === 0,
            'score' => count($checks) > 0
                ? (int)round((count($checks) - count($failed)) / count($checks) * 100)
                : 0,
            'checks' => $checks,
            'failed' => $failed,
            'categories' => $categories,
            'product_acceptance' => $product,
            'delivery_gates' => $deliveryGates,
            'config_path' => (string)($diagnostics['expected_path'] ?? '/labs/config.php'),
            'generated_at' => gmdate('c'),
            'safe_boundaries' => [
                'read_only' => true,
                'no_sql_writes' => true,
                'no_config_mutation' => true,
                'no_secret_output' => true,
                'no_reward_issuing' => true,
                'no_worker_execution' => true,
                'no_external_delivery' => true,
            ],
        ];
    }
}
