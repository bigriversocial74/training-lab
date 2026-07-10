<?php
require_once __DIR__ . '/../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
$stage885Path = __DIR__ . '/../includes/training-lab-stage885-proof-review-handoff.php';
if (is_file($stage885Path)) require_once $stage885Path;
$stage890Path = __DIR__ . '/../includes/training-lab-stage890-reward-handoff-outbox.php';
if (is_file($stage890Path)) require_once $stage890Path;
$stage891Path = __DIR__ . '/../includes/training-lab-stage891-reward-handoff-recovery.php';
if (is_file($stage891Path)) require_once $stage891Path;
$stage893Path = __DIR__ . '/../includes/training-lab-stage893-processing-wrapper.php';
if (is_file($stage893Path)) require_once $stage893Path;

$result = null;
$error = null;
$requestId = tl_security_request_id();
$action = '';
try {
    $raw = tl_security_request_data(false);
    $action = preg_replace('/[^a-z0-9_\-]/i', '', (string)($raw['training_action'] ?? $raw['action'] ?? ''));
    if ($action === '') throw new TlHttpException('Training action is required.', 422, 'action_required');
    $user = tl_security_guard_write($action, $raw);
    $data = tl_security_apply_actor($raw, $user);
    $stage893Actions = [
        'stage893_reconcile_delivery' => ['Reconcile external reward delivery', 'tl_stage893_reconcile_handoff'],
        'stage893_reconcile_delivery_batch' => ['Reconcile external reward delivery batch', 'tl_stage893_reconcile_batch'],
    ];
    $stage891Actions = [
        'stage891_recover_stale_handoffs' => ['Recover stale reward handoffs', 'tl_stage891_recover_stale_processing'],
        'stage891_requeue_handoff' => ['Requeue reward handoff', 'tl_stage893_requeue_handoff_guarded'],
        'stage891_process_resilient_batch' => ['Recover and process reward handoff batch', 'tl_stage893_process_guarded_batch'],
        'stage891_run_handoff_acceptance' => ['Run reward handoff acceptance', 'tl_stage891_run_acceptance'],
    ];
    $stage890Actions = [
        'enqueue_reward_handoff' => ['Enqueue reward handoff', 'tl_stage890_enqueue_reward_event'],
        'sync_reward_handoff_outbox' => ['Sync reward handoff outbox', 'tl_stage890_sync_outbox'],
        'process_reward_handoff' => ['Process reward handoff', 'tl_stage893_process_handoff_guarded'],
        'process_reward_handoff_batch' => ['Process reward handoff batch', 'tl_stage893_process_guarded_batch'],
        'cancel_reward_handoff' => ['Cancel reward handoff', 'tl_stage890_cancel_handoff'],
    ];
    if (isset($stage893Actions[$action]) && function_exists($stage893Actions[$action][1])) {
        $fn = $stage893Actions[$action][1];
        $result = ['action'=>$action, 'label'=>$stage893Actions[$action][0], 'result'=>$fn($data)];
    } elseif (isset($stage891Actions[$action]) && function_exists($stage891Actions[$action][1])) {
        $fn = $stage891Actions[$action][1];
        $result = ['action'=>$action, 'label'=>$stage891Actions[$action][0], 'result'=>$fn($data)];
    } elseif (isset($stage890Actions[$action]) && function_exists($stage890Actions[$action][1])) {
        $fn = $stage890Actions[$action][1];
        $result = ['action'=>$action, 'label'=>$stage890Actions[$action][0], 'result'=>$fn($data)];
    } elseif ($action === 'stage885_review_proof' && function_exists('tl_stage885_submit_review_decision')) {
        $result = tl_stage885_submit_review_decision($data);
    } else {
        $result = tl_training_handle_app_action($data);
    }
    if (in_array($action, ['review_proof','stage885_review_proof'], true) && function_exists('tl_stage890_sync_outbox') && tl_stage890_table_ready()) {
        try {
            $result['stage890_outbox_sync'] = tl_stage890_sync_outbox($data + ['limit'=>25]);
        } catch (Throwable $syncError) {
            $result['stage890_outbox_sync'] = ['ok'=>false, 'error'=>$syncError->getMessage()];
        }
    }
} catch (Throwable $e) {
    [$payload] = tl_security_error_payload($e);
    $error = (string)$payload['error'];
    $requestId = (string)$payload['request_id'];
}

$nextMap = [
    'create_campaign_blueprint'=>['/app/participant-portal.php','Open Mission Control'],
    'join_campaign'=>['/app/task-runner.php','Run Tasks'],
    'complete_task'=>['/app/participant-portal.php','Return to Mission Control'],
    'review_proof'=>['/admin/reward-bridge.php','Check Reward Bridge'],
    'stage885_review_proof'=>['/admin/review-workbench.php','Return to Stage 885 Review Workbench'],
    'claim_training_reward'=>['/app/rewards.php','Return to Rewards'],
    'retry_microgifter_reward_issue'=>['/admin/reward-bridge.php','Return to Reward Bridge'],
    'mark_reward_manual_issued'=>['/admin/reward-bridge.php','Return to Reward Bridge'],
    'cancel_training_reward'=>['/admin/reward-bridge.php','Return to Reward Bridge'],
    'enqueue_reward_handoff'=>['/admin/reward-bridge.php','Return to Reward Bridge'],
    'sync_reward_handoff_outbox'=>['/admin/reward-bridge.php','Return to Reward Bridge'],
    'process_reward_handoff'=>['/admin/reward-bridge.php','Return to Reward Bridge'],
    'process_reward_handoff_batch'=>['/admin/reward-bridge.php','Return to Reward Bridge'],
    'cancel_reward_handoff'=>['/admin/reward-bridge.php','Return to Reward Bridge'],
    'stage891_recover_stale_handoffs'=>['/admin/reward-bridge.php','Return to Reward Bridge'],
    'stage891_requeue_handoff'=>['/admin/reward-bridge.php','Return to Reward Bridge'],
    'stage891_process_resilient_batch'=>['/admin/reward-bridge.php','Return to Reward Bridge'],
    'stage891_run_handoff_acceptance'=>['/admin/reward-bridge.php','Return to Reward Bridge'],
    'stage893_reconcile_delivery'=>['/admin/reward-bridge.php','Return to Reward Bridge'],
    'stage893_reconcile_delivery_batch'=>['/admin/reward-bridge.php','Return to Reward Bridge'],
    'run_core_workflow_qa'=>['/admin/backend-readiness.php','View Readiness'],
    'create_workflow_snapshot'=>['/app/flow-board.php','View Flow Board'],
    'create_account_link_snapshot'=>['/account.php','View Account'],
    'save_proof_quality_note'=>['/admin/review-workbench.php','Review Workbench'],
    'save_reviewer_quality_snapshot'=>['/admin/review-workbench.php','Review Workbench'],
    'run_reward_assurance'=>['/admin/reward-bridge.php','Reward Bridge'],
    'run_release_candidate_qa'=>['/admin/backend-readiness.php','Backend Readiness'],
];
$next = $nextMap[$action] ?? ['/admin/command-center.php','Command Center'];
labs_page_start(['title'=>'Action Result | Training Lab','section'=>'admin','active'=>'admin-command-center']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-action-result'); ?>
<section class="labs-page-title labs-stage200-title"><div><span class="labs-eyebrow">Training Lab action</span><h1><?php echo $error ? 'Action needs attention.' : 'Action completed.'; ?></h1><p class="labs-copy">The result below comes from the protected Training Lab action router.</p></div><div class="labs-actions"><a class="labs-btn labs-btn-primary" href="<?php echo labs_url($next[0]); ?>"><?php echo labs_e($next[1]); ?></a><a class="labs-btn" href="<?php echo labs_url('/app/flow-board.php'); ?>">Flow Board</a></div></section>
<section class="labs-card <?php echo $error ? 'labs-error-card' : 'labs-success-card'; ?>"><?php if ($error): ?><h2>Error</h2><p class="labs-copy"><?php echo labs_e($error); ?></p><small>Request ID: <?php echo labs_e($requestId); ?></small><?php else: ?><h2><?php echo labs_e((string)($result['label'] ?? 'Action complete')); ?></h2><p class="labs-copy">Written to Training Lab tables only unless the explicitly gated Stage 893 guarded processor confirms or reconciles a Microgifter handoff.</p><pre class="labs-stage25-code"><?php echo labs_e(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre><?php endif; ?></section>
<section class="labs-safe-note">Protected by authenticated actor mapping, role permissions, CSRF verification, worker-lease ownership, external-delivery reconciliation, and safe error handling.</section>
<?php labs_page_end(['section'=>'admin']); ?>
