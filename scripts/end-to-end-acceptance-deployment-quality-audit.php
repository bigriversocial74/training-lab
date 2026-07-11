<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$load = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$has = static fn(string $path, string $needle): bool => str_contains($load($path), $needle);
$lacks = static fn(string $path, string $needle): bool => !str_contains($load($path), $needle);
$exists = static fn(string $path): bool => is_file($root . '/' . $path);
$sections = [
    'Read-only acceptance' => [
        $has('includes/training-lab-product-acceptance.php', "'read_only'=>true"),
        $has('includes/training-lab-product-acceptance.php', "'no_sql_writes'=>true"),
        $lacks('includes/training-lab-product-acceptance.php', 'INSERT INTO'),
        $lacks('includes/training-lab-product-acceptance.php', 'DELETE FROM'),
    ],
    'Product route coverage' => [
        $has('includes/training-lab-product-acceptance.php', "'participant_home'=>'app/index.php'"),
        $has('includes/training-lab-product-acceptance.php', "'reward_rules'=>'admin/reward-rules.php'"),
        $has('includes/training-lab-product-acceptance.php', "'product_acceptance'=>'admin/product-acceptance.php'"),
        $has('includes/training-lab-product-acceptance.php', "'accessibility'=>'accessibility.php'"),
    ],
    'Service coverage' => [
        $has('includes/training-lab-product-acceptance.php', "'campaign_experience'=>'includes/training-lab-campaign-experience.php'"),
        $has('includes/training-lab-product-acceptance.php', "'reward_management'=>'includes/training-lab-reward-management.php'"),
        $has('includes/training-lab-product-acceptance.php', "'onboarding'=>'includes/training-lab-onboarding.php'"),
        $has('includes/training-lab-product-acceptance.php', "'accessibility_helpers'=>'includes/training-lab-accessibility.php'"),
    ],
    'Database fail-closed' => [
        $has('includes/training-lab-product-acceptance.php', 'tl_training_required_tables()'),
        $has('includes/training-lab-product-acceptance.php', "tl_table_exists('training_reward_handoffs')"),
        $has('includes/training-lab-product-acceptance.php', "'ready'=>count(\$failed) === 0"),
        $has('includes/training-lab-product-acceptance.php', "'db_connected'"),
    ],
    'Administrator access' => [
        $has('admin/product-acceptance.php', "'required_role'=>'admin'"),
        $has('includes/training-lab-product-shell.php', 'admin-product-acceptance'),
        $has('bin/product-acceptance.php', "PHP_SAPI !== 'cli'"),
    ],
    'Deployment discipline' => [
        $has('docs/END-TO-END-ACCEPTANCE-DEPLOYMENT-V1.md', 'Back up the current application files and Training Lab database.'),
        $has('docs/END-TO-END-ACCEPTANCE-DEPLOYMENT-V1.md', 'Preserve the active `config.php`'),
        $has('docs/END-TO-END-ACCEPTANCE-DEPLOYMENT-V1.md', 'Run `php ./bin/product-acceptance.php`'),
        $has('docs/END-TO-END-ACCEPTANCE-DEPLOYMENT-V1.md', 'Do not enable production delivery as part of this product deployment.'),
    ],
    'Rollback safety' => [
        $has('docs/END-TO-END-ACCEPTANCE-DEPLOYMENT-V1.md', 'Restore the previous application file package.'),
        $has('docs/END-TO-END-ACCEPTANCE-DEPLOYMENT-V1.md', 'Do not delete Training Lab rows created before rollback.'),
        $has('docs/END-TO-END-ACCEPTANCE-DEPLOYMENT-V1.md', 'does not require a destructive schema reversal'),
    ],
    'Reward gate preservation' => [
        $has('includes/training-lab-product-acceptance.php', "'limited_scheduler'=>'includes/training-lab-stage899-limited-scheduled-processing.php'"),
        $has('includes/training-lab-product-acceptance.php', "'signed_lookup'=>'includes/training-lab-stage894-signed-reward-lookup-client.php'"),
        $has('admin/product-acceptance.php', 'Stage 890–899 gate'),
        $has('includes/training-lab-product-acceptance.php', "'no_external_delivery'=>true"),
    ],
    'Responsive reporting' => [
        $exists('assets/css/product-acceptance.css'),
        $has('assets/css/product-acceptance.css', '@media(max-width:520px)'),
        $has('assets/css/reward-management.css', "@import url('product-acceptance.css')"),
        $has('admin/product-acceptance.php', 'aria-label="Acceptance category summary"'),
    ],
    'Acceptance integration' => [
        $exists('tests/end-to-end-acceptance-deployment-contract-test.php'),
        $exists('bin/product-acceptance.php'),
        $has('run-quality-gate.sh', 'end-to-end-acceptance-deployment-contract-test.php'),
        $has('.github/workflows/quality-gate.yml', 'End-to-end acceptance and deployment contract'),
    ],
];
$failed = false;
echo "End-to-End Acceptance + Deployment quality audit\n";
foreach ($sections as $name => $checks) {
    $passed = count(array_filter($checks));
    $total = count($checks);
    $score = round(($passed / $total) * 10, 1);
    echo sprintf("%-28s %s/10 (%d/%d)\n", $name, number_format($score, $score === 10.0 ? 0 : 1), $passed, $total);
    if ($passed !== $total) $failed = true;
}
if ($failed) {
    fwrite(STDERR, "Section 13 has not reached 10/10 in every category.\n");
    exit(1);
}
echo "Every Section 13 category scored 10/10.\n";
