<?php
require_once __DIR__ . '/../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
require_once __DIR__ . '/../includes/training-lab-campaign-enrollment.php';
require_once __DIR__ . '/../includes/training-lab-task-submission.php';

$result = null;
$error = null;
$requestId = tl_security_request_id();
$action = '';
$campaignRef = '';
$taskRef = '';
try {
    $raw = tl_security_request_data(false);
    $action = preg_replace('/[^a-z0-9_\-]/i', '', (string)($raw['training_action'] ?? $raw['action'] ?? ''));
    if ($action === '') throw new TlHttpException('Training action is required.', 422, 'action_required');
    $user = tl_security_guard_write($action, $raw);
    $data = tl_security_apply_actor($raw, $user);
    if ($action === 'join_campaign') {
        $campaignRef = tl_campaign_clean_ref((string)($data['campaign_id'] ?? $data['campaign'] ?? $data['slug'] ?? ''));
        $enrollment = tl_campaign_secure_enroll($user, $campaignRef);
        $result = [
            'action'=>'join_campaign',
            'label'=>!empty($enrollment['invitation_accepted']) ? 'Invitation accepted' : (!empty($enrollment['already_joined']) ? 'Campaign already joined' : 'Campaign joined'),
            'result'=>$enrollment,
        ];
    } elseif (in_array($action, ['complete_task','submit_proof'], true)) {
        $campaignRef = tl_campaign_clean_ref((string)($data['campaign_id'] ?? $data['campaign'] ?? ''));
        $taskRef = tl_task_clean_ref((string)($data['task_id'] ?? $data['task'] ?? ''));
        $submission = tl_task_secure_submit($user, $data);
        $result = [
            'action'=>$action,
            'label'=>!empty($submission['is_revision']) ? 'Updated proof submitted' : ((string)($submission['status'] ?? '') === 'submitted' ? 'Proof submitted' : 'Task completed'),
            'result'=>$submission,
        ];
    } else {
        $result = tl_training_handle_app_action($data);
    }
} catch (Throwable $e) {
    [$payload] = tl_security_error_payload($e);
    $error = (string)$payload['error'];
    $requestId = (string)$payload['request_id'];
}

$taskQuery = [];
if ($campaignRef !== '') $taskQuery['campaign'] = $campaignRef;
if ($taskRef !== '') $taskQuery['task'] = $taskRef;
$taskDestination = '/app/task-runner.php' . ($taskQuery ? '?' . http_build_query($taskQuery) : '');
$nextMap = [
    'create_campaign_blueprint'=>['/app/index.php','Open My Training'],
    'join_campaign'=>[$campaignRef !== '' ? '/app/campaign-detail.php?id=' . rawurlencode($campaignRef) : '/app/campaigns.php?view=mine','View Campaign'],
    'complete_task'=>[$taskDestination,'Return to Task'],
    'submit_proof'=>[$taskDestination,'Return to Task'],
    'review_proof'=>['/admin/reward-bridge.php','Check Reward Bridge'],
    'claim_training_reward'=>['/app/rewards.php','Return to Rewards'],
    'retry_microgifter_reward_issue'=>['/admin/reward-bridge.php','Return to Reward Bridge'],
    'mark_reward_manual_issued'=>['/admin/reward-bridge.php','Return to Reward Bridge'],
    'cancel_training_reward'=>['/admin/reward-bridge.php','Return to Reward Bridge'],
    'run_core_workflow_qa'=>['/admin/backend-readiness.php','View Readiness'],
    'create_workflow_snapshot'=>['/app/flow-board.php','View Flow Board'],
    'create_account_link_snapshot'=>['/account.php','View Account'],
    'save_proof_quality_note'=>['/admin/review-workbench.php','Review Workbench'],
    'save_reviewer_quality_snapshot'=>['/admin/review-workbench.php','Review Workbench'],
    'run_reward_assurance'=>['/admin/reward-bridge.php','Reward Bridge'],
    'run_release_candidate_qa'=>['/admin/backend-readiness.php','Backend Readiness'],
];
$next = $nextMap[$action] ?? ['/app/index.php','My Training'];
labs_page_start(['title'=>'Action Result | Training Lab','section'=>'app','active'=>'app-dashboard']);
?>
<section class="labs-page-title labs-stage200-title"><div><span class="labs-eyebrow">Training Lab action</span><h1><?php echo $error ? 'Action needs attention.' : 'Action completed.'; ?></h1><p class="labs-copy">The result below comes from the protected Training Lab action router.</p></div><div class="labs-actions"><a class="labs-btn labs-btn-primary" href="<?php echo labs_url($next[0]); ?>"><?php echo labs_e($next[1]); ?></a><a class="labs-btn" href="<?php echo labs_url('/app/index.php'); ?>">My Training</a></div></section>
<section class="labs-card <?php echo $error ? 'labs-error-card' : 'labs-success-card'; ?>"><?php if ($error): ?><h2>Error</h2><p class="labs-copy"><?php echo labs_e($error); ?></p><small>Request ID: <?php echo labs_e($requestId); ?></small><?php else: ?><h2><?php echo labs_e((string)($result['label'] ?? 'Action complete')); ?></h2><p class="labs-copy">Your Training Lab record has been updated.</p><?php endif; ?></section>
<section class="labs-safe-note">Protected by authenticated actor mapping, role permissions, CSRF verification, and safe error handling.</section>
<?php labs_page_end(['section'=>'app']); ?>
