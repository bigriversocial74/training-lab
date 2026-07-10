<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/training-lab-stage891-reward-handoff-recovery.php';

$failures = [];
$check = static function (bool $condition, string $label) use (&$failures): void {
    if (!$condition) $failures[] = $label;
};
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';

$service = $read('includes/training-lab-stage891-reward-handoff-recovery.php');
$ownedProcessor = $read('includes/training-lab-stage891-owned-processor.php');
$panel = $read('includes/training-lab-stage891-terminal-failure-panel.php');
$api = $read('api/training/reward-handoff-operations.php');
$outboxApi = $read('api/training/reward-handoff-outbox.php');
$actionResult = $read('admin/action-result.php');
$rewardBridge = $read('admin/reward-bridge.php');
$config = $read('config-example.php');
$labsConfig = $read('labs/config-example.php');

$now = strtotime('2026-07-10 12:00:00 UTC');
$check(tl_stage891_is_stale_lock('2026-07-10 11:54:59', 300, $now), 'lock older than lease is stale');
$check(!tl_stage891_is_stale_lock('2026-07-10 11:55:01', 300, $now), 'lock inside lease is not stale');
$check(!tl_stage891_is_stale_lock(null, 300, $now), 'missing lock is not stale');
$check(tl_stage891_is_terminal_failure(['handoff_status'=>'failed','attempt_count'=>5,'next_attempt_at'=>null], 5), 'max-attempt failure is terminal');
$check(!tl_stage891_is_terminal_failure(['handoff_status'=>'failed','attempt_count'=>4,'next_attempt_at'=>null], 5), 'retryable attempt is not terminal');
$check(!tl_stage891_is_terminal_failure(['handoff_status'=>'failed','attempt_count'=>5,'next_attempt_at'=>'2026-07-10 12:05:00'], 5), 'scheduled retry is not terminal');

$recoverStart = strpos($service, "function tl_stage891_recover_stale_processing");
$recoverEnd = strpos($service, "function tl_stage891_requeue_handoff");
$recoverBody = ($recoverStart !== false && $recoverEnd !== false) ? substr($service, $recoverStart, $recoverEnd - $recoverStart) : '';
$check($recoverBody !== '', 'recovery function exists');
$check(str_contains($recoverBody, "handoff_status='processing'"), 'recovery selects processing rows');
$check(str_contains($recoverBody, 'FOR UPDATE'), 'recovery row locks candidates');
$check(str_contains($recoverBody, "locked_at=NULL, locked_by=NULL"), 'recovery releases abandoned lease');
$check(str_contains($recoverBody, 'worker_lease_expired_recovered'), 'recovery records retryable lease failure');
$check(str_contains($recoverBody, 'worker_lease_expired_terminal'), 'recovery records terminal lease failure');
$check(!str_contains($recoverBody, 'tl_stage890_call_adapter'), 'recovery never calls Microgifter adapter');
$check(!str_contains($recoverBody, 'microgifter_issue_training_reward'), 'recovery contains no direct issue call');

$requeueStart = strpos($service, "function tl_stage891_requeue_handoff");
$requeueEnd = strpos($service, "function tl_stage891_scalar");
$requeueBody = ($requeueStart !== false && $requeueEnd !== false) ? substr($service, $requeueStart, $requeueEnd - $requeueStart) : '';
$check(str_contains($requeueBody, 'LIMIT 1 FOR UPDATE'), 'manual requeue row locks handoff');
$check(str_contains($requeueBody, "attempt_count=0"), 'manual requeue starts a fresh retry cycle');
$check(str_contains($requeueBody, 'stage891_handoff_requeued'), 'manual requeue writes audit event');
$check(str_contains($requeueBody, 'stage891_recovery_history'), 'operator history is retained');
$check(!str_contains($requeueBody, 'tl_stage890_call_adapter'), 'manual requeue never calls Microgifter adapter');

$check(str_contains($ownedProcessor, 'function tl_stage891_process_handoff_owned'), 'owned processor exists');
$check(str_contains($ownedProcessor, "hash_equals((string)(\$current['locked_by'] ?? ''), \$worker)"), 'owned processor verifies worker token');
$check(str_contains($ownedProcessor, "handoff_status='processing' AND locked_by=?"), 'final updates require active worker ownership');
$check(str_contains($ownedProcessor, 'ownership_lost'), 'late worker result is rejected');
$check(str_contains($ownedProcessor, 'adapter_result_unapplied'), 'lost-lease adapter result is not applied');
$check(str_contains($ownedProcessor, 'stage891_worker_lease_lost'), 'lost lease is audit logged');
$check(str_contains($ownedProcessor, 'tl_stage891_process_owned_batch'), 'owned batch processor exists');

$check(str_contains($service, 'orphan_handoffs'), 'acceptance checks orphan handoffs');
$check(str_contains($service, 'delivered_reward_mismatch'), 'acceptance checks delivered reward consistency');
$check(str_contains($service, 'duplicate_idempotency_keys'), 'acceptance checks duplicate idempotency keys');
$check(str_contains($service, 'duplicate_external_references'), 'acceptance checks duplicate external references');
$check(str_contains($service, 'safe_to_observe'), 'acceptance separates safe observation state');
$check(str_contains($service, 'ready_for_production_processing'), 'acceptance separates production readiness');
$check(str_contains($service, 'processing_disabled_or_all_gates_open'), 'acceptance verifies production gate state');

$check(str_contains($api, 'tl_security_guard_write($action, $raw)'), 'operations API protects POST actions');
$check(str_contains($api, 'tl_auth_role_allowed'), 'operations API restricts GET summary');
$check(str_contains($api, 'tl_stage891_process_owned_batch'), 'operations API uses owned batch processor');
$check(!str_contains($api, 'tl_stage891_process_resilient_batch($input)'), 'operations API does not use unowned batch wrapper');
$check(str_contains($api, 'tl_security_json_exception'), 'operations API uses safe JSON errors');
$check(str_contains($outboxApi, "'process_reward_handoff' => 'tl_stage891_process_handoff_owned'"), 'Stage 890 API routes single processing through owned lease');
$check(str_contains($outboxApi, "'process_reward_handoff_batch' => 'tl_stage891_process_owned_batch'"), 'Stage 890 API routes batch processing through owned leases');
$check(!str_contains($outboxApi, "'process_reward_handoff' => 'tl_stage890_process_handoff'"), 'Stage 890 API does not expose old single processor');
$check(!str_contains($outboxApi, "'process_reward_handoff_batch' => 'tl_stage890_process_batch'"), 'Stage 890 API does not expose old batch processor');

$check(str_contains($actionResult, "'process_reward_handoff' => ['Process reward handoff', 'tl_stage891_process_handoff_owned']"), 'admin routes single processing through owned lease');
$check(str_contains($actionResult, "'process_reward_handoff_batch' => ['Process reward handoff batch', 'tl_stage891_process_owned_batch']"), 'admin routes batch processing through owned leases');
$check(str_contains($actionResult, 'stage891_recover_stale_handoffs'), 'admin action router wires stale recovery');
$check(str_contains($actionResult, 'stage891_requeue_handoff'), 'admin action router wires operator requeue');
$check(str_contains($actionResult, "'stage891_process_resilient_batch' => ['Recover and process reward handoff batch', 'tl_stage891_process_owned_batch']"), 'resilient batch action uses owned processor');
$check(str_contains($actionResult, 'stage891_run_handoff_acceptance'), 'admin action router wires acceptance');
$check(str_contains($rewardBridge, 'tl_stage891_render_admin_panel'), 'Reward Bridge renders Stage 891 acceptance panel');
$check(str_contains($rewardBridge, 'tl_stage891_render_terminal_failure_panel'), 'Reward Bridge renders terminal failure queue');
$check(str_contains($panel, 'stage891_requeue_handoff'), 'terminal failure panel provides protected requeue action');

foreach ([$config, $labsConfig] as $index => $example) {
    $label = $index === 0 ? 'root config example' : 'labs config example';
    $check(str_contains($example, "'reward_handoff_lease_seconds' => 300"), $label . ' includes lease setting');
    $check(str_contains($example, "'reward_handoff_recovery_batch_size' => 25"), $label . ' includes recovery batch setting');
}
$check(!is_file($root . '/database/stage891_reward_handoff_recovery.sql'), 'Stage 891 requires no SQL migration');

if ($failures) {
    fwrite(STDERR, "Stage 891 reward handoff recovery contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Stage 891 reward handoff recovery contract passed.\n";
