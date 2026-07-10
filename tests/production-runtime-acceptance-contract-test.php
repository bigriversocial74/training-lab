<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/training-lab-security.php';
require_once $root . '/includes/training-lab-auth-gate.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    return is_file($full) ? (file_get_contents($full) ?: '') : '';
};

$module = $read('includes/training-lab-production-runtime-acceptance.php');
$adminRoute = $read('admin/runtime-acceptance.php');
$apiRoute = $read('api/training/runtime-acceptance.php');
$runner = $read('run-quality-gate.sh');

$assert($module !== '', 'Production runtime acceptance module must exist.');
$assert($adminRoute !== '', 'Protected admin runtime acceptance route must exist.');
$assert($apiRoute !== '', 'Protected JSON runtime acceptance route must exist.');
$assert(str_contains($module, 'tl_runtime_acceptance_summary'), 'Runtime summary function must be present.');
$assert(str_contains($module, 'ready_for_live_probe') && str_contains($module, "'accepted'=>$accepted"), 'Acceptance must separate base readiness from final live-probe acceptance.');
$assert(str_contains($module, 'CURLOPT_SSL_VERIFYPEER') && str_contains($module, 'CURLOPT_FOLLOWLOCATION=>false'), 'Live probes must verify TLS and reject redirects.');
$assert(str_contains($module, 'same_origin_get_probes_only') && str_contains($module, 'no_microgifter_reward_issuing'), 'Read-only safety boundaries must be explicit.');
$assert(!preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE|TRUNCATE|DROP|ALTER)\s+/i', $module), 'Runtime acceptance module must not contain mutating SQL.');
$assert(str_contains($adminRoute, "tl_auth_role_allowed(\$user, 'manager')"), 'Admin diagnostics must require manager or administrator authority.');
$assert(str_contains($apiRoute, 'runtime_acceptance_forbidden') && str_contains($apiRoute, "tl_security_require_method('GET')"), 'Runtime API must be protected and GET-only.');
$assert(str_contains($runner, 'production-runtime-acceptance-contract-test.php'), 'Main quality runner must execute the runtime acceptance contract test.');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'labs.example.test';
$_SERVER['HTTP_ORIGIN'] = 'https://labs.example.test';
tl_security_session_start();
$originalSession = $_SESSION;

$_SESSION = [];
try {
    tl_security_authorize_action('complete_task');
    $failures[] = 'Anonymous write authorization was not rejected.';
} catch (TlHttpException $e) {
    $assert($e->httpStatus() === 401 && $e->errorCode() === 'authentication_required', 'Anonymous writes must return the authentication-required contract.');
}

$_SESSION = [
    'training_lab_user' => [
        'id' => 'local-participant',
        'name' => 'Local Participant',
        'email' => 'participant@example.test',
        'role' => 'admin',
        'source' => 'training_lab_demo_session',
    ],
];
try {
    $participant = tl_security_authorize_action('complete_task');
    $assert(($participant['role'] ?? '') === 'participant', 'Local sessions must remain participant authority.');
} catch (Throwable $e) {
    $failures[] = 'Participant action was rejected: ' . $e->getMessage();
}
try {
    tl_security_authorize_action('review_proof');
    $failures[] = 'Local participant was allowed to review proof.';
} catch (TlHttpException $e) {
    $assert($e->httpStatus() === 403 && $e->errorCode() === 'permission_denied', 'Participant proof review must return permission denied.');
}

$_SESSION = [
    'microgifter_user_id' => '42',
    'name' => 'Trusted Reviewer',
    'email' => 'reviewer@example.test',
    'role' => 'reviewer',
];
try {
    $reviewer = tl_security_authorize_action('review_proof');
    $assert(($reviewer['role'] ?? '') === 'reviewer', 'Trusted Microgifter reviewer must retain review authority.');
} catch (Throwable $e) {
    $failures[] = 'Trusted reviewer action was rejected: ' . $e->getMessage();
}
try {
    tl_security_authorize_action('create_campaign');
    $failures[] = 'Reviewer was allowed to manage campaigns.';
} catch (TlHttpException $e) {
    $assert($e->httpStatus() === 403, 'Reviewer campaign management must be denied.');
}

$_SESSION = $originalSession;
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

if ($failures) {
    fwrite(STDERR, "Production runtime acceptance contract test failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Production runtime acceptance contract test passed.\n";
