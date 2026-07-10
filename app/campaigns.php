<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-campaign-experience.php';

$page = ['title' => 'Campaigns | Training Lab', 'section' => 'app', 'active' => 'app-campaigns', 'required_role' => 'participant'];
$user = tl_product_require_page_access($page);
$filter = in_array((string)($_GET['view'] ?? 'available'), ['available', 'mine', 'completed'], true) ? (string)$_GET['view'] : 'available';
$query = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$campaigns = tl_campaign_catalog($user ?? [], $filter, $query);
$counts = [
    'available' => count(tl_campaign_catalog($user ?? [], 'available')),
    'mine' => count(tl_campaign_catalog($user ?? [], 'mine')),
    'completed' => count(tl_campaign_catalog($user ?? [], 'completed')),
];
$labels = ['available' => 'Available', 'mine' => 'My Campaigns', 'completed' => 'Completed'];
labs_page_start($page);
?>

<section class="labs-product-hero">
  <article class="labs-product-hero-main">
    <span class="labs-product-kicker">Training campaigns</span>
    <h1>Find the right training path.</h1>
    <p>Browse campaigns that are open to you, continue programs you have joined, and revisit completed training.</p>
  </article>
  <aside class="labs-product-next">
    <div><span>Your campaign library</span><h2><?php echo array_sum($counts); ?> programs</h2><p><?php echo (int)$counts['mine']; ?> currently in progress and <?php echo (int)$counts['completed']; ?> completed.</p></div>
    <a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url('/app/index.php'), ENT_QUOTES, 'UTF-8'); ?>">My Training</a>
  </aside>
</section>

<section class="labs-campaign-toolbar" aria-label="Campaign filters">
  <nav class="labs-campaign-tabs" aria-label="Campaign views">
    <?php foreach ($labels as $key => $label): ?>
      <a class="<?php echo $filter === $key ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(labs_url('/app/campaigns.php?view=' . rawurlencode($key)), ENT_QUOTES, 'UTF-8'); ?>"<?php echo $filter === $key ? ' aria-current="page"' : ''; ?>><?php echo labs_e($label); ?> · <?php echo (int)$counts[$key]; ?></a>
    <?php endforeach; ?>
  </nav>
  <form class="labs-campaign-search" method="get" action="<?php echo htmlspecialchars(labs_url('/app/campaigns.php'), ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="view" value="<?php echo labs_e($filter); ?>">
    <input id="campaign-search" type="search" name="q" value="<?php echo labs_e($query); ?>" placeholder="Search campaigns" aria-label="Search campaigns">
    <button class="labs-btn" type="submit">Search</button>
  </form>
</section>

<section class="labs-campaign-grid" aria-live="polite">
  <?php foreach ($campaigns as $campaign): $state = $campaign['state']; ?>
    <article class="labs-card labs-campaign-card">
      <div class="labs-campaign-card-top">
        <span class="labs-campaign-status is-<?php echo labs_e((string)$state['tone']); ?>"><?php echo labs_e((string)$state['label']); ?></span>
        <h2><?php echo labs_e((string)$campaign['title']); ?></h2>
        <p><?php echo labs_e((string)($campaign['summary'] ?: $campaign['description'] ?: 'Complete a guided training path and submit proof when requested.')); ?></p>
      </div>
      <div class="labs-campaign-card-body">
        <div class="labs-campaign-facts">
          <div class="labs-campaign-fact"><span>Tasks</span><strong><?php echo (int)$campaign['task_count']; ?></strong></div>
          <div class="labs-campaign-fact"><span>Reward</span><strong><?php echo labs_e((string)($campaign['reward_label'] ?: $campaign['reward_display'])); ?></strong></div>
          <div class="labs-campaign-fact"><span>Audience</span><strong><?php echo labs_e((string)$campaign['audience_label']); ?></strong></div>
          <div class="labs-campaign-fact"><span>Start</span><strong><?php echo !empty($campaign['starts_at']) ? date('M j', strtotime((string)$campaign['starts_at'])) : 'Anytime'; ?></strong></div>
        </div>
        <p class="labs-muted"><?php echo labs_e((string)$state['reason']); ?></p>
        <div class="labs-campaign-card-actions">
          <a class="labs-btn" href="<?php echo htmlspecialchars(labs_url((string)$campaign['detail_href']), ENT_QUOTES, 'UTF-8'); ?>">View Details</a>
          <?php if (!empty($state['enrolled'])): ?>
            <a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url(in_array((string)$state['key'], ['completed','ended_enrolled'], true) ? '/app/progress-map.php?campaign=' . rawurlencode((string)$campaign['ref']) : (string)$campaign['continue_href']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo in_array((string)$state['key'], ['completed','ended_enrolled'], true) ? 'View Progress' : 'Continue'; ?></a>
          <?php elseif (!empty($state['can_join'])): ?>
            <form method="post" action="<?php echo htmlspecialchars(labs_url('/app/campaign-enroll.php'), ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo tl_security_csrf_field(); ?>
              <input type="hidden" name="campaign_id" value="<?php echo labs_e((string)$campaign['ref']); ?>">
              <button class="labs-btn labs-btn-primary" type="submit"><?php echo $state['key'] === 'invited' ? 'Accept Invitation' : 'Join'; ?></button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </article>
  <?php endforeach; ?>

  <?php if (!$campaigns): ?>
    <section class="labs-product-empty labs-campaign-empty">
      <span class="labs-product-kicker"><?php echo labs_e($labels[$filter]); ?></span>
      <h2><?php echo $query !== '' ? 'No campaigns match your search.' : 'No campaigns in this view yet.'; ?></h2>
      <p><?php echo $filter === 'available' ? 'New published campaigns and invitations will appear here.' : ($filter === 'mine' ? 'Join an available campaign to begin training.' : 'Completed campaigns will appear here after your training is verified.'); ?></p>
      <?php if ($query !== ''): ?><a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/app/campaigns.php?view=' . rawurlencode($filter)), ENT_QUOTES, 'UTF-8'); ?>">Clear Search</a><?php endif; ?>
    </section>
  <?php endif; ?>
</section>

<?php labs_page_end(['section' => 'app']); ?>
