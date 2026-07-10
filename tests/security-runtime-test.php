<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/training-lab-security.php';
require_once dirname(__DIR__) . '/includes/training-lab-auth-gate.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'labs.example.test';
$_SERVER['HTTP_ORIGIN'] = 'https://labs.example.test';
tl_security_session_start();

$token = tl_security_csrf_token();
$assert(strlen($token) === 64, 'CSRF token must contain 32 random bytes.');
$assert(hash_equals($token, tl_security_csrf_token()), 'CSRF token must be stable inside a session.');
$assert(str_starts_with(tl_security_csrf_field(), '<input type="hidden"'), 'CSRF field must render a hidden input.');

$assert(tl_auth_safe_path('/app/index.php') === '/app/index.php', 'Safe internal paths must be retained.');
$assert(tl_auth_safe_path('https://evil.example', '/signin.php') === '/signin.php', 'Absolute redirects must be rejected.');
$assert(tl_auth_safe_path("/ok\nLocation: x", '/signin.php') === '/signin.php', 'Header-injection paths must be rejected.');

$local = ['id'=>'local-1','role'=>'admin','source'=>'training_lab_demo_session'];
$trusted = ['id'=>'42','role'=>'admin','source'=>'existing_microgifter_session','microgifter_user_id'=>'42'];
$assert(tl_security_trusted_role($local) === 'participant', 'Untrusted local sessions must not self-elevate.');
$assert(tl_security_trusted_role($trusted) === 'admin', 'Trusted Microgifter sessions must retain approved roles.');
$assert(!in_array('training.proof.review', tl_security_role_permissions('participant'), true), 'Participants must not review proof.');
$assert(in_array('training.proof.review', tl_security_role_permissions('reviewer'), true), 'Reviewers must receive proof-review permission.');

try {
    tl_security_validate_origin();
} catch (Throwable $e) {
    $failures[] = 'Same-origin POST was rejected: ' . $e->getMessage();
}
$_SERVER['HTTP_ORIGIN'] = 'https://evil.example';
try {
    tl_security_validate_origin();
    $failures[] = 'Cross-origin POST was not rejected.';
} catch (TlHttpException $e) {
    $assert($e->httpStatus() === 403, 'Cross-origin rejection must use HTTP 403.');
}
$_SERVER['HTTP_ORIGIN'] = 'https://labs.example.test';

$_SESSION['training_lab_user'] = ['id'=>'local-2','name'=>'Local','email'=>'local@example.test','role'=>'admin','source'=>'training_lab_demo_session'];
$assert((tl_auth_current_user()['role'] ?? '') === 'participant', 'Local session role must be normalized to participant.');

try {
    tl_security_verify_csrf(['_csrf'=>$token]);
} catch (Throwable $e) {
    $failures[] = 'Valid CSRF token was rejected: ' . $e->getMessage();
}
try {
    tl_security_verify_csrf(['_csrf'=>'invalid']);
    $failures[] = 'Invalid CSRF token was accepted.';
} catch (TlHttpException $e) {
    $assert($e->httpStatus() === 419, 'Invalid CSRF must use HTTP 419.');
}

if ($failures) {
    fwrite(STDERR, "Security runtime test failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Security runtime test passed.\n";
