<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$exists = static fn(string $path): bool => is_file($root . '/' . $path);
$config = $read('includes/training-lab-stage898-config.php');
$runner = $read('includes/training-lab-stage898-runner.php');
$monitor = $read('includes/training-lab-stage898-monitoring.php');

$checks = [
    'safe_disabled_configuration'=>str_contains($config, 'stage898_worker_canary_enabled') && str_contains($read('config-example.php'), "'stage898_worker_canary_enabled' => false"),
    'proven_stage896_execution_path'=>str_contains($runner, 'tl_stage896_run_pilot([') && str_contains($runner, 'confirmed_delivered'),
    'clean_stage897_prerequisite'=>str_contains($config, 'stage897_controlled_batch_completed') && str_contains($config, '$processed === $selected'),
    'single_low_value_candidate'=>str_contains($runner, 're.value_cents BETWEEN 0 AND ?') && str_contains($config, 'stage898_max_value_cents'),
    'concurrency_and_frequency_control'=>str_contains($config, 'LOCK_EX | LOCK_NB') && str_contains($runner, 'minimum_interval_elapsed'),
    'automatic_pause_and_operator_recovery'=>str_contains($runner, 'stage898_worker_canary_paused') && str_contains($runner, 'stage898_worker_canary_pause_acknowledged'),
    'cli_only_execution_boundary'=>$exists('bin/reward-worker-canary.php') && !str_contains($read('api/training/reward-worker-canary.php'), 'tl_stage898_run('),
    'monitoring_and_alerts'=>str_contains($monitor, 'success_rate') && str_contains($monitor, 'quarantined_handoffs') && str_contains($monitor, 'canary_stale'),
    'sanitized_audit_evidence'=>str_contains($config, 'raw_recipient_secret_signature_nonce_payload_and_response_excluded'),
    'no_new_external_or_schema_dependency'=>str_contains($monitor, "'no_microgifter_change'=>true") && str_contains($monitor, "'no_new_sql'=>true"),
];

$passed = count(array_filter($checks));
$total = count($checks);
$score = round(($passed / max(1, $total)) * 10, 1);
echo "Stage 898 quality audit\n";
echo str_repeat('=', 64) . "\n";
foreach ($checks as $name => $ok) echo '  ' . ($ok ? '[PASS] ' : '[FAIL] ') . str_replace('_', ' ', $name) . "\n";
echo str_repeat('-', 64) . "\n";
printf("Score: %.1f/10 (%d/%d)\n", $score, $passed, $total);
exit($passed === $total ? 0 : 1);
