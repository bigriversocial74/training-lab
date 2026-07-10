#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    if (!headers_sent()) http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/includes/training-lab-stage898-worker-canary-monitoring.php';

$parsed = tl_stage898_parse_cli_arguments($argv);
if (!empty($parsed['help'])) {
    echo "Training Lab Stage 898 scheduled worker canary\n\n";
    echo "Usage:\n";
    echo "  php bin/reward-worker-canary.php --observe\n";
    echo "  php bin/reward-worker-canary.php --run\n\n";
    echo "Modes:\n";
    echo "  --observe  Read-only readiness and health report. This is the default.\n";
    echo "  --run      Process at most one eligible low-value reward through Stage 896.\n";
    exit(0);
}
if (!empty($parsed['errors'])) {
    $payload = [
        'stage'=>'Stage 898 Scheduled Worker Canary & Monitoring v1',
        'status'=>'invalid_arguments',
        'exit_code'=>64,
        'errors'=>array_values((array)$parsed['errors']),
    ];
    fwrite(STDERR, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(64);
}
if ((string)$parsed['mode'] === 'run' && empty($parsed['explicit_run'])) {
    $payload = [
        'stage'=>'Stage 898 Scheduled Worker Canary & Monitoring v1',
        'status'=>'invalid_arguments',
        'exit_code'=>64,
        'errors'=>['The --run flag is required for canary processing.'],
    ];
    fwrite(STDERR, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(64);
}

$result = tl_stage898_run(['mode'=>(string)$parsed['mode']]);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(max(0, min(255, (int)($result['exit_code'] ?? 0))));
