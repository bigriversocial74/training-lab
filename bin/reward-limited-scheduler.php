#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    if (!headers_sent()) http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/includes/training-lab-stage899-limited-scheduled-processing.php';

$parsed = tl_stage899_parse_cli_arguments($argv);
if (!empty($parsed['help'])) {
    echo "Training Lab Stage 899 limited scheduler\n\n";
    echo "Usage:\n";
    echo "  php bin/reward-limited-scheduler.php --observe\n";
    echo "  php bin/reward-limited-scheduler.php --run\n\n";
    echo "Modes:\n";
    echo "  --observe  Read-only graduation, health, and readiness report. Default.\n";
    echo "  --run      Process at most two eligible rewards through Stage 896.\n";
    exit(0);
}
if (!empty($parsed['errors'])) {
    $payload = [
        'stage'=>'Stage 899 Canary Graduation & Limited Scheduled Processing v1',
        'status'=>'invalid_arguments',
        'exit_code'=>64,
        'errors'=>array_values((array)$parsed['errors']),
    ];
    fwrite(STDERR, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(64);
}
$result = tl_stage899_run([
    'mode'=>(string)$parsed['mode'],
    'explicit_run'=>!empty($parsed['explicit_run']),
]);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(max(0, min(255, (int)($result['exit_code'] ?? 1))));
