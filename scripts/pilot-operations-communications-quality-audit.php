<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$load = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$has = static fn(string $path, string $needle): bool => str_contains($load($path), $needle);
$lacks = static fn(string $path, string $needle): bool => !str_contains($load($path), $needle);
$exists = static fn(string $path): bool => is_file($root . '/' . $path);
$serviceSource = $load('includes/training-lab-pilot-communications.php');
$noPhpMailFallback = !preg_match('/\bmail\s*\(/i', $serviceSource);

$sections = [
    'Schema and idempotency' => [
        $has('database/pilot_operations_communications_v1.sql', 'training_notification_outbox'),
        $has('database/pilot_operations_communications_v1.sql', 'uq_training_notification_outbox_idempotency'),
        $has('database/pilot_operations_communications_v1.sql', 'training_notification_attempts'),
        $has('database/pilot_operations_communications_v1.sql', 'training_pilot_controls'),
    ],
    'Trusted recipient ownership' => [
        $has('includes/training-lab-pilot-communications.php', 'training_account_links'),
        $has('includes/training-lab-pilot-communications.php', 'tl_reward_management_scope'),
        $has('includes/training-lab-pilot-communications-actions.php', 'c.owner_user_id'),
        $has('includes/training-lab-pilot-communications-sync.php', 'WHERE 1=1'),
        $lacks('admin/pilot-communications.php', "['email']"),
    ],
    'Lifecycle synchronization' => [
        $has('includes/training-lab-pilot-communications.php', 'participant_invited'),
        $has('includes/training-lab-pilot-communications.php', 'proof_submitted'),
        $has('includes/training-lab-pilot-communications.php', 'review_revision_required'),
        $has('includes/training-lab-pilot-communications.php', 'reward_delivery_succeeded'),
        $has('bin/notification-worker.php', 'tl_notifications_sync_events_scoped'),
    ],
    'Delivery and retry safety' => [
        $has('includes/training-lab-pilot-communications.php', 'FOR UPDATE'),
        $has('includes/training-lab-pilot-communications.php', 'lease_token_hash'),
        $has('includes/training-lab-pilot-communications.php', 'tl_notifications_retry_delay'),
        $has('includes/training-lab-pilot-communications.php', 'training_lab_send_notification_email'),
        $noPhpMailFallback,
    ],
    'Templates and previews' => [
        $exists('admin/notification-templates.php'),
        $has('includes/training-lab-pilot-communications.php', 'tl_notifications_allowed_placeholders'),
        $has('includes/training-lab-pilot-communications.php', 'str_replace(["\r", "\n"]'),
        $has('admin/notification-templates.php', 'Participant preview'),
        $has('includes/training-lab-pilot-communications-actions.php', 'is_system=0'),
    ],
    'Preferences and suppression' => [
        $exists('notification-preferences.php'),
        $has('notification-preferences.php', 'tl_security_verify_csrf'),
        $has('notification-preferences.php', 'tl_security_validate_origin'),
        $has('includes/training-lab-pilot-communications.php', 'tl_notifications_unsubscribe_token'),
        $has('includes/training-lab-pilot-communications-actions.php', 'tl_notifications_add_suppression'),
    ],
    'Pilot controls and reporting' => [
        $exists('admin/pilot-communications.php'),
        $exists('admin/pilot-reporting.php'),
        $has('includes/training-lab-pilot-communications.php', 'daily_notification_limit'),
        $has('includes/training-lab-pilot-communications-reporting.php', 'engagement_rate'),
        $has('includes/training-lab-pilot-communications-reporting.php', 'completion_rate'),
    ],
    'Incidents and privacy' => [
        $exists('admin/notification-incidents.php'),
        $has('admin/notification-incidents.php', "'required_role'=>'admin'"),
        $has('includes/training-lab-pilot-communications.php', 'no_raw_provider_response_storage'),
        $has('api/training/pilot-communications.php', "'recipient_addresses_exposed'=>false"),
        $lacks('admin/notification-incidents.php', "['email']"),
    ],
    'Mobile and navigation' => [
        $has('assets/css/pilot-communications.css', '@media(max-width:680px)'),
        $has('assets/css/reward-management.css', "@import url('pilot-communications.css')"),
        $has('includes/training-lab-product-shell.php', 'admin-pilot-communications'),
        $has('includes/training-lab-product-shell.php', 'admin-notification-incidents'),
    ],
    'Configuration and operations' => [
        $has('labs/config-example.php', "'notification_delivery_enabled' => false"),
        $has('labs/config-example.php', "'notification_worker_enabled' => false"),
        $has('docs/PILOT-OPERATIONS-COMMUNICATIONS-V1.md', 'Safe activation order'),
        $has('docs/PILOT-OPERATIONS-COMMUNICATIONS-V1.md', 'Rollback'),
        $exists('tests/pilot-operations-communications-contract-test.php'),
    ],
];

$failed = false;
echo "Pilot Operations + Communications quality audit\n";
foreach ($sections as $name => $checks) {
    $passed = count(array_filter($checks));
    $total = count($checks);
    $score = round(($passed / $total) * 10, 1);
    echo sprintf("%-31s %s/10 (%d/%d)\n", $name, number_format($score, $score === 10.0 ? 0 : 1), $passed, $total);
    if ($passed !== $total) $failed = true;
}
if ($failed) {
    fwrite(STDERR, "Section 15 has not reached 10/10 in every category.\n");
    exit(1);
}
echo "Every Section 15 category scored 10/10.\n";
