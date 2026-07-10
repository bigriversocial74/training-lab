<?php
declare(strict_types=1);

$root = dirname(__DIR__);
putenv('TL_IDENTITY_SHARED_SECRET=stage889-test-secret-0123456789-abcdef');
putenv('TL_IDENTITY_ISSUER=microgifter.test');
putenv('TL_IDENTITY_AUDIENCE=training-lab-test');
putenv('TL_IDENTITY_MAX_TTL=180');
putenv('TL_IDENTITY_CLOCK_SKEW=5');
putenv('TL_IDENTITY_SESSION_TTL=28800');
putenv('TL_IDENTITY_SESSION_IDLE_TTL=3600');
require_once $root . '/includes/training-lab-stage886-account-integration.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$config = tl_stage886_config();
$assert((int)$config['max_ttl'] === 180, 'Assertion TTL must remain independently capped.');
$assert((int)$config['session_ttl'] === 28800, 'Trusted session TTL must be configurable independently.');
$assert((int)$config['session_idle_ttl'] === 3600, 'Trusted session idle TTL must be configurable independently.');

$now = 1700000000;
$deadlines = tl_stage889_session_deadlines($now);
$assert((int)$deadlines['expires_at'] === $now + 28800, 'Session deadline must use the trusted session TTL.');
$assert((int)$deadlines['last_seen_at'] === $now, 'Session activity clock must start at handoff acceptance.');

$assertionExpiredButSessionActive = [
    'source'=>'microgifter_adapter',
    'identity_assertion_expires_at'=>$now - 60,
    'session_expires_at'=>$now + 3600,
    'session_last_seen_at'=>$now - 30,
    'session_idle_ttl'=>3600,
];
$assert(
    tl_stage889_session_expiry_reason($assertionExpiredButSessionActive, $now) === null,
    'A trusted session must survive expiration of the one-time identity assertion.'
);

$absoluteExpired = $assertionExpiredButSessionActive;
$absoluteExpired['session_expires_at'] = $now;
$assert(
    tl_stage889_session_expiry_reason($absoluteExpired, $now) === 'absolute_timeout',
    'Trusted sessions must expire at their own absolute deadline.'
);

$idleExpired = $assertionExpiredButSessionActive;
$idleExpired['session_last_seen_at'] = $now - 3600;
$assert(
    tl_stage889_session_expiry_reason($idleExpired, $now) === 'idle_timeout',
    'Trusted sessions must expire after the configured idle timeout.'
);

$assert(tl_account_bridge_normalize_role('unknown-browser-role') === 'participant', 'Unknown roles must downgrade to participant.');

$link = [
    'id'=>7,
    'public_id'=>'11111111-2222-4333-8444-555555555555',
    'training_user_id'=>42,
];
$claims = [
    'microgifter_user_id'=>'42',
    'name'=>'Session Test User',
    'email'=>'session@example.test',
    'role'=>'manager',
    'merchant_context'=>'merchant-7',
    'organization_context'=>'organization-2',
    'issuer'=>'microgifter.test',
    'audience'=>'training-lab-test',
    'issued_at'=>time(),
    'expires_at'=>time() + 120,
];
$trusted = tl_stage886_create_trusted_session($link, $claims);
$assert((int)$trusted['session_expires_at'] > (int)$trusted['identity_assertion_expires_at'], 'Trusted session must outlive the assertion window.');
$assert((int)$trusted['session_idle_ttl'] === 3600, 'Trusted session must retain the configured idle timeout.');
$assert((string)$trusted['merchant_context'] === 'merchant-7', 'Signed merchant context must be retained.');
$assert(!array_key_exists('password', $trusted) && !array_key_exists('password_hash', $trusted), 'Trusted sessions must never contain password credentials.');

$secondClaims = $claims;
$secondClaims['microgifter_user_id'] = '43';
$secondClaims['email'] = 'fresh-session@example.test';
$secondTrusted = tl_stage886_create_trusted_session(['id'=>8,'public_id'=>'66666666-7777-4888-8999-aaaaaaaaaaaa','training_user_id'=>43], $secondClaims);
$assert((string)$secondTrusted['microgifter_user_id'] === '43', 'A new valid assertion must create a fresh trusted session.');
$assert((int)$secondTrusted['session_expires_at'] > (int)$secondTrusted['identity_assertion_expires_at'], 'Fresh sessions must use the independent session TTL.');

$service = file_get_contents($root . '/includes/training-lab-stage886-account-integration.php') ?: '';
$authGate = file_get_contents($root . '/includes/training-lab-auth-gate.php') ?: '';
$admin = file_get_contents($root . '/includes/training-lab-stage886-admin.php') ?: '';
$configExample = file_get_contents($root . '/labs/config-example.php') ?: '';
$assert(str_contains($service, 'expires_at=NULL'), 'Successful handoff must clear legacy assertion-based link expiration.');
$assert(!str_contains($service, "strtotime((string)$link['expires_at'])"), 'Session validation must not expire persistent links using assertion timestamps.');
$assert(str_contains($service, "link_status'] !== 'active'"), 'Every signed session validation must reject inactive links.');
$assert(str_contains($authGate, 'tl_auth_signed_identity_configured'), 'Signed configuration must disable raw legacy session trust.');
$assert(str_contains($authGate, "training-lab-stage886-account-integration.php"), 'Signed session validation must lazy-load across all authenticated pages.');
$assert(str_contains($admin, 'session_ttl_seconds') && str_contains($admin, 'current_session'), 'Diagnostics must expose the independent session policy.');
$assert(str_contains($configExample, 'identity_session_ttl_seconds') && str_contains($configExample, 'identity_session_idle_ttl_seconds'), 'Deployment config must document both session controls.');

if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION = [];
    session_destroy();
}

if ($failures) {
    fwrite(STDERR, "Stage 889 shared session hardening contract test failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Stage 889 shared session hardening contract test passed.\n";
