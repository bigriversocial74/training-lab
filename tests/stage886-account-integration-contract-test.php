<?php
declare(strict_types=1);

$root = dirname(__DIR__);
putenv('TL_IDENTITY_SHARED_SECRET=stage886-test-secret-0123456789-abcdef');
putenv('TL_IDENTITY_ISSUER=microgifter.test');
putenv('TL_IDENTITY_AUDIENCE=training-lab-test');
putenv('TL_IDENTITY_MAX_TTL=180');
putenv('TL_IDENTITY_CLOCK_SKEW=5');
require_once $root . '/includes/training-lab-stage886-identity-token.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void { if (!$condition) $failures[] = $message; };
$encode = static fn(string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
$make = static function (array $payload) use ($encode): string {
    $header = $encode(json_encode(['alg'=>'HS256','typ'=>'TL-ID'], JSON_THROW_ON_ERROR));
    $body = $encode(json_encode($payload, JSON_THROW_ON_ERROR));
    $signature = hash_hmac('sha256', $header . '.' . $body, 'stage886-test-secret-0123456789-abcdef', true);
    return $header . '.' . $body . '.' . $encode($signature);
};
$now = time();
$claims = ['iss'=>'microgifter.test','aud'=>'training-lab-test','sub'=>'42','email'=>'user@example.test','name'=>'Test User','role'=>'reviewer','merchant'=>'merchant-7','organization'=>'org-2','iat'=>$now,'exp'=>$now+120,'nonce'=>'nonce-0123456789abcdef','jti'=>'token-1'];

try {
    $verified = tl_stage886_verify_token($make($claims), $now);
    $assert($verified['microgifter_user_id'] === '42', 'Valid token subject must be retained.');
    $assert($verified['role'] === 'reviewer', 'Allowed connected role must be retained.');
    $assert($verified['merchant_context'] === 'merchant-7', 'Merchant context must be retained.');
} catch (Throwable $e) { $failures[] = 'Valid token rejected: ' . $e->getMessage(); }

$cases = [
    'expired'=>array_merge($claims, ['iat'=>$now-240,'exp'=>$now-60,'nonce'=>'nonce-expired-123456789']),
    'wrong audience'=>array_merge($claims, ['aud'=>'other-app','nonce'=>'nonce-audience-12345678']),
    'long ttl'=>array_merge($claims, ['exp'=>$now+400,'nonce'=>'nonce-longttl-123456789']),
];
foreach ($cases as $label=>$payload) {
    try { tl_stage886_verify_token($make($payload), $now); $failures[] = ucfirst($label) . ' token was accepted.'; }
    catch (TlHttpException $e) { $assert($e->httpStatus() === 401, ucfirst($label) . ' token must return HTTP 401.'); }
}

$valid = $make($claims);
$parts = explode('.', $valid, 3);
if (count($parts) === 3 && $parts[2] !== '') {
    $parts[2][0] = $parts[2][0] === 'a' ? 'b' : 'a';
}
$tampered = implode('.', $parts);
try { tl_stage886_verify_token($tampered, $now); $failures[] = 'Tampered token was accepted.'; }
catch (TlHttpException $e) { $assert($e->errorCode() === 'identity_signature_invalid', 'Tampered token must fail signature verification.'); }

$sql = file_get_contents($root . '/database/stage886_shared_account_integration_v1.sql') ?: '';
$service = file_get_contents($root . '/includes/training-lab-stage886-account-integration.php') ?: '';
$assert(str_contains($sql, 'training_account_links') && str_contains($sql, 'training_auth_nonces'), 'Stage 886 SQL must create both required tables.');
$assert(str_contains($sql, 'UNIQUE KEY uq_training_auth_nonces_hash'), 'Nonce hash must be unique for replay protection.');
$assert(str_contains($service, 'FOR UPDATE') && str_contains($service, 'identity_replay_rejected'), 'Nonce consumption must use locking and explicit replay rejection.');
$assert(str_contains($service, 'session_regenerate_id(true)'), 'Trusted handoff must regenerate the session ID.');
$assert(is_file($root . '/api/training/account-link.php') && is_file($root . '/admin/account-integration.php'), 'Receiver and diagnostics routes must exist.');

if ($failures) {
    fwrite(STDERR, "Stage 886 account integration contract test failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "Stage 886 account integration contract test passed.\n";
