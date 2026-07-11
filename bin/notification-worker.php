#!/usr/bin/env php
<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once dirname(__DIR__) . '/includes/training-lab-resend-email-provider.php';
require_once dirname(__DIR__) . '/includes/training-lab-pilot-communications-sync.php';

$options = getopt('', ['sync','process','campaign::','include-reminders','limit::','json']);
$sync = array_key_exists('sync', $options);
$process = array_key_exists('process', $options);
if (!$sync && !$process) { $sync = true; }
$limit = max(1, min(1000, (int)($options['limit'] ?? 250)));
$campaign = trim((string)($options['campaign'] ?? ''));
$includeReminders = array_key_exists('include-reminders', $options);
$cfg = function_exists('tl_security_config') ? tl_security_config() : [];
$actorEnv = getenv('TL_NOTIFICATION_WORKER_ACTOR_USER_ID');
$actorId = max(1, (int)($actorEnv !== false && $actorEnv !== '' ? $actorEnv : ($cfg['notification_worker_actor_user_id'] ?? 1)));
$actor = ['id'=>(string)$actorId,'numeric_user_id'=>$actorId,'role'=>'admin','source'=>'developer_key','name'=>'Notification Worker'];

$result = ['ok'=>true,'synced'=>null,'processed'=>null,'provider'=>tl_notifications_provider_state()];
try {
    if ($sync) $result['synced'] = tl_notifications_sync_events_scoped($actor, $campaign, $limit, $includeReminders);
    if ($process) $result['processed'] = tl_notifications_process_batch(min(100, $limit));
} catch (Throwable $error) {
    [$payload] = tl_security_error_payload($error);
    $result = ['ok'=>false,'error'=>$payload['error'],'error_code'=>$payload['error_code'],'provider'=>tl_notifications_provider_state()];
}

if (array_key_exists('json', $options)) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} else {
    echo "Training Lab notification worker\n";
    echo 'Status: ' . (!empty($result['ok']) ? 'OK' : 'BLOCKED') . "\n";
    if (is_array($result['synced'] ?? null)) echo 'Synchronized: ' . json_encode($result['synced'], JSON_UNESCAPED_SLASHES) . "\n";
    if (is_array($result['processed'] ?? null)) echo 'Processed: ' . json_encode($result['processed'], JSON_UNESCAPED_SLASHES) . "\n";
    if (empty($result['ok'])) echo 'Error: ' . (string)($result['error'] ?? 'Unknown error') . "\n";
}
exit(!empty($result['ok']) ? 0 : 1);
