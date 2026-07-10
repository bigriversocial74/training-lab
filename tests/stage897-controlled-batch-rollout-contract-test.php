<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$exists = static fn(string $path): bool => is_file($root . '/' . $path);
$core = $read('includes/training-lab-stage897-controlled-batch-rollout.php');
$admin = $read('admin/reward-batch.php');
$api = $read('api/training/reward-batch.php');

$checks = [
    'isolated_stage897_service'=>$exists('includes/training-lab-stage897-controlled-batch-rollout.php'),
    'reuses_stage896_bootstrap'=>str_contains($core, "training-lab-stage896-pilot-bootstrap.php"),
    'disabled_by_default'=>str_contains($core, "stage897_controlled_batch_enabled") && str_contains($read('config-example.php'), "'stage897_controlled_batch_enabled' => false"),
    'bounded_two_to_five_items'=>str_contains($core, "'min_batch_size'=>2") && str_contains($core, 'min(5'),
    'cumulative_value_ceiling'=>str_contains($core, 'TL_STAGE897_MAX_TOTAL_VALUE_CENTS') && str_contains($core, 'stage897_total_value_exceeded'),
    'runtime_ceiling'=>str_contains($core, 'TL_STAGE897_MAX_RUNTIME_SECONDS') && str_contains($core, 'runtime_limit_reached_before_next_item'),
    'verified_stage896_prerequisite'=>str_contains($core, "event_type='stage896_pilot_delivery_verified'") && str_contains($core, 'verified_stage896_pilot_fresh'),
    'fresh_stage896_evidence'=>str_contains($core, 'verified_pilot_max_age_seconds') && str_contains($core, "'fresh'=>$fresh"),
    'holds_stage896_lock'=>str_contains($core, 'tl_stage896_acquire_lock($pdo)') && str_contains($core, 'tl_stage896_release_lock($pdo)'),
    'sequential_stage896_execution'=>str_contains($core, "foreach ($plan['items'] as $item)") && str_contains($core, 'tl_stage896_run_pilot(['),
    'exact_stage896_confirmation'=>str_contains($core, "'confirmation_phrase'=>'ISSUE ONE PILOT'"),
    'batch_confirmation'=>str_contains($core, 'ISSUE CONTROLLED BATCH'),
    'recipient_reentry_per_item'=>str_contains($core, 'confirm_microgifter_user_ids') && str_contains($core, 'stage897_recipient_mismatch'),
    'full_preflight_before_first_issue'=>strpos($core, 'tl_stage897_preflight($pdo, $selection)') < strpos($core, 'stage897_controlled_batch_started'),
    'stops_on_first_unverified'=>str_contains($core, 'if (!$verified)') && str_contains($core, 'item_not_verified'),
    'immediate_readback_inherited'=>str_contains($core, 'confirmed_delivered') && str_contains($core, 'tl_stage896_run_pilot'),
    'pause_requires_acknowledgement'=>str_contains($core, 'stage897_controlled_batch_pause_acknowledged') && str_contains($core, 'ACKNOWLEDGE BATCH PAUSE'),
    'active_pilot_blocks_ack'=>str_contains($core, 'stage897_active_pilot_unresolved'),
    'scheduled_worker_guard'=>str_contains($core, 'scheduled_worker_disabled') && str_contains($core, 'scheduled_worker_remained_disabled'),
    'sanitized_evidence'=>str_contains($core, 'raw_recipient_signature_nonce_payload_and_response_excluded') && str_contains($core, 'microgifter_user_fingerprint'),
    'protected_admin_surface'=>$exists('admin/reward-batch.php') && str_contains($admin, 'tl_security_guard_write'),
    'protected_api_surface'=>$exists('api/training/reward-batch.php') && str_contains($api, 'tl_security_guard_write') && str_contains($api, 'tl_auth_role_allowed'),
    'csrf_operator_form'=>str_contains($core, 'tl_security_csrf_field'),
    'reward_bridge_entry'=>str_contains($read('admin/reward-bridge.php'), 'tl_stage897_render_reward_bridge_panel'),
    'quality_runner_entry'=>str_contains($read('run-quality-gate.sh'), 'stage897-controlled-batch-rollout-contract-test.php'),
    'workflow_entry'=>str_contains($read('.github/workflows/quality-gate.yml'), 'Stage 897 controlled batch rollout contract'),
    'scored_audit_entry'=>str_contains($read('run-quality-gate.sh'), 'stage897-quality-audit.php') && str_contains($read('.github/workflows/quality-gate.yml'), 'Stage 897 scored quality audit'),
    'config_examples_match'=>str_contains($read('config-example.php'), "'stage897_max_batch_size' => 3") && str_contains($read('labs/config-example.php'), "'stage897_max_total_value_cents' => 7500"),
    'documentation_present'=>$exists('docs/STAGE-897-CONTROLLED-BATCH-ROLLOUT-V1.md'),
    'no_new_microgifter_endpoint'=>!str_contains($core, '/api/integrations/') && str_contains($core, "'no_new_microgifter_endpoint'=>true"),
    'no_new_sql'=>!$exists('database/stage897_controlled_batch_rollout_v1.sql') && str_contains($core, "'no_new_sql'=>true"),
];

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
foreach ($checks as $name => $ok) echo ($ok ? '[PASS] ' : '[FAIL] ') . str_replace('_', ' ', $name) . "\n";
if ($failed) {
    fwrite(STDERR, "Stage 897 contract failed: " . implode(', ', $failed) . "\n");
    exit(1);
}
echo "Stage 897 controlled batch rollout contract passed.\n";
