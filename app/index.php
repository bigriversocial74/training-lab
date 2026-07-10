<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-participant-home.php';

$page = ['title' => 'My Training | Training Lab', 'section' => 'app', 'active' => 'app-dashboard', 'required_role' => 'participant'];
$user = tl_product_require_page_access($page);
$home = tl_product_participant_home($user ?? [], (string)($_GET['campaign'] ?? ''));
$selected = $home['selected'];
$nextAction = $home['next_action'];
$role = (string)$home['role'];
labs_page_start($page);
?>

<?php if (isset($_GET['access']) && $_GET['access'] === 'denied'): ?>
<section class="labs-product-alert" role="status">
  <div><strong>That area is not available for your role.</strong><p>You have been returned to your Training Lab home.</p></div>
</section>
<?php endif; ?>

<section class="labs-product-hero">
  <article class="labs-product-hero-main">
    <span class="labs-product-kicker">My Training</span>
    <h1>Welcome back, <?php echo labs_e((string)$home['first_name']); ?>.</h1>
    <p>Continue your current campaign, see what is waiting for review, and keep every completed action connected to your reward progress.</p>
  </article>
  <aside class="labs-product-next">
    <div>
      <span>Recommended next step</span>
      <h2><?php echo labs_e((string)$nextAction['label']); ?></h2>
      <p><?php echo labs_e((string)$nextAction['detail']); ?></p>
    </div>
    <a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url((string)$nextAction['href']), ENT_QUOTES, 'UTF-8'); ?>">Continue</a>
  </aside>
</section>

<section class="labs-product-stats" aria-label="Training summary">
  <article class="labs-product-stat"><span>Campaigns</span><strong><?php echo (int)$home['totals']['campaigns']; ?></strong><small>joined training programs</small></article>
  <article class="labs-product-stat"><span>Completed</span><strong><?php echo (int)$home['totals']['completed_tasks']; ?></strong><small>verified tasks</small></article>
  <article class="labs-product-stat<?php echo (int)$home['totals']['pending_reviews'] > 0 ? ' is-action' : ''; ?>"><span>In Review</span><strong><?php echo (int)$home['totals']['pending_reviews']; ?></strong><small>proof decisions pending</small></article>
  <article class="labs-product-stat<?php echo (int)$home['totals']['claimable'] > 0 ? ' is-action' : ''; ?>"><span>Rewards</span><strong><?php echo (int)$home['totals']['rewards']; ?></strong><small><?php echo (int)$home['totals']['claimable']; ?> ready to claim</small></article>
</section>

<?php if (!$selected): ?>
<section class="labs-product-empty">
  <span class="labs-product-kicker">Start here</span>
  <h2>No campaigns yet.</h2>
  <p>Browse available training campaigns and join the one that matches your next goal.</p>
  <a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url('/app/campaigns.php'), ENT_QUOTES, 'UTF-8'); ?>">Browse Campaigns</a>
</section>
<?php else: ?>
<section class="labs-product-layout">
  <div class="labs-product-stack">
    <article class="labs-product-card">
      <div class="labs-product-card-head">
        <div>
          <span class="labs-product-kicker">Current campaign</span>
          <h2><?php echo labs_e((string)$selected['campaign_title']); ?></h2>
          <p><?php echo labs_e((string)($selected['campaign_summary'] ?: $selected['campaign_description'] ?: 'Complete each step and submit proof when requested.')); ?></p>
        </div>
        <a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/app/campaigns.php'), ENT_QUOTES, 'UTF-8'); ?>">All Campaigns</a>
      </div>
      <div class="labs-product-meta">
        <span><?php echo labs_e(ucfirst((string)$selected['participant_status'])); ?></span>
        <span><?php echo count($home['tasks']); ?> tasks</span>
        <span><?php echo (int)$selected['current_streak_days']; ?> day streak</span>
      </div>
      <div class="labs-product-progress" aria-label="<?php echo (int)$home['progress_percent']; ?> percent complete"><span style="width:<?php echo (int)$home['progress_percent']; ?>%"></span></div>
      <div class="labs-product-progress-label"><span>Campaign progress</span><strong><?php echo (int)$home['progress_percent']; ?>%</strong></div>
    </article>

    <article class="labs-product-card">
      <div class="labs-product-card-head">
        <div><span class="labs-product-kicker">Task path</span><h2>Your next actions</h2><p>Open a task to review its instructions and complete the required action.</p></div>
      </div>
      <?php if ($home['tasks']): ?>
      <div class="labs-product-task-list">
        <?php foreach ($home['tasks'] as $index => $task): ?>
          <a class="labs-product-task" href="<?php echo htmlspecialchars(labs_url((string)$task['href']), ENT_QUOTES, 'UTF-8'); ?>">
            <span class="labs-product-task-index"><?php echo $index + 1; ?></span>
            <span><strong><?php echo labs_e((string)$task['title']); ?></strong><small><?php echo $task['proof_required'] ? 'Proof required' : 'Checklist task'; ?></small></span>
            <span class="labs-product-status is-<?php echo labs_e((string)$task['status']['tone']); ?>"><?php echo labs_e((string)$task['status']['label']); ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="labs-product-empty"><h3>No tasks have been published.</h3><p>Your campaign manager is still preparing this training path.</p></div>
      <?php endif; ?>
    </article>
  </div>

  <aside class="labs-product-stack">
    <?php if (count($home['campaigns']) > 1): ?>
    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Campaigns</span><h2>Switch training</h2></div></div>
      <div class="labs-product-quick-links">
        <?php foreach ($home['campaigns'] as $campaign): ?>
          <a class="labs-product-quick-link" href="<?php echo htmlspecialchars(labs_url('/app/index.php?campaign=' . rawurlencode((string)$campaign['campaign_slug'])), ENT_QUOTES, 'UTF-8'); ?>"><span><?php echo labs_e((string)$campaign['campaign_title']); ?></span><span>Open</span></a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Recent activity</span><h2>Your timeline</h2></div></div>
      <?php if ($home['recent_activity']): ?>
      <div class="labs-product-activity">
        <?php foreach ($home['recent_activity'] as $item): $timestamp = strtotime((string)$item['at']); ?>
          <div class="labs-product-activity-item">
            <span class="labs-product-activity-dot is-<?php echo labs_e((string)$item['tone']); ?>" aria-hidden="true"></span>
            <div><strong><?php echo labs_e((string)$item['label']); ?></strong><span><?php echo labs_e((string)$item['detail']); ?></span><?php if ($timestamp): ?><time datetime="<?php echo gmdate('c', $timestamp); ?>"><?php echo date('M j, Y', $timestamp); ?></time><?php endif; ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="labs-product-empty"><h3>No activity yet.</h3><p>Your completed tasks, proof reviews, and rewards will appear here.</p></div>
      <?php endif; ?>
    </section>

    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Quick links</span><h2>Training tools</h2></div></div>
      <div class="labs-product-quick-links">
        <a class="labs-product-quick-link" href="<?php echo htmlspecialchars(labs_url('/app/progress-map.php?campaign=' . rawurlencode((string)$selected['campaign_slug'])), ENT_QUOTES, 'UTF-8'); ?>"><span>Progress</span><span>View</span></a>
        <a class="labs-product-quick-link" href="<?php echo htmlspecialchars(labs_url('/app/rewards.php'), ENT_QUOTES, 'UTF-8'); ?>"><span>Rewards</span><span>Open</span></a>
        <a class="labs-product-quick-link" href="<?php echo htmlspecialchars(labs_url('/app/resource-hub.php'), ENT_QUOTES, 'UTF-8'); ?>"><span>Resources</span><span>Open</span></a>
        <?php if (tl_product_role_allows($role, 'reviewer')): ?><a class="labs-product-quick-link" href="<?php echo htmlspecialchars(labs_url('/admin/index.php'), ENT_QUOTES, 'UTF-8'); ?>"><span><?php echo tl_product_role_allows($role, 'manager') ? 'Manage Training' : 'Review Proof'; ?></span><span>Open</span></a><?php endif; ?>
      </div>
    </section>
  </aside>
</section>
<?php endif; ?>

<?php labs_page_end(['section' => 'app']); ?>
