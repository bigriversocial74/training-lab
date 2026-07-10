<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
require_once __DIR__ . '/../includes/training-lab-microgifter-rewards.php';
require_once __DIR__ . '/../includes/training-lab-stage890-reward-handoff-outbox.php';
require_once __DIR__ . '/../includes/training-lab-stage891-reward-handoff-recovery.php';
require_once __DIR__ . '/../includes/training-lab-stage891-terminal-failure-panel.php';
$bridge = tl_mg_stage160_bridge_summary();
$counts = $bridge['counts'] ?? [];
$rewards = $bridge['admin_rewards'] ?? [];
$stage240Fulfillment = tl_stage240_reward_fulfillment_state();

labs_page_start(['title' => 'Reward Bridge | Training Lab', 'section' => 'admin', 'active' => 'admin-reward-bridge']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-reward-bridge'); ?>
<?php if (function_exists('tl_stage880_render_adapter_configuration_center')) tl_stage880_render_adapter_configuration_center(); ?>
<?php if (function_exists('tl_stage880_render_campaign_sync_health')) tl_stage880_render_campaign_sync_health(); ?>
<?php if (function_exists('tl_stage890_render_admin_panel')) tl_stage890_render_admin_panel(); ?>
<?php if (function_exists('tl_stage891_render_admin_panel')) tl_stage891_render_admin_panel(); ?>
<?php if (function_exists('tl_stage891_render_terminal_failure_panel')) tl_stage891_render_terminal_failure_panel(); ?>
<?php if (function_exists('tl_stage880_render_award_handoff_queue')) tl_stage880_render_award_handoff_queue(max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage800_render_reward_campaign_import')) tl_stage800_render_reward_campaign_import(); ?>
<?php if (function_exists('tl_stage800_render_reward_inventory_board')) tl_stage800_render_reward_inventory_board(); ?>
<?php if (function_exists('tl_stage800_render_assignment_preview')) tl_stage800_render_assignment_preview((string)($_GET['campaign'] ?? '')); ?>
<?php if (function_exists('tl_stage760_render_reward_package_builder')) tl_stage760_render_reward_package_builder((string)($_GET['campaign'] ?? '')); ?>
<?php if (function_exists('tl_stage760_render_merchant_operations_console')) tl_stage760_render_merchant_operations_console(); ?>

<?php if (function_exists('tl_stage640_render_reward_audit_assurance')) tl_stage640_render_reward_audit_assurance(); ?>

<?php if (function_exists('tl_stage520_render_admin_operations')) tl_stage520_render_admin_operations(); ?>
<?php if (function_exists('tl_stage560_render_review_reward_assurance')) tl_stage560_render_review_reward_assurance(); ?>

<?php if (function_exists('tl_stage600_render_reward_operations')) tl_stage600_render_reward_operations(); ?>


<section class="labs-page-title labs-stage200-title"><div><span class="labs-eyebrow">Reward bridge</span><h1>Operate Microgifter reward sync safely.</h1><p class="labs-copy">Use this page to inspect pending reward claims, retry adapter issuing, mark manual issue, cancel training rewards, and reconcile lifecycle metadata.</p></div><div class="labs-actions"><a class="labs-btn" href="<?php echo labs_url('/api/training/reward-bridge.php'); ?>">Bridge API</a><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/admin/backend-readiness.php'); ?>">Readiness</a></div></section>
<section class="labs-kpis labs-stage160-kpis"><div class="labs-kpi"><span>Total</span><strong><?php echo (int)($counts['total'] ?? 0); ?></strong><small>reward events</small></div><div class="labs-kpi"><span>Claimable</span><strong><?php echo (int)($counts['claimable'] ?? 0); ?></strong><small>user action</small></div><div class="labs-kpi"><span>Pending</span><strong><?php echo (int)($counts['pending_microgifter_sync'] ?? 0); ?></strong><small>sync queue</small></div><div class="labs-kpi"><span>Failed</span><strong><?php echo (int)($counts['failed_retry_available'] ?? 0); ?></strong><small>retry needed</small></div><div class="labs-kpi"><span>Issued</span><strong><?php echo (int)(($counts['issued'] ?? 0)+($counts['linked_to_microgifter'] ?? 0)); ?></strong><small>complete</small></div></section>
<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Lifecycle operations</span><h2>Reward event queue</h2></div><form action="<?php echo labs_url('/admin/action-result.php'); ?>" method="post"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="reconcile_reward_lifecycle"><button class="labs-btn" type="submit">Reconcile</button></form></div><div class="labs-stage160-claim-table"><?php foreach ($rewards as $reward): ?><div class="labs-stage160-claim-row labs-lifecycle-<?php echo labs_e((string)$reward['lifecycle_status']); ?>"><div><span class="labs-pill"><?php echo labs_e((string)$reward['lifecycle_status']); ?></span><strong><?php echo labs_e((string)$reward['display_label']); ?></strong><p><?php echo labs_e((string)($reward['campaign_title'] ?? 'Campaign')); ?> · <?php echo labs_e((string)($reward['participant_label'] ?? 'Participant')); ?> · <?php echo labs_e((string)$reward['display_value']); ?></p><small><?php echo labs_e((string)($reward['failure_message'] ?? $reward['eligibility_reason'] ?? '')); ?></small></div><div class="labs-stage160-claim-actions"><form action="<?php echo labs_url('/admin/action-result.php'); ?>" method="post"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="retry_microgifter_reward_issue"><input type="hidden" name="reward_event_id" value="<?php echo (int)$reward['id']; ?>"><button class="labs-btn" type="submit">Retry</button></form><form action="<?php echo labs_url('/admin/action-result.php'); ?>" method="post"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="mark_reward_manual_issued"><input type="hidden" name="reward_event_id" value="<?php echo (int)$reward['id']; ?>"><button class="labs-btn" type="submit">Manual Issue</button></form><form action="<?php echo labs_url('/admin/action-result.php'); ?>" method="post"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="cancel_training_reward"><input type="hidden" name="reward_event_id" value="<?php echo (int)$reward['id']; ?>"><button class="labs-btn labs-btn-danger" type="submit">Cancel</button></form></div></div><?php endforeach; ?><?php if (!$rewards): ?><div class="labs-empty-state"><strong>No reward events yet</strong><p>Approve proof or complete a sequence to create eligible rewards.</p></div><?php endif; ?></div></section>
<section class="labs-safe-note">Real Microgifter issuing is adapter/developer-key gated. Manual issue records are Training Lab records only and do not mutate wallet balances.</section>

<section class="labs-card labs-stage240-panel">
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Build 9</span><h2>Reward Fulfillment Queue</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/fulfillment-queue.php'); ?>">Fulfillment API</a></div>
  <div class="labs-kpis labs-stage200-kpis"><div class="labs-kpi"><span>Open</span><strong><?php echo (int)$stage240Fulfillment['open_count']; ?></strong><small>eligible/queued/failed</small></div><div class="labs-kpi"><span>Failed</span><strong><?php echo (int)$stage240Fulfillment['counts']['failed']; ?></strong><small>retry needed</small></div><div class="labs-kpi"><span>Score</span><strong><?php echo (int)$stage240Fulfillment['score']; ?>/100</strong><small>fulfillment health</small></div></div>
  <form action="<?php echo labs_url('/admin/action-result.php'); ?>" method="post" class="labs-stage30-form"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="create_fulfillment_snapshot"><button class="labs-btn" type="submit">Save Fulfillment Snapshot</button></form>
</section>


<section class="labs-card labs-stage280-panel">
  <?php $stage280Assurance = tl_stage280_reward_assurance(null, ''); ?>
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Build 14</span><h2>Reward Claim Assurance</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/reward-assurance.php'); ?>">Assurance API</a></div>
  <div class="labs-kpis labs-stage200-kpis"><div class="labs-kpi"><span>Assurance</span><strong><?php echo (int)$stage280Assurance['score']; ?>/100</strong><small>bridge and recovery</small></div><div class="labs-kpi"><span>Needs Action</span><strong><?php echo (int)$stage280Assurance['needs_action_count']; ?></strong><small>reward queue</small></div></div>
  <form action="<?php echo labs_url('/admin/action-result.php'); ?>" method="post" class="labs-stage30-form"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="run_reward_assurance"><input type="hidden" name="reconcile" value="1"><button class="labs-btn labs-btn-primary" type="submit">Run Assurance + Reconcile</button></form>
</section>

<?php labs_page_end(['section' => 'admin']); ?>