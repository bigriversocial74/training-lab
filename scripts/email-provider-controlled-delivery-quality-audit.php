<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$load = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$has = static fn(string $path, string $needle): bool => str_contains($load($path), $needle);
$lacksPattern = static fn(string $path, string $pattern): bool => !preg_match($pattern, $load($path));
$exists = static fn(string $path): bool => is_file($root . '/' . $path);

$sections = [
    'Provider configuration' => [
        $has('includes/training-lab-resend-email-provider.php', "'api_url'=>'https://api.resend.com/emails'"),
        $has('includes/training-lab-resend-email-provider.php', 'TL_RESEND_API_KEY'),
        $has('includes/training-lab-resend-email-provider.php', 'TL_NOTIFICATION_FROM_EMAIL'),
        $has('labs/config-example.php', "'notification_provider' => 'resend'"),
    ],
    'Transport security' => [
        $has('includes/training-lab-resend-email-provider.php', 'CURLOPT_FOLLOWLOCATION=>false'),
        $has('includes/training-lab-resend-email-provider.php', 'CURLOPT_SSL_VERIFYPEER=>true'),
        $has('includes/training-lab-resend-email-provider.php', 'CURLOPT_SSL_VERIFYHOST=>2'),
        $has('includes/training-lab-resend-email-provider.php', 'max_response_bytes'),
        !$has('includes/training-lab-resend-email-provider.php', 'CURLOPT_URL'),
    ],
    'Payload and idempotency' => [
        $has('includes/training-lab-resend-email-provider.php', 'tl_resend_normalize_payload'),
        $has('includes/training-lab-resend-email-provider.php', 'Idempotency-Key: '),
        $has('includes/training-lab-resend-email-provider.php', 'tl_resend_header_text'),
        $lacksPattern('includes/training-lab-resend-email-provider.php', '/\bmail\s*\(/i'),
    ],
    'Failure classification' => [
        $has('includes/training-lab-resend-email-provider.php', 'tl_resend_retryable'),
        $has('includes/training-lab-resend-email-provider.php', 'concurrent_idempotent_requests'),
        $has('includes/training-lab-resend-email-provider.php', 'invalid_idempotent_request'),
        $has('includes/training-lab-resend-email-provider.php', "outbox_status='failed' AND next_attempt_at IS NOT NULL"),
    ],
    'Independent delivery gates' => [
        $has('labs/config-example.php', "'notification_test_delivery_enabled' => false"),
        $has('labs/config-example.php', "'notification_delivery_enabled' => false"),
        $has('labs/config-example.php', "'notification_worker_enabled' => false"),
        $has('includes/training-lab-resend-email-provider.php', "'can_test'"),
        $has('includes/training-lab-resend-email-provider.php', "'can_process'"),
    ],
    'Administrator diagnostics' => [
        $exists('admin/email-provider.php'),
        $exists('admin/email-provider-action.php'),
        $has('admin/email-provider.php', "'required_role'=>'admin'"),
        !$has('admin/email-provider.php', 'name="email"'),
        $has('admin/email-provider-action.php', 'send_notification_provider_test'),
    ],
    'Privacy and audit' => [
        $has('includes/training-lab-resend-email-provider.php', 'provider_message_id_hashed'),
        $has('includes/training-lab-resend-email-provider.php', 'no_raw_provider_response_storage'),
        $has('includes/training-lab-resend-email-provider.php', 'recipient_confirmation'),
        $has('includes/training-lab-resend-email-provider.php', 'raw_response_stored'),
        !$has('admin/email-provider.php', 'TL_RESEND_API_KEY'),
    ],
    'Operations and rollback' => [
        $exists('bin/email-provider-check.php'),
        $has('bin/email-provider-check.php', "['test','json']"),
        $has('docs/EMAIL-PROVIDER-CONTROLLED-DELIVERY-V1.md', 'Campaign activation order'),
        $has('docs/EMAIL-PROVIDER-CONTROLLED-DELIVERY-V1.md', 'Rollback'),
        $has('docs/EMAIL-PROVIDER-CONTROLLED-DELIVERY-V1.md', 'No SQL required'),
    ],
    'Canonical product integration' => [
        $has('includes/training-lab-product-shell.php', 'admin-email-provider'),
        $has('includes/training-lab-product-acceptance.php', 'training-lab-resend-email-provider.php'),
        $has('includes/training-lab-product-acceptance.php', 'email-provider-controlled-delivery-contract-test.php'),
        $has('includes/training-lab-pilot-communications-actions.php', 'training-lab-resend-email-provider.php'),
        $has('bin/notification-worker.php', 'training-lab-resend-email-provider.php'),
    ],
    'Authority boundaries' => [
        !$has('includes/training-lab-resend-email-provider.php', 'microgifter_issue_training_reward'),
        !$has('includes/training-lab-resend-email-provider.php', 'microgifter_create_user_account'),
        !$has('includes/training-lab-resend-email-provider.php', 'UPDATE wallets'),
        !$has('includes/training-lab-resend-email-provider.php', 'INSERT INTO gifts'),
        !$has('includes/training-lab-resend-email-provider.php', 'ALTER TABLE'),
    ],
];

$failed = false;
echo "Email Provider + Controlled Delivery quality audit\n";
foreach ($sections as $name => $checks) {
    $passed = count(array_filter($checks));
    $total = count($checks);
    $score = round(($passed / $total) * 10, 1);
    echo sprintf("%-31s %s/10 (%d/%d)\n", $name, number_format($score, $score === 10.0 ? 0 : 1), $passed, $total);
    if ($passed !== $total) $failed = true;
}
if ($failed) {
    fwrite(STDERR, "Section 16 has not reached 10/10 in every category.\n");
    exit(1);
}
echo "Every Section 16 category scored 10/10.\n";
