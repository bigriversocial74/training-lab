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

$readiness = $read('includes/training-lab-production-readiness.php');
$live = $read('includes/training-lab-live-acceptance.php');
$builder = $read('bin/build-release-package.php');
$verifier = $read('bin/verify-release-package.php');
$cli = $read('bin/live-acceptance.php');
$page = $read('admin/live-acceptance.php');
$legacy = $read('admin/deployment-acceptance.php');
$css = $read('assets/css/production-readiness.css');
$imports = $read('assets/css/reward-management.css');
$docs = $read('docs/PRODUCTION-DEPLOYMENT-LIVE-ACCEPTANCE-V1.md');
$runner = $read('run-quality-gate.sh');
$workflow = $read('.github/workflows/quality-gate.yml');

$requires($readiness, 'tl_production_readiness_report', 'Production readiness must expose one report.');
$requires($readiness, "PHP_VERSION_ID >= 80200", 'Production readiness must require PHP 8.2+.');
$requires($readiness, "['json', 'mbstring', 'openssl', 'pdo', 'pdo_mysql']", 'Required PHP extensions must be checked.');
$requires($readiness, 'tl_db_config_ready()', 'Private config readiness must be checked.');
$requires($readiness, "'use_existing_microgifter_auth'", 'Shared Microgifter auth must be checked.');
$requires($readiness, "'stage899_limited_scheduler_enabled'", 'Stage 899 gate state must be checked.');
$requires($readiness, "'no_secret_output' => true", 'Readiness must declare secret-output safety.');
$forbids($readiness, 'INSERT INTO', 'Readiness must not insert rows.');
$forbids($readiness, 'UPDATE training_', 'Readiness must not update Training Lab rows.');
$forbids($readiness, 'DELETE FROM', 'Readiness must not delete rows.');
$forbids($readiness, '$_POST', 'Readiness must not consume write input.');

$requires($builder, "'package_root' => 'labs'", 'Release package must use the outer labs folder.');
$requires($builder, "'config.php', 'labs/config.php'", 'Release builder must exclude private configs.');
$requires($builder, "'.env', '.env.local'", 'Release builder must exclude environment files.');
$requires($builder, '$file->isLink()', 'Release builder must exclude symbolic links.');
$requires($builder, "hash_file('sha256'", 'Release builder must hash packaged files.');
$requires($builder, "'preserve_active_labs_config_php' => true", 'Manifest must preserve active config.');
$requires($builder, "'does_not_enable_reward_delivery' => true", 'Manifest must preserve delivery gates.');
$requires($verifier, "preg_match('#(^|/)\\.\\.(/|$)#'", 'Verifier must reject path traversal.');
$requires($verifier, 'Private configuration must not be packaged', 'Verifier must reject private config.');
$requires($verifier, "hash_equals(\$expectedHash, hash('sha256', \$contents))", 'Verifier must validate every manifest hash.');
$requires($verifier, "'labs/release-manifest.json'", 'Verifier must require the release manifest.');

$requires($live, "Production live acceptance requires HTTPS.", 'Live acceptance must require HTTPS.');
$requires($live, "'method' => 'GET'", 'Stream fallback must use GET only.');
$requires($live, 'CURLOPT_FOLLOWLOCATION => false', 'Live requests must not follow redirects.');
$requires($live, "'content-security-policy'", 'Live acceptance must verify CSP.');
$requires($live, "'strict-transport-security'", 'Live acceptance must verify HSTS.');
$requires($live, "TL_ACCEPTANCE_PARTICIPANT_COOKIE", 'Participant session smoke test is required.');
$requires($live, "TL_ACCEPTANCE_REVIEWER_COOKIE", 'Reviewer session smoke test is required.');
$requires($live, "TL_ACCEPTANCE_MANAGER_COOKIE", 'Manager session smoke test is required.');
$requires($live, "TL_ACCEPTANCE_ADMIN_COOKIE", 'Administrator session smoke test is required.');
$requires($live, "'no_cookie_output' => true", 'Live acceptance must declare cookie-output safety.');
$forbids($live, 'CURLOPT_POST', 'Live acceptance must not perform POST requests.');
$forbids($live, '$_POST', 'Live acceptance must not accept web write input.');

$requires($cli, "'require-role-sessions'", 'CLI must support required role sessions.');
$requires($cli, "TL_PUBLIC_BASE_URL", 'CLI must support a protected base URL environment variable.');
$requires($cli, "exit(!empty(\$report['ready']) ? 0 : 1)", 'CLI must fail closed.');
$requires($page, "'required_role' => 'admin'", 'Production dashboard must be administrator-only.');
$requires($page, 'This dashboard performs reads only.', 'Production dashboard must state its read-only boundary.');
$requires($legacy, "tl_product_redirect('/admin/live-acceptance.php', 302)", 'Legacy deployment route must lead to production readiness.');
$requires($css, '@media(max-width:680px)', 'Production dashboard must support small screens.');
$requires($imports, "@import url('production-readiness.css')", 'Production readiness styles must be loaded.');

$requires($docs, 'Back up the current Training Lab application files.', 'Deployment guide must require file backups.');
$requires($docs, 'Back up the `ywzyeite_microlabs` database.', 'Deployment guide must require database backups.');
$requires($docs, 'Do **not** overwrite the existing `/labs/config.php`.', 'Deployment guide must preserve private config.');
$requires($docs, 'php ./bin/verify-release-package.php', 'Deployment guide must require package verification.');
$requires($docs, 'php ./bin/live-acceptance.php --require-role-sessions', 'Deployment guide must require role smoke tests.');
$requires($docs, 'Cookie values are used only as request headers and are never printed', 'Deployment guide must protect session cookies.');
$requires($docs, 'Rollback', 'Deployment guide must include rollback.');
$requires($docs, 'No SQL is required for Section 14.', 'Deployment guide must state the SQL boundary.');
$requires($runner, 'production-deployment-live-acceptance-contract-test.php', 'Local gate must run Section 14 contract.');
$requires($workflow, 'Production deployment and live acceptance contract', 'CI must run Section 14 contract.');

if ($failures) {
    fwrite(STDERR, "Production deployment and live acceptance contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "Production deployment and live acceptance contract passed.\n";
