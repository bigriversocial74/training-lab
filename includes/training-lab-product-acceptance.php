<?php
/**
 * Read-only end-to-end product and deployment acceptance.
 * No row, configuration, reward, claim, wallet, or external system is mutated.
 */
require_once __DIR__ . '/training-lab-db.php';

if (!function_exists('tl_acceptance_check')) {
    function tl_acceptance_check(string $key, string $label, bool $passed, string $detail, string $category = 'product'): array
    {
        return compact('key', 'label', 'passed', 'detail', 'category');
    }
}

if (!function_exists('tl_acceptance_file')) {
    function tl_acceptance_file(string $relative): bool
    {
        return is_file(dirname(__DIR__) . '/' . ltrim($relative, '/'));
    }
}

if (!function_exists('tl_product_acceptance_report')) {
    function tl_product_acceptance_report(): array
    {
        $checks = [];
        $requiredRoutes = [
            'participant_home'=>'app/index.php',
            'campaign_discovery'=>'app/campaigns.php',
            'campaign_detail'=>'app/campaign-detail.php',
            'task_detail'=>'app/task-runner.php',
            'progress'=>'app/progress-map.php',
            'participant_onboarding'=>'app/getting-started.php',
            'merchant_home'=>'admin/index.php',
            'merchant_onboarding'=>'admin/getting-started.php',
            'reward_rules'=>'admin/reward-rules.php',
            'analytics'=>'admin/analytics.php',
            'fulfillment'=>'admin/reward-bridge.php',
            'advanced_rewards'=>'admin/reward-operations.php',
            'product_acceptance'=>'admin/product-acceptance.php',
            'live_acceptance'=>'admin/live-acceptance.php',
            'accessibility'=>'accessibility.php',
        ];
        foreach ($requiredRoutes as $key => $route) {
            $checks[] = tl_acceptance_check('route_' . $key, 'Route: ' . str_replace('_', ' ', $key), tl_acceptance_file($route), $route, 'routes');
        }

        $requiredServices = [
            'product_shell'=>'includes/training-lab-product-shell.php',
            'campaign_experience'=>'includes/training-lab-campaign-experience.php',
            'task_experience'=>'includes/training-lab-task-experience.php',
            'progress_experience'=>'includes/training-lab-progress-experience.php',
            'reward_management'=>'includes/training-lab-reward-management.php',
            'onboarding'=>'includes/training-lab-onboarding.php',
            'accessibility_helpers'=>'includes/training-lab-accessibility.php',
            'product_acceptance'=>'includes/training-lab-product-acceptance.php',
            'production_readiness'=>'includes/training-lab-production-readiness.php',
            'live_acceptance'=>'includes/training-lab-live-acceptance.php',
        ];
        foreach ($requiredServices as $key => $file) {
            $checks[] = tl_acceptance_check('service_' . $key, 'Service: ' . str_replace('_', ' ', $key), tl_acceptance_file($file), $file, 'services');
        }

        $db = tl_db();
        $dbConnected = $db instanceof PDO;
        $checks[] = tl_acceptance_check('db_connected', 'Training database connected', $dbConnected, $dbConnected ? 'PDO connection is available.' : 'Database connection is unavailable.', 'database');
        $requiredTables = function_exists('tl_training_required_tables')
            ? tl_training_required_tables()
            : ['training_campaigns','training_campaign_tasks','training_participants','training_proof_submissions','training_reviews','training_action_receipts','training_reward_rules','training_reward_events','training_streaks','training_events'];
        foreach ($requiredTables as $table) {
            $present = $dbConnected && tl_table_exists($table);
            $checks[] = tl_acceptance_check('table_' . $table, 'Table: ' . $table, $present, $present ? 'Present.' : 'Missing or unavailable.', 'database');
        }
        $handoffPresent = $dbConnected && tl_table_exists('training_reward_handoffs');
        $checks[] = tl_acceptance_check('table_training_reward_handoffs', 'Reward handoff outbox', $handoffPresent, $handoffPresent ? 'Stage 890 handoff table is present.' : 'Import the existing Stage 890 handoff migration before enabling delivery operations.', 'database');

        $acceptanceFiles = [
            'tests/role-aware-shell-participant-home-contract-test.php',
            'tests/campaign-discovery-detail-enrollment-contract-test.php',
            'tests/task-detail-status-proof-revisions-contract-test.php',
            'tests/reward-rules-analytics-operational-health-contract-test.php',
            'tests/onboarding-guided-empty-states-contract-test.php',
            'tests/mobile-accessibility-completion-contract-test.php',
            'tests/end-to-end-acceptance-deployment-contract-test.php',
            'tests/production-deployment-live-acceptance-contract-test.php',
            'scripts/end-to-end-acceptance-deployment-quality-audit.php',
            'scripts/production-deployment-live-acceptance-quality-audit.php',
            'docs/PRODUCTION-DEPLOYMENT-LIVE-ACCEPTANCE-V1.md',
            'run-quality-gate.sh',
            'run-full-syntax-check.sh',
            'bin/product-acceptance.php',
            'bin/build-release-package.php',
            'bin/verify-release-package.php',
            'bin/live-acceptance.php',
        ];
        foreach ($acceptanceFiles as $file) {
            $checks[] = tl_acceptance_check('acceptance_' . md5($file), 'Acceptance asset', tl_acceptance_file($file), $file, 'acceptance');
        }

        $boundaryFiles = [
            'account_bridge'=>'includes/training-lab-account-bridge.php',
            'route_bootstrap'=>'includes/training-lab-route-bootstrap.php',
            'security'=>'includes/training-lab-security.php',
            'signed_lookup'=>'includes/training-lab-stage894-signed-reward-lookup-client.php',
            'limited_scheduler'=>'includes/training-lab-stage899-limited-scheduled-processing.php',
        ];
        foreach ($boundaryFiles as $key => $file) {
            $checks[] = tl_acceptance_check('boundary_' . $key, 'Boundary: ' . str_replace('_', ' ', $key), tl_acceptance_file($file), $file, 'boundaries');
        }

        $failed = array_values(array_filter($checks, static fn(array $check): bool => !$check['passed']));
        $categories = [];
        foreach ($checks as $check) {
            $category = $check['category'];
            $categories[$category] ??= ['passed'=>0, 'total'=>0];
            $categories[$category]['total']++;
            if ($check['passed']) $categories[$category]['passed']++;
        }
        foreach ($categories as &$category) {
            $category['percent'] = $category['total'] ? (int)round($category['passed'] / $category['total'] * 100) : 0;
        }
        unset($category);

        return [
            'ready'=>count($failed) === 0,
            'score'=>count($checks) ? (int)round((count($checks) - count($failed)) / count($checks) * 100) : 0,
            'checks'=>$checks,
            'failed'=>$failed,
            'categories'=>$categories,
            'generated_at'=>gmdate('c'),
            'safe_boundaries'=>[
                'read_only'=>true,
                'no_sql_writes'=>true,
                'no_config_mutation'=>true,
                'no_reward_issuing'=>true,
                'no_claim_or_wallet_mutation'=>true,
                'no_external_delivery'=>true,
            ],
        ];
    }
}
