<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/training-lab-limited-email-pilot.php';
$options = getopt('', ['run::','json']);
$run = trim((string)($options['run'] ?? ''));
$user = ['id'=>'section18-cli','numeric_user_id'=>1,'role'=>'admin','source'=>'developer_key','name'=>'Section 18 CLI'];
try {
    $report = tl_limited_email_pilot_dashboard($user,$run);
    $payload = [
        'ok'=>true,
        'read_only'=>true,
        'schema_ready'=>!empty($report['readiness']['schema_ready']),
        'configuration'=>$report['readiness']['configuration'] ?? [],
        'general_worker_disabled'=>!empty($report['readiness']['general_worker_disabled']),
        'provider_ready'=>!empty($report['readiness']['provider']['configured']),
        'webhook_ready'=>!empty($report['readiness']['webhook']['ready']),
        'selected_run'=>$report['selected'] ? [
            'public_id'=>(string)$report['selected']['public_id'],
            'campaign'=>(string)$report['selected']['campaign_title'],
            'status'=>(string)$report['selected']['run_status'],
            'canary'=>(string)($report['selected']['canary_effective_status'] ?? $report['selected']['canary_status']),
        ] : null,
        'member_count'=>count($report['members'] ?? []),
        'metrics'=>$report['metrics'] ?? [],
        'breaches'=>$report['breaches'] ?? [],
        'generated_at'=>gmdate('c'),
        'safe_boundaries'=>[
            'no_write_actions'=>true,
            'no_recipient_addresses'=>true,
            'no_provider_credentials'=>true,
            'no_worker_activation'=>true,
            'no_microgifter_authority'=>true,
        ],
    ];
    if (isset($options['json'])) echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    else {
        echo "Limited Email Pilot diagnostics\n";
        echo 'Schema: ' . ($payload['schema_ready'] ? 'ready' : 'missing') . "\n";
        echo 'General worker: ' . ($payload['general_worker_disabled'] ? 'disabled' : 'enabled') . "\n";
        echo 'Provider: ' . ($payload['provider_ready'] ? 'ready' : 'blocked') . "\n";
        echo 'Webhook: ' . ($payload['webhook_ready'] ? 'ready' : 'blocked') . "\n";
        echo 'Run: ' . ($payload['selected_run']['status'] ?? 'none') . "\n";
        echo 'Members: ' . $payload['member_count'] . "\n";
        echo 'Breaches: ' . ($payload['breaches'] ? implode(', ', $payload['breaches']) : 'none') . "\n";
    }
    exit($payload['schema_ready'] ? 0 : 1);
} catch (Throwable $error) {
    $payload = ['ok'=>false,'read_only'=>true,'error'=>$error instanceof TlHttpException ? $error->getMessage() : 'Pilot diagnostics failed.','error_code'=>$error instanceof TlHttpException ? $error->errorCode() : 'limited_email_pilot_diagnostics_failed'];
    fwrite(STDERR,json_encode($payload,JSON_UNESCAPED_SLASHES) . "\n");
    exit(1);
}
