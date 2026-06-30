<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
require_once __DIR__ . '/../includes/training-lab-stage34-service.php';
$reviews = tl_training_review_queue_snapshots(50);
$dash = tl_stage34_dashboard();
labs_page_start(['title'=>'Review Queue | Training Lab','section'=>'admin','active'=>'admin-review']);
?>
<?php if (function_exists('tl_stage880_render_award_handoff_queue')) tl_stage880_render_award_handoff_queue(max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-review'); ?>

<?php if (function_exists('tl_stage680_render_admin_communication_console')) tl_stage680_render_admin_communication_console(); ?>
<?php if (function_exists('tl_stage640_render_proof_evidence_quality')) tl_stage640_render_proof_evidence_quality(); ?>

<?php if (function_exists('tl_stage520_render_admin_operations')) tl_stage520_render_admin_operations(); ?>
<?php if (function_exists('tl_stage560_render_review_reward_assurance')) tl_stage560_render_review_reward_assurance(); ?>

<?php if (function_exists('tl_stage600_render_proof_review_console')) tl_stage600_render_proof_review_console(); ?>


<section class="labs-page-title"><div><span class="labs-eyebrow">Review workflow</span><h1>Review queue links to proof inspection and review workbench actions.</h1><p class="labs-copy">Use the inspector to view proof details, reviewer notes, action receipts, reward previews, and timeline visibility. Use the workbench for guarded Training Lab-only decisions.</p></div><div class="labs-actions"><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/admin/review-inspector.php'); ?>">Open Review Inspector</a><button class="labs-btn" type="button" data-demo-action="approve-proof">Approve Demo</button></div></section>
<section class="labs-admin-band">
  <div class="labs-kpi"><span class="labs-muted">Queue</span><strong><?php echo count($reviews) ?: (int)$dash['review_queue']; ?></strong><small>read-only rows</small></div>
  <div class="labs-kpi"><span class="labs-muted">Review</span><strong data-demo-review-status>Not submitted</strong><small>browser demo state</small></div>
  <div class="labs-kpi"><span class="labs-muted">Reward</span><strong data-demo-reward-status>Pending</strong><small>browser demo state</small></div>
</section>
<section class="labs-card labs-review-card"><div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>Participant</th><th>Campaign</th><th>Task</th><th>Proof</th><th>Review</th><th>Reward</th><th>Updated</th><th>Inspect</th></tr></thead><tbody><?php foreach($reviews as $i=>$review): ?><tr><td><?php echo htmlspecialchars($review['participant'] ?? 'Participant'); ?></td><td><?php echo htmlspecialchars($review['campaign'] ?? 'Training Campaign'); ?></td><td><?php echo htmlspecialchars($review['task'] ?? 'Task'); ?></td><td><?php echo $i===0?'<span class="labs-pill" data-demo-proof-status>'.htmlspecialchars($review['proof_status'] ?? 'Not submitted').'</span>':'<span class="labs-pill">'.htmlspecialchars($review['proof_status'] ?? 'Submitted').'</span>'; ?></td><td><?php echo $i===0?'<span class="labs-pill" data-demo-review-status>'.htmlspecialchars($review['review_status'] ?? 'Not submitted').'</span>':'<span class="labs-pill">'.htmlspecialchars($review['review_status'] ?? 'Pending').'</span>'; ?></td><td><?php echo $i===0?'<span class="labs-pill" data-demo-reward-status>'.htmlspecialchars($review['reward_status'] ?? 'Pending').'</span>':'<span class="labs-pill">'.htmlspecialchars($review['reward_status'] ?? 'Pending').'</span>'; ?></td><td><?php echo $i===0?'<span data-demo-updated-at>'.htmlspecialchars((string)($review['updated_at'] ?? 'Not updated yet')).'</span>':htmlspecialchars((string)($review['updated_at'] ?? 'Not updated yet')); ?></td><td><a class="labs-btn" href="<?php echo labs_url('/admin/review-inspector.php?proof=' . urlencode((string)($review['inspect_ref'] ?? $review['public_id'] ?? ''))); ?>">Inspect</a></td></tr><?php endforeach; ?><?php if(!$reviews): ?><tr><td colspan="8">No review queue rows found yet.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="labs-flow-grid"><article class="labs-card"><h2>Review boundary</h2><p class="labs-muted">The review inspector shows proof detail, review history, receipt visibility, and reward eligibility previews. It does not approve, reject, issue rewards, write receipts, process uploads, or change wallet balances.</p></article><article class="labs-card"><h2>Demo controls</h2><button class="labs-btn" type="button" data-demo-action="reset-demo">Reset Demo State</button><br><br><a class="labs-btn" href="<?php echo labs_url('/api/training/review-inspector.php'); ?>">View Inspector JSON</a></article></section>
<?php labs_page_end(['section'=>'admin']); ?>
