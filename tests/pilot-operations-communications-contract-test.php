<?php
declare(strict_types=1);

$root = dirname(__DIR__);
putenv('TL_NOTIFICATION_DELIVERY_ENABLED=false');
putenv('TL_NOTIFICATION_WORKER_ENABLED=false');
putenv('TL_NOTIFICATION_RETRY_BASE_SECONDS=120');
putenv('TL_NOTIFICATION_UNSUBSCRIBE_SECRET=section15-test-unsubscribe-secret-0123456789abcdef');
putenv('TL_PUBLIC_BASE_URL=https://labs.example.test');
if (!function_exists('training_lab_send_notification_email')) {
    function training_lab_send_notification_email(array $payload): array { return ['ok'=>true,'message_id'=>'test-message']; }
}
require_once $root . '/includes/training-lab-pilot-communications-sync.php';
require_once $root . '/includes/training-lab-pilot-communications-reporting.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void { if (!$condition) $failures[] = $message; };
$read = static function (string $path) use ($root, &$failures): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) { $failures[] = $path . ' is missing.'; return ''; }
    return file_get_contents($full) ?: '';
};

$sql = $read('database/pilot_operations_communications_v1.sql');
$service = $read('includes/training-lab-pilot-communications.php');
$syncService = $read('includes/training-lab-pilot-communications-sync.php');
$actions = $read('includes/training-lab-pilot-communications-actions.php');
$reporting = $read('includes/training-lab-pilot-communications-reporting.php');
$worker = $read('bin/notification-worker.php');
$actionRoute = $read('admin/pilot-communications-action.php');
$dashboard = $read('admin/pilot-communications.php');
$templates = $read('admin/notification-templates.php');
$incidents = $read('admin/notification-incidents.php');
$preferences = $read('notification-preferences.php');
$api = $read('api/training/pilot-communications.php');
$config = $read('labs/config-example.php');
$docs = $read('docs/PILOT-OPERATIONS-COMMUNICATIONS-V1.md');
$css = $read('assets/css/pilot-communications.css');
$nav = $read('includes/training-lab-product-shell.php');

foreach (['training_notification_templates','training_notification_preferences','training_notification_suppressions','training_pilot_controls','training_notification_outbox','training_notification_attempts'] as $table) {
    $assert(str_contains($sql, 'CREATE TABLE IF NOT EXISTS ' . $table), $table . ' must be created by the migration.');
}
$assert(str_contains($sql, 'UNIQUE KEY uq_training_notification_outbox_idempotency'), 'Outbox idempotency keys must be unique.');
$assert(str_contains($sql, "ENUM('queued','blocked','processing','delivered','failed','cancelled','suppressed')"), 'Outbox lifecycle statuses must be explicit.');
$assert(str_contains($sql, 'UNIQUE KEY uq_training_notification_attempts_number'), 'Attempt numbers must be unique per outbox item.');
$assert(str_contains($sql, "'task_reminder','email','reminder'"), 'Reminder templates must be classified separately.');
$assert(!str_contains($sql, 'ALTER TABLE users') && !str_contains($sql, 'ALTER TABLE wallets'), 'Migration must not alter Microgifter authority tables.');

$assert(str_contains($service, 'training_account_links'), 'Recipients must resolve through the existing account-link table.');
$assert(str_contains($service, 'tl_notifications_email_hash'), 'Recipient addresses must have a hash confirmation.');
$assert(str_contains($service, 'idempotency_key'), 'Outbox events must use idempotency keys.');
$assert(str_contains($service, 'FOR UPDATE'), 'Delivery processing must use row locks.');
$assert(str_contains($service, 'lease_token_hash'), 'Delivery processing must use lease hashes.');
$assert(str_contains($service, 'training_lab_send_notification_email'), 'Delivery must use the explicit provider adapter.');
$assert(!preg_match('/\bmail\s*\(/i', $service), 'There must be no PHP mail fallback.');
$assert(!str_contains($service, 'microgifter_issue') && !str_contains($service, 'CURLOPT_URL'), 'Communication delivery must not call Microgifter or arbitrary HTTP endpoints.');
$assert(str_contains($service, 'no_raw_provider_response_storage'), 'Provider state must declare raw-response exclusion.');
$assert(str_contains($service, 'recipient_suppressed') && str_contains($service, 'reminders_disabled'), 'Suppressions and reminder preferences must block delivery.');
$assert(str_contains($service, 'participant_invited') && str_contains($service, 'proof_submitted') && str_contains($service, 'review_approved') && str_contains($service, 'reward_earned') && str_contains($service, 'reward_delivery_succeeded'), 'Core lifecycle events must be synchronized.');
$assert(str_contains($syncService, 'WHERE 1=1') && str_contains($syncService, 'c.owner_user_id=') && str_contains($syncService, 'c.id='), 'Every operational synchronization query must support strict owner and campaign filters.');
$assert(str_contains($actionRoute, 'tl_notifications_sync_events_scoped') && str_contains($worker, 'tl_notifications_sync_events_scoped'), 'Web and CLI synchronization must use the strictly scoped service.');
$assert(str_contains($actions, 'tl_notifications_owned_outbox') && str_contains($actions, 'c.owner_user_id'), 'Retry and cancel actions must enforce campaign ownership.');
$assert(str_contains($actions, 'tl_product_role($user) !== \'admin\''), 'Suppressions must remain administrator-only.');
$assert(str_contains($reporting, 'engagement_rate') && str_contains($reporting, 'completion_rate') && str_contains($reporting, 'delivery_rate'), 'Pilot reporting must cover engagement, completion, and delivery.');

$assert(str_contains($dashboard, "'required_role'=>'manager'"), 'Pilot dashboard must require manager access.');
$assert(str_contains($templates, "'required_role'=>'manager'"), 'Template management must require manager access.');
$assert(str_contains($incidents, "'required_role'=>'admin'"), 'Incident operations must require administrator access.');
$assert(str_contains($preferences, 'tl_security_verify_csrf') && str_contains($preferences, 'tl_security_validate_origin') && str_contains($preferences, 'tl_security_rate_limit'), 'Preference changes must use CSRF, origin validation, and rate limiting.');
$assert(!str_contains($dashboard, "['email']") && !str_contains($incidents, "['email']"), 'Operational pages must not display recipient addresses.');
$assert(str_contains($api, "'recipient_addresses_exposed'=>false") && str_contains($api, "'raw_provider_responses_exposed'=>false"), 'API must declare privacy boundaries.');
$assert(str_contains($worker, "'sync'") && str_contains($worker, "'process'") && str_contains($worker, "'include-reminders'"), 'Worker must support sync, process, and reminder modes.');

$assert(str_contains($config, "'notification_delivery_enabled' => false"), 'Delivery must default to disabled.');
$assert(str_contains($config, "'notification_worker_enabled' => false"), 'Worker must default to disabled.');
$assert(str_contains($config, "'notification_provider' => 'adapter'"), 'Provider must default to adapter mode.');
$assert(str_contains($config, "'notification_unsubscribe_secret'"), 'Config example must document the unsubscribe secret without providing it.');
$assert(str_contains($docs, 'database/pilot_operations_communications_v1.sql'), 'Deployment guide must name the migration.');
$assert(str_contains($docs, 'training_lab_send_notification_email'), 'Deployment guide must document the adapter contract.');
$assert(str_contains(strtolower($docs), 'no php `mail()` fallback'), 'Deployment guide must state the mail fallback boundary.');
$assert(str_contains($docs, 'Rollback'), 'Deployment guide must include rollback.');
$assert(str_contains($css, '@media(max-width:680px)'), 'Communications UI must include mobile reflow.');
$assert(str_contains($nav, 'admin-pilot-communications') && str_contains($nav, 'admin-notification-incidents'), 'Role-aware navigation must expose merchant and administrator surfaces.');

$configState = tl_notifications_config();
$assert($configState['delivery_enabled'] === false, 'Delivery must remain disabled in the runtime test.');
$assert($configState['worker_enabled'] === false, 'Worker must remain disabled in the runtime test.');
$assert(tl_notifications_retry_delay(1) === 120, 'First retry must use the configured base delay.');
$assert(tl_notifications_retry_delay(2) === 240, 'Retry delay must use exponential backoff.');
$assert(tl_notifications_retry_delay(20) <= 86400, 'Retry delay must be capped at 24 hours.');
$rendered = tl_notifications_render(['subject_template'=>"Hello {{participant_name}}\r\nInjected",'body_template'=>'Open {{action_url}}'], ['participant_name'=>'Jordan','action_url'=>'https://labs.example.test/app/index.php']);
$assert(!str_contains($rendered['subject'], "\n") && !str_contains($rendered['subject'], "\r"), 'Rendered subjects must strip line breaks.');
$assert(str_contains($rendered['text'], 'https://labs.example.test/app/index.php'), 'Allowed placeholders must render.');
$token = tl_notifications_unsubscribe_token(42, time() + 600);
$assert($token !== '' && tl_notifications_verify_unsubscribe_token($token) === 42, 'Signed unsubscribe tokens must round-trip.');
$parts = explode('.', $token, 2); if (isset($parts[1][0])) $parts[1][0] = $parts[1][0] === 'a' ? 'b' : 'a';
try { tl_notifications_verify_unsubscribe_token(implode('.', $parts)); $failures[] = 'Tampered unsubscribe token was accepted.'; }
catch (TlHttpException $error) { $assert($error->errorCode() === 'unsubscribe_signature_invalid', 'Tampered token must fail signature verification.'); }
$provider = tl_notifications_provider_state();
$assert($provider['adapter_available'] === true && $provider['can_process'] === false, 'An adapter alone must not bypass disabled delivery and worker gates.');

if ($failures) {
    fwrite(STDERR, "Pilot Operations + Communications contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "Pilot Operations + Communications contract passed.\n";
