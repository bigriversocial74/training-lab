<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$failures = [];
$read = static function (string $path) use ($root, &$failures): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) { $failures[] = $path . ' is missing.'; return ''; }
    return file_get_contents($full) ?: '';
};
$requires = static function (string $body, string $needle, string $message) use (&$failures): void {
    if (!str_contains($body, $needle)) $failures[] = $message;
};
$forbids = static function (string $body, string $needle, string $message) use (&$failures): void {
    if (str_contains($body, $needle)) $failures[] = $message;
};
$service = $read('includes/training-lab-product-acceptance.php');
$page = $read('admin/product-acceptance.php');
$cli = $read('bin/product-acceptance.php');
$nav = $read('includes/training-lab-product-shell.php');
$css = $read('assets/css/product-acceptance.css');
$docs = $read('docs/END-TO-END-ACCEPTANCE-DEPLOYMENT-V1.md');
$runner = $read('run-quality-gate.sh');
$workflow = $read('.github/workflows/quality-gate.yml');

$requires($service, 'tl_product_acceptance_report', 'Acceptance service must expose one product report.');
$requires($service, "'product_acceptance'=>'admin/product-acceptance.php'", 'Acceptance route must verify itself.');
$requires($service, "'product_acceptance'=>'includes/training-lab-product-acceptance.php'", 'Acceptance service must verify itself.');
$requires($service, "tl_table_exists('training_reward_handoffs')", 'Acceptance must fail closed when the Stage 890 handoff table is absent.');
$requires($service, 'tl_training_required_tables()', 'Acceptance must verify the established Training Lab schema.');
$requires($service, "'read_only'=>true", 'Acceptance must declare read-only behavior.');
$requires($service, "'no_sql_writes'=>true", 'Acceptance must declare no SQL writes.');
$requires($service, "'no_external_delivery'=>true", 'Acceptance must declare no external delivery.');
$forbids($service, 'INSERT INTO', 'Acceptance service must not insert rows.');
$forbids($service, 'UPDATE training_', 'Acceptance service must not update Training Lab rows.');
$forbids($service, 'DELETE FROM', 'Acceptance service must not delete rows.');
$forbids($service, '$_POST', 'Acceptance service must not consume write input.');

$requires($page, "'required_role'=>'admin'", 'Acceptance dashboard must be administrator-only.');
$requires($page, 'This page performs reads only.', 'Dashboard must explain its read-only boundary.');
$requires($page, 'Stage 890–899 gate', 'Dashboard must preserve production reward gates.');
$requires($cli, "PHP_SAPI !== 'cli'", 'Acceptance CLI must reject web execution.');
$requires($cli, "exit(\$report['ready']?0:1)", 'Acceptance CLI must fail closed when checks are blocked.');
$requires($nav, "'admin-product-acceptance' => ['/admin/product-acceptance.php', 'Product Acceptance']", 'Administrator navigation must expose product acceptance.');
$requires($css, '@media(max-width:520px)', 'Acceptance checklist must reflow on small screens.');

$requires($docs, 'Back up the current application files and Training Lab database.', 'Deployment guide must require backups.');
$requires($docs, 'Preserve the active `config.php`', 'Deployment guide must preserve active configuration.');
$requires($docs, 'Run `bash ./run-quality-gate.sh`.', 'Deployment guide must require the full quality gate.');
$requires($docs, 'Run `php ./bin/product-acceptance.php`', 'Deployment guide must require CLI acceptance.');
$requires($docs, 'Sections 10–13 require no new SQL.', 'Deployment guide must state the current SQL boundary.');
$requires($docs, 'Rollback', 'Deployment guide must include rollback.');
$requires($docs, 'does not require a destructive schema reversal', 'Rollback must avoid destructive schema changes.');
$requires($docs, 'Do not enable production delivery as part of this product deployment.', 'Deployment must not enable reward delivery.');

$requires($runner, 'end-to-end-acceptance-deployment-contract-test.php', 'Local gate must run the Section 13 contract.');
$requires($runner, 'end-to-end-acceptance-deployment-quality-audit.php', 'Local gate must run the Section 13 scored audit.');
$requires($workflow, 'End-to-end acceptance and deployment contract', 'CI must run the Section 13 contract.');
$requires($workflow, 'End-to-end acceptance and deployment scored audit', 'CI must run the Section 13 scored audit.');

if ($failures) {
    fwrite(STDERR, "End-to-end acceptance and deployment contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "End-to-end acceptance and deployment contract passed.\n";
