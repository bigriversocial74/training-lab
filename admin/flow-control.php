<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
$summary = tl_app_flow_summary();
$pending = tl_app_pending_proofs(30);
$recentReviews = tl_app_recent_reviews(12);
$receipts = tl_app_recent_receipts(12);
$rewards = tl_app_recent_rewards(12);
$events = tl_app_recent_events(20);
labs_page_start(['title' => 'Flow Control | Training Lab', 'section' => 'admin', 'active' => 'admin-flow-control']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-flow-control'); ?>

<section class="labs-page-title labs-stage30-title">
  <div>
    <span class="labs-eyebrow">Functional admin control</span>
    <h1>Review proof and complete the Training Lab loop.</h1>
    <p class="labs-copy">Approve, reject, or request more info on submitted Training Lab proof. Approved proof creates a training action receipt and simulated reward eligibility only.</p>
  </div>
  <div class="labs-actions">
    <a class="labs-btn" href="<?php echo labs_url('/app/workspace.php'); ?>">App Workspace</a>
    <a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/api/training/flow-state.php'); ?>">Flow JSON</a>
  </div>
</section>

<section class="labs-kpis labs-stage30-kpis">
  <div class="labs-kpi"><span class="labs-muted">Pending</span><strong><?php echo (int)$summary['counts']['pending_proofs']; ?></strong><small>submitted / in review</small></div>
  <div class="labs-kpi"><span class="labs-muted">Reviews</span><strong><?php echo (int)$summary['counts']['reviews']; ?></strong><small>decision records</small></div>
  <div class="labs-kpi"><span class="labs-muted">Receipts</span><strong><?php echo (int)$summary['counts']['receipts']; ?></strong><small>training-only</small></div>
  <div class="labs-kpi"><span class="labs-muted">Rewards</span><strong><?php echo (int)$summary['counts']['reward_events']; ?></strong><small>simulated eligibility</small></div>
</section>

<section class="labs-card labs-stage30-review-card">
  <h2>Proof review actions</h2>
  <p class="labs-muted">These actions write to <code>training_reviews</code>, <code>training_action_receipts</code>, <code>training_reward_events</code>, <code>training_streaks</code>, and <code>training_events</code> only.</p>
  <?php if (!$pending): ?>
    <div class="labs-empty-state"><h3>No pending proof right now.</h3><p>Submit proof from the App Workspace to test the review flow.</p><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/app/workspace.php'); ?>">Submit Proof</a></div>
  <?php else: ?>
  <div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>Proof</th><th>Campaign</th><th>Participant</th><th>Status</th><th>Decision</th></tr></thead><tbody>
    <?php foreach ($pending as $proof): ?>
      <tr>
        <td><strong><?php echo htmlspecialchars((string)$proof['public_id'], ENT_QUOTES, 'UTF-8'); ?></strong><br><small><?php echo htmlspecialchars((string)$proof['task_title'], ENT_QUOTES, 'UTF-8'); ?></small></td>
        <td><?php echo htmlspecialchars((string)$proof['campaign_title'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><?php echo htmlspecialchars((string)$proof['participant_label'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><span class="labs-pill"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)$proof['status'])), ENT_QUOTES, 'UTF-8'); ?></span></td>
        <td>
          <div class="labs-stage30-review-actions">
            <?php foreach (['approved' => 'Approve', 'needs_more_info' => 'Needs Info', 'rejected' => 'Reject'] as $decision => $label): ?>
              <form action="<?php echo labs_url('/admin/action-result.php'); ?>" method="post">
                <input type="hidden" name="confirm_training_action" value="1">
                <input type="hidden" name="training_action" value="review_proof">
                <input type="hidden" name="proof_id" value="<?php echo htmlspecialchars((string)$proof['public_id'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="decision" value="<?php echo htmlspecialchars($decision, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="reviewer_user_id" value="1">
                <input type="hidden" name="review_notes" value="Admin flow decision: <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
                <button class="labs-btn <?php echo $decision === 'approved' ? 'labs-btn-primary' : ''; ?>" type="submit"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></button>
              </form>
            <?php endforeach; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</section>

<section class="labs-flow-grid">
  <article class="labs-card"><h2>Recent reviews</h2><div class="labs-panel-list"><?php foreach ($recentReviews as $review): ?><div class="labs-panel-item"><span class="labs-mini-icon">R</span><div><strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)$review['decision'])), ENT_QUOTES, 'UTF-8'); ?></strong><p><?php echo htmlspecialchars((string)$review['campaign_title'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string)$review['created_at'], ENT_QUOTES, 'UTF-8'); ?></p></div><span class="labs-pill"><?php echo htmlspecialchars((string)$review['proof_status'], ENT_QUOTES, 'UTF-8'); ?></span></div><?php endforeach; if (!$recentReviews): ?><p class="labs-muted">No reviews yet.</p><?php endif; ?></div></article>
  <article class="labs-card"><h2>Action receipts</h2><div class="labs-panel-list"><?php foreach ($receipts as $receipt): ?><div class="labs-panel-item"><span class="labs-mini-icon">A</span><div><strong><?php echo htmlspecialchars((string)$receipt['participant_label'], ENT_QUOTES, 'UTF-8'); ?></strong><p><?php echo htmlspecialchars((string)$receipt['campaign_title'], ENT_QUOTES, 'UTF-8'); ?></p></div><span class="labs-pill"><?php echo htmlspecialchars((string)$receipt['receipt_status'], ENT_QUOTES, 'UTF-8'); ?></span></div><?php endforeach; if (!$receipts): ?><p class="labs-muted">No receipts yet.</p><?php endif; ?></div></article>
</section>
<section class="labs-flow-grid">
  <article class="labs-card"><h2>Simulated reward eligibility</h2><div class="labs-panel-list"><?php foreach ($rewards as $reward): ?><div class="labs-panel-item"><span class="labs-mini-icon">$</span><div><strong><?php echo htmlspecialchars((string)($reward['reward_label'] ?: 'Training Reward'), ENT_QUOTES, 'UTF-8'); ?></strong><p><?php echo htmlspecialchars((string)$reward['participant_label'], ENT_QUOTES, 'UTF-8'); ?> · no wallet write</p></div><span class="labs-pill"><?php echo htmlspecialchars((string)$reward['status'], ENT_QUOTES, 'UTF-8'); ?></span></div><?php endforeach; if (!$rewards): ?><p class="labs-muted">No reward events yet.</p><?php endif; ?></div></article>
  <article class="labs-card"><h2>Recent event log</h2><div class="labs-panel-list"><?php foreach ($events as $event): ?><div class="labs-panel-item"><span class="labs-mini-icon">E</span><div><strong><?php echo htmlspecialchars((string)$event['event_type'], ENT_QUOTES, 'UTF-8'); ?></strong><p><?php echo htmlspecialchars((string)$event['subject_type'], ENT_QUOTES, 'UTF-8'); ?> #<?php echo (int)$event['subject_id']; ?></p></div><small><?php echo htmlspecialchars((string)$event['created_at'], ENT_QUOTES, 'UTF-8'); ?></small></div><?php endforeach; if (!$events): ?><p class="labs-muted">No events yet.</p><?php endif; ?></div></article>
</section>
<section class="labs-safe-note">Standalone Training Lab action boundary: no real uploads, no payments, no wallet balance updates, no Microgifter reward issuing, and no claim/redeem logic.</section>
<?php labs_page_end(['section' => 'admin']); ?>
