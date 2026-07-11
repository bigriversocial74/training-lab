<?php
declare(strict_types=1);

$root = dirname(__DIR__);
putenv('TL_RESEND_WEBHOOK_ENABLED=true');
putenv('TL_RESEND_WEBHOOK_SECRET=whsec_plJ3nmyCDGBKInavdOK15jsl');
putenv('TL_RESEND_WEBHOOK_TOLERANCE_SECONDS=300');
putenv('TL_RESEND_WEBHOOK_MAX_BODY_BYTES=262144');
putenv('TL_NOTIFICATION_PROVIDER=resend');

require_once $root . '/includes/training-lab-resend-webhooks.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void { if (!$condition) $failures[] = $message; };
$read = static function (string $path) use ($root, &$failures): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) { $failures[] = $path . ' is missing.'; return ''; }
    return file_get_contents($full) ?: '';
};

$sql = $read('database/notification_provider_webhooks_v1.sql');
$service = $read('includes/training-lab-resend-webhooks.php');
$endpoint = $read('api/webhooks/resend.php');
$page = $read('admin/email-webhooks.php');
$cli = $read('bin/webhook-reconciliation-check.php');
$config = $read('labs/config-example.php');
$nav = $read('includes/training-lab-product-shell.php');
$acceptance = $read('includes/training-lab-product-acceptance.php');
$docs = $read('docs/RESEND-WEBHOOKS-DELIVERY-RECONCILIATION-V1.md');

$assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS training_notification_provider_events'), 'Provider event ledger table must be additive.');
$assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS training_notification_provider_states'), 'Provider current-state table must be additive.');
$assert(str_contains($sql, 'UNIQUE KEY uq_training_notification_provider_events_svix_hash'), 'Hashed svix-id replay keys must be unique.');
$assert(str_contains($sql, 'UNIQUE KEY uq_training_notification_provider_states_outbox'), 'Only one current provider state may exist per outbox item.');
$assert(!str_contains($sql, 'ALTER TABLE users') && !str_contains($sql, 'ALTER TABLE wallets'), 'Webhook migration must not alter Microgifter authority tables.');

$assert(str_contains($service, '$id . \'.\' . $timestamp . \'.\' . $rawBody'), 'Signature input must use id.timestamp.raw-body.');
$assert(str_contains($service, "hash_hmac('sha256'") && str_contains($service, 'hash_equals'), 'Verification must use HMAC-SHA256 and constant-time comparison.');
$assert(str_contains($service, 'str_starts_with($secret, \'whsec_\')'), 'Webhook secret must use the whsec_ format.');
$assert(str_contains($service, 'str_starts_with($candidate, \'v1,\')'), 'Only v1 signature candidates may be accepted.');
$assert(str_contains($service, 'abs($clock - $timestampValue)'), 'Webhook timestamp tolerance must be enforced.');
$assert(str_contains($service, 'duplicate_count=duplicate_count+1'), 'Duplicate deliveries must be acknowledged without reapplying state.');
$assert(str_contains($service, 'tl_resend_webhook_should_apply'), 'Out-of-order reconciliation must use an explicit ordering decision.');
$assert(str_contains($service, "'email.delivery_delayed'") && str_contains($service, "'email.bounced'") && str_contains($service, "'email.complained'"), 'Delivery, delay, bounce, and complaint events must be supported.');
$assert(str_contains($service, 'provider_message_hash') && str_contains($service, 'hash(\'sha256\', $messageId)'), 'Provider messages must correlate by SHA-256 hash only.');
$assert(str_contains($service, 'tl_resend_webhook_add_suppression') && str_contains($service, "'hard_bounce'") && str_contains($service, "'complaint'"), 'Bounces and complaints must create suppressions.');
$assert(str_contains($service, "raw_payload_stored'=>false") && str_contains($service, "recipient_address_stored'=>false") && str_contains($service, "provider_message_id_stored'=>false"), 'Persisted webhook metadata must explicitly exclude raw and identifying values.');
$assert(!preg_match('/\bmail\s*\(/i', $service) && !str_contains($service, 'curl_init('), 'Webhook reconciliation must not send email or make outbound HTTP calls.');
$assert(!str_contains($service, 'microgifter_issue') && !str_contains($service, 'wallet_balance'), 'Webhook reconciliation must not create Microgifter or wallet authority.');

$examplePayload = '{"event_type":"ping","data":{"success":true}}';
$verified = tl_resend_webhook_verify($examplePayload, [
    'id'=>'msg_loFOjxBNrRLzqYUf',
    'timestamp'=>'1731705121',
    'signature'=>'v1,rAvfW3dJ/X/qxhsaXPOyyCGmRKsaKWcsNccKXlIktD0=',
], 1731705121);
$assert(strlen((string)$verified['id_hash']) === 64 && strlen((string)$verified['payload_hash']) === 64, 'Official Svix verification fixture must produce hashed identifiers.');

$multi = tl_resend_webhook_verify($examplePayload, [
    'id'=>'msg_loFOjxBNrRLzqYUf',
    'timestamp'=>'1731705121',
    'signature'=>'v1,AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= v1,rAvfW3dJ/X/qxhsaXPOyyCGmRKsaKWcsNccKXlIktD0=',
], 1731705121);
$assert($multi['timestamp'] === 1731705121, 'Any valid v1 signature in a space-delimited signature list must be accepted.');

try {
    tl_resend_webhook_verify($examplePayload . ' ', [
        'id'=>'msg_loFOjxBNrRLzqYUf','timestamp'=>'1731705121','signature'=>'v1,rAvfW3dJ/X/qxhsaXPOyyCGmRKsaKWcsNccKXlIktD0=',
    ], 1731705121);
    $failures[] = 'A modified raw webhook body was accepted.';
} catch (TlHttpException $error) {
    $assert($error->errorCode() === 'resend_webhook_signature_invalid', 'Modified raw bodies must fail signature verification.');
}

try {
    tl_resend_webhook_verify($examplePayload, [
        'id'=>'msg_loFOjxBNrRLzqYUf','timestamp'=>'1731705121','signature'=>'v1,rAvfW3dJ/X/qxhsaXPOyyCGmRKsaKWcsNccKXlIktD0=',
    ], 1731706122);
    $failures[] = 'A stale webhook timestamp was accepted.';
} catch (TlHttpException $error) {
    $assert($error->errorCode() === 'resend_webhook_timestamp_stale', 'Stale webhook timestamps must fail closed.');
}

$eventBody = json_encode([
    'type'=>'email.bounced',
    'created_at'=>'2026-07-10T12:00:00Z',
    'data'=>[
        'email_id'=>'provider-message-123',
        'to'=>['Person@Example.com'],
        'subject'=>'Private subject must not persist',
        'bounce'=>['type'=>'Permanent','subType'=>'General','message'=>'Private provider detail'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$event = tl_resend_webhook_normalize($eventBody);
$assert($event['delivery_status'] === 'bounced' && $event['event_rank'] === 60, 'Bounce events must normalize to terminal bounce state.');
$assert(strlen((string)$event['provider_message_hash']) === 64 && strlen((string)$event['recipient_hash']) === 64, 'Provider and recipient identifiers must normalize to hashes.');
$metadataJson = json_encode($event['metadata'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$assert(!str_contains($metadataJson, 'Person@Example.com') && !str_contains($metadataJson, 'Private subject') && !str_contains($metadataJson, 'Private provider detail'), 'Normalized metadata must exclude addresses, subjects, and provider messages.');
$assert(tl_resend_webhook_should_apply(['event_occurred_at'=>'2026-07-10 12:01:00','event_rank'=>30], $event) === false, 'An older event must not downgrade newer state.');
$assert(tl_resend_webhook_should_apply(['event_occurred_at'=>'2026-07-10 12:00:00','event_rank'=>30], $event) === true, 'A stronger event at the same timestamp must apply.');

$assert(str_contains($endpoint, "tl_security_require_method('POST')") && str_contains($endpoint, "file_get_contents('php://input')"), 'Webhook route must be POST-only and read the raw body.');
$assert(str_contains($endpoint, 'tl_resend_webhook_ingest($rawBody'), 'Webhook route must pass the unchanged raw body to verification and ingestion.');
$assert(!str_contains($endpoint, 'tl_security_guard_write') && !str_contains($endpoint, 'tl_security_validate_origin'), 'Provider webhooks must authenticate by signature rather than browser session or origin.');
$assert(str_contains($page, "'required_role'=>'admin'") && !str_contains($page, "['email']"), 'Webhook diagnostics must be administrator-only and must not display addresses.');
$assert(str_contains($cli, "['json','limit::']") && str_contains($cli, "'read_only'=>true"), 'Webhook CLI must be read-only and support JSON output.');
$assert(str_contains($config, "'resend_webhook_enabled' => false") && str_contains($config, "'resend_webhook_secret' => 'DO_NOT_COMMIT_A_REAL_SECRET'"), 'Webhook gate must default off and the secret must be documented without a real value.');
$assert(str_contains($nav, 'admin-email-webhooks'), 'Administrator navigation must expose webhook monitoring.');
$assert(str_contains($acceptance, 'training-lab-resend-webhooks.php') && str_contains($acceptance, 'notification_provider_webhooks_v1.sql'), 'Canonical product acceptance must include Section 17 service and migration.');
$assert(str_contains($docs, 'Safe activation order') && str_contains($docs, 'Rollback') && str_contains($docs, 'no outbound HTTP request'), 'Section 17 documentation must include activation, rollback, and no-outbound boundaries.');

if ($failures) {
    fwrite(STDERR, "Resend Webhooks + Delivery Reconciliation contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "Resend Webhooks + Delivery Reconciliation contract passed.\n";
