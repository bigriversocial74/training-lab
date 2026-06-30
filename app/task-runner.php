<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
$campaignRef = trim((string)($_GET['campaign'] ?? tl_app_default_campaign_ref()));
$userId = max(1, (int)($_GET['user_id'] ?? tl_stage200_actor_id()));
$focusTask = trim((string)($_GET['task'] ?? ''));
$campaigns = tl_app_campaign_options();
$state = tl_stage200_workflow_state($campaignRef, $userId);
$context = $state['participant_context'];
labs_page_start(['title' => 'Task Runner | Training Lab', 'section' => 'app', 'active' => 'app-task-runner']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('app-task-runner'); ?>
<?php if (function_exists('tl_stage840_render_claim_readiness')) tl_stage840_render_claim_readiness(max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage800_render_assignment_preview')) tl_stage800_render_assignment_preview((string)($_GET['campaign'] ?? '')); ?>

<?php if (function_exists('tl_stage720_render_participant_learning_experience')) tl_stage720_render_participant_learning_experience((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>

<?php if (function_exists('tl_stage680_render_participant_communication')) tl_stage680_render_participant_communication((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage520_render_participant_mission')) tl_stage520_render_participant_mission((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage560_render_mission_runbook')) tl_stage560_render_mission_runbook((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>

<?php if (function_exists('tl_stage600_render_participant_timeline')) tl_stage600_render_participant_timeline((string)($_GET['campaign'] ?? ''), (int)($_GET['user_id'] ?? 0)); ?>


<section class="labs-page-title labs-stage200-title"><div><span class="labs-eyebrow">Task runner</span><h1>Complete the next action and keep the backend trail clean.</h1><p class="labs-copy">Checklist tasks create an approved Training Lab receipt. Proof tasks create a submitted proof for the admin review queue.</p></div><div class="labs-actions"><a class="labs-btn" href="<?php echo labs_url('/app/participant-portal.php?campaign=' . rawurlencode($state['campaign_ref']) . '&user_id=' . $userId); ?>">Mission Control</a><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/admin/review-workbench.php'); ?>">Review Queue</a></div></section>
<form class="labs-card labs-stage35-filter" method="get"><label>Campaign<select name="campaign"><?php foreach ($campaigns as $c): ?><option value="<?php echo labs_e((string)$c['ref']); ?>" <?php echo $c['ref'] === $state['campaign_ref'] ? 'selected' : ''; ?>><?php echo labs_e((string)$c['title']); ?></option><?php endforeach; ?></select></label><label>User ID<input type="number" name="user_id" min="1" value="<?php echo $userId; ?>"></label><button class="labs-btn labs-btn-primary" type="submit">Load</button></form>
<?php if (empty($context['joined'])): ?><section class="labs-card labs-warning-card"><h2>Join first</h2><p class="labs-copy">Join creates the participant record used for progress, receipts, and rewards.</p><form action="<?php echo labs_url('/app/action-result.php'); ?>" method="post" class="labs-stage30-form"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="join_campaign"><input type="hidden" name="campaign_id" value="<?php echo labs_e((string)$state['campaign_ref']); ?>"><input type="hidden" name="user_id" value="<?php echo $userId; ?>"><label>Participant label<input name="participant_label" value="Training Participant <?php echo $userId; ?>"></label><button class="labs-btn labs-btn-primary" type="submit">Join Campaign</button></form></section><?php endif; ?>
<section class="labs-kpis labs-stage200-kpis"><div class="labs-kpi"><span>Progress</span><strong><?php echo (int)$state['progress_percent']; ?>%</strong><small>approved task path</small></div><div class="labs-kpi"><span>Next</span><strong><?php echo labs_e((string)($state['next_task']['title'] ?? 'None')); ?></strong><small>recommended action</small></div><div class="labs-kpi"><span>Claimable</span><strong><?php echo (int)($state['rewards']['counts']['claimable'] ?? 0); ?></strong><small>rewards waiting</small></div></section>
<section class="labs-stage35-runner-grid">
<?php foreach (($context['tasks'] ?? []) as $task): $tid=(string)($task['db_id'] ?? $task['id'] ?? ''); $proofRows=$context['proofs_by_task'][$tid] ?? []; $latest=$proofRows[0] ?? null; $isFocused=($focusTask !== '' && $focusTask === $tid) || ($focusTask === '' && isset($state['next_task']) && (string)($state['next_task']['db_id'] ?? $state['next_task']['id'] ?? '') === $tid); $requiresProof=(string)($task['proof'] ?? '') === 'Required'; ?>
  <article class="labs-card labs-stage35-task-run-card <?php echo $isFocused ? 'is-focused' : ''; ?>">
    <div class="labs-stage35-task-head"><span class="labs-pill">Step <?php echo (int)($task['day'] ?? 1); ?></span><span class="labs-pill"><?php echo $requiresProof ? 'Proof required' : 'Checklist'; ?></span><?php if ($isFocused): ?><span class="labs-pill">Recommended</span><?php endif; ?></div>
    <h2><?php echo labs_e((string)$task['title']); ?></h2><p class="labs-muted"><?php echo labs_e((string)($task['instructions'] ?? 'Complete this Training Lab action.')); ?></p>
    <?php if ($latest): ?><div class="labs-stage35-status-box"><strong>Latest: <?php echo labs_e(ucwords(str_replace('_', ' ', (string)$latest['status']))); ?></strong><small><?php echo labs_e((string)$latest['created_at']); ?></small></div><?php endif; ?>
    <form action="<?php echo labs_url('/app/action-result.php'); ?>" method="post" class="labs-stage30-form"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="complete_task"><input type="hidden" name="campaign_id" value="<?php echo labs_e((string)$state['campaign_ref']); ?>"><input type="hidden" name="task_id" value="<?php echo labs_e($tid); ?>"><input type="hidden" name="user_id" value="<?php echo $userId; ?>"><input type="hidden" name="participant_label" value="Training Participant <?php echo $userId; ?>">
    <?php if ($requiresProof): ?><label>Proof note<textarea name="proof_text" rows="4" required>I completed <?php echo labs_e((string)$task['title']); ?> and am submitting this proof for review.</textarea></label><label>Optional reference URL<input name="external_url" placeholder="https://example.com/proof-note"></label><button class="labs-btn labs-btn-primary" type="submit">Submit Proof</button><?php else: ?><input type="hidden" name="auto_approve" value="1"><input type="hidden" name="proof_text" value="Checklist task completed from Stage 200 Task Runner."><button class="labs-btn labs-btn-primary" type="submit">Complete Checklist</button><?php endif; ?></form>
  </article>
<?php endforeach; ?>
</section>
<section class="labs-safe-note">Task Runner still performs Training Lab-only writes. Proof text is stored as text; no real upload pipeline is activated.</section>

<section class="labs-card labs-stage280-panel">
  <?php $stage280Proof = tl_stage280_proof_quality_state($campaignRef ?? '', $userId ?? null); ?>
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Build 12</span><h2>Proof Quality Engine</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/proof-quality.php?campaign=' . rawurlencode((string)($campaignRef ?? '')) . '&user_id=' . (int)($userId ?? 1)); ?>">Proof API</a></div>
  <div class="labs-kpis labs-stage200-kpis"><div class="labs-kpi"><span>Quality Score</span><strong><?php echo (int)$stage280Proof['score']; ?>/100</strong><small>engine readiness</small></div><div class="labs-kpi"><span>Submitted</span><strong><?php echo (int)$stage280Proof['status_counts']['submitted']; ?></strong><small>needs review</small></div><div class="labs-kpi"><span>Approved</span><strong><?php echo (int)$stage280Proof['status_counts']['approved']; ?></strong><small>accepted proof</small></div></div>
  <ul class="labs-stage280-list"><?php foreach ($stage280Proof['guidance'] as $tip): ?><li><?php echo labs_e((string)$tip); ?></li><?php endforeach; ?></ul>
</section>

<?php labs_page_end(['section' => 'app']); ?>
