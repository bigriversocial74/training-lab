<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$exists = static fn(string $path): bool => is_file($root . '/' . $path);
$config = $read('includes/training-lab-stage899-config.php');
$runner = $read('includes/training-lab-stage899-runner.php');
$monitor = $read('includes/training-lab-stage899-monitoring.php');
$cli = $read('bin/reward-limited-scheduler.php');
$admin = $read('admin/reward-limited-scheduler.php');
$api = $read('api/training/reward-limited-scheduler.php');
$advancedOperations = $read('admin/reward-operations.php');

$checks = [
    'modular_stage899_services'=>$exists('includes/training-lab-stage899-limited-scheduled-processing.php') && $exists('includes/training-lab-stage899-config.php') && $exists('includes/training-lab-stage899-runner.php') && $exists('includes/training-lab-stage899-monitoring.php'),
    'disabled_by_default'=>str_contains($config, 'TL_STAGE899_LIMITED_SCHEDULER_ENABLED') && str_contains($read('config-example.php'), "'stage899_limited_scheduler_enabled' => false"),
    'cli_only_explicit_run'=>$exists('bin/reward-limited-scheduler.php') && str_contains($cli, "PHP_SAPI !== 'cli'") && str_contains($cli, '--run') && str_contains($runner, 'explicit_run_flag_required'),
    'maximum_two_item_batch'=>str_contains($config, 'min(2') && str_contains($runner, "count(\$items) >= (int)\$config['max_batch_size']"),
    'item_and_total_value_ceilings'=>str_contains($config, 'TL_STAGE899_MAX_ITEM_VALUE_CENTS') && str_contains($config, 'TL_STAGE899_MAX_TOTAL_VALUE_CENTS') && str_contains($runner, 'max_total_value_cents'),
    'reuses_stage896_controller'=>str_contains($runner, 'tl_stage896_run_pilot([') && str_contains($runner, "'confirmation_phrase'=>'ISSUE ONE PILOT'"),
    'canary_graduation_required'=>str_contains($config, 'stage898_worker_canary_completed') && str_contains($config, 'min_verified_canaries') && str_contains($runner, 'stage898_canaries_graduated'),
    'dual_scheduler_prevention'=>str_contains($runner, 'stage898_canary_disabled') && str_contains($runner, 'stage897_manual_batch_disabled') && str_contains($runner, 'normal_stage892_worker_disabled'),
    'immediate_readback_required'=>str_contains($runner, "pilot_status'] ?? '') === 'verified'") && str_contains($runner, "verification']['confirmed_delivered"),
    'stop_and_suspend_on_uncertainty'=>str_contains($runner, 'scheduled_item_not_verified') && str_contains($runner, 'scheduled_item_exception') && str_contains($runner, 'runtime_limit_reached_before_next_item'),
    'escalation_event'=>str_contains($runner, 'stage899_limited_processing_escalated') && str_contains($runner, "'severity'=>'critical'"),
    'suspension_clearance_guarded'=>str_contains($config, 'ACKNOWLEDGE LIMITED PROCESSING SUSPENSION') && str_contains($runner, 'stage899_quarantine_unresolved') && str_contains($runner, 'stage899_canary_pause_unresolved'),
    'rolling_health_monitoring'=>str_contains($monitor, 'rolling_success_below_threshold') && str_contains($monitor, 'minimum_success_rate_percent') && str_contains($monitor, 'scheduler_stale'),
    'sanitized_evidence'=>str_contains($config, 'raw_recipient_secret_signature_nonce_payload_and_response_excluded') && str_contains($config, 'microgifter_user_fingerprint'),
    'no_web_execution'=>!str_contains($admin, 'tl_stage899_run(') && !str_contains($api, 'tl_stage899_run('),
    'protected_operator_surfaces'=>$exists('admin/reward-limited-scheduler.php') && $exists('api/training/reward-limited-scheduler.php') && str_contains($admin, 'tl_security_guard_write') && str_contains($api, 'tl_auth_role_allowed'),
    'advanced_operations_entry'=>str_contains($advancedOperations, 'tl_stage899_render_reward_bridge_panel'),
    'config_examples_match'=>str_contains($read('config-example.php'), "'stage899_max_batch_size' => 2") && str_contains($read('labs/config-example.php'), "'stage899_min_verified_canaries' => 3"),
    'quality_gate_entries'=>str_contains($read('run-quality-gate.sh'), 'stage899-canary-graduation-limited-scheduled-processing-contract-test.php') && str_contains($read('.github/workflows/quality-gate.yml'), 'Stage 899 canary graduation limited scheduling contract'),
    'documentation_present'=>$exists('docs/STAGE-899-CANARY-GRADUATION-LIMITED-SCHEDULED-PROCESSING-V1.md'),
    'no_microgifter_or_sql_changes'=>str_contains($monitor, "'no_microgifter_change'=>true") && str_contains($monitor, "'no_new_sql'=>true") && !$exists('database/stage899_canary_graduation_limited_scheduled_processing_v1.sql'),
];

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
foreach ($checks as $name => $ok) echo ($ok ? '[PASS] ' : '[FAIL] ') . str_replace('_', ' ', $name) . "\n";
if ($failed) {
    fwrite(STDERR, 'Stage 899 contract failed: ' . implode(', ', $failed) . "\n");
    exit(1);
}
echo "Stage 899 canary graduation limited scheduled processing contract passed.\n";
