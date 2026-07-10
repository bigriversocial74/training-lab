<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-progress-experience.php';

$page = ['title'=>'Training History | Training Lab','section'=>'app','active'=>'app-history','required_role'=>'participant'];
$user = tl_product_require_page_access($page);
$history = tl_progress_history($user ?? []);
labs_page_start($page);
?>

<section class="labs-product-hero">
  <article class="labs-product-hero-main">
    <span class="labs-product-kicker">Training history</span>
    <h1>Every campaign, milestone, and completion.</h1>
    <p>Review active training, completed campaigns, verified tasks, rewards, and your longest learning streak.</p>
  </article>
  <aside class="labs-product-next">
    <div><span>Lifetime progress</span><h2><?php echo (int)$history['totals']['completed']; ?> campaigns completed</h2><p><?php echo (int)$history['totals']['tasks']; ?> verified tasks across <?php echo (int)$history['totals']['campaigns']; ?> campaign<?php echo (int)$history['totals']['campaigns'] === 1 ? '' : 's'; ?>.</p></div>
    <a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url('/app/progress-map.php'), ENT_QUOTES, 'UTF-8'); ?>">Current Progress</a>
  </aside>
</section>

<section class="labs-product-stats" aria-label="Training history summary">
  <article class="labs-product-stat"><span>Campaigns</span><strong><?php echo (int)$history['totals']['campaigns']; ?></strong><small>joined</small></article>
  <article class="labs-product-stat"><span>Completed</span><strong><?php echo (int)$history['totals']['completed']; ?></strong><small>verified campaigns</small></article>
  <article class="labs-product-stat"><span>Tasks</span><strong><?php echo (int)$history['totals']['tasks']; ?></strong><small>verified actions</small></article>
  <article class="labs-product-stat"><span>Rewards</span><strong><?php echo (int)$history['totals']['rewards']; ?></strong><small>earned or available</small></article>
  <article class="labs-product-stat"><span>Longest streak</span><strong><?php echo (int)$history['totals']['longest_streak']; ?></strong><small>days</small></article>
</section>

<?php if (!$history['campaigns']): ?>
<section class="labs-product-empty"><span class="labs-product-kicker">No history yet</span><h2>Join your first training campaign.</h2><p>Your verified tasks, completions, and rewards will appear here.</p><a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url('/app/campaigns.php'), ENT_QUOTES, 'UTF-8'); ?>">Browse Campaigns</a></section>
<?php else: ?>
<section class="labs-history-layout">
  <div class="labs-product-stack">
    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Active training</span><h2>Continue learning</h2><p>Campaigns that are active or paused remain connected to your progress.</p></div></div>
      <?php if ($history['active']): ?><div class="labs-history-card-list">
        <?php foreach ($history['active'] as $campaign): ?>
          <article class="labs-history-card">
            <div><span class="labs-product-status is-<?php echo (string)$campaign['participant_status'] === 'paused' ? 'warning' : 'info'; ?>"><?php echo labs_e(ucfirst((string)$campaign['participant_status'])); ?></span><h3><?php echo labs_e((string)$campaign['campaign_title']); ?></h3><p><?php echo labs_e((string)($campaign['campaign_summary'] ?: 'Training campaign')); ?></p></div>
            <div class="labs-product-progress" aria-label="<?php echo (int)$campaign['progress_percent']; ?> percent complete"><span style="width:<?php echo (int)$campaign['progress_percent']; ?>%"></span></div>
            <div class="labs-history-card-foot"><span><?php echo (int)$campaign['completed_task_count']; ?>/<?php echo (int)$campaign['task_count']; ?> tasks</span><a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/app/progress-map.php?campaign=' . rawurlencode((string)$campaign['ref'])), ENT_QUOTES, 'UTF-8'); ?>">View Progress</a></div>
          </article>
        <?php endforeach; ?>
      </div><?php else: ?><p class="labs-muted">No active campaigns right now.</p><?php endif; ?>
    </section>

    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Completed training</span><h2>Completion records</h2><p>Each campaign listed here has a verified sequence-completion receipt.</p></div></div>
      <?php if ($history['completed']): ?><div class="labs-history-card-list">
        <?php foreach ($history['completed'] as $campaign): ?>
          <article class="labs-history-card is-completed">
            <div><span class="labs-product-status is-success">Completed</span><h3><?php echo labs_e((string)$campaign['campaign_title']); ?></h3><p>Completed <?php echo !empty($campaign['certificate_issued_at']) ? date('M j, Y', strtotime((string)$campaign['certificate_issued_at'])) : (!empty($campaign['completed_at']) ? date('M j, Y', strtotime((string)$campaign['completed_at'])) : 'and verified'); ?>.</p><small>Completion record <?php echo labs_e(strtoupper(substr((string)$campaign['certificate_public_id'], 0, 8))); ?></small></div>
            <div class="labs-history-card-foot"><span><?php echo (int)$campaign['completed_task_count']; ?> verified tasks · <?php echo (int)$campaign['reward_count']; ?> rewards</span><a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/app/progress-map.php?campaign=' . rawurlencode((string)$campaign['ref'])), ENT_QUOTES, 'UTF-8'); ?>">View Record</a></div>
          </article>
        <?php endforeach; ?>
      </div><?php else: ?><p class="labs-muted">Complete every task in a campaign to create your first completion record.</p><?php endif; ?>
    </section>
  </div>

  <aside class="labs-product-stack">
    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Achievements</span><h2>Your training totals</h2></div></div>
      <div class="labs-history-achievements">
        <article><strong><?php echo (int)$history['totals']['tasks']; ?></strong><span>verified tasks</span></article>
        <article><strong><?php echo (int)$history['totals']['completed']; ?></strong><span>completed campaigns</span></article>
        <article><strong><?php echo (int)$history['totals']['rewards']; ?></strong><span>reward records</span></article>
        <article><strong><?php echo (int)$history['totals']['longest_streak']; ?></strong><span>day longest streak</span></article>
      </div>
    </section>

    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Invitations</span><h2>Training waiting for you</h2></div></div>
      <?php if ($history['invited']): ?><div class="labs-history-invites"><?php foreach ($history['invited'] as $campaign): ?><a href="<?php echo htmlspecialchars(labs_url('/app/campaign-detail.php?id=' . rawurlencode((string)$campaign['ref'])), ENT_QUOTES, 'UTF-8'); ?>"><strong><?php echo labs_e((string)$campaign['campaign_title']); ?></strong><span>Review invitation</span></a><?php endforeach; ?></div><?php else: ?><p class="labs-muted">No pending campaign invitations.</p><?php endif; ?>
    </section>

    <section class="labs-product-card"><div class="labs-product-card-head"><div><span class="labs-product-kicker">Next step</span><h2>Keep building your history.</h2><p>Continue active training or discover another published campaign.</p></div></div><div class="labs-history-actions"><a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url('/app/progress-map.php'), ENT_QUOTES, 'UTF-8'); ?>">Current Progress</a><a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/app/campaigns.php'), ENT_QUOTES, 'UTF-8'); ?>">Browse Campaigns</a></div></section>
  </aside>
</section>
<?php endif; ?>

<?php labs_page_end(['section'=>'app']); ?>
