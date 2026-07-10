<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$secret = str_repeat('stage886-production-test-secret-', 2);
putenv('TL_ACCOUNT_BRIDGE_SECRET=' . $secret);
putenv('TL_ACCOUNT_BRIDGE_PREVIOUS_SECRET');
putenv('TL_ACCOUNT_BRIDGE_ISSUER=https://microgifter.example.test');
putenv('TL_ACCOUNT_BRIDGE_AUDIENCE=training-lab-test');
putenv('TL_ACCOUNT_BRIDGE_MAX_TTL=300');
putenv('TL_ACCOUNT_BRIDGE_CLOCK_SKEW=30');
putenv('TL_ACCOUNT_BRIDGE_SESSION_TTL=28800');

require_once $root . '/includes/training-lab-stage886-account-integration.php';
require_once $root . '/includes/training-lab-stage886-session-policy.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    return is_file($full) ? (file_get_contents($full) ?: '') : '';
};
$encode = static fn(string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
$sign = static function (array $claims, string $key) use ($encode): string {
    $payload = $encode(json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $signature = $encode(hash_hmac('sha256', 'v1.' . $payload, $key, true));
    return 'v1.' . $payload . '.' . $signature;
};

$now = time();
$claims = [
    'iss'=>'https://microgifter.example.test',
    'aud'=>'training-lab-test',
    'sub'=>'42',
    'jti'=>'nonce-' . bin2hex(random_bytes(16)),
    'iat'=>$now,
    'exp'=>$now + 180,
    'email'=>'reviewer@example.test',
    'name'=>'Trusted Reviewer',
    'role'=>'merchant_admin',
    'merchant_id'=>'merchant-7',
    'organization_id'=>'org-3',
];

try {
    $verified = tl_stage886_verify_assertion($sign($claims, $secret));
    $assert($verified['microgifter_user_id'] === '42', 'Verified subject must be retained.');
    $assert($verified['role'] === 'admin', 'Approved role aliases must normalize to the Training Lab allowlist.');
    $assert($verified['merchant_id'] === 'merchant-7', 'Merchant context must be retained.');
    $assert($verified['secret_version'] === 'current', 'Current secret must be identified.');
} catch (Throwable $e) {
    $failures[] = 'Valid assertion was rejected: ' . $e->getMessage();
}

try {
    tl_stage886_verify_assertion($sign($claims, str_repeat('wrong-secret-', 4)));
    $failures[] = 'Forged assertion signature was accepted.';
} catch (TlHttpException $e) {
    $assert($e->errorCode() === 'assertion_signature_invalid', 'Forged assertion must return signature-invalid.');
}

$wrongAudience = $claims;
$wrongAudience['jti'] = 'nonce-' . bin2hex(random_bytes(16));
$wrongAudience['aud'] = 'another-service';
try {
    tl_stage886_verify_assertion($sign($wrongAudience, $secret));
    $failures[] = 'Wrong-audience assertion was accepted.';
} catch (TlHttpException $e) {
    $assert($e->errorCode() === 'assertion_audience_invalid', 'Wrong audience must be rejected.');
}

$expired = $claims;
$expired['jti'] = 'nonce-' . bin2hex(random_bytes(16));
$expired['iat'] = $now - 300;
$expired['exp'] = $now - 120;
try {
    tl_stage886_verify_assertion($sign($expired, $secret));
    $failures[] = 'Expired assertion was accepted.';
} catch (TlHttpException $e) {
    $assert($e->errorCode() === 'assertion_expired', 'Expired assertion must return assertion-expired.');
}

$longLived = $claims;
$longLived['jti'] = 'nonce-' . bin2hex(random_bytes(16));
$longLived['exp'] = $now + 600;
try {
    tl_stage886_verify_assertion($sign($longLived, $secret));
    $failures[] = 'Overlong assertion lifetime was accepted.';
} catch (TlHttpException $e) {
    $assert($e->errorCode() === 'assertion_ttl_invalid', 'Overlong assertion must return TTL-invalid.');
}

$installSql = $read('database/stage886_shared_account_integration_v1.sql');
$rollbackSql = $read('database/stage886_shared_account_integration_v1_rollback.sql');
$service = $read('includes/training-lab-stage886-account-integration.php');
$sessionPolicy = $read('includes/training-lab-stage886-session-policy.php');
$authGate = $read('includes/training-lab-auth-gate.php');
$runner = $read('run-quality-gate.sh');

$assert(str_contains($installSql, 'CREATE TABLE IF NOT EXISTS training_account_links'), 'Install SQL must create account links.');
$assert(str_contains($installSql, 'CREATE TABLE IF NOT EXISTS training_auth_nonces'), 'Install SQL must create auth nonces.');
$assert(str_contains($installSql, 'UNIQUE KEY uq_training_auth_nonces_nonce_hash'), 'Nonce replay prevention must be database-enforced.');
$assert(str_contains($installSql, 'FOREIGN KEY (account_link_id)'), 'Nonce audit rows must reference account links.');
$assert(str_contains($rollbackSql, 'DROP TABLE IF EXISTS training_auth_nonces') && str_contains($rollbackSql, 'DROP TABLE IF EXISTS training_account_links'), 'Rollback SQL must remove both Stage 886 tables in dependency order.');
$assert(str_contains($service, "hash_hmac('sha256'") && str_contains($service, 'hash_equals'), 'Assertion signatures must use HMAC-SHA256 and timing-safe comparison.');
$assert(str_contains($service, 'LIMIT 1 FOR UPDATE'), 'Account links must be row-locked during assertion consumption.');
$assert(str_contains($service, 'assertion_replayed'), 'Replay attempts must have a machine-readable rejection code.');
$assert(str_contains($service, 'session_regenerate_id(true)'), 'Trusted session creation must rotate the session ID.');
$assert(str_contains($service, "'source' => 'microgifter_assertion'"), 'Trusted sessions must have an explicit assertion source.');
$assert(tl_stage886_session_ttl_seconds() === 28800, 'Trusted-session TTL must be independent from assertion TTL.');
$assert(str_contains($sessionPolicy, 'TL_ACCOUNT_BRIDGE_SESSION_TTL') && str_contains($sessionPolicy, "link_status='active'"), 'Session policy must be configurable and update active links only.');
$assert(str_contains($authGate, 'tl_stage886_current_principal'), 'Auth gate must resolve verified Stage 886 principals first.');
$assert(str_contains($authGate, 'if (function_exists(\'tl_stage886_enabled\') && tl_stage886_enabled()) return null;'), 'Configured Stage 886 must close legacy raw-session fallback.');
$assert(is_file($root . '/account-link.php'), 'Browser account-link receiver must exist.');
$assert(is_file($root . '/admin/account-integration.php'), 'Admin account integration console must exist.');
$assert(is_file($root . '/api/training/account-link.php'), 'Account-link API must exist.');
$assert(is_file($root . '/api/training/account-integration-status.php'), 'Integration status API must exist.');
$assert(is_file($root . '/examples/microgifter-stage886-emitter.php'), 'Microgifter emitter reference must exist.');
$assert(str_contains($runner, 'stage886-account-integration-contract-test.php'), 'Main quality runner must execute Stage 886 contracts.');

if ($failures) {
    fwrite(STDERR, "Stage 886 account integration contract test failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Stage 886 account integration contract test passed.\n";
