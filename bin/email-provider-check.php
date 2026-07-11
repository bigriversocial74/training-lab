#!/usr/bin/env php
<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once dirname(__DIR__) . '/includes/training-lab-resend-email-provider.php';
require_once dirname(__DIR__) . '/includes/training-lab-pilot-communications.php';

$options = getopt('', ['test','json']);
$state = tl_notifications_provider_state();
$result = ['ok'=>!empty($state['configured']),'provider'=>$state,'test'=>null];
if (array_key_exists('test', $options)) {
    try {
        $actor = ['id'=>'provider-check','numeric_user_id'=>1,'role'=>'admin','source'=>'developer_key','name'=>'Provider Check'];
        $result['test'] = tl_resend_send_test($actor);
    } catch (Throwable $error) {
        $result['ok'] = false;
        if ($error instanceof TlNotificationProviderFailure) {
            $result['error'] = tl_resend_safe_message($error->getMessage());
            $result['error_code'] = $error->providerCode();
            $result['retryable'] = $error->retryable();
        } else {
            [$payload] = tl_security_error_payload($error);
            $result['error'] = $payload['error'];
            $result['error_code'] = $payload['error_code'];
        }
    }
}

if (array_key_exists('json', $options)) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} else {
    echo "Training Lab email provider check\n";
    echo 'Provider: ' . (string)$state['provider_name'] . "\n";
    echo 'Configured: ' . (!empty($state['configured']) ? 'yes' : 'no') . "\n";
    echo 'Test enabled: ' . (!empty($state['can_test']) ? 'yes' : 'no') . "\n";
    echo 'Campaign processing: ' . (!empty($state['can_process']) ? 'enabled' : 'disabled') . "\n";
    if (!empty($result['test'])) echo 'Test accepted: ' . (string)$result['test']['provider_message_confirmation'] . "\n";
    if (empty($result['ok']) && !empty($result['error'])) echo 'Error: ' . (string)$result['error'] . "\n";
}
exit(!empty($result['ok']) ? 0 : 1);
