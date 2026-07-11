<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$exists = static fn(string $path): bool => is_file($root . '/' . $path);
$config = $read('includes/training-lab-stage898-config.php');
$runner = $read('includes/training-lab-stage898-runner.php');
$monitor = $read('includes/training-lab-stage898-monitoring.php');
$cli = $read('bin/reward-worker-canary.php');
$admin = $read('admin/reward-worker-canary.php');
$api = $read('api/training/reward-worker-canary.php');
$advancedOperations = $read('admin/reward-operations.php');

$checks = [
    'modular_stage898_services'=>$exists('includes/training-lab-stage898-worker-canary-monitoring.php') && $exists('includes/training-lab-stage898-config.php') && $exists('includes/training-lab-stage898-runner.php') && $exists('includes/training-lab-stage898-monitoring.php'),
    'disabled_by_default'=>str_contains($config, 'TL_STAGE898_WORKER_CANARY_ENABLED') && str_contains($read('config-example.php'), "'stage898_worker_canary_enabled' => false"),
    'cli_only_explicit_run'=>$exists('bin/reward-worker-canary.php') && str_contains($cli, "PHP_SAPI !== 'cli'") && str_contains($cli, '--run') && str_contains($cli, 'explicit_run'),
    'one_item_canary'=>str_contains($runner, 'LIMIT 25') && str_contains($runner, 'return [') && !str_contains($runner, 'tl_stage897_run_batch('),
    'reuses_stage896_controller'=>str_contains($runner, 'tl_stage896_run_pilot([') && str_contains($runner, "'confirmation_phrase'=>'ISSUE ONE PILOT'"),
    'fresh_clean_stage897_evidence'=>str_contains($config, 'stage897_controlled_batch_completed') && str_contains($config, '$verified === $selected') && str_contains($runner, 'clean_stage897_batch_fresh'),
    'bounded_value_interval_and_lock'=>str_contains($config, 'TL_STAGE898_MAX_VALUE_CENTS') && str_contains($config, 'TL_STAGE898_MIN_INTERVAL_SECONDS') && str_contains($config, 'outside_repository_tree'),
    'normal_worker_must_stay_disabled'=>str_contains($runner, 'normal_scheduled_worker_disabled') && str_contains($monitor, 'normal_stage892_worker_remains_disabled'),
    'immediate_readback_required'=>str_contains($runner, "pilot_status'] ?? '') === 'verified'") && str_contains($runner, "verification']['confirmed_delivered"),
    'automatic_pause_latch'=>str_contains($runner, 'stage898_worker_canary_paused') && str_contains($runner, 'canary_delivery_not_verified') && str_contains($runner, 'canary_exception'),
    'pause_ack_requires_resolution'=>str_contains($config, 'ACKNOWLEDGE CANARY PAUSE') && str_contains($runner, 'stage898_active_pilot_unresolved'),
    'sanitized_evidence'=>str_contains($config, 'raw_recipient_secret_signature_nonce_payload_and_response_excluded') && str_contains($config, 'microgifter_user_fingerprint'),
    'monitoring_metrics'=>str_contains($monitor, 'success_rate') && str_contains($monitor, 'canary_stale') && str_contains($monitor, 'quarantined_handoffs'),
    'no_web_execution'=>!str_contains($admin, 'tl_stage898_run(') && !str_contains($api, 'tl_stage898_run('),
    'protected_operator_surfaces'=>$exists('admin/reward-worker-canary.php') && $exists('api/training/reward-worker-canary.php') && str_contains($admin, 'tl_security_guard_write') && str_contains($api, 'tl_auth_role_allowed'),
    'advanced_operations_entry'=>str_contains($advancedOperations, 'tl_stage898_render_reward_bridge_panel'),
    'config_examples_match'=>str_contains($read('config-example.php'), "'stage898_max_value_cents' => 1000") && str_contains($read('labs/config-example.php'), "'stage898_min_interval_seconds' => 900"),
    'quality_gate_entries'=>str_contains($read('run-quality-gate.sh'), 'stage898-worker-canary-monitoring-contract-test.php') && str_contains($read('.github/workflows/quality-gate.yml'), 'Stage 898 worker canary monitoring contract'),
    'documentation_present'=>$exists('docs/STAGE-898-SCHEDULED-WORKER-CANARY-MONITORING-V1.md'),
    'no_microgifter_or_sql_changes'=>str_contains($monitor, "'no_microgifter_change'=>true") && str_contains($monitor, "'no_new_sql'=>true") && !$exists('database/stage898_scheduled_worker_canary_monitoring_v1.sql'),
];

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
foreach ($checks as $name => $ok) echo ($ok ? '[PASS] ' : '[FAIL] ') . str_replace('_', ' ', $name) . "\n";
if ($failed) {
    fwrite(STDERR, 'Stage 898 contract failed: ' . implode(', ', $failed) . "\n");
    exit(1);
}
echo "Stage 898 scheduled worker canary monitoring contract passed.\n";
