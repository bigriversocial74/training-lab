<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
$campaignRef = trim((string)($_GET['campaign'] ?? tl_app_default_campaign_ref()));
$userId = max(1, (int)($_GET['user_id'] ?? tl_stage200_actor_id()));
$state = tl_stage200_workflow_state($campaignRef, $userId);
$context = $state['participant_context'];
$campaigns = tl_app_campaign_options();
$participant = $context['participant'] ?? null;
$nextTask = $state['next_task'] ?? null;
$stage240Timeline = tl_stage240_user_activity_timeline($userId, 18);
labs_page_start(['title' => 'Mission Control | Training Lab', 'section' => 'app', 'active' => 'app-participant-portal']);
?>
<?php if (function_exists('tl_stage880_render_identity_matching')) tl_stage880_render_identity_matching(max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('app-participant-portal'); ?>
<?php if (function_exists('tl_stage840_render_customer_account_bridge')) tl_stage840_render_customer_account_bridge(max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage840_render_award_inbox')) tl_stage840_render_award_inbox(max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage760_render_offer_preview_experience')) tl_stage760_render_offer_preview_experience((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>


<?php if (function_exists('tl_stage720_render_participant_learning_experience')) tl_stage720_render_participant_learning_experience((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>

<?php if (function_exists('tl_stage680_render_participant_communication')) tl_stage680_render_participant_communication((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage640_render_participant_data_quality')) tl_stage640_render_participant_data_quality((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>

<?php if (function_exists('tl_stage520_render_participant_mission')) tl_stage520_render_participant_mission((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage560_render_mission_runbook')) tl_stage560_render_mission_runbook((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>

<?php if (function_exists('tl_stage600_render_participant_timeline')) tl_stage600_render_participant_timeline((string)($_GET['campaign'] ?? ''), (int)($_GET['user_id'] ?? 0)); ?>


<section class="labs-page-title labs-stage200-title">
  <div><span class="labs-eyebrow">Participant mission control</span><h1>Know what to do next.</h1><p class="labs-copy">This page now acts as the participant cockpit: join state, next task, task path, proof state, reward state, and notes all in one place.</p></div>
  <div class="labs-actions"><a class="labs-btn" href="<?php echo labs_url('/app/rewards.php?user_id=' . $userId); ?>">Rewards</a><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/app/task-runner.php?campaign=' . rawurlencode($state['campaign_ref']) . '&user_id=' . $userId); ?>">Run Next Task</a></div>
</section>
<form class="labs-card labs-stage35-filter" method="get">
  <label>Campaign<select name="campaign"><?php foreach ($campaigns as $c): ?><option value="<?php echo labs_e((string)$c['ref']); ?>" <?php echo $c['ref'] === $state['campaign_ref'] ? 'selected' : ''; ?>><?php echo labs_e((string)$c['title']); ?></option><?php endforeach; ?></select></label>
  <label>User ID<input type="number" name="user_id" min="1" value="<?php echo $userId; ?>"></label>
  <button class="labs-btn labs-btn-primary" type="submit">Load</button>
</form>
<section class="labs-kpis labs-stage200-kpis">
  <div class="labs-kpi"><span>Joined</span><strong><?php echo !empty($context['joined']) ? 'Yes' : 'No'; ?></strong><small><?php echo $participant ? labs_e((string)$participant['status']) : 'create participant'; ?></small></div>
  <div class="labs-kpi"><span>Progress</span><strong><?php echo (int)$state['progress_percent']; ?>%</strong><small>approved tasks</small></div>
  <div class="labs-kpi"><span>Tasks</span><strong><?php echo count($context['tasks'] ?? []); ?></strong><small>in path</small></div>
  <div class="labs-kpi"><span>Rewards</span><strong><?php echo (int)($state['rewards']['counts']['total'] ?? 0); ?></strong><small>earned/available</small></div>
</section>
<?php if (empty($context['joined'])): ?>
<section class="labs-card labs-warning-card"><h2>Join this campaign</h2><p class="labs-copy">A participant row is required before progress, proof, receipts, and rewards can connect.</p><form action="<?php echo labs_url('/app/action-result.php'); ?>" method="post" class="labs-stage30-form"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="join_campaign"><input type="hidden" name="campaign_id" value="<?php echo labs_e((string)$state['campaign_ref']); ?>"><input type="hidden" name="user_id" value="<?php echo $userId; ?>"><label>Participant label<input name="participant_label" value="Training Participant <?php echo $userId; ?>"></label><button class="labs-btn labs-btn-primary" type="submit">Join Campaign</button></form></section>
<?php endif; ?>
<section class="labs-flow-grid labs-stage200-grid">
  <article class="labs-card">
    <div class="labs-card-headline"><div><span class="labs-eyebrow">Next task</span><h2><?php echo $nextTask ? labs_e((string)$nextTask['title']) : 'No active task'; ?></h2></div><?php if ($nextTask): ?><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/app/task-runner.php?campaign=' . rawurlencode($state['campaign_ref']) . '&user_id=' . $userId . '&task=' . rawurlencode((string)($nextTask['db_id'] ?? $nextTask['id'] ?? ''))); ?>">Open Task</a><?php endif; ?></div>
    <p class="labs-muted"><?php echo $nextTask ? labs_e((string)($nextTask['instructions'] ?? 'Complete this training step.')) : 'Create campaign tasks first.'; ?></p>
    <div class="labs-progress-meter"><span style="width:<?php echo (int)$state['progress_percent']; ?>%"></span></div>
  </article>
  <aside class="labs-card">
    <h2>Participant note</h2>
    <form action="<?php echo labs_url('/app/action-result.php'); ?>" method="post" class="labs-stage30-form">
      <input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="save_training_note"><input type="hidden" name="subject_type" value="participant"><input type="hidden" name="subject_id" value="<?php echo (int)($participant['id'] ?? 0); ?>"><input type="hidden" name="user_id" value="<?php echo $userId; ?>"><input type="hidden" name="source" value="participant_portal">
      <label>Note<textarea name="note" rows="4" placeholder="Write a participant reflection, blocker, or coaching note."></textarea></label>
      <button class="labs-btn" type="submit">Save Note</button>
    </form>
  </aside>
</section>
<section class="labs-card"><h2>Task path</h2><div class="labs-stage200-timeline"><?php foreach (($context['tasks'] ?? []) as $task): $tid=(string)($task['db_id'] ?? $task['id'] ?? ''); $latest=($context['proofs_by_task'][$tid] ?? [])[0] ?? null; ?><a href="<?php echo labs_url('/app/task-runner.php?campaign=' . rawurlencode($state['campaign_ref']) . '&user_id=' . $userId . '&task=' . rawurlencode($tid)); ?>"><span><?php echo labs_e((string)($latest['status'] ?? 'not_started')); ?></span><strong><?php echo labs_e((string)$task['title']); ?></strong><small><?php echo labs_e((string)($task['proof'] ?? 'Checklist')); ?></small></a><?php endforeach; ?></div></section>
<section class="labs-safe-note">Mission Control uses existing Training Lab tables and event notes. Notes do not send email, SMS, push, or external notifications.</section>

<section class="labs-flow-grid labs-stage240-grid">
  <article class="labs-card">
    <div class="labs-card-headline"><div><span class="labs-eyebrow">Build 7</span><h2>Participant Timeline</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/participant-timeline.php?user_id=' . $userId); ?>">Timeline API</a></div>
    <div class="labs-stage240-timeline"><?php foreach ($stage240Timeline as $item): ?><div><span><?php echo labs_e((string)$item['type']); ?></span><strong><?php echo labs_e((string)$item['label']); ?></strong><small><?php echo labs_e((string)($item['created_at'] ?? '')); ?></small></div><?php endforeach; if (!$stage240Timeline): ?><p class="labs-muted">No participant activity yet.</p><?php endif; ?></div>
  </article>
  <aside class="labs-card">
    <h2>Save checkpoint</h2>
    <form action="<?php echo labs_url('/app/action-result.php'); ?>" method="post" class="labs-stage30-form">
      <input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="save_participant_checkpoint"><input type="hidden" name="participant_id" value="<?php echo (int)($participant['id'] ?? 0); ?>"><input type="hidden" name="user_id" value="<?php echo $userId; ?>">
      <label>Checkpoint type<select name="checkpoint_type"><option value="progress">Progress</option><option value="blocker">Blocker</option><option value="coach_note">Coach note</option><option value="ready_for_review">Ready for review</option></select></label>
      <label>Checkpoint note<textarea name="checkpoint_note" rows="4" placeholder="What changed since the last step?"></textarea></label>
      <button class="labs-btn" type="submit">Save Checkpoint</button>
    </form>
  </aside>
</section>


<section class="labs-flow-grid labs-stage280-grid">
  <?php $stage280Ledger = tl_stage280_account_ledger($userId); ?>
  <article class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Build 11</span><h2>Account Link Ledger</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/account-ledger.php?user_id=' . $userId); ?>">Ledger API</a></div><div class="labs-stage130-check-grid"><?php foreach ($stage280Ledger['checks'] as $key => $ok): ?><div class="<?php echo $ok ? 'is-ok' : 'is-warn'; ?>"><span><?php echo labs_e(ucwords(str_replace('_',' ',$key))); ?></span><strong><?php echo $ok ? 'OK' : 'Watch'; ?></strong></div><?php endforeach; ?></div></article>
  <aside class="labs-card"><h2>Snapshot account state</h2><form action="<?php echo labs_url('/app/action-result.php'); ?>" method="post" class="labs-stage30-form"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="create_account_link_snapshot"><input type="hidden" name="user_id" value="<?php echo $userId; ?>"><button class="labs-btn labs-btn-primary" type="submit">Save Account Snapshot</button></form></aside>
</section>

<?php labs_page_end(['section' => 'app']); ?>
