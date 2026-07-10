<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-progress-experience.php';

$page = ['title'=>'Progress | Training Lab','section'=>'app','active'=>'app-progress-map','required_role'=>'participant'];
$user = tl_product_require_page_access($page);
$campaignRef = tl_campaign_clean_ref((string)($_GET['campaign'] ?? ''));
$progress = tl_progress_detail($user ?? [], $campaignRef);
$flash = tl_progress_flash_take();
labs_page_start($page);
?>

<?php if ($flash): ?><div class="labs-progress-flash is-<?php echo labs_e((string)$flash['type']); ?>" role="status"><?php echo labs_e((string)$flash['message']); ?></div><?php endif; ?>

<?php if (empty($progress['found'])): ?>
<section class="labs-product-empty">
  <span class="labs-product-kicker">Training progress</span>
  <h1>Your progress begins with a campaign.</h1>
  <p>Join a published campaign to complete tasks, submit proof, earn completion receipts, and unlock rewards.</p>
  <a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url('/app/campaigns.php'), ENT_QUOTES, 'UTF-8'); ?>">Browse Campaigns</a>
</section>
<?php else: $selected=$progress['selected']; ?>
<section class="labs-progress-hero">
  <article class="labs-progress-hero-main">
    <div class="labs-progress-hero-top">
      <div><span class="labs-product-kicker">Campaign progress</span><h1><?php echo labs_e((string)$selected['campaign_title']); ?></h1></div>
      <span class="labs-product-status is-<?php echo !empty($progress['completion_recorded']) ? 'success' : ((string)$selected['participant_status'] === 'paused' ? 'warning' : 'info'); ?>"><?php echo !empty($progress['completion_recorded']) ? 'Completed' : labs_e(ucfirst((string)$selected['participant_status'])); ?></span>
    </div>
    <p><?php echo labs_e((string)($selected['campaign_summary'] ?: 'Complete each task, submit proof when required, and track every verified milestone.')); ?></p>
    <div class="labs-product-progress" aria-label="<?php echo (int)$progress['progress_percent']; ?> percent complete"><span style="width:<?php echo (int)$progress['progress_percent']; ?>%"></span></div>
    <div class="labs-progress-hero-foot"><strong><?php echo (int)$progress['progress_percent']; ?>%</strong><span><?php echo (int)$progress['task_complete']; ?> of <?php echo (int)$progress['task_total']; ?> tasks verified</span></div>
  </article>
  <aside class="labs-product-next">
    <div><span>Recommended next step</span><h2><?php echo labs_e((string)$progress['next_action']['label']); ?></h2><p><?php echo !empty($progress['completion_recorded']) ? 'Your completion and reward history are available below.' : 'Keep your training moving with the next verified action.'; ?></p></div>
    <a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url((string)$progress['next_action']['href']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo labs_e((string)$progress['next_action']['label']); ?></a>
  </aside>
</section>

<section class="labs-product-stats" aria-label="Progress summary">
  <article class="labs-product-stat"><span>Tasks complete</span><strong><?php echo (int)$progress['task_complete']; ?>/<?php echo (int)$progress['task_total']; ?></strong><small>verified actions</small></article>
  <article class="labs-product-stat"><span>In review</span><strong><?php echo (int)$progress['pending_review_count']; ?></strong><small>proof decisions pending</small></article>
  <article class="labs-product-stat"><span>Needs update</span><strong><?php echo (int)$progress['revision_count']; ?></strong><small>reviewer follow-up</small></article>
  <article class="labs-product-stat"><span>Current streak</span><strong><?php echo (int)$progress['current_streak_days']; ?></strong><small>days</small></article>
  <article class="labs-product-stat"><span>Rewards</span><strong><?php echo count($progress['rewards']); ?></strong><small>earned or available</small></article>
</section>

<section class="labs-progress-layout">
  <div class="labs-product-stack">
    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Task timeline</span><h2>Your training path</h2><p>Verified receipts determine campaign completion and reward eligibility.</p></div></div>
      <div class="labs-progress-task-list">
        <?php foreach ($progress['tasks'] as $index => $task): $status=$task['status_model']; ?>
          <a href="<?php echo htmlspecialchars(labs_url('/app/task-runner.php?campaign=' . rawurlencode((string)$selected['ref']) . '&task=' . rawurlencode((string)($task['public_id'] ?: $task['id']))), ENT_QUOTES, 'UTF-8'); ?>">
            <span class="labs-progress-task-number"><?php echo $status['key'] === 'complete' ? '✓' : $index + 1; ?></span>
            <div><strong><?php echo labs_e((string)$task['title']); ?></strong><small><?php echo labs_e((string)$status['reason']); ?></small></div>
            <span class="labs-product-status is-<?php echo labs_e((string)$status['tone']); ?>"><?php echo labs_e((string)$status['label']); ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="labs-product-card" id="campaign-completion">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Campaign completion</span><h2><?php echo !empty($progress['completion_recorded']) ? 'Completion recorded' : (!empty($progress['completion_eligible']) ? 'Ready to complete' : 'Finish every task'); ?></h2></div></div>
      <?php if (!empty($progress['completion_recorded'])): ?>
        <div class="labs-progress-certificate">
          <span aria-hidden="true">✓</span>
          <div><strong><?php echo labs_e((string)$selected['campaign_title']); ?></strong><p>Completed <?php echo !empty($selected['certificate_issued_at']) ? date('M j, Y', strtotime((string)$selected['certificate_issued_at'])) : 'and verified'; ?>.</p><small>Completion record <?php echo labs_e(strtoupper(substr((string)$selected['certificate_public_id'], 0, 8))); ?></small></div>
        </div>
        <div class="labs-progress-actions"><a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/app/history.php'), ENT_QUOTES, 'UTF-8'); ?>">Training History</a><a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url('/app/rewards.php'), ENT_QUOTES, 'UTF-8'); ?>">View Rewards</a></div>
      <?php elseif (!empty($progress['completion_eligible'])): ?>
        <p class="labs-copy">Every active task has a verified completion receipt. Finalize the campaign to create your completion record and evaluate the campaign reward.</p>
        <form method="post" action="<?php echo htmlspecialchars(labs_url('/app/complete-campaign.php'), ENT_QUOTES, 'UTF-8'); ?>">
          <?php echo tl_security_csrf_field(); ?>
          <input type="hidden" name="campaign_id" value="<?php echo labs_e((string)$selected['ref']); ?>">
          <button class="labs-btn labs-btn-primary" type="submit">Complete Campaign</button>
        </form>
      <?php else: ?>
        <p class="labs-copy"><?php echo max(0, (int)$progress['task_total'] - (int)$progress['task_complete']); ?> verified task<?php echo ((int)$progress['task_total'] - (int)$progress['task_complete']) === 1 ? '' : 's'; ?> remaining before campaign completion.</p>
        <?php if ((int)$progress['revision_count'] > 0): ?><p class="labs-progress-warning">Respond to reviewer feedback before finalizing this campaign.</p><?php endif; ?>
      <?php endif; ?>
    </section>

    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Recent milestones</span><h2>Training activity</h2><p>Proof, reviews, verified receipts, completion, and rewards in one timeline.</p></div></div>
      <?php if ($progress['activity']): ?>
      <div class="labs-progress-activity">
        <?php foreach ($progress['activity'] as $item): ?>
          <article><span class="labs-product-status is-<?php echo labs_e((string)$item['tone']); ?>"><?php echo labs_e((string)$item['type']); ?></span><div><strong><?php echo labs_e((string)$item['label']); ?></strong><p><?php echo labs_e((string)$item['detail']); ?></p></div><time datetime="<?php echo htmlspecialchars((string)$item['at'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo date('M j, g:i A', strtotime((string)$item['at'])); ?></time></article>
        <?php endforeach; ?>
      </div>
      <?php else: ?><div class="labs-product-empty"><h3>No milestones yet.</h3><p>Complete your first task to begin the timeline.</p></div><?php endif; ?>
    </section>
  </div>

  <aside class="labs-product-stack">
    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Campaigns</span><h2>Switch progress view</h2></div></div>
      <nav class="labs-progress-campaign-list" aria-label="Your campaign progress">
        <?php foreach ($progress['campaigns'] as $campaign): ?>
          <a class="<?php echo (int)$campaign['campaign_id'] === (int)$selected['campaign_id'] ? 'is-current' : ''; ?>" href="<?php echo htmlspecialchars(labs_url('/app/progress-map.php?campaign=' . rawurlencode((string)$campaign['ref'])), ENT_QUOTES, 'UTF-8'); ?>"<?php echo (int)$campaign['campaign_id'] === (int)$selected['campaign_id'] ? ' aria-current="page"' : ''; ?>><div><strong><?php echo labs_e((string)$campaign['campaign_title']); ?></strong><small><?php echo (int)$campaign['completed_task_count']; ?>/<?php echo (int)$campaign['task_count']; ?> tasks</small></div><span><?php echo (int)$campaign['progress_percent']; ?>%</span></a>
        <?php endforeach; ?>
      </nav>
    </section>

    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Rewards</span><h2>Campaign rewards</h2></div><a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/app/rewards.php'), ENT_QUOTES, 'UTF-8'); ?>">All Rewards</a></div>
      <?php if ($progress['rewards']): ?><div class="labs-progress-rewards"><?php foreach (array_slice($progress['rewards'],0,6) as $reward): ?><article><strong><?php echo labs_e((string)($reward['reward_label'] ?: 'Training reward')); ?></strong><span><?php echo labs_e(ucwords(str_replace('_',' ',(string)$reward['status']))); ?></span></article><?php endforeach; ?></div><?php else: ?><p class="labs-muted">Campaign rewards appear after verified eligibility.</p><?php endif; ?>
    </section>

    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Achievement</span><h2>Longest streak</h2></div></div><div class="labs-progress-streak"><strong><?php echo (int)$progress['longest_streak_days']; ?></strong><span>days</span></div><a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/app/history.php'), ENT_QUOTES, 'UTF-8'); ?>">View Training History</a>
    </section>
  </aside>
</section>
<?php endif; ?>

<?php labs_page_end(['section'=>'app']); ?>
