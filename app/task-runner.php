<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-task-experience.php';

$page = ['title' => 'Task | Training Lab', 'section' => 'app', 'active' => 'app-task-runner', 'required_role' => 'participant'];
$user = tl_product_require_page_access($page);
$campaignRef = tl_campaign_clean_ref((string)($_GET['campaign'] ?? ''));
$taskRef = tl_task_clean_ref((string)($_GET['task'] ?? ''));
$experience = tl_task_experience($user ?? [], $campaignRef, $taskRef);
$flash = tl_task_flash_take();
labs_page_start($page);
?>

<?php if ($flash): ?><div class="labs-task-flash is-<?php echo labs_e((string)$flash['type']); ?>" role="status"><?php echo labs_e((string)$flash['message']); ?></div><?php endif; ?>

<?php if (empty($experience['found'])): ?>
<section class="labs-product-empty">
  <span class="labs-product-kicker">Task unavailable</span>
  <h1>No enrolled training path was found.</h1>
  <p><?php echo labs_e((string)($experience['reason'] ?? 'Join a campaign before opening its tasks.')); ?></p>
  <a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url('/app/campaigns.php'), ENT_QUOTES, 'UTF-8'); ?>">Browse Campaigns</a>
</section>
<?php elseif (empty($experience['selected'])): ?>
<section class="labs-product-empty">
  <span class="labs-product-kicker">Training path</span>
  <h1>No active tasks are published.</h1>
  <p>The campaign manager is still preparing this training path.</p>
  <a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/app/campaign-detail.php?id=' . rawurlencode((string)$experience['campaign_ref'])), ENT_QUOTES, 'UTF-8'); ?>">Campaign Details</a>
</section>
<?php else:
$campaign = $experience['campaign'];
$task = $experience['selected'];
$status = $task['status_model'];
$proofRequired = (int)($task['proof_required'] ?? 0) === 1;
$dueAt = $status['due_at'] ?? null;
?>
<a class="labs-task-back" href="<?php echo htmlspecialchars(labs_url('/app/campaign-detail.php?id=' . rawurlencode((string)$experience['campaign_ref'])), ENT_QUOTES, 'UTF-8'); ?>">← Back to campaign</a>

<section class="labs-task-hero">
  <article class="labs-task-hero-main">
    <div class="labs-task-hero-top">
      <span class="labs-product-kicker"><?php echo labs_e((string)$campaign['title']); ?></span>
      <span class="labs-product-status is-<?php echo labs_e((string)$status['tone']); ?>"><?php echo labs_e((string)$status['label']); ?></span>
    </div>
    <h1><?php echo labs_e((string)$task['title']); ?></h1>
    <p><?php echo labs_e((string)($task['instructions'] ?: 'Complete this training action and submit the requested confirmation.')); ?></p>
    <div class="labs-task-meta">
      <span>Task <?php echo (int)$experience['selected_index'] + 1; ?> of <?php echo count($experience['tasks']); ?></span>
      <span><?php echo $proofRequired ? 'Proof required' : 'Checklist completion'; ?></span>
      <?php if (!empty($task['expected_duration_minutes'])): ?><span><?php echo (int)$task['expected_duration_minutes']; ?> minutes</span><?php endif; ?>
      <?php if ($dueAt instanceof DateTimeImmutable): ?><span>Due <?php echo $dueAt->format('M j, Y g:i A'); ?></span><?php endif; ?>
    </div>
  </article>
  <aside class="labs-task-progress-card">
    <span>Campaign progress</span>
    <strong><?php echo (int)$experience['progress_percent']; ?>%</strong>
    <div class="labs-product-progress" aria-label="<?php echo (int)$experience['progress_percent']; ?> percent complete"><span style="width:<?php echo (int)$experience['progress_percent']; ?>%"></span></div>
    <small><?php echo (int)$experience['complete_count']; ?> of <?php echo count($experience['tasks']); ?> tasks verified</small>
  </aside>
</section>

<section class="labs-task-layout">
  <div class="labs-product-stack">
    <section class="labs-product-card">
      <div class="labs-product-card-head">
        <div><span class="labs-product-kicker">Current status</span><h2><?php echo labs_e((string)$status['label']); ?></h2><p><?php echo labs_e((string)$status['reason']); ?></p></div>
      </div>

      <?php if (!empty($status['can_submit'])): ?>
      <form class="labs-task-submit-form" method="post" action="<?php echo htmlspecialchars(labs_url('/app/task-submit.php'), ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo tl_security_csrf_field(); ?>
        <input type="hidden" name="campaign_id" value="<?php echo labs_e((string)$experience['campaign_ref']); ?>">
        <input type="hidden" name="task_id" value="<?php echo labs_e((string)($task['public_id'] ?: $task['id'])); ?>">
        <?php if ($proofRequired): ?>
          <?php if (($status['key'] ?? '') === 'needs_revision'): ?><div class="labs-task-review-note"><strong>Reviewer feedback</strong><p><?php echo labs_e((string)$status['reason']); ?></p></div><?php endif; ?>
          <label>What did you complete?<textarea name="proof_text" rows="7" minlength="10" maxlength="5000" placeholder="Describe the action you completed and what changed." required></textarea></label>
          <label>Supporting link <span class="labs-muted">optional</span><input name="external_url" type="url" maxlength="500" placeholder="https://example.com/proof"></label>
          <p class="labs-muted">Text and links are stored as proof records. Real file upload processing remains disabled.</p>
          <button class="labs-btn labs-btn-primary" type="submit"><?php echo ($status['key'] ?? '') === 'needs_revision' ? 'Submit Updated Proof' : 'Submit for Review'; ?></button>
        <?php else: ?>
          <div class="labs-task-checklist-confirm"><span aria-hidden="true">✓</span><div><strong>Confirm completion</strong><p>Completing this checklist creates a verified Training Lab receipt.</p></div></div>
          <input type="hidden" name="proof_text" value="Checklist task completed from the participant task experience.">
          <button class="labs-btn labs-btn-primary" type="submit">Mark Task Complete</button>
        <?php endif; ?>
      </form>
      <?php elseif (($status['key'] ?? '') === 'in_review'): ?>
        <div class="labs-task-waiting"><strong>Your current proof is already in review</strong><p>A reviewer will approve it, request an update, or reject it. You cannot submit another version while this one is in review.</p></div>
      <?php elseif (($status['key'] ?? '') === 'complete'): ?>
        <div class="labs-task-complete"><strong>Task verified</strong><p>Your completion receipt is part of your campaign progress and reward eligibility.</p></div>
      <?php else: ?>
        <div class="labs-task-locked"><strong><?php echo labs_e((string)$status['label']); ?></strong><p><?php echo labs_e((string)$status['reason']); ?></p></div>
      <?php endif; ?>
    </section>

    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Submission history</span><h2>Proof and review timeline</h2><p>Every version and reviewer decision stays connected to this task.</p></div></div>
      <?php if ($experience['history']): ?>
      <div class="labs-task-history">
        <?php foreach ($experience['history'] as $proof): ?>
          <article class="labs-task-history-item">
            <div class="labs-task-history-head"><strong>Submission <?php echo (int)($proof['metadata']['revision_number'] ?? 1); ?></strong><span class="labs-product-status is-<?php echo (string)$proof['status'] === 'approved' ? 'success' : ((string)$proof['status'] === 'cancelled' ? 'neutral' : 'pending'); ?>"><?php echo labs_e(ucwords(str_replace('_', ' ', (string)$proof['status']))); ?></span></div>
            <?php if (!empty($proof['proof_text'])): ?><p><?php echo nl2br(labs_e((string)$proof['proof_text'])); ?></p><?php endif; ?>
            <?php if (!empty($proof['external_url'])): ?><a href="<?php echo htmlspecialchars((string)$proof['external_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Open supporting link</a><?php endif; ?>
            <time datetime="<?php echo htmlspecialchars((string)$proof['created_at'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo date('M j, Y g:i A', strtotime((string)$proof['created_at'])); ?></time>
            <?php foreach (($proof['reviews'] ?? []) as $review): ?>
              <div class="labs-task-review-decision"><strong><?php echo labs_e(ucwords(str_replace('_', ' ', (string)$review['decision']))); ?></strong><p><?php echo labs_e((string)($review['review_notes'] ?: 'No reviewer note.')); ?></p></div>
            <?php endforeach; ?>
          </article>
        <?php endforeach; ?>
      </div>
      <?php else: ?><div class="labs-product-empty"><h3>No submissions yet.</h3><p>Your proof and reviewer feedback will appear here.</p></div><?php endif; ?>
    </section>
  </div>

  <aside class="labs-product-stack">
    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Task path</span><h2>Campaign tasks</h2></div></div>
      <nav class="labs-task-path" aria-label="Campaign tasks">
        <?php foreach ($experience['tasks'] as $index => $pathTask): $pathStatus=$pathTask['status_model']; ?>
          <a class="<?php echo (int)$pathTask['id'] === (int)$task['id'] ? 'is-current' : ''; ?>" href="<?php echo htmlspecialchars(labs_url('/app/task-runner.php?campaign=' . rawurlencode((string)$experience['campaign_ref']) . '&task=' . rawurlencode((string)($pathTask['public_id'] ?: $pathTask['id']))), ENT_QUOTES, 'UTF-8'); ?>"<?php echo (int)$pathTask['id'] === (int)$task['id'] ? ' aria-current="step"' : ''; ?>><span><?php echo $index + 1; ?></span><div><strong><?php echo labs_e((string)$pathTask['title']); ?></strong><small><?php echo labs_e((string)$pathStatus['label']); ?></small></div></a>
        <?php endforeach; ?>
      </nav>
    </section>

    <section class="labs-product-card">
      <div class="labs-product-card-head"><div><span class="labs-product-kicker">Navigate</span><h2>Previous or next</h2></div></div>
      <div class="labs-task-nav-actions">
        <?php if ($experience['previous']): ?><a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/app/task-runner.php?campaign=' . rawurlencode((string)$experience['campaign_ref']) . '&task=' . rawurlencode((string)($experience['previous']['public_id'] ?: $experience['previous']['id']))), ENT_QUOTES, 'UTF-8'); ?>">Previous Task</a><?php endif; ?>
        <?php if ($experience['next']): ?><a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/app/task-runner.php?campaign=' . rawurlencode((string)$experience['campaign_ref']) . '&task=' . rawurlencode((string)($experience['next']['public_id'] ?: $experience['next']['id']))), ENT_QUOTES, 'UTF-8'); ?>">Next Task</a><?php endif; ?>
        <a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/app/progress-map.php?campaign=' . rawurlencode((string)$experience['campaign_ref'])), ENT_QUOTES, 'UTF-8'); ?>">View Progress</a>
      </div>
    </section>
  </aside>
</section>
<?php endif; ?>

<?php labs_page_end(['section'=>'app']); ?>
