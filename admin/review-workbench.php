<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
$pending = tl_app_pending_proofs(50);
$recent = tl_app_recent_reviews(20);
$stage240Sla = tl_stage240_review_sla_state();

labs_page_start(['title' => 'Review Workbench | Training Lab', 'section' => 'admin', 'active' => 'admin-review-workbench']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-review-workbench'); ?>
<?php if (function_exists('tl_stage880_render_award_handoff_queue')) tl_stage880_render_award_handoff_queue(max(0, (int)($_GET['user_id'] ?? 0))); ?>

<?php if (function_exists('tl_stage720_render_admin_training_quality_console')) tl_stage720_render_admin_training_quality_console(); ?>

<?php if (function_exists('tl_stage680_render_admin_communication_console')) tl_stage680_render_admin_communication_console(); ?>
<?php if (function_exists('tl_stage640_render_proof_evidence_quality')) tl_stage640_render_proof_evidence_quality(); ?>

<?php if (function_exists('tl_stage520_render_admin_operations')) tl_stage520_render_admin_operations(); ?>
<?php if (function_exists('tl_stage560_render_review_reward_assurance')) tl_stage560_render_review_reward_assurance(); ?>

<?php if (function_exists('tl_stage600_render_proof_review_console')) tl_stage600_render_proof_review_console(); ?>


<section class="labs-page-title labs-stage200-title"><div><span class="labs-eyebrow">Review workbench</span><h1>Decide proof and move the reward lifecycle forward.</h1><p class="labs-copy">Approvals create receipts and can create eligible rewards. Needs-more-info keeps the proof in review without issuing a receipt.</p></div><div class="labs-actions"><a class="labs-btn" href="<?php echo labs_url('/admin/reward-bridge.php'); ?>">Reward Bridge</a><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/app/flow-board.php'); ?>">Flow Board</a></div></section>
<section class="labs-kpis labs-stage200-kpis"><div class="labs-kpi"><span>Pending</span><strong><?php echo count($pending); ?></strong><small>proof decisions</small></div><div class="labs-kpi"><span>Recent Decisions</span><strong><?php echo count($recent); ?></strong><small>latest reviews</small></div></section>
<section class="labs-card"><h2>Decision queue</h2><div class="labs-stage35-review-list"><?php foreach ($pending as $proof): ?><article class="labs-stage35-review-item"><div><span class="labs-pill"><?php echo labs_e((string)$proof['status']); ?></span><h3><?php echo labs_e((string)($proof['task_title'] ?? 'Training proof')); ?></h3><p><?php echo labs_e((string)($proof['campaign_title'] ?? 'Campaign')); ?> · <?php echo labs_e((string)($proof['participant_label'] ?? 'Participant')); ?></p><blockquote><?php echo labs_e((string)($proof['proof_text'] ?? 'No proof text.')); ?></blockquote></div><form action="<?php echo labs_url('/admin/action-result.php'); ?>" method="post" class="labs-stage30-form labs-stage35-decision-form"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="review_proof"><input type="hidden" name="proof_id" value="<?php echo (int)$proof['id']; ?>"><label>Decision<select name="decision"><option value="approved">Approve</option><option value="needs_more_info">Needs more info</option><option value="rejected">Reject</option></select></label><label>Review notes<textarea name="review_notes" rows="3">Reviewed in Stage 200 Review Workbench.</textarea></label><button class="labs-btn labs-btn-primary" type="submit">Submit Decision</button></form></article><?php endforeach; ?><?php if (!$pending): ?><div class="labs-empty-state"><strong>No pending proof</strong><p>Submitted proof will appear here. Complete a proof-required task to test the review loop.</p></div><?php endif; ?></div></section>
<section class="labs-card"><h2>Recent review decisions</h2><div class="labs-panel-list"><?php foreach ($recent as $row): ?><div class="labs-panel-item"><span class="labs-mini-icon">R</span><div><strong><?php echo labs_e((string)($row['decision'] ?? 'review')); ?></strong><p><?php echo labs_e((string)($row['reviewed_at'] ?? $row['created_at'] ?? '')); ?></p></div></div><?php endforeach; ?></div></section>
<section class="labs-safe-note">Review Workbench creates Training Lab review/receipt/reward eligibility records only. It does not issue production rewards directly.</section>

<section class="labs-card labs-stage240-panel">
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Build 8</span><h2>Review SLA + Decision Quality</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/review-ops.php'); ?>">Review Ops API</a></div>
  <div class="labs-kpis labs-stage200-kpis"><div class="labs-kpi"><span>Pending</span><strong><?php echo (int)$stage240Sla['counts']['pending']; ?></strong><small>submitted/in review</small></div><div class="labs-kpi"><span>Over 24h</span><strong><?php echo (int)$stage240Sla['counts']['over_24h']; ?></strong><small>SLA watch</small></div><div class="labs-kpi"><span>Score</span><strong><?php echo (int)$stage240Sla['score']; ?>/100</strong><small>review health</small></div></div>
  <form action="<?php echo labs_url('/admin/action-result.php'); ?>" method="post" class="labs-stage30-form"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="create_review_sla_snapshot"><button class="labs-btn" type="submit">Save Review SLA Snapshot</button></form>
</section>


<section class="labs-flow-grid labs-stage280-grid">
  <?php $stage280Review = tl_stage280_reviewer_scorecard(); ?>
  <article class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Build 13</span><h2>Reviewer Decision Scorecard</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/reviewer-scorecard.php'); ?>">Scorecard API</a></div><div class="labs-kpis labs-stage200-kpis"><div class="labs-kpi"><span>Score</span><strong><?php echo (int)$stage280Review['score']; ?>/100</strong><small>review loop</small></div><div class="labs-kpi"><span>Total</span><strong><?php echo (int)$stage280Review['counts']['total']; ?></strong><small>decisions</small></div><div class="labs-kpi"><span>Pending</span><strong><?php echo (int)$stage280Review['counts']['pending_proofs']; ?></strong><small>proof queue</small></div></div><ul class="labs-stage280-list"><?php foreach ($stage280Review['quality_prompts'] as $tip): ?><li><?php echo labs_e((string)$tip); ?></li><?php endforeach; ?></ul></article>
  <aside class="labs-card"><h2>Save review snapshot</h2><form action="<?php echo labs_url('/admin/action-result.php'); ?>" method="post" class="labs-stage30-form"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="save_reviewer_quality_snapshot"><button class="labs-btn labs-btn-primary" type="submit">Save Snapshot</button></form></aside>
</section>

<?php labs_page_end(['section' => 'admin']); ?>
