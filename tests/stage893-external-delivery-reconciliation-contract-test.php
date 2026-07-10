<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/training-lab-stage893-external-delivery-reconciliation.php';

$failures = [];
$check = static function (bool $condition, string $label) use (&$failures): void {
    if (!$condition) $failures[] = $label;
};
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$functionBody = static function (string $source, string $name, ?string $next = null): string {
    $start = strpos($source, 'function ' . $name);
    if ($start === false) return '';
    $end = $next ? strpos($source, 'function ' . $next, $start + 1) : false;
    return $end !== false ? substr($source, $start, $end - $start) : substr($source, $start);
};

$service = $read('includes/training-lab-stage893-external-delivery-reconciliation.php');
$processing = $read('includes/training-lab-stage893-processing-wrapper.php');
$workerWrapper = $read('includes/training-lab-stage893-worker-wrapper.php');
$cli = $read('bin/reward-handoff-worker.php');
$outboxApi = $read('api/training/reward-handoff-outbox.php');
$operationsApi = $read('api/training/reward-handoff-operations.php');
$reconciliationApi = $read('api/training/reward-delivery-reconciliation.php');
$proofReviewApi = $read('api/training/proof-review-workflow.php');
$actionResult = $read('admin/action-result.php');
$rewardBridge = $read('admin/reward-bridge.php');
$config = $read('config-example.php');
$labsConfig = $read('labs/config-example.php');

$delivered = tl_stage893_normalize_lookup(['found'=>true,'status'=>'issued','gift_id'=>91,'external_reference'=>'MG-91']);
$check(!empty($delivered['confirmed_delivered']), 'issued lookup normalizes to confirmed delivery');
$check((string)$delivered['delivery_status'] === 'delivered', 'delivered lookup status normalized');
$check((int)$delivered['references']['gift_id'] === 91, 'lookup extracts linked gift reference');
$missing = tl_stage893_normalize_lookup(['found'=>false,'status'=>'not_found']);
$check(!empty($missing['confirmed_absent']), 'missing lookup is explicitly absent');
$pending = tl_stage893_normalize_lookup(['found'=>true,'status'=>'processing']);
$check(empty($pending['confirmed_delivered']) && (string)$pending['delivery_status'] === 'pending', 'pending lookup is not delivered');

$lookupBody = $functionBody($service, 'tl_stage893_lookup_external', 'tl_stage893_metadata');
$reconcileBody = $functionBody($service, 'tl_stage893_reconcile_handoff', 'tl_stage893_reconcile_batch');
$quarantineBody = $functionBody($service, 'tl_stage893_quarantine_lost_outcome', 'tl_stage893_process_handoff_guarded');
$guardBody = $functionBody($service, 'tl_stage893_process_handoff_guarded', 'tl_stage893_candidate_rows');
$candidateBody = $functionBody($processing, 'tl_stage893_reconciliation_candidate_rows_guarded', 'tl_stage893_reconcile_handoff_guarded');
$singleGuardBody = $functionBody($processing, 'tl_stage893_reconcile_handoff_guarded', 'tl_stage893_reconcile_batch_guarded');
$enqueueBody = $functionBody($processing, 'tl_stage893_enqueue_reward_event_guarded', 'tl_stage893_sync_outbox_guarded');
$syncBody = $functionBody($processing, 'tl_stage893_sync_outbox_guarded', 'tl_stage893_requeue_handoff_guarded');
$requeueBody = $functionBody($processing, 'tl_stage893_requeue_handoff_guarded', 'tl_stage893_process_guarded_batch');

$check(str_contains($service, 'microgifter_training_reward_lookup'), 'read adapter contract includes training reward lookup');
$check(str_contains($service, 'microgifter_find_reward_by_idempotency_key'), 'read adapter contract supports idempotency lookup');
$check(str_contains($lookupBody, "'read_only' => true"), 'lookup payload explicitly declares read-only mode');
$check(str_contains($lookupBody, "'idempotency_key'"), 'lookup payload includes idempotency key');
$check(str_contains($lookupBody, "'external_reference'"), 'lookup payload includes external reference');
$check(!str_contains($lookupBody, 'tl_stage890_call_adapter'), 'lookup never calls Stage 890 issue adapter');
$check(!str_contains($lookupBody, 'microgifter_issue_training_reward'), 'lookup never calls issue function');
$check(!str_contains($lookupBody, 'microgifter_create_reward_claim'), 'lookup never calls claim function');

$check(str_contains($quarantineBody, 'LIMIT 1 FOR UPDATE'), 'lost-delivery quarantine row locks handoff');
$check(str_contains($quarantineBody, "handoff_status='blocked'"), 'lost-delivery outcome is blocked');
$check(str_contains($quarantineBody, 'external_delivery_confirmation_required'), 'quarantine uses dedicated failure code');
$check(str_contains($quarantineBody, 'next_attempt_at=NULL'), 'quarantine removes retry schedule');
$check(str_contains($quarantineBody, 'stage893_external_delivery_quarantined'), 'quarantine writes audit event');
$check(str_contains($guardBody, 'tl_stage891_process_handoff_owned'), 'guard wraps lease-owned processor');
$check(str_contains($guardBody, 'tl_stage893_quarantine_lost_outcome'), 'guard quarantines lost adapter success');

$check(str_contains($service, "h.failure_code='external_delivery_confirmation_required'"), 'base candidate query includes quarantined handoffs');
$check(str_contains($service, "h.handoff_status='delivered' AND re.status NOT IN ('issued','linked')"), 'base candidate query includes local delivery mismatches');
$check(str_contains($reconcileBody, 'reconciliation_disabled'), 'reconciliation mutation requires explicit enablement');
$check(substr_count($reconcileBody, 'FOR UPDATE') >= 4, 'reconciliation uses two-phase row locking');
$check(str_contains($reconcileBody, 'state_changed_retry_later'), 'reconciliation rejects stale snapshots');
$check(str_contains($reconcileBody, "'local_outbox_delivery'"), 'delivered outbox can repair local reward mismatch');
$check(str_contains($reconcileBody, "handoff_status='delivered'"), 'confirmed external delivery finalizes handoff');
$check(str_contains($reconcileBody, "SET handoff_status='blocked'"), 'unconfirmed delivery remains blocked');
$check(str_contains($reconcileBody, 'stage893_external_delivery_reconciled'), 'confirmed reconciliation writes audit event');
$check(str_contains($reconcileBody, 'stage893_external_delivery_unconfirmed'), 'unconfirmed lookup writes audit event');
$check(!str_contains($reconcileBody, 'tl_stage890_call_adapter'), 'reconciliation never calls issue adapter');
$check(!str_contains($reconcileBody, 'microgifter_issue_training_reward'), 'reconciliation never calls Microgifter issue function');

$check(str_contains($candidateBody, "NOT (h.handoff_status='delivered' AND re.status IN ('issued','linked'))"), 'guarded candidates exclude completed deliveries');
$check(str_contains($candidateBody, "h.handoff_status<>'processing' OR h.locked_at IS NULL OR h.locked_at<=?"), 'guarded candidates exclude active worker leases');
$check(str_contains($singleGuardBody, 'active_worker_lease'), 'single reconciliation rejects active worker lease');
$check(str_contains($processing, "failure_code<>'external_delivery_confirmation_required'"), 'guarded due query excludes reconciliation failure code');
$check(str_contains($processing, "COALESCE(metadata_json,'') NOT LIKE"), 'guarded due query excludes reconciliation metadata flag');
$check(str_contains($processing, 'tl_stage893_reconcile_batch_guarded'), 'guarded batch reconciles safe candidates before delivery');
$check(str_contains($processing, 'tl_stage893_process_handoff_guarded'), 'guarded batch uses quarantine wrapper');

$check(str_contains($enqueueBody, 'external_delivery_reconciliation_required'), 'explicit enqueue rejects reconciliation quarantine');
$check(str_contains($enqueueBody, 'tl_stage890_enqueue_reward_event'), 'safe enqueue delegates after quarantine check');
$check(str_contains($syncBody, "COALESCE(h.failure_code,'') <> 'external_delivery_confirmation_required'"), 'bulk sync excludes reconciliation failure code');
$check(str_contains($syncBody, "COALESCE(h.metadata_json,'') NOT LIKE"), 'bulk sync excludes reconciliation metadata flag');
$check(str_contains($syncBody, 'tl_stage893_enqueue_reward_event_guarded'), 'bulk sync uses guarded enqueue');
$check(str_contains($requeueBody, 'external_delivery_reconciliation_required'), 'manual requeue rejects reconciliation quarantine');
$check(str_contains($requeueBody, 'tl_stage891_requeue_handoff'), 'safe requeue delegates to Stage 891');

$check(str_contains($workerWrapper, 'external_delivery_reconciliation_disabled'), 'scheduled process requires reconciliation gate');
$check(str_contains($workerWrapper, 'tl_stage893_reconcile_batch_guarded'), 'worker runs guarded reconciliation preflight');
$check(str_contains($workerWrapper, 'tl_stage893_sync_outbox_guarded'), 'worker uses quarantine-aware sync');
$check(str_contains($workerWrapper, 'tl_stage893_process_handoff_guarded'), 'worker uses lost-success quarantine processor');
$check(str_contains($workerWrapper, 'active_worker_leases_excluded_from_reconciliation'), 'worker declares active lease exclusion');
$check(str_contains($cli, 'training-lab-stage893-worker-wrapper.php'), 'CLI loads Stage 893 wrapper');
$check(str_contains($cli, 'tl_stage893_run_scheduled_worker'), 'CLI calls Stage 893 worker wrapper');

$check(str_contains($reconciliationApi, 'if ($method === \'GET\')'), 'reconciliation API supports protected GET summary');
$check(str_contains($reconciliationApi, 'tl_security_guard_write($action, $raw)'), 'reconciliation API protects writes');
$check(str_contains($reconciliationApi, 'tl_auth_role_allowed'), 'reconciliation API restricts GET to manager/admin');
$check(str_contains($reconciliationApi, 'tl_stage893_reconcile_handoff_guarded'), 'reconciliation API guards active leases');
$check(str_contains($reconciliationApi, 'tl_stage893_reconcile_batch_guarded'), 'reconciliation API uses guarded candidates');
$check(str_contains($outboxApi, "'enqueue_reward_handoff' => 'tl_stage893_enqueue_reward_event_guarded'"), 'outbox API guards explicit enqueue');
$check(str_contains($outboxApi, "'sync_reward_handoff_outbox' => 'tl_stage893_sync_outbox_guarded'"), 'outbox API guards bulk sync');
$check(str_contains($outboxApi, "'process_reward_handoff' => 'tl_stage893_process_handoff_guarded'"), 'outbox API uses guarded single processor');
$check(str_contains($outboxApi, "'process_reward_handoff_batch' => 'tl_stage893_process_guarded_batch'"), 'outbox API uses guarded batch');
$check(str_contains($operationsApi, 'tl_stage893_process_guarded_batch'), 'operations API uses guarded batch');
$check(str_contains($operationsApi, 'tl_stage893_requeue_handoff_guarded'), 'operations API guards manual requeue');
$check(str_contains($proofReviewApi, 'tl_stage893_sync_outbox_guarded'), 'proof review uses quarantine-aware outbox sync');
$check(str_contains($actionResult, "'stage893_reconcile_delivery' => ['Reconcile external reward delivery', 'tl_stage893_reconcile_handoff_guarded']"), 'admin single reconciliation guards active leases');
$check(str_contains($actionResult, "'stage893_reconcile_delivery_batch' => ['Reconcile external reward delivery batch', 'tl_stage893_reconcile_batch_guarded']"), 'admin batch reconciliation uses guarded candidates');
$check(str_contains($actionResult, "'stage891_requeue_handoff' => ['Requeue reward handoff', 'tl_stage893_requeue_handoff_guarded']"), 'admin requeue uses Stage 893 guard');
$check(str_contains($actionResult, "'enqueue_reward_handoff' => ['Enqueue reward handoff', 'tl_stage893_enqueue_reward_event_guarded']"), 'admin enqueue uses Stage 893 guard');
$check(str_contains($actionResult, "'sync_reward_handoff_outbox' => ['Sync reward handoff outbox', 'tl_stage893_sync_outbox_guarded']"), 'admin sync uses Stage 893 guard');
$check(str_contains($actionResult, "'process_reward_handoff' => ['Process reward handoff', 'tl_stage893_process_handoff_guarded']"), 'admin single processing uses guard');
$check(str_contains($rewardBridge, 'tl_stage893_render_admin_panel_guarded'), 'Reward Bridge renders guarded Stage 893 panel');

foreach ([$config, $labsConfig] as $index => $example) {
    $label = $index === 0 ? 'root config example' : 'labs config example';
    $check(str_contains($example, "'reward_delivery_reconciliation_enabled' => false"), $label . ' keeps reconciliation disabled');
    $check(str_contains($example, "'reward_delivery_reconciliation_batch_size' => 25"), $label . ' documents reconciliation batch');
    $check(str_contains($example, "'reward_delivery_reconciliation_min_age_seconds' => 300"), $label . ' documents minimum age');
}

$check(!is_file($root . '/database/stage893_external_delivery_reconciliation.sql'), 'Stage 893 requires no SQL migration');

if ($failures) {
    fwrite(STDERR, "Stage 893 external delivery reconciliation contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Stage 893 external delivery reconciliation contract passed.\n";
