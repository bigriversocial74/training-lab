<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-campaign-experience.php';
require_once __DIR__ . '/../includes/training-lab-participant-home.php';

$page = ['title' => 'Campaign Details | Training Lab', 'section' => 'app', 'active' => 'app-campaigns', 'required_role' => 'participant'];
$user = tl_product_require_page_access($page);
$campaignRef = tl_campaign_clean_ref((string)($_GET['id'] ?? $_GET['campaign'] ?? ''));
$campaign = tl_campaign_detail($user ?? [], $campaignRef);
$flash = tl_campaign_flash_take();
labs_page_start($page);
?>

<a class="labs-campaign-back" href="<?php echo htmlspecialchars(labs_url('/app/campaigns.php'), ENT_QUOTES, 'UTF-8'); ?>">← Back to campaigns</a>

<?php if ($flash): ?>
  <div class="labs-campaign-flash is-<?php echo labs_e((string)$flash['type']); ?>" role="status"><?php echo labs_e((string)$flash['message']); ?></div>
<?php endif; ?>

<?php if (!$campaign): ?>
<section class="labs-product-empty">
  <span class="labs-product-kicker">Campaign unavailable</span>
  <h1>This campaign could not be opened.</h1>
  <p>It may be private, archived, or no longer available to your account.</p>
  <a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url('/app/campaigns.php'), ENT_QUOTES, 'UTF-8'); ?>">Browse Campaigns</a>
</section>
<?php else: $state = $campaign['state']; ?>
<section class="labs-campaign-detail-hero">
  <article class="labs-campaign-detail-main">
    <span class="labs-campaign-status is-<?php echo labs_e((string)$state['tone']); ?>"><?php echo labs_e((string)$state['label']); ?></span>
    <h1><?php echo labs_e((string)$campaign['title']); ?></h1>
    <p><?php echo labs_e((string)($campaign['description'] ?: $campaign['summary'] ?: 'Complete the guided task sequence and submit proof when requested.')); ?></p>
    <div class="labs-campaign-detail-meta">
      <span><?php echo (int)$campaign['task_count']; ?> tasks</span>
      <span><?php echo labs_e((string)$campaign['audience_label']); ?></span>
      <span><?php echo labs_e((string)($campaign['reward_label'] ?: $campaign['reward_display'])); ?></span>
      <?php if (!empty($campaign['starts_at'])): ?><span>Starts <?php echo date('M j, Y', strtotime((string)$campaign['starts_at'])); ?></span><?php endif; ?>
      <?php if (!empty($campaign['ends_at'])): ?><span>Ends <?php echo date('M j, Y', strtotime((string)$campaign['ends_at'])); ?></span><?php endif; ?>
    </div>
  </article>

  <aside class="labs-campaign-enroll-card">
    <div>
      <span class="labs-product-kicker">Your status</span>
      <h2><?php echo labs_e((string)$state['label']); ?></h2>
      <p><?php echo labs_e((string)$state['reason']); ?></p>
      <?php if ($state['spots_remaining'] !== null): ?><p><strong><?php echo (int)$state['spots_remaining']; ?></strong> spots remaining</p><?php endif; ?>
    </div>
    <?php if (!empty($state['enrolled'])): ?>
      <?php if ($state['key'] === 'completed'): ?>
        <a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url('/app/progress-map.php?campaign=' . rawurlencode((string)$campaign['ref'])), ENT_QUOTES, 'UTF-8'); ?>">View Completed Training</a>
      <?php elseif ($state['key'] === 'paused'): ?>
        <a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/app/progress-map.php?campaign=' . rawurlencode((string)$campaign['ref'])), ENT_QUOTES, 'UTF-8'); ?>">View Progress</a>
      <?php else: ?>
        <a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url((string)$campaign['continue_href']), ENT_QUOTES, 'UTF-8'); ?>">Continue Training</a>
      <?php endif; ?>
    <?php elseif (!empty($state['can_join'])): ?>
      <form method="post" action="<?php echo htmlspecialchars(labs_url('/app/campaign-enroll.php'), ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo tl_security_csrf_field(); ?>
        <input type="hidden" name="campaign_id" value="<?php echo labs_e((string)$campaign['ref']); ?>">
        <button class="labs-btn labs-btn-primary" type="submit">Join Campaign</button>
      </form>
    <?php else: ?>
      <button class="labs-btn" type="button" disabled><?php echo labs_e((string)$state['label']); ?></button>
    <?php endif; ?>
  </aside>
</section>

<section class="labs-campaign-detail-layout">
  <div class="labs-product-stack">
    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Training path</span><h2>What you will complete</h2><p>Tasks are completed in order. Some tasks require proof and a reviewer decision.</p></div></div>
      <?php if ($campaign['tasks']): ?>
      <div class="labs-campaign-task-sequence">
        <?php foreach ($campaign['tasks'] as $index => $task): $taskState = $task['participant_status']; ?>
          <article class="labs-campaign-task-row">
            <span class="labs-campaign-task-number"><?php echo $index + 1; ?></span>
            <div><strong><?php echo labs_e((string)$task['title']); ?></strong><p><?php echo labs_e((string)($task['instructions'] ?: 'Complete this training action.')); ?></p></div>
            <div class="labs-campaign-task-tags">
              <?php if (!empty($task['expected_duration_minutes'])): ?><span><?php echo (int)$task['expected_duration_minutes']; ?> min</span><?php endif; ?>
              <span><?php echo !empty($task['proof_required']) ? 'Proof required' : 'Checklist'; ?></span>
              <?php if (!empty($state['enrolled'])): ?><span class="labs-product-status is-<?php echo labs_e((string)$taskState['tone']); ?>"><?php echo labs_e((string)$taskState['label']); ?></span><?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
        <div class="labs-product-empty"><h3>The task path is being prepared.</h3><p>Campaign tasks will appear here when they are published.</p></div>
      <?php endif; ?>
    </section>
  </div>

  <aside class="labs-product-stack">
    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Reward</span><h2><?php echo labs_e((string)($campaign['reward_label'] ?: 'Completion recognition')); ?></h2></div></div>
      <p class="labs-copy"><?php echo labs_e((string)$campaign['reward_display']); ?> after the campaign requirements are verified.</p>
      <p class="labs-muted">Reward eligibility is created from verified Training Lab completion records. Delivery remains protected by the existing Microgifter reward bridge.</p>
    </section>

    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Requirements</span><h2>Before you begin</h2></div></div>
      <ul class="labs-campaign-side-list">
        <?php if ($campaign['requirements']): foreach ($campaign['requirements'] as $requirement): ?>
          <li><?php echo labs_e((string)$requirement); ?></li>
        <?php endforeach; else: ?>
          <li>Use your signed-in Training Lab account.</li>
          <li>Complete each assigned task.</li>
          <li>Submit proof when a task requires review.</li>
        <?php endif; ?>
      </ul>
    </section>

    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Participation</span><h2><?php echo (int)$campaign['enrolled_count']; ?> enrolled</h2></div></div>
      <p class="labs-muted"><?php echo $state['capacity'] > 0 ? number_format((int)$state['capacity']) . ' participant limit.' : 'No participant limit is currently displayed.'; ?></p>
    </section>
  </aside>
</section>
<?php endif; ?>

<?php labs_page_end(['section' => 'app']); ?>
