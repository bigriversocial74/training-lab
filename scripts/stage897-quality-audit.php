<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$exists = static fn(string $path): bool => is_file($root . '/' . $path);
$core = $read('includes/training-lab-stage897-controlled-batch-rollout.php');

$checks = [
    'verified_pilot_rollout_gate'=>str_contains($core, "event_type='stage896_pilot_delivery_verified'") && str_contains($core, 'verified_stage896_pilot_fresh'),
    'bounded_batch_and_total_value'=>str_contains($core, "'min_batch_size'=>2") && str_contains($core, 'min(5') && str_contains($core, 'stage897_total_value_exceeded'),
    'cross_operation_locking'=>str_contains($core, 'stage897_acquire_lock') && str_contains($core, 'tl_stage896_acquire_lock($pdo)'),
    'preflight_all_items'=>str_contains($core, 'function tl_stage897_preflight') && str_contains($core, 'stage897_recipient_mismatch'),
    'sequential_canonical_pilots'=>str_contains($core, 'tl_stage896_run_pilot([') && !str_contains($core, 'tl_stage893_process_guarded_batch('),
    'stop_on_first_unverified'=>str_contains($core, 'item_not_verified') && str_contains($core, 'stage897_controlled_batch_paused'),
    'operator_pause_acknowledgement'=>str_contains($core, 'ACKNOWLEDGE BATCH PAUSE') && str_contains($core, 'stage897_active_pilot_unresolved'),
    'sanitized_audit_evidence'=>str_contains($core, 'raw_recipient_signature_nonce_payload_and_response_excluded') && str_contains($core, "'microgifter_user_fingerprint'=>") && !str_contains($core, "'raw_microgifter_user_id'=>"),
    'protected_surfaces'=>$exists('admin/reward-batch.php') && $exists('api/training/reward-batch.php') && str_contains($read('api/training/reward-batch.php'), 'tl_security_guard_write'),
    'config_docs_and_no_sql'=>str_contains($read('config-example.php'), "'stage897_controlled_batch_enabled' => false") && $exists('docs/STAGE-897-CONTROLLED-BATCH-ROLLOUT-V1.md') && !$exists('database/stage897_controlled_batch_rollout_v1.sql'),
];

$passed = count(array_filter($checks));
$total = count($checks);
$score = round(($passed / max(1, $total)) * 10, 1);
echo "Stage 897 quality audit\n";
echo str_repeat('=', 64) . "\n";
foreach ($checks as $name => $ok) echo '  ' . ($ok ? '[PASS] ' : '[FAIL] ') . str_replace('_', ' ', $name) . "\n";
echo str_repeat('-', 64) . "\n";
printf("Score: %.1f/10 (%d/%d)\n", $score, $passed, $total);
exit($passed === $total ? 0 : 1);
