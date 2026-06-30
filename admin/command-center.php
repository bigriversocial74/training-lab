<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
$admin = tl_stage200_admin_state();
$score = $admin['operations_score'];
$flow = $admin['flow'];
$counts = $flow['counts'] ?? [];
$stage240Ready = tl_stage240_product_readiness();

labs_page_start(['title' => 'Command Center | Training Lab', 'section' => 'admin', 'active' => 'admin-command-center']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-command-center'); ?>
<?php if (function_exists('tl_stage880_render_adapter_configuration_center')) tl_stage880_render_adapter_configuration_center(); ?>
<?php if (function_exists('tl_stage880_render_campaign_sync_health')) tl_stage880_render_campaign_sync_health(); ?>
<?php if (function_exists('tl_stage800_render_reward_campaign_import')) tl_stage800_render_reward_campaign_import(); ?>
<?php if (function_exists('tl_stage800_render_reward_inventory_board')) tl_stage800_render_reward_inventory_board(); ?>
<?php if (function_exists('tl_stage760_render_merchant_operations_console')) tl_stage760_render_merchant_operations_console(); ?>
<?php if (function_exists('tl_stage760_render_merchant_sponsor_context')) tl_stage760_render_merchant_sponsor_context((string)($_GET['campaign'] ?? '')); ?>


<?php if (function_exists('tl_stage720_render_admin_training_quality_console')) tl_stage720_render_admin_training_quality_console(); ?>

<?php if (function_exists('tl_stage680_render_admin_communication_console')) tl_stage680_render_admin_communication_console(); ?>
<?php if (function_exists('tl_stage680_render_mission_followup_logic')) tl_stage680_render_mission_followup_logic(); ?>
<?php if (function_exists('tl_stage680_render_operator_daily_rhythm')) tl_stage680_render_operator_daily_rhythm(); ?>
<?php if (function_exists('tl_stage640_render_operator_health_dashboard')) tl_stage640_render_operator_health_dashboard(); ?>

<?php if (function_exists('tl_stage640_render_reward_audit_assurance')) tl_stage640_render_reward_audit_assurance(); ?>

<?php if (function_exists('tl_stage520_render_admin_operations')) tl_stage520_render_admin_operations(); ?>
<?php if (function_exists('tl_stage560_render_review_reward_assurance')) tl_stage560_render_review_reward_assurance(); ?>

<?php if (function_exists('tl_stage600_render_operator_command_snapshot')) tl_stage600_render_operator_command_snapshot(); ?>
<?php if (function_exists('tl_stage600_render_reward_operations')) tl_stage600_render_reward_operations(); ?>


<section class="labs-page-title labs-stage200-title"><div><span class="labs-eyebrow">Admin command center</span><h1>Operate the Training Lab backend flow.</h1><p class="labs-copy">Command Center now focuses on the actual operating loop: campaigns, participants, proof review, receipts, rewards, QA, and readiness.</p></div><div class="labs-actions"><a class="labs-btn" href="<?php echo labs_url('/admin/review-workbench.php'); ?>">Review Workbench</a><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/admin/backend-readiness.php'); ?>">Readiness</a></div></section>
<section class="labs-kpis labs-stage200-kpis"><div class="labs-kpi"><span>Ops Score</span><strong><?php echo (int)$score['score']; ?>/100</strong><small><?php echo (int)$score['passed']; ?>/<?php echo (int)$score['total']; ?> checks</small></div><div class="labs-kpi"><span>Campaigns</span><strong><?php echo (int)($counts['campaigns'] ?? 0); ?></strong><small>programs</small></div><div class="labs-kpi"><span>Participants</span><strong><?php echo (int)($counts['participants'] ?? 0); ?></strong><small>users in flow</small></div><div class="labs-kpi"><span>Pending Proof</span><strong><?php echo count($admin['pending_proofs'] ?? []); ?></strong><small>review needed</small></div><div class="labs-kpi"><span>Reward Queue</span><strong><?php echo (int)($admin['reward_bridge']['counts']['retryable'] ?? 0); ?></strong><small>retryable</small></div></section>
<section class="labs-flow-grid labs-stage200-grid"><article class="labs-card"><h2>Operating lanes</h2><div class="labs-stage200-step-grid"><a class="labs-stage200-step" href="<?php echo labs_url('/admin/campaigns.php'); ?>"><span>programs</span><strong>Campaign Ops</strong><small>Create, inspect, and status campaigns.</small></a><a class="labs-stage200-step" href="<?php echo labs_url('/admin/review-workbench.php'); ?>"><span>proof</span><strong>Review Queue</strong><small>Approve, reject, or request more info.</small></a><a class="labs-stage200-step" href="<?php echo labs_url('/admin/reward-bridge.php'); ?>"><span>rewards</span><strong>Reward Bridge</strong><small>Retry, manual issue, or cancel claim events.</small></a><a class="labs-stage200-step" href="<?php echo labs_url('/admin/backend-readiness.php'); ?>"><span>qa</span><strong>Backend Readiness</strong><small>Run route and workflow QA.</small></a></div></article><aside class="labs-card"><h2>Run QA snapshot</h2><p class="labs-muted">Stores a Training Lab event with the current backend readiness state.</p><form action="<?php echo labs_url('/admin/action-result.php'); ?>" method="post" class="labs-stage30-form"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="run_core_workflow_qa"><input type="hidden" name="log_event" value="1"><button class="labs-btn labs-btn-primary" type="submit">Run Core QA</button></form><form action="<?php echo labs_url('/admin/action-result.php'); ?>" method="post" class="labs-stage30-form"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="create_workflow_snapshot"><button class="labs-btn" type="submit">Create Snapshot</button></form></aside></section>
<section class="labs-card"><h2>Recent activity</h2><div class="labs-panel-list"><?php foreach (array_slice($admin['recent_events'] ?? [], 0, 10) as $event): ?><div class="labs-panel-item"><span class="labs-mini-icon">E</span><div><strong><?php echo labs_e((string)($event['event_type'] ?? 'event')); ?></strong><p><?php echo labs_e((string)($event['subject_type'] ?? 'system')); ?> · <?php echo labs_e((string)($event['created_at'] ?? '')); ?></p></div></div><?php endforeach; ?></div></section>
<section class="labs-safe-note">Command Center writes only Training Lab QA/snapshot events unless a user submits an existing workflow action.</section>

<section class="labs-card labs-stage240-panel">
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 201–240</span><h2>Stacked Product Build Health</h2></div><a class="labs-btn" href="<?php echo labs_url('/admin/backend-readiness.php'); ?>">Backend Readiness</a></div>
  <div class="labs-kpis labs-stage200-kpis"><div class="labs-kpi"><span>Product</span><strong><?php echo (int)$stage240Ready['score']; ?>/100</strong><small>self-test</small></div><div class="labs-kpi"><span>Campaign Ops</span><strong><?php echo (int)$stage240Ready['campaign_ops']['score']; ?>/100</strong><small>builder depth</small></div><div class="labs-kpi"><span>Fulfillment</span><strong><?php echo (int)$stage240Ready['reward_fulfillment']['score']; ?>/100</strong><small>reward queue</small></div></div>
</section>


<section class="labs-card labs-stage280-panel">
  <?php $stage280 = tl_stage280_summary(); ?>
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 241–280</span><h2>Stacked app quality console</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/release-candidate.php'); ?>">Release API</a></div>
  <div class="labs-kpis labs-stage200-kpis"><div class="labs-kpi"><span>Release</span><strong><?php echo (int)$stage280['release_candidate']['score']; ?>/100</strong><small><?php echo !empty($stage280['release_candidate']['accepted']) ? 'accepted' : 'needs checks'; ?></small></div><div class="labs-kpi"><span>Proof</span><strong><?php echo (int)$stage280['proof_quality']['score']; ?>/100</strong><small>quality engine</small></div><div class="labs-kpi"><span>Review</span><strong><?php echo (int)$stage280['reviewer_scorecard']['score']; ?>/100</strong><small>decision loop</small></div><div class="labs-kpi"><span>Reward</span><strong><?php echo (int)$stage280['reward_assurance']['score']; ?>/100</strong><small>claim assurance</small></div></div>
</section>

<?php labs_page_end(['section' => 'admin']); ?>
