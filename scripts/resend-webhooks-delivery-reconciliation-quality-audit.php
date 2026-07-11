<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$has = static fn(string $path, string $needle): bool => str_contains($read($path), $needle);
$lacks = static fn(string $path, string $needle): bool => !str_contains($read($path), $needle);
$exists = static fn(string $path): bool => is_file($root . '/' . $path);

$sections = [
    'Additive schema and replay safety' => [
        $has('database/notification_provider_webhooks_v1.sql', 'CREATE TABLE IF NOT EXISTS training_notification_provider_events'),
        $has('database/notification_provider_webhooks_v1.sql', 'CREATE TABLE IF NOT EXISTS training_notification_provider_states'),
        $has('database/notification_provider_webhooks_v1.sql', 'UNIQUE KEY uq_training_notification_provider_events_svix_hash'),
        $has('database/notification_provider_webhooks_v1.sql', 'UNIQUE KEY uq_training_notification_provider_states_outbox'),
    ],
    'Signature and timestamp verification' => [
        $has('includes/training-lab-resend-webhooks.php', "hash_hmac('sha256'"),
        $has('includes/training-lab-resend-webhooks.php', 'hash_equals'),
        $has('includes/training-lab-resend-webhooks.php', 'str_starts_with($candidate, \'v1,\')'),
        $has('includes/training-lab-resend-webhooks.php', 'abs($clock - $timestampValue)'),
    ],
    'Raw-body and endpoint discipline' => [
        $has('api/webhooks/resend.php', "file_get_contents('php://input')"),
        $has('api/webhooks/resend.php', "tl_security_require_method('POST')"),
        $has('api/webhooks/resend.php', 'tl_resend_webhook_ingest($rawBody'),
        $lacks('api/webhooks/resend.php', 'tl_security_guard_write'),
    ],
    'Lifecycle and ordering' => [
        $has('includes/training-lab-resend-webhooks.php', "'email.sent'"),
        $has('includes/training-lab-resend-webhooks.php', "'email.delivery_delayed'"),
        $has('includes/training-lab-resend-webhooks.php', "'email.bounced'"),
        $has('includes/training-lab-resend-webhooks.php', 'tl_resend_webhook_should_apply'),
    ],
    'Correlation and privacy' => [
        $has('includes/training-lab-resend-webhooks.php', 'hash(\'sha256\', $messageId)'),
        $has('includes/training-lab-resend-webhooks.php', "raw_payload_stored'=>false"),
        $has('includes/training-lab-resend-webhooks.php', "recipient_address_stored'=>false"),
        $has('includes/training-lab-resend-webhooks.php', "provider_message_id_stored'=>false"),
    ],
    'Suppression and terminal outcomes' => [
        $has('includes/training-lab-resend-webhooks.php', 'tl_resend_webhook_add_suppression'),
        $has('includes/training-lab-resend-webhooks.php', "'hard_bounce'"),
        $has('includes/training-lab-resend-webhooks.php', "'complaint'"),
        $has('includes/training-lab-resend-webhooks.php', "outbox_status='suppressed'"),
    ],
    'Administrator operations' => [
        $has('admin/email-webhooks.php', "'required_role'=>'admin'"),
        $has('admin/email-webhooks.php', 'Recent webhook events'),
        $has('bin/webhook-reconciliation-check.php', "'read_only'=>true"),
        $has('includes/training-lab-product-shell.php', 'admin-email-webhooks'),
    ],
    'Disabled-first deployment' => [
        $has('labs/config-example.php', "'resend_webhook_enabled' => false"),
        $has('labs/config-example.php', "'resend_webhook_tolerance_seconds' => 300"),
        $has('labs/config-example.php', "'resend_webhook_secret' => 'DO_NOT_COMMIT_A_REAL_SECRET'"),
        $has('docs/RESEND-WEBHOOKS-DELIVERY-RECONCILIATION-V1.md', 'Safe activation order'),
    ],
    'Acceptance and quality integration' => [
        $exists('tests/resend-webhooks-delivery-reconciliation-contract-test.php'),
        $exists('scripts/resend-webhooks-delivery-reconciliation-quality-audit.php'),
        $has('includes/training-lab-product-acceptance.php', 'training-lab-resend-webhooks.php'),
        $has('includes/training-lab-product-acceptance.php', 'notification_provider_webhooks_v1.sql'),
    ],
    'Authority boundaries and rollback' => [
        $lacks('includes/training-lab-resend-webhooks.php', 'curl_init('),
        !preg_match('/\bmail\s*\(/i', $read('includes/training-lab-resend-webhooks.php')),
        $lacks('includes/training-lab-resend-webhooks.php', 'microgifter_issue'),
        $has('docs/RESEND-WEBHOOKS-DELIVERY-RECONCILIATION-V1.md', '## Rollback'),
    ],
];

$failed = false;
echo "Resend Webhooks + Delivery Reconciliation quality audit\n";
foreach ($sections as $name => $checks) {
    $passed = count(array_filter($checks));
    $total = count($checks);
    $score = round(($passed / $total) * 10, 1);
    echo sprintf("%-39s %s/10 (%d/%d)\n", $name, number_format($score, $score === 10.0 ? 0 : 1), $passed, $total);
    if ($passed !== $total) $failed = true;
}
exit($failed ? 1 : 0);
