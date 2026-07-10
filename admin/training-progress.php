<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-progress-experience.php';

$page = ['title'=>'Training Progress | Training Lab','section'=>'admin','active'=>'admin-training-progress','required_role'=>'manager'];
$user = tl_product_require_page_access($page);
$summary = tl_progress_admin_summary($user ?? []);
labs_page_start($page);
?>

<section class="labs-product-hero">
  <article class="labs-product-hero-main">
    <span class="labs-product-kicker">Training progress</span>
    <h1>See where participants are moving or blocked.</h1>
    <p>Review campaign completion, verified actions, proof-review load, and reward activity from one merchant-scoped dashboard.</p>
  </article>
  <aside class="labs-product-next">
    <div><span>Progress scope</span><h2><?php echo (string)$summary['scope'] === 'platform' ? 'Platform overview' : 'Your campaigns'; ?></h2><p><?php echo (int)$summary['totals']['participants']; ?> participants across <?php echo (int)$summary['totals']['campaigns']; ?> campaign<?php echo (int)$summary['totals']['campaigns'] === 1 ? '' : 's'; ?>.</p></div>
    <a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url('/admin/review-workbench.php'), ENT_QUOTES, 'UTF-8'); ?>">Review Proof</a>
  </aside>
</section>

<section class="labs-product-stats" aria-label="Merchant training progress summary">
  <article class="labs-product-stat"><span>Campaigns</span><strong><?php echo (int)$summary['totals']['campaigns']; ?></strong><small><?php echo (string)$summary['scope'] === 'platform' ? 'platform' : 'owned'; ?></small></article>
  <article class="labs-product-stat"><span>Participants</span><strong><?php echo (int)$summary['totals']['participants']; ?></strong><small>active or completed</small></article>
  <article class="labs-product-stat"><span>Completed</span><strong><?php echo (int)$summary['totals']['completed']; ?></strong><small>campaign completions</small></article>
  <article class="labs-product-stat"><span>Pending proof</span><strong><?php echo (int)$summary['totals']['pending_reviews']; ?></strong><small>review decisions</small></article>
  <article class="labs-product-stat"><span>Verified actions</span><strong><?php echo (int)$summary['totals']['verified_actions']; ?></strong><small>task receipts</small></article>
  <article class="labs-product-stat"><span>Rewards</span><strong><?php echo (int)$summary['totals']['rewards']; ?></strong><small>campaign reward records</small></article>
</section>

<section class="labs-product-card">
  <div class="labs-product-card-head"><div><span class="labs-product-kicker">Campaign performance</span><h2>Progress by campaign</h2><p>Completion and progress are calculated from participant records and verified task receipts.</p></div><a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/admin/campaigns.php'), ENT_QUOTES, 'UTF-8'); ?>">Campaigns</a></div>
  <?php if ($summary['campaigns']): ?>
  <div class="labs-merchant-progress-list">
    <?php foreach ($summary['campaigns'] as $campaign): ?>
      <article class="labs-merchant-progress-row">
        <div class="labs-merchant-progress-main"><span class="labs-product-status is-<?php echo in_array((string)$campaign['status'], ['active','completed'], true) ? 'success' : ((string)$campaign['status'] === 'paused' ? 'warning' : 'neutral'); ?>"><?php echo labs_e(ucfirst((string)$campaign['status'])); ?></span><h3><?php echo labs_e((string)$campaign['title']); ?></h3><p><?php echo (int)$campaign['participant_count']; ?> participants · <?php echo (int)$campaign['task_count']; ?> tasks · <?php echo (int)$campaign['pending_review_count']; ?> awaiting review</p></div>
        <div class="labs-merchant-progress-metrics"><div><span>Average progress</span><strong><?php echo (int)$campaign['average_progress_percent']; ?>%</strong></div><div><span>Completion rate</span><strong><?php echo (int)$campaign['completion_rate_percent']; ?>%</strong></div><div><span>Verified actions</span><strong><?php echo (int)$campaign['verified_action_count']; ?></strong></div><div><span>Rewards</span><strong><?php echo (int)$campaign['reward_count']; ?></strong></div></div>
        <div class="labs-product-progress" aria-label="<?php echo (int)$campaign['average_progress_percent']; ?> percent average progress"><span style="width:<?php echo (int)$campaign['average_progress_percent']; ?>%"></span></div>
        <div class="labs-merchant-progress-actions"><a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/admin/campaign-inspector.php?campaign=' . rawurlencode((string)$campaign['ref'])), ENT_QUOTES, 'UTF-8'); ?>">Campaign Details</a><?php if ((int)$campaign['pending_review_count'] > 0): ?><a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url('/admin/review-workbench.php?campaign=' . rawurlencode((string)$campaign['ref'])), ENT_QUOTES, 'UTF-8'); ?>">Review Proof</a><?php endif; ?></div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php else: ?><div class="labs-product-empty"><h3>No campaign progress yet.</h3><p>Create a campaign and enroll participants to begin tracking verified progress.</p><a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url('/app/campaign-builder.php'), ENT_QUOTES, 'UTF-8'); ?>">Create Campaign</a></div><?php endif; ?>
</section>

<?php labs_page_end(['section'=>'admin']); ?>
