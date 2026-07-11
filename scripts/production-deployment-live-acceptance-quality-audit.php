<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$load = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$has = static fn(string $path, string $needle): bool => str_contains($load($path), $needle);
$lacks = static fn(string $path, string $needle): bool => !str_contains($load($path), $needle);
$exists = static fn(string $path): bool => is_file($root . '/' . $path);

$sections = [
    'Runtime readiness' => [
        $has('includes/training-lab-production-readiness.php', 'PHP_VERSION_ID >= 80200'),
        $has('includes/training-lab-production-readiness.php', "'pdo_mysql'"),
        $has('includes/training-lab-production-readiness.php', 'tl_db_config_ready()'),
        $has('includes/training-lab-production-readiness.php', 'tl_product_acceptance_report()'),
    ],
    'Safe configuration' => [
        $has('includes/training-lab-production-readiness.php', "'demo_login_disabled'"),
        $has('includes/training-lab-production-readiness.php', "'shared_auth_enabled'"),
        $has('includes/training-lab-production-readiness.php', "'payments_disabled'"),
        $has('includes/training-lab-production-readiness.php', "'proof_records_only'"),
    ],
    'Release package safety' => [
        $exists('bin/build-release-package.php'),
        $has('bin/build-release-package.php', "'package_root' => 'labs'"),
        $has('bin/build-release-package.php', "'config.php', 'labs/config.php'"),
        $has('bin/build-release-package.php', "hash_file('sha256'"),
        $has('bin/build-release-package.php', '$file->isLink()'),
    ],
    'Package verification' => [
        $exists('bin/verify-release-package.php'),
        $has('bin/verify-release-package.php', 'Unsafe archive path'),
        $has('bin/verify-release-package.php', 'Private configuration must not be packaged'),
        $has('bin/verify-release-package.php', "hash_equals(\$expectedHash, hash('sha256', \$contents))"),
    ],
    'HTTPS smoke tests' => [
        $has('includes/training-lab-live-acceptance.php', 'Production live acceptance requires HTTPS.'),
        $has('includes/training-lab-live-acceptance.php', 'CURLOPT_FOLLOWLOCATION => false'),
        $has('includes/training-lab-live-acceptance.php', "'content-security-policy'"),
        $has('includes/training-lab-live-acceptance.php', "'strict-transport-security'"),
        $lacks('includes/training-lab-live-acceptance.php', 'CURLOPT_POST'),
    ],
    'Role acceptance' => [
        $has('includes/training-lab-live-acceptance.php', 'TL_ACCEPTANCE_PARTICIPANT_COOKIE'),
        $has('includes/training-lab-live-acceptance.php', 'TL_ACCEPTANCE_REVIEWER_COOKIE'),
        $has('includes/training-lab-live-acceptance.php', 'TL_ACCEPTANCE_MANAGER_COOKIE'),
        $has('includes/training-lab-live-acceptance.php', 'TL_ACCEPTANCE_ADMIN_COOKIE'),
        $has('bin/live-acceptance.php', "'require-role-sessions'"),
    ],
    'Secret and authority safety' => [
        $has('includes/training-lab-production-readiness.php', "'no_secret_output' => true"),
        $has('includes/training-lab-live-acceptance.php', "'no_cookie_output' => true"),
        $lacks('includes/training-lab-production-readiness.php', 'INSERT INTO'),
        $lacks('includes/training-lab-production-readiness.php', 'DELETE FROM'),
        $lacks('includes/training-lab-live-acceptance.php', '$_POST'),
    ],
    'Admin and responsive UX' => [
        $has('admin/live-acceptance.php', "'required_role' => 'admin'"),
        $has('admin/deployment-acceptance.php', "tl_product_redirect('/admin/live-acceptance.php', 302)"),
        $has('assets/css/production-readiness.css', '@media(max-width:680px)'),
        $has('assets/css/reward-management.css', "@import url('production-readiness.css')"),
    ],
    'Deployment and rollback' => [
        $has('docs/PRODUCTION-DEPLOYMENT-LIVE-ACCEPTANCE-V1.md', 'Back up the current Training Lab application files.'),
        $has('docs/PRODUCTION-DEPLOYMENT-LIVE-ACCEPTANCE-V1.md', 'Do **not** overwrite the existing `/labs/config.php`.'),
        $has('docs/PRODUCTION-DEPLOYMENT-LIVE-ACCEPTANCE-V1.md', 'Rollback'),
        $has('docs/PRODUCTION-DEPLOYMENT-LIVE-ACCEPTANCE-V1.md', 'No SQL is required for Section 14.'),
    ],
    'Acceptance integration' => [
        $exists('tests/production-deployment-live-acceptance-contract-test.php'),
        $exists('scripts/production-deployment-live-acceptance-quality-audit.php'),
        $has('run-quality-gate.sh', 'production-deployment-live-acceptance-contract-test.php'),
        $has('.github/workflows/quality-gate.yml', 'Production deployment and live acceptance contract'),
    ],
];

$failed = false;
echo "Production Deployment + Live Acceptance quality audit\n";
foreach ($sections as $name => $checks) {
    $passed = count(array_filter($checks));
    $total = count($checks);
    $score = round(($passed / $total) * 10, 1);
    echo sprintf("%-29s %s/10 (%d/%d)\n", $name, number_format($score, $score === 10.0 ? 0 : 1), $passed, $total);
    if ($passed !== $total) $failed = true;
}
if ($failed) {
    fwrite(STDERR, "Section 14 has not reached 10/10 in every category.\n");
    exit(1);
}
echo "Every Section 14 category scored 10/10.\n";
