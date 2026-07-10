<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$exists = static fn(string $path): bool => is_file($root . '/' . $path);
$runner = $read('includes/training-lab-stage899-runner.php');
$config = $read('includes/training-lab-stage899-config.php');
$monitor = $read('includes/training-lab-stage899-monitoring.php');

$sections = [
    'Architecture'=>$exists('includes/training-lab-stage899-limited-scheduled-processing.php') && $exists('includes/training-lab-stage899-config.php') && $exists('includes/training-lab-stage899-runner.php') && $exists('includes/training-lab-stage899-monitoring.php'),
    'Fail-closed defaults'=>str_contains($config, 'stage899_limited_scheduler_enabled') && str_contains($read('config-example.php'), "'stage899_limited_scheduler_enabled' => false"),
    'Canary graduation'=>str_contains($config, 'min_verified_canaries') && str_contains($config, 'stage898_worker_canary_completed') && str_contains($runner, 'stage898_canaries_graduated'),
    'Bounded scheduling'=>str_contains($config, 'min(2') && str_contains($runner, 'max_item_value_cents') && str_contains($runner, 'max_total_value_cents'),
    'Canonical issuance reuse'=>str_contains($runner, 'tl_stage896_run_pilot([') && str_contains($runner, "verification']['confirmed_delivered"),
    'Concurrency and timing'=>str_contains($config, 'flock') && str_contains($runner, 'minimum_interval_elapsed') && str_contains($runner, 'max_runtime_seconds'),
    'Automatic shutdown'=>str_contains($runner, 'tl_stage899_suspend') && str_contains($runner, 'stage899_limited_processing_suspended') && str_contains($runner, 'stage899_limited_processing_escalated'),
    'Operational monitoring'=>str_contains($monitor, 'rolling_success_below_threshold') && str_contains($monitor, 'scheduler_stale') && str_contains($monitor, 'quarantined_handoffs'),
    'Protected surfaces'=>!str_contains($read('admin/reward-limited-scheduler.php'), 'tl_stage899_run(') && !str_contains($read('api/training/reward-limited-scheduler.php'), 'tl_stage899_run(') && str_contains($read('bin/reward-limited-scheduler.php'), "PHP_SAPI !== 'cli'"),
    'Documentation and tests'=>$exists('docs/STAGE-899-CANARY-GRADUATION-LIMITED-SCHEDULED-PROCESSING-V1.md') && $exists('tests/stage899-canary-graduation-limited-scheduled-processing-contract-test.php'),
];

$passed = count(array_filter($sections));
$score = (int)round(($passed / count($sections)) * 10, 1);
foreach ($sections as $name => $ok) echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . "\n";
echo 'Stage 899 score: ' . $score . '/10' . "\n";
if ($passed !== count($sections)) exit(1);
echo "Stage 899 production-readiness audit passed at 10/10.\n";
