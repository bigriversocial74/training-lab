<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
$stage885Path = __DIR__ . '/../includes/training-lab-stage885-proof-review-handoff.php';
if (is_file($stage885Path)) require_once $stage885Path;

$pending = tl_app_pending_proofs(50);
$recent = tl_app_recent_reviews(20);
$stage240Sla = tl_stage240_review_sla_state();
$stage280Review = tl_stage280_reviewer_scorecard();

labs_page_start(['title' => 'Review Workbench | Training Lab', 'section' => 'admin', 'active' => 'admin-review-workbench']);
?>
<link rel="stylesheet" href="<?php echo labs_asset('css/stage885-single-column.css'); ?>">
<div class="labs-stage885-single-column">

<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-review-workbench'); ?>
<?php if (function_exists('tl_stage885_render_workflow')) tl_stage885_render_workflow(isset($_GET['proof']) ? (string)$_GET['proof'] : null); ?>

<section class="labs-page-title labs-stage200-title">
  <div>
    <span class="labs-eyebrow">Review workbench</span>
    <h1>Decide proof and move the reward lifecycle forward.</h1>
    <p class="labs-copy">Single-column review workflow. Approvals create Training Lab receipts and eligible reward previews only. Microgifter issuing, claims, wallets, and payments remain closed.</p>
  </div>
  <div class="labs-actions">
    <a class="labs-btn" href="<?php echo labs_url('/api/training/proof-review-workflow.php'); ?>">Stage 885 API</a>
    <a class="labs-btn" href="<?php echo labs_url('/admin/reward-bridge.php'); ?>">Reward Bridge</a>
    <a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/app/flow-board.php'); ?>">Flow Board</a>
  </div>
</section>

<section class="labs-kpis labs-stage200-kpis">
  <div class="labs-kpi"><span>Pending</span><strong><?php echo count($pending); ?></strong><small>proof decisions</small></div>
  <div class="labs-kpi"><span>Recent Decisions</span><strong><?php echo count($recent); ?></strong><small>latest reviews</small></div>
</section>

<section class="labs-card">
  <h2>Decision queue</h2>
  <div class="labs-stage35-review-list">
    <?php foreach ($pending as $proof): ?>
      <article class="labs-stage35-review-item">
        <div>
          <span class="labs-pill"><?php echo labs_e((string)$proof['status']); ?></span>
          <h3><?php echo labs_e((string)($proof['task_title'] ?? 'Training proof')); ?></h3>
          <p><?php echo labs_e((string)($proof['campaign_title'] ?? 'Campaign')); ?> · <?php echo labs_e((string)($proof['participant_label'] ?? 'Participant')); ?></p>
          <blockquote><?php echo labs_e((string)($proof['proof_text'] ?? 'No proof text.')); ?></blockquote>
        </div>
        <form action="<?php echo labs_url('/admin/action-result.php'); ?>" method="post" class="labs-stage30-form labs-stage35-decision-form">
          <input type="hidden" name="confirm_training_action" value="1">
          <input type="hidden" name="training_action" value="stage885_review_proof">
          <input type="hidden" name="proof_id" value="<?php echo labs_e((string)($proof['public_id'] ?? $proof['id'] ?? '')); ?>">
          <label>Decision<select name="decision"><option value="approved">Approve</option><option value="needs_more_info">Needs more info</option><option value="rejected">Reject</option></select></label>
          <label>Review notes<textarea name="review_notes" rows="3">Reviewed in Stage 885 single-column workflow.</textarea></label>
          <button class="labs-btn labs-btn-primary" type="submit">Submit Decision</button>
        </form>
      </article>
    <?php endforeach; ?>
    <?php if (!$pending): ?>
      <div class="labs-empty-state"><strong>No pending proof</strong><p>Submitted proof will appear here. Complete a proof-required task to test the review loop.</p></div>
    <?php endif; ?>
  </div>
</section>

<section class="labs-card">
  <h2>Recent review decisions</h2>
  <div class="labs-panel-list">
    <?php foreach ($recent as $row): ?>
      <div class="labs-panel-item"><span class="labs-mini-icon">R</span><div><strong><?php echo labs_e((string)($row['decision'] ?? 'review')); ?></strong><p><?php echo labs_e((string)($row['reviewed_at'] ?? $row['created_at'] ?? '')); ?></p></div></div>
    <?php endforeach; ?>
  </div>
</section>

<section class="labs-card labs-stage240-panel">
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Build 8</span><h2>Review SLA + Decision Quality</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/review-ops.php'); ?>">Review Ops API</a></div>
  <div class="labs-kpis labs-stage200-kpis">
    <div class="labs-kpi"><span>Pending</span><strong><?php echo (int)$stage240Sla['counts']['pending']; ?></strong><small>submitted/in review</small></div>
    <div class="labs-kpi"><span>Over 24h</span><strong><?php echo (int)$stage240Sla['counts']['over_24h']; ?></strong><small>SLA watch</small></div>
    <div class="labs-kpi"><span>Score</span><strong><?php echo (int)$stage240Sla['score']; ?>/100</strong><small>review health</small></div>
  </div>
  <form action="<?php echo labs_url('/admin/action-result.php'); ?>" method="post" class="labs-stage30-form"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="create_review_sla_snapshot"><button class="labs-btn" type="submit">Save Review SLA Snapshot</button></form>
</section>

<section class="labs-card">
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Build 13</span><h2>Reviewer Decision Scorecard</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/reviewer-scorecard.php'); ?>">Scorecard API</a></div>
  <div class="labs-kpis labs-stage200-kpis">
    <div class="labs-kpi"><span>Score</span><strong><?php echo (int)$stage280Review['score']; ?>/100</strong><small>review loop</small></div>
    <div class="labs-kpi"><span>Total</span><strong><?php echo (int)$stage280Review['counts']['total']; ?></strong><small>decisions</small></div>
    <div class="labs-kpi"><span>Pending</span><strong><?php echo (int)$stage280Review['counts']['pending_proofs']; ?></strong><small>proof queue</small></div>
  </div>
  <ul class="labs-stage280-list"><?php foreach ($stage280Review['quality_prompts'] as $tip): ?><li><?php echo labs_e((string)$tip); ?></li><?php endforeach; ?></ul>
</section>

<section class="labs-card">
  <h2>Save review snapshot</h2>
  <form action="<?php echo labs_url('/admin/action-result.php'); ?>" method="post" class="labs-stage30-form"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="save_reviewer_quality_snapshot"><button class="labs-btn labs-btn-primary" type="submit">Save Snapshot</button></form>
</section>

<section class="labs-safe-note">Review Workbench creates Training Lab review/receipt/reward eligibility records only. It does not issue production rewards directly.</section>

</div>
<?php labs_page_end(['section' => 'admin']); ?>
