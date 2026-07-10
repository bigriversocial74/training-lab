<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$actions = file_get_contents($root . '/includes/training-lab-actions.php') ?: '';
$security = file_get_contents($root . '/includes/training-lab-security.php') ?: '';
$failures = [];
$requires = static function (string $haystack, string $needle, string $message) use (&$failures): void {
    if (!str_contains($haystack, $needle)) $failures[] = $message;
};

$requires($actions, 'campaign_id = ? AND (id = ? OR public_id = ?)', 'Proof task selection must be scoped to the selected campaign.');
$requires($actions, 'FOR UPDATE', 'Mutation paths must use row locking for concurrency-sensitive decisions.');
$requires($actions, 'random_bytes(32)', 'Receipt verification material must use cryptographic randomness.');
$requires($actions, 'tl_action_external_url', 'External proof URLs must be restricted to HTTP(S).');
$requires($actions, 'JSON_THROW_ON_ERROR', 'Database metadata encoding must fail safely.');
$requires($security, 'tl_security_guard_write', 'A centralized write guard is required.');
$requires($security, 'tl_security_verify_csrf', 'CSRF validation is required.');
$requires($security, 'tl_security_authorize_action', 'Action authorization is required.');
$requires($security, 'tl_security_validate_origin', 'Same-origin validation is required.');
$requires($security, 'tl_security_error_payload', 'Safe production error payloads are required.');

$writeRoutes = [
    'api/training/app-action.php',
    'api/training/proof-review-workflow.php',
    'admin/action-result.php',
    'app/action-result.php',
];
foreach ($writeRoutes as $route) {
    $content = file_get_contents($root . '/' . $route) ?: '';
    if (!str_contains($content, 'tl_security_guard_write')) $failures[] = $route . ' must call the centralized write guard.';
    if (!str_contains($content, 'tl_security_apply_actor')) $failures[] = $route . ' must replace client-supplied actor IDs.';
}

$actionDir = $root . '/api/training/actions';
foreach (glob($actionDir . '/*.php') ?: [] as $path) {
    if (basename($path) === '_action-bootstrap.php') continue;
    $content = file_get_contents($path) ?: '';
    if (!str_contains($content, '_action-bootstrap.php') || !str_contains($content, 'tl_action_wrap')) {
        $failures[] = str_replace($root . '/', '', $path) . ' must use the shared action bootstrap.';
    }
}

if ($failures) {
    fwrite(STDERR, "Data-integrity contract test failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Data-integrity contract test passed.\n";
