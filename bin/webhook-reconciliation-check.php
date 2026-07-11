#!/usr/bin/env php
<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once dirname(__DIR__) . '/includes/training-lab-resend-webhooks.php';

$options = getopt('', ['json','limit::']);
$limit = max(1, min(250, (int)($options['limit'] ?? 25)));
$actor = ['id'=>'webhook-check','numeric_user_id'=>1,'role'=>'admin','source'=>'developer_key','name'=>'Webhook Check'];

try {
    $report = tl_resend_webhook_dashboard($actor, $limit);
    $readiness = (array)($report['readiness'] ?? []);
    $payload = [
        'ok'=>!empty($readiness['ready']),
        'readiness'=>$readiness,
        'totals'=>$report['totals'] ?? [],
        'recent_events'=>$report['events'] ?? [],
        'recent_states'=>$report['states'] ?? [],
        'safe_boundaries'=>[
            'read_only'=>true,
            'secret_exposed'=>false,
            'raw_payload_exposed'=>false,
            'recipient_address_exposed'=>false,
            'no_external_request'=>true,
        ],
    ];
} catch (Throwable $error) {
    [$errorPayload] = tl_security_error_payload($error);
    $payload = ['ok'=>false,'error'=>$errorPayload['error'],'error_code'=>$errorPayload['error_code']];
}

if (array_key_exists('json', $options)) {
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} else {
    echo "Training Lab Resend webhook reconciliation\n";
    echo 'Status: ' . (!empty($payload['ok']) ? 'READY' : 'BLOCKED') . "\n";
    if (isset($payload['readiness'])) {
        echo 'Endpoint: ' . (string)($payload['readiness']['endpoint'] ?? '/api/webhooks/resend.php') . "\n";
        echo 'Webhook gate: ' . (!empty($payload['readiness']['enabled']) ? 'enabled' : 'disabled') . "\n";
        echo 'Signing secret: ' . (!empty($payload['readiness']['secret_present']) ? 'present' : 'missing') . "\n";
        echo 'Schema: ' . (!empty($payload['readiness']['schema_ready']) ? 'ready' : 'missing') . "\n";
    }
    if (isset($payload['totals'])) echo 'Totals: ' . json_encode($payload['totals'], JSON_UNESCAPED_SLASHES) . "\n";
    if (empty($payload['ok'])) echo 'Error: ' . (string)($payload['error'] ?? 'Webhook readiness is blocked.') . "\n";
}
exit(!empty($payload['ok']) ? 0 : 1);
