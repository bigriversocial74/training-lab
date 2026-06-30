<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
require_once __DIR__ . '/../includes/training-lab-microgifter-rewards.php';
$userId = max(1, (int)($_GET['user_id'] ?? tl_stage200_actor_id()));
$summary = tl_mg_stage160_user_summary($userId);
$counts = $summary['counts'] ?? [];
$groups = [];
foreach (($summary['rewards'] ?? []) as $reward) { $groups[(string)($reward['lifecycle_status'] ?? 'available_to_claim')][] = $reward; }
$order = ['available_to_claim','claimed_in_app','pending_microgifter_sync','failed_retry_available','issued','linked_to_microgifter','cancelled'];
labs_page_start(['title' => 'My Rewards | Training Lab', 'section' => 'app', 'active' => 'app-rewards']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('app-rewards'); ?>
<?php if (function_exists('tl_stage880_render_award_handoff_queue')) tl_stage880_render_award_handoff_queue(max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage880_render_campaign_sync_health')) tl_stage880_render_campaign_sync_health(); ?>
<?php if (function_exists('tl_stage840_render_award_inbox')) tl_stage840_render_award_inbox(max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage840_render_claim_readiness')) tl_stage840_render_claim_readiness(max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage840_render_award_history')) tl_stage840_render_award_history(max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage800_render_reward_inventory_board')) tl_stage800_render_reward_inventory_board(); ?>
<?php if (function_exists('tl_stage800_render_assignment_preview')) tl_stage800_render_assignment_preview((string)($_GET['campaign'] ?? '')); ?>
<?php if (function_exists('tl_stage760_render_offer_preview_experience')) tl_stage760_render_offer_preview_experience((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage760_render_reward_package_builder')) tl_stage760_render_reward_package_builder((string)($_GET['campaign'] ?? '')); ?>


<?php if (function_exists('tl_stage680_render_participant_communication')) tl_stage680_render_participant_communication((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage640_render_reward_audit_assurance')) tl_stage640_render_reward_audit_assurance(); ?>

<?php if (function_exists('tl_stage520_render_participant_mission')) tl_stage520_render_participant_mission((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage560_render_mission_runbook')) tl_stage560_render_mission_runbook((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>

<?php if (function_exists('tl_stage600_render_reward_operations')) tl_stage600_render_reward_operations(); ?>


<section class="labs-page-title labs-stage200-title"><div><span class="labs-eyebrow">Reward wallet</span><h1>Track and claim Training Lab rewards.</h1><p class="labs-copy">Rewards now follow a clear lifecycle. In-app claim creates Training Lab claim tracking; real Microgifter issuing is adapter/developer-key gated.</p></div><div class="labs-actions"><a class="labs-btn" href="<?php echo labs_url('/api/training/rewards.php?user_id=' . $userId); ?>">Rewards API</a><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/app/task-runner.php?user_id=' . $userId); ?>">Earn More</a></div></section>
<section class="labs-kpis labs-stage160-kpis"><div class="labs-kpi"><span>Total</span><strong><?php echo (int)($counts['total'] ?? 0); ?></strong><small>reward events</small></div><div class="labs-kpi"><span>Claimable</span><strong><?php echo (int)($counts['claimable'] ?? 0); ?></strong><small>available now</small></div><div class="labs-kpi"><span>Pending Sync</span><strong><?php echo (int)($counts['pending_microgifter_sync'] ?? 0); ?></strong><small>adapter queue</small></div><div class="labs-kpi"><span>Issued</span><strong><?php echo (int)(($counts['issued'] ?? 0)+($counts['linked_to_microgifter'] ?? 0)); ?></strong><small>issued/linked</small></div><div class="labs-kpi"><span>Retryable</span><strong><?php echo (int)($counts['retryable'] ?? 0); ?></strong><small>admin can retry</small></div></section>
<section class="labs-flow-grid labs-stage160-rewards-grid"><article class="labs-card labs-stage160-claims-card"><h2>Reward list</h2><?php foreach ($order as $state): $items=$groups[$state] ?? []; if (!$items) continue; ?><div class="labs-stage160-lifecycle-group"><h3><?php echo labs_e(ucwords(str_replace('_',' ',$state))); ?> <span><?php echo count($items); ?></span></h3><div class="labs-stage160-claim-table"><?php foreach ($items as $reward): ?><div class="labs-stage160-claim-row labs-lifecycle-<?php echo labs_e($state); ?>"><div><span class="labs-pill"><?php echo labs_e($state); ?></span><strong><?php echo labs_e((string)$reward['display_label']); ?></strong><p><?php echo labs_e((string)($reward['campaign_title'] ?? 'Training campaign')); ?> · <?php echo labs_e((string)$reward['display_value']); ?></p><small><?php echo labs_e((string)($reward['eligibility_reason'] ?? 'Training Lab eligibility reached.')); ?></small></div><div class="labs-stage160-claim-actions"><?php if (!empty($reward['claimable'])): ?><form action="<?php echo labs_url('/app/action-result.php'); ?>" method="post"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="claim_training_reward"><input type="hidden" name="reward_event_id" value="<?php echo (int)$reward['id']; ?>"><input type="hidden" name="user_id" value="<?php echo $userId; ?>"><button class="labs-btn labs-btn-primary" type="submit">Claim In App</button></form><?php else: ?><a class="labs-btn" href="<?php echo labs_url('/app/flow-board.php?user_id=' . $userId); ?>">View Flow</a><?php endif; ?></div></div><?php endforeach; ?></div></div><?php endforeach; ?><?php if (empty($summary['rewards'])): ?><div class="labs-empty-state"><strong>No rewards yet</strong><p>Complete tasks and get proof approved to generate reward eligibility.</p><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/app/task-runner.php?user_id=' . $userId); ?>">Run Tasks</a></div><?php endif; ?></article><aside class="labs-card"><h2>Claim policy</h2><ol class="labs-stage160-steps"><li><strong>Earn</strong><span>Complete tasks and receive an approved receipt.</span></li><li><strong>Claim in app</strong><span>Creates Training Lab claim metadata and a claim code.</span></li><li><strong>Sync</strong><span>Adapter/key can issue or link to Microgifter.</span></li><li><strong>Recover</strong><span>Admin can retry, mark manual, or cancel safely.</span></li></ol></aside></section>
<section class="labs-safe-note">The rewards page does not mutate wallet balances or redeem production claims. It tracks claim state and calls Microgifter only through configured adapters.</section>

<section class="labs-card labs-stage280-panel">
  <?php $stage280Rewards = tl_stage280_reward_assurance($userId ?? null, ''); ?>
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Build 14</span><h2>Reward Claim Assurance</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/reward-assurance.php?user_id=' . (int)($userId ?? 1)); ?>">Assurance API</a></div>
  <div class="labs-kpis labs-stage200-kpis"><div class="labs-kpi"><span>Assurance</span><strong><?php echo (int)$stage280Rewards['score']; ?>/100</strong><small>claim lifecycle</small></div><div class="labs-kpi"><span>Needs Action</span><strong><?php echo (int)$stage280Rewards['needs_action_count']; ?></strong><small>claim/issue queue</small></div></div>
</section>

<?php labs_page_end(['section' => 'app']); ?>
