<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
$isAdmin = __DIR__ === dirname(__DIR__) . '/admin';
$result = null;
$error = null;
$action = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $data = tl_request_data();
        $action = (string)($data['training_action'] ?? $data['action'] ?? '');
        $result = tl_training_handle_app_action($data);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
} else {
    $error = 'Training actions must be submitted with POST.';
}
$nextMap = [
    'create_campaign_blueprint' => ['/app/participant-portal.php', 'Open Mission Control'],
    'join_campaign' => ['/app/task-runner.php', 'Run Tasks'],
    'complete_task' => ['/app/participant-portal.php', 'Return to Mission Control'],
    'review_proof' => ['/admin/reward-bridge.php', 'Check Reward Bridge'],
    'claim_training_reward' => ['/app/rewards.php', 'Return to Rewards'],
    'retry_microgifter_reward_issue' => ['/admin/reward-bridge.php', 'Return to Reward Bridge'],
    'mark_reward_manual_issued' => ['/admin/reward-bridge.php', 'Return to Reward Bridge'],
    'cancel_training_reward' => ['/admin/reward-bridge.php', 'Return to Reward Bridge'],
    'run_core_workflow_qa' => ['/admin/backend-readiness.php', 'View Readiness'],
    'create_workflow_snapshot' => ['/app/flow-board.php', 'View Flow Board'],
    'create_account_link_snapshot' => ['/account.php', 'View Account'],
    'save_proof_quality_note' => ['/admin/review-workbench.php', 'Review Workbench'],
    'save_reviewer_quality_snapshot' => ['/admin/review-workbench.php', 'Review Workbench'],
    'run_reward_assurance' => ['/admin/reward-bridge.php', 'Reward Bridge'],
    'run_release_candidate_qa' => ['/admin/backend-readiness.php', 'Backend Readiness'],
];
$next = $nextMap[$action] ?? ($isAdmin ? ['/admin/command-center.php', 'Command Center'] : ['/app/index.php', 'App Dashboard']);
labs_page_start(['title' => 'Action Result | Training Lab', 'section' => $isAdmin ? 'admin' : 'app', 'active' => $isAdmin ? 'admin-command-center' : 'app-dashboard']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-action-result'); ?>

<section class="labs-page-title labs-stage200-title"><div><span class="labs-eyebrow">Training Lab action</span><h1><?php echo $error ? 'Action needs attention.' : 'Action completed.'; ?></h1><p class="labs-copy">The result below comes from the standalone Training Lab backend action router.</p></div><div class="labs-actions"><a class="labs-btn labs-btn-primary" href="<?php echo labs_url($next[0]); ?>"><?php echo labs_e($next[1]); ?></a><a class="labs-btn" href="<?php echo labs_url('/app/flow-board.php'); ?>">Flow Board</a></div></section>
<section class="labs-card <?php echo $error ? 'labs-error-card' : 'labs-success-card'; ?>"><?php if ($error): ?><h2>Error</h2><p class="labs-copy"><?php echo labs_e($error); ?></p><?php else: ?><h2><?php echo labs_e((string)($result['label'] ?? 'Action complete')); ?></h2><p class="labs-copy">Written to Training Lab tables only.</p><pre class="labs-stage25-code"><?php echo labs_e(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre><?php endif; ?></section>
<section class="labs-safe-note">No payment, wallet mutation, production redeem, upload processing, or external notification was performed by this action result page.</section>
<?php labs_page_end(['section' => $isAdmin ? 'admin' : 'app']); ?>
