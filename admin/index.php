<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-participant-home.php';

$page = ['title' => 'Training Management | Training Lab', 'section' => 'admin', 'active' => 'admin-overview', 'required_role' => 'reviewer'];
$user = tl_product_require_page_access($page);
$admin = tl_product_admin_home($user ?? []);
$canManage = !empty($admin['can_manage']);
labs_page_start($page);
?>

<?php if (isset($_GET['access']) && $_GET['access'] === 'denied'): ?>
<section class="labs-product-alert" role="status"><div><strong>Your role does not include that management action.</strong><p>You can continue from the tools available on this dashboard.</p></div></section>
<?php endif; ?>

<section class="labs-manager-hero">
  <div>
    <span class="labs-product-kicker"><?php echo $canManage ? 'Training Management' : 'Proof Review'; ?></span>
    <h1><?php echo $canManage ? 'Manage campaigns, participants, reviews, and rewards.' : 'Review participant proof and keep training moving.'; ?></h1>
    <p><?php echo $canManage ? 'See the work that needs attention, open active campaigns, and move participants from enrollment to verified completion.' : 'Focus on submitted proof, consistent decisions, and clear feedback for participants.'; ?></p>
  </div>
  <div class="labs-manager-actions">
    <?php if ($canManage): ?><a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/admin/campaigns.php'), ENT_QUOTES, 'UTF-8'); ?>">View Campaigns</a><?php endif; ?>
    <a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url('/admin/review-workbench.php'), ENT_QUOTES, 'UTF-8'); ?>">Open Review Queue</a>
  </div>
</section>

<section class="labs-product-stats" aria-label="Training management summary">
  <?php if ($canManage): ?><article class="labs-product-stat"><span>Campaigns</span><strong><?php echo (int)$admin['counts']['campaigns']; ?></strong><small>training programs</small></article><?php endif; ?>
  <?php if ($canManage): ?><article class="labs-product-stat"><span>Participants</span><strong><?php echo (int)$admin['counts']['participants']; ?></strong><small>people in training</small></article><?php endif; ?>
  <article class="labs-product-stat<?php echo (int)$admin['counts']['pending_proofs'] > 0 ? ' is-action' : ''; ?>"><span>Needs Review</span><strong><?php echo (int)$admin['counts']['pending_proofs']; ?></strong><small>submitted proof</small></article>
  <article class="labs-product-stat"><span>Decisions</span><strong><?php echo (int)$admin['counts']['reviews']; ?></strong><small>completed reviews</small></article>
  <?php if ($canManage): ?><article class="labs-product-stat<?php echo (int)$admin['counts']['retryable'] > 0 ? ' is-action' : ''; ?>"><span>Rewards</span><strong><?php echo (int)$admin['counts']['rewards']; ?></strong><small><?php echo (int)$admin['counts']['retryable']; ?> need attention</small></article><?php endif; ?>
</section>

<section class="labs-manager-grid">
  <div class="labs-product-stack">
    <section class="labs-product-card">
      <div class="labs-product-card-head">
        <div><span class="labs-product-kicker">Review queue</span><h2>Proof waiting for a decision</h2><p>Review the participant submission, leave a clear note, and move the task forward.</p></div>
        <a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/admin/review-workbench.php'), ENT_QUOTES, 'UTF-8'); ?>">View All</a>
      </div>
      <?php if ($admin['pending_proofs']): ?>
      <div class="labs-manager-list">
        <?php foreach ($admin['pending_proofs'] as $proof): $proofRef = (string)($proof['public_id'] ?? $proof['id'] ?? ''); ?>
          <div class="labs-manager-row">
            <div><strong><?php echo labs_e((string)($proof['task_title'] ?? 'Training proof')); ?></strong><p><?php echo labs_e((string)($proof['participant_label'] ?? 'Participant')); ?> · <?php echo labs_e((string)($proof['campaign_title'] ?? 'Campaign')); ?></p></div>
            <a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/admin/review-workbench.php' . ($proofRef !== '' ? '?proof=' . rawurlencode($proofRef) : '')), ENT_QUOTES, 'UTF-8'); ?>">Review</a>
          </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="labs-product-empty"><h3>The review queue is clear.</h3><p>New participant proof will appear here when a task requires verification.</p></div>
      <?php endif; ?>
    </section>

    <?php if ($canManage): ?>
    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Campaigns</span><h2>Recent training programs</h2><p>Open a campaign to review its status, tasks, participation, and reward setup.</p></div><a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/admin/campaigns.php'), ENT_QUOTES, 'UTF-8'); ?>">Manage</a></div>
      <?php if ($admin['campaigns']): ?>
      <div class="labs-manager-list">
        <?php foreach ($admin['campaigns'] as $campaign): $ref = (string)($campaign['ref'] ?? ''); ?>
          <div class="labs-manager-row"><div><strong><?php echo labs_e((string)$campaign['title']); ?></strong><p><?php echo labs_e(ucfirst((string)$campaign['status'])); ?> · <?php echo (int)$campaign['target_action_count']; ?> target actions</p></div><a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/admin/campaign-inspector.php' . ($ref !== '' ? '?campaign=' . rawurlencode($ref) : '')), ENT_QUOTES, 'UTF-8'); ?>">Open</a></div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="labs-product-empty"><h3>No campaigns yet.</h3><p>Create the first campaign to begin enrolling participants and assigning tasks.</p><a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url('/app/campaign-builder.php'), ENT_QUOTES, 'UTF-8'); ?>">Create Campaign</a></div>
      <?php endif; ?>
    </section>
    <?php endif; ?>
  </div>

  <aside class="labs-product-stack">
    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Actions</span><h2>What needs attention</h2></div></div>
      <div class="labs-product-quick-links">
        <a class="labs-product-quick-link" href="<?php echo htmlspecialchars(labs_url('/admin/review-workbench.php'), ENT_QUOTES, 'UTF-8'); ?>"><span>Review submitted proof</span><span><?php echo (int)$admin['counts']['pending_proofs']; ?></span></a>
        <?php if ($canManage): ?>
        <a class="labs-product-quick-link" href="<?php echo htmlspecialchars(labs_url('/admin/cohort-manager.php'), ENT_QUOTES, 'UTF-8'); ?>"><span>Manage participants</span><span>Open</span></a>
        <a class="labs-product-quick-link" href="<?php echo htmlspecialchars(labs_url('/admin/reward-bridge.php'), ENT_QUOTES, 'UTF-8'); ?>"><span>Review reward delivery</span><span><?php echo (int)$admin['counts']['retryable']; ?></span></a>
        <a class="labs-product-quick-link" href="<?php echo htmlspecialchars(labs_url('/admin/reporting-center.php'), ENT_QUOTES, 'UTF-8'); ?>"><span>View reports</span><span>Open</span></a>
        <?php endif; ?>
      </div>
    </section>

    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Recent decisions</span><h2>Review activity</h2></div></div>
      <?php if ($admin['recent_reviews']): ?>
      <div class="labs-product-activity">
        <?php foreach ($admin['recent_reviews'] as $review): $timestamp = strtotime((string)($review['reviewed_at'] ?? $review['created_at'] ?? '')); $decision = (string)($review['decision'] ?? 'reviewed'); ?>
          <div class="labs-product-activity-item"><span class="labs-product-activity-dot is-<?php echo $decision === 'approved' ? 'success' : ($decision === 'needs_more_info' ? 'warning' : 'info'); ?>" aria-hidden="true"></span><div><strong><?php echo labs_e(ucwords(str_replace('_', ' ', $decision))); ?></strong><span><?php echo labs_e((string)($review['campaign_title'] ?? 'Training campaign')); ?></span><?php if ($timestamp): ?><time datetime="<?php echo gmdate('c', $timestamp); ?>"><?php echo date('M j, Y', $timestamp); ?></time><?php endif; ?></div></div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="labs-product-empty"><h3>No review history yet.</h3><p>Completed proof decisions will be shown here.</p></div>
      <?php endif; ?>
    </section>
  </aside>
</section>

<?php labs_page_end(['section' => 'admin']); ?>
