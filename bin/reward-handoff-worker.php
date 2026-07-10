#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    if (!headers_sent()) http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/includes/training-lab-stage893-worker-wrapper.php';

$parsed = tl_stage892_parse_cli_arguments($argv);
if (!empty($parsed['help'])) {
    echo "Training Lab Stage 892/893 reward handoff worker\n\n";
    echo "Usage:\n";
    echo "  php bin/reward-handoff-worker.php --observe [--limit=N]\n";
    echo "  php bin/reward-handoff-worker.php --recover [--limit=N]\n";
    echo "  php bin/reward-handoff-worker.php --process [--limit=N]\n\n";
    echo "Modes:\n";
    echo "  --observe  Read-only acceptance/status run. This is the default.\n";
    echo "  --recover  Recover expired processing leases; never calls Microgifter.\n";
    echo "  --process  Reconcile, recover, sync, and process a bounded due batch. Requires every production and reconciliation gate.\n";
    exit(0);
}

if (!empty($parsed['errors'])) {
    $payload = [
        'stage'=>'Stage 892/893 Scheduled Reward Handoff Worker',
        'status'=>'invalid_arguments',
        'exit_code'=>64,
        'errors'=>array_values((array)$parsed['errors']),
    ];
    fwrite(STDERR, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(64);
}

$input = [
    'mode'=>(string)$parsed['mode'],
    'explicit_process'=>!empty($parsed['explicit_process']),
];
if ($parsed['limit'] !== null) $input['limit'] = (int)$parsed['limit'];

$result = tl_stage893_run_scheduled_worker($input);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(max(0, min(255, (int)($result['exit_code'] ?? 1))));
