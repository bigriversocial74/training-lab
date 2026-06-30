<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
require_once __DIR__ . '/../includes/training-lab-stage34-service.php';

$proofRef = isset($_GET['proof']) ? preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)$_GET['proof']) : null;
$inspector = tl_training_review_inspector_summary($proofRef);
$proof = $inspector['proof'] ?? [];
$summary = $inspector['review_summary'] ?? [];
$queue = $inspector['nearby_queue'] ?? [];
$reviews = $inspector['reviews'] ?? [];
$receipts = $inspector['receipts'] ?? [];
$rewardEvents = $inspector['reward_events'] ?? [];
$timeline = $inspector['timeline'] ?? [];

function tl_stage15_value($value): string
{
    if ($value === null || $value === '') return 'None yet';
    return (string)$value;
}

function tl_stage15_label($value): string
{
    return ucwords(str_replace('_', ' ', tl_stage15_value($value)));
}

labs_page_start(['title'=>'Review Inspector | Training Lab','section'=>'admin','active'=>'admin-review-inspector']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-review-inspector'); ?>
<?php if (function_exists('tl_stage640_render_proof_evidence_quality')) tl_stage640_render_proof_evidence_quality(); ?>

<?php if (function_exists('tl_stage600_render_proof_review_console')) tl_stage600_render_proof_review_console(); ?>


<section class="labs-page-title labs-stage15-title">
  <div>
    <span class="labs-eyebrow">Review inspector</span>
    <h1>Proof review details are now visible without approval writes.</h1>
    <p class="labs-copy">This page drills into one proof submission and shows the related campaign, task, participant, review decisions, receipts, reward preview events, and timeline. It does not approve, reject, issue rewards, touch wallets, process uploads, or create claim/redeem behavior.</p>
  </div>
  <div class="labs-actions"><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/admin/review-queue.php'); ?>">Review Queue</a><a class="labs-btn" href="<?php echo labs_url('/api/training/review-inspector.php' . (!empty($proof['public_id']) ? '?proof=' . urlencode((string)$proof['public_id']) : '')); ?>">Inspector JSON</a></div>
</section>

<section class="labs-stage13-status-band labs-stage15-proof-band">
  <div class="labs-stage13-score"><span>Proof status</span><strong><?php echo htmlspecialchars($summary['proof_status'] ?? 'Submitted'); ?></strong><small>read-only</small></div>
  <div class="labs-stage13-status-copy"><span class="labs-eyebrow">Current proof</span><h2><?php echo htmlspecialchars($proof['participant_label'] ?? 'Participant'); ?></h2><p><?php echo htmlspecialchars(($proof['campaign_title'] ?? 'Training Campaign') . ' · ' . ($proof['task_title'] ?? 'Training task')); ?></p></div>
  <div class="labs-stage13-status-actions"><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/admin/campaign-inspector.php' . (!empty($proof['campaign_slug']) ? '?campaign=' . urlencode((string)$proof['campaign_slug']) : '')); ?>">Campaign Inspector</a><a class="labs-btn" href="<?php echo labs_url('/admin/command-center.php'); ?>">Command Center</a></div>
<a class="labs-btn" href="<?php echo labs_url('/admin/participant-inspector.php'); ?>">Participant Inspector</a></section>

<section class="labs-kpis labs-stage15-kpis">
  <div class="labs-kpi"><span class="labs-muted">Decision</span><strong><?php echo htmlspecialchars($summary['latest_decision'] ?? 'Pending'); ?></strong><small>no decision written</small></div>
  <div class="labs-kpi"><span class="labs-muted">Reviews</span><strong><?php echo number_format((int)($summary['review_count'] ?? 0)); ?></strong><small>linked rows</small></div>
  <div class="labs-kpi"><span class="labs-muted">Receipts</span><strong><?php echo number_format((int)($summary['receipt_count'] ?? 0)); ?></strong><small>read-only</small></div>
  <div class="labs-kpi"><span class="labs-muted">Reward preview</span><strong><?php echo htmlspecialchars($summary['preview_reward_value_display'] ?? '$0.00'); ?></strong><small>no wallet writes</small></div>
</section>

<section class="labs-stage13-tabs" aria-label="Review inspector sections">
  <a href="#stage15-proof">Proof</a>
  <a href="#stage15-review-history">Reviews</a>
  <a href="#stage15-reward-preview">Rewards</a>
  <a href="#stage15-timeline">Timeline</a>
  <a href="#stage15-queue">Queue</a>
</section>

<section class="labs-flow-grid" id="stage15-proof">
  <article class="labs-card">
    <div class="labs-card-headline"><div><span class="labs-eyebrow">Proof detail</span><h2><?php echo htmlspecialchars($proof['public_id'] ?? 'Demo proof'); ?></h2></div><span class="labs-pill"><?php echo htmlspecialchars(tl_stage15_label($proof['proof_type'] ?? 'text')); ?></span></div>
    <div class="labs-stage15-detail-grid">
      <div><span>Campaign</span><strong><?php echo htmlspecialchars($proof['campaign_title'] ?? 'Training Campaign'); ?></strong></div>
      <div><span>Task</span><strong><?php echo htmlspecialchars($proof['task_title'] ?? 'Training task'); ?></strong></div>
      <div><span>Participant</span><strong><?php echo htmlspecialchars($proof['participant_label'] ?? 'Participant'); ?></strong></div>
      <div><span>Submitted</span><strong><?php echo htmlspecialchars(tl_stage15_value($proof['submitted_at'] ?? null)); ?></strong></div>
      <div><span>Reviewed</span><strong><?php echo htmlspecialchars(tl_stage15_value($proof['reviewed_at'] ?? null)); ?></strong></div>
      <div><span>Mode</span><strong><?php echo htmlspecialchars(tl_stage15_label($inspector['mode'] ?? 'demo-fallback')); ?></strong></div>
    </div>
    <div class="labs-stage15-proof-text"><span class="labs-eyebrow">Proof text / reference preview</span><p><?php echo nl2br(htmlspecialchars(tl_stage15_value($proof['proof_text'] ?? $proof['external_url'] ?? $proof['storage_reference'] ?? 'No proof text preview available.'))); ?></p></div>
  </article>
  <aside class="labs-card">
    <h2>Task instructions</h2>
    <p class="labs-muted"><?php echo nl2br(htmlspecialchars(tl_stage15_value($proof['task_instructions'] ?? 'No task instructions found.'))); ?></p>
    <div class="labs-stage13-boundary-grid labs-stage15-boundaries"><span>No approval writes</span><span>No rejection writes</span><span>No upload processing</span><span>No reward issuing</span><span>No wallet changes</span><span>No new SQL</span></div>
  </aside>
</section>

<section class="labs-card" id="stage15-review-history">
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Review history</span><h2>Reviewer decisions and notes</h2></div><a class="labs-btn" href="<?php echo labs_url('/admin/review-queue.php'); ?>">Back to Queue</a></div>
  <div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>Decision</th><th>Reviewer</th><th>Notes</th><th>Reviewed</th></tr></thead><tbody>
    <?php foreach ($reviews as $review): ?>
      <tr><td><span class="labs-pill"><?php echo htmlspecialchars(tl_stage15_label($review['decision'] ?? 'pending')); ?></span></td><td><?php echo htmlspecialchars(tl_stage15_value($review['reviewer_user_id'] ?? null)); ?></td><td><?php echo htmlspecialchars(tl_stage15_value($review['review_notes'] ?? null)); ?></td><td><?php echo htmlspecialchars(tl_stage15_value($review['reviewed_at'] ?? $review['created_at'] ?? null)); ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$reviews): ?><tr><td colspan="4">No review rows found for this proof yet.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>

<section class="labs-flow-grid" id="stage15-reward-preview">
  <article class="labs-card">
    <div class="labs-card-headline"><div><span class="labs-eyebrow">Receipts</span><h2>Action receipt visibility</h2></div></div>
    <div class="labs-panel-list labs-compact-list">
      <?php foreach ($receipts as $receipt): ?>
        <div class="labs-panel-item"><span class="labs-mini-icon">#</span><div><strong><?php echo htmlspecialchars($receipt['receipt_type'] ?? 'receipt'); ?></strong><p><?php echo htmlspecialchars(($receipt['receipt_status'] ?? 'active') . ' · ' . tl_stage15_value($receipt['issued_at'] ?? $receipt['created_at'] ?? null)); ?></p></div><span class="labs-pill"><?php echo htmlspecialchars($receipt['public_id'] ?? 'receipt'); ?></span></div>
      <?php endforeach; ?>
      <?php if (!$receipts): ?><div class="labs-panel-item"><span class="labs-mini-icon">—</span><div><strong>No receipt rows</strong><p>No action receipt has been linked to this proof yet.</p></div></div><?php endif; ?>
    </div>
  </article>
  <aside class="labs-card">
    <div class="labs-card-headline"><div><span class="labs-eyebrow">Reward events</span><h2>Eligibility preview</h2></div></div>
    <div class="labs-panel-list labs-compact-list">
      <?php foreach ($rewardEvents as $event): ?>
        <div class="labs-panel-item"><span class="labs-mini-icon">$</span><div><strong><?php echo htmlspecialchars(tl_stage15_label($event['status'] ?? 'eligible')); ?></strong><p><?php echo htmlspecialchars(tl_stage15_value($event['eligibility_reason'] ?? 'Reward preview')); ?></p></div><span class="labs-pill"><?php echo htmlspecialchars(tl_training_money_display((int)($event['value_cents'] ?? 0))); ?></span></div>
      <?php endforeach; ?>
      <?php if (!$rewardEvents): ?><div class="labs-panel-item"><span class="labs-mini-icon">—</span><div><strong>No reward events</strong><p>No reward preview event is linked to this participant/campaign yet.</p></div></div><?php endif; ?>
    </div>
  </aside>
</section>

<section class="labs-card" id="stage15-timeline">
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Timeline</span><h2>Proof-to-review trail</h2></div></div>
  <div class="labs-stage15-timeline">
    <?php foreach ($timeline as $item): ?>
      <div><span><?php echo htmlspecialchars($item['type'] ?? 'event'); ?></span><strong><?php echo htmlspecialchars($item['label'] ?? 'Timeline item'); ?></strong><small><?php echo htmlspecialchars(tl_stage15_value($item['at'] ?? null)); ?></small></div>
    <?php endforeach; ?>
    <?php if (!$timeline): ?><div><span>empty</span><strong>No timeline rows yet</strong><small>Read-only preview</small></div><?php endif; ?>
  </div>
</section>

<section class="labs-card" id="stage15-queue">
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Nearby queue</span><h2>Open another proof</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/review-inspector.php'); ?>">Queue JSON</a></div>
  <div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>Participant</th><th>Campaign</th><th>Task</th><th>Proof</th><th>Review</th><th>Inspect</th></tr></thead><tbody>
    <?php foreach ($queue as $row): ?>
      <tr><td><?php echo htmlspecialchars($row['participant'] ?? 'Participant'); ?></td><td><?php echo htmlspecialchars($row['campaign'] ?? 'Campaign'); ?></td><td><?php echo htmlspecialchars($row['task'] ?? 'Task'); ?></td><td><span class="labs-pill"><?php echo htmlspecialchars($row['proof_status'] ?? 'Submitted'); ?></span></td><td><span class="labs-pill"><?php echo htmlspecialchars($row['review_status'] ?? 'Pending'); ?></span></td><td><a class="labs-btn" href="<?php echo labs_url('/admin/review-inspector.php?proof=' . urlencode((string)($row['inspect_ref'] ?? $row['public_id'] ?? ''))); ?>">Inspect</a></td></tr>
    <?php endforeach; ?>
    <?php if (!$queue): ?><tr><td colspan="6">No proof queue rows found yet.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>
<?php labs_page_end(['section'=>'admin']); ?>
