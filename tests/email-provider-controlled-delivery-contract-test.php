<?php
declare(strict_types=1);

$root = dirname(__DIR__);
putenv('TL_NOTIFICATION_PROVIDER=resend');
putenv('TL_RESEND_API_KEY=re_section16_test_key_0123456789');
putenv('TL_NOTIFICATION_FROM_EMAIL=training@verified.example');
putenv('TL_NOTIFICATION_FROM_NAME=Training Lab Test');
putenv('TL_NOTIFICATION_REPLY_TO=support@verified.example');
putenv('TL_NOTIFICATION_TEST_RECIPIENT=admin@verified.example');
putenv('TL_NOTIFICATION_TEST_DELIVERY_ENABLED=true');
putenv('TL_NOTIFICATION_DELIVERY_ENABLED=false');
putenv('TL_NOTIFICATION_WORKER_ENABLED=false');

$GLOBALS['section16_provider_payload'] = null;
if (!function_exists('training_lab_send_notification_email')) {
    function training_lab_send_notification_email(array $payload): array
    {
        $GLOBALS['section16_provider_payload'] = $payload;
        return ['ok'=>true,'message_id'=>'provider-test-message-id','code'=>'accepted','retryable'=>false,'http_status'=>200];
    }
}

require_once $root . '/includes/training-lab-resend-email-provider.php';
require_once $root . '/includes/training-lab-pilot-communications-actions.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void { if (!$condition) $failures[] = $message; };
$read = static function (string $path) use ($root, &$failures): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) { $failures[] = $path . ' is missing.'; return ''; }
    return file_get_contents($full) ?: '';
};

$providerSource = $read('includes/training-lab-resend-email-provider.php');
$actionsSource = $read('includes/training-lab-pilot-communications-actions.php');
$workerSource = $read('bin/notification-worker.php');
$cliSource = $read('bin/email-provider-check.php');
$pageSource = $read('admin/email-provider.php');
$actionSource = $read('admin/email-provider-action.php');
$configSource = $read('labs/config-example.php');
$navSource = $read('includes/training-lab-product-shell.php');
$acceptanceSource = $read('includes/training-lab-product-acceptance.php');
$docsSource = $read('docs/EMAIL-PROVIDER-CONTROLLED-DELIVERY-V1.md');

$assert(str_contains($providerSource, "'api_url'=>'https://api.resend.com/emails'"), 'Provider endpoint must be fixed to the Resend HTTPS email endpoint.');
$assert(str_contains($providerSource, 'CURLOPT_FOLLOWLOCATION=>false') && str_contains($providerSource, 'CURLOPT_SSL_VERIFYPEER=>true') && str_contains($providerSource, 'CURLOPT_SSL_VERIFYHOST=>2'), 'Provider transport must reject redirects and verify TLS.');
$assert(str_contains($providerSource, 'CURLOPT_WRITEFUNCTION') && str_contains($providerSource, 'max_response_bytes'), 'Provider response size must be bounded.');
$assert(str_contains($providerSource, 'Idempotency-Key: '), 'Provider request must send an idempotency key.');
$assert(!preg_match('/\bmail\s*\(/i', $providerSource), 'Provider must not use PHP mail().');
$assert(!str_contains($providerSource, 'CURLOPT_URL'), 'Provider URL must not be supplied from runtime payload or configuration.');
$assert(str_contains($providerSource, 'provider_message_id_hashed') && str_contains($providerSource, 'no_raw_provider_response_storage'), 'Provider state must declare message hashing and raw-response exclusion.');
$assert(str_contains($providerSource, "outbox_status='failed' AND next_attempt_at IS NOT NULL"), 'Failed notifications must be retried only when a retry time exists.');
$assert(str_contains($actionsSource, "require_once __DIR__ . '/training-lab-resend-email-provider.php'"), 'Web communication actions must load the provider before the base service.');
$assert(str_contains($workerSource, 'training-lab-resend-email-provider.php'), 'Notification worker must load the provider before processing.');

$state = tl_notifications_provider_state();
$assert($state['provider_name'] === 'resend', 'Resend must be the selected provider in the runtime contract.');
$assert($state['configured'] === true && $state['adapter_available'] === true, 'Provider must report configured when all nonsecret requirements and the adapter are available.');
$assert($state['can_test'] === true, 'Fixed administrator test delivery must be available when its separate gate is enabled.');
$assert($state['can_process'] === false, 'Provider configuration and test delivery must not bypass disabled campaign delivery and worker gates.');
$assert(($state['diagnostics']['api_key_exposed'] ?? true) === false && ($state['diagnostics']['recipient_exposed'] ?? true) === false, 'Diagnostics must declare API key and recipient exclusion.');
$assert(!array_key_exists('api_key', $state['diagnostics']) && !array_key_exists('test_recipient', $state['diagnostics']), 'Diagnostics must not return credential or recipient values.');

$normalized = tl_resend_normalize_payload([
    'to'=>'Person@Example.com',
    'subject'=>"Provider test\r\nInjected",
    'text'=>'Plain text body',
    'idempotency_key'=>'section16-idempotency-key',
]);
$assert($normalized['to'] === 'person@example.com', 'Recipient must be normalized.');
$assert(!str_contains($normalized['subject'], "\r") && !str_contains($normalized['subject'], "\n"), 'Subject must strip CRLF characters.');
$assert(tl_resend_retryable(429, 'rate_limit_exceeded') === true, 'HTTP 429 must be retryable.');
$assert(tl_resend_retryable(500, 'internal_server_error') === true, 'Provider 5xx must be retryable.');
$assert(tl_resend_retryable(422, 'invalid_from_address') === false, 'Provider validation failures must be terminal.');
$assert(tl_resend_retryable(409, 'concurrent_idempotent_requests') === true, 'Concurrent idempotent requests must be retryable.');
$assert(tl_resend_retryable(409, 'invalid_idempotent_request') === false, 'Conflicting idempotent payloads must be terminal.');
$assert(!str_contains(tl_resend_safe_message('Authorization: Bearer re_secret_key'), 're_secret_key'), 'Provider errors must redact credentials.');

$testResult = tl_resend_send_test(['id'=>'1','numeric_user_id'=>1,'role'=>'admin','source'=>'developer_key','name'=>'Test Administrator']);
$assert(!empty($testResult['ok']), 'Administrator fixed-recipient provider test must succeed through the test adapter.');
$assert(strlen((string)$testResult['recipient_confirmation']) === 12, 'Provider test must return only a short recipient confirmation hash.');
$assert(strlen((string)$testResult['provider_message_confirmation']) === 12, 'Provider test must return only a short message confirmation hash.');
$assert(!array_key_exists('recipient', $testResult) && !array_key_exists('message_id', $testResult), 'Provider test response must not expose the recipient or full provider message ID.');
$testPayload = $GLOBALS['section16_provider_payload'];
$assert(is_array($testPayload) && ($testPayload['to'] ?? '') === 'admin@verified.example', 'Provider test must use only the protected fixed recipient.');
$assert(str_starts_with((string)($testPayload['idempotency_key'] ?? ''), 'training-lab-provider-test-'), 'Provider test must use a unique server-generated idempotency key.');
$assert(!str_contains((string)($testPayload['text'] ?? ''), 'participant@example'), 'Provider test message must not include participant data.');

$assert(str_contains($pageSource, "'required_role'=>'admin'"), 'Provider diagnostics page must require administrator access.');
$assert(!str_contains($pageSource, 'name="email"') && !str_contains($pageSource, 'name="recipient"'), 'Provider test form must not accept an arbitrary recipient.');
$assert(str_contains($actionSource, "tl_security_guard_write('send_notification_provider_test'"), 'Provider test action must use the protected write guard.');
$assert(str_contains($actionSource, 'tl_product_role($user) !== \'admin\''), 'Provider test action must explicitly enforce administrator access.');
$assert(str_contains($cliSource, "getopt('', ['test','json'])"), 'Provider CLI must make live test delivery explicit.');
$assert(str_contains($configSource, "'notification_test_delivery_enabled' => false") && str_contains($configSource, "'notification_delivery_enabled' => false") && str_contains($configSource, "'notification_worker_enabled' => false"), 'All provider and campaign delivery gates must default to disabled.');
$assert(str_contains($configSource, "'notification_provider' => 'resend'"), 'Configuration example must select the built-in Resend provider.');
$assert(str_contains($configSource, "'resend_api_key' => 'DO_NOT_COMMIT_A_REAL_SECRET'"), 'Configuration example must document the API key without committing a live value.');
$assert(str_contains($navSource, 'admin-email-provider'), 'Administrator navigation must expose provider diagnostics.');
$assert(str_contains($acceptanceSource, 'training-lab-resend-email-provider.php') && str_contains($acceptanceSource, 'email-provider-controlled-delivery-contract-test.php'), 'Canonical product acceptance must include Section 16 assets.');
$assert(str_contains($docsSource, 'No SQL required') && str_contains($docsSource, 'Rollback') && str_contains($docsSource, 'A successful administrator test does **not** enable campaign email'), 'Deployment documentation must cover SQL status, activation boundary, and rollback.');
foreach (['microgifter_issue_training_reward','microgifter_create_user_account','UPDATE wallets','INSERT INTO gifts','ALTER TABLE'] as $forbiddenAuthority) {
    $assert(!str_contains($providerSource, $forbiddenAuthority), 'Provider must not contain authority operation: ' . $forbiddenAuthority . '.');
}
$assert(str_contains($providerSource, 'no_wallet_or_reward_mutation'), 'Provider payload must declare the existing no-wallet/no-reward-mutation boundary.');

if ($failures) {
    fwrite(STDERR, "Email Provider + Controlled Delivery contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "Email Provider + Controlled Delivery contract passed.\n";
