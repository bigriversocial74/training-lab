<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
$state = tl_stage200_workflow_state((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0)));
$summary = $state['summary'] ?? [];
$userId = (int)$state['actor_user_id'];
labs_page_start(['title' => 'Flow Board | Training Lab', 'section' => 'app', 'active' => 'app-flow-board']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('app-flow-board'); ?>
<?php if (function_exists('tl_stage840_render_award_history')) tl_stage840_render_award_history(max(0, (int)($_GET['user_id'] ?? 0))); ?>

<?php if (function_exists('tl_stage680_render_mission_followup_logic')) tl_stage680_render_mission_followup_logic((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage640_render_participant_data_quality')) tl_stage640_render_participant_data_quality((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>

<?php if (function_exists('tl_stage520_render_participant_mission')) tl_stage520_render_participant_mission((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage560_render_mission_runbook')) tl_stage560_render_mission_runbook((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>

<?php if (function_exists('tl_stage600_render_participant_timeline')) tl_stage600_render_participant_timeline((string)($_GET['campaign'] ?? ''), (int)($_GET['user_id'] ?? 0)); ?>


<section class="labs-page-title labs-stage200-title"><div><span class="labs-eyebrow">Flow board</span><h1>The complete Training Lab lifecycle.</h1><p class="labs-copy">Use this as the end-to-end check: campaign → participant → task/proof → review → receipt → reward → report.</p></div><div class="labs-actions"><a class="labs-btn" href="<?php echo labs_url('/api/training/workflow-state.php?user_id=' . $userId); ?>">Workflow API</a><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/app/rewards.php?user_id=' . $userId); ?>">Rewards</a></div></section>
<section class="labs-card labs-stage200-primary"><h2>Lifecycle map</h2><div class="labs-stage200-flowline"><?php foreach ($state['steps'] as $step): ?><a class="is-<?php echo labs_e((string)$step['status']); ?>" href="<?php echo labs_url((string)$step['href']); ?>"><span><?php echo labs_e((string)$step['status']); ?></span><strong><?php echo labs_e((string)$step['label']); ?></strong><small><?php echo labs_e((string)$step['detail']); ?></small></a><?php endforeach; ?></div></section>
<section class="labs-flow-grid labs-stage200-grid"><article class="labs-card"><h2>Recent events</h2><div class="labs-panel-list"><?php foreach (array_slice($summary['recent_events'] ?? [], 0, 10) as $event): ?><div class="labs-panel-item"><span class="labs-mini-icon">E</span><div><strong><?php echo labs_e((string)($event['event_type'] ?? 'event')); ?></strong><p><?php echo labs_e((string)($event['subject_type'] ?? 'system')); ?> · <?php echo labs_e((string)($event['created_at'] ?? '')); ?></p></div></div><?php endforeach; ?></div></article><aside class="labs-card"><h2>Snapshot</h2><p class="labs-muted">Create a workflow snapshot for QA and admin reporting.</p><form action="<?php echo labs_url('/app/action-result.php'); ?>" method="post" class="labs-stage30-form"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="create_workflow_snapshot"><input type="hidden" name="user_id" value="<?php echo $userId; ?>"><button class="labs-btn labs-btn-primary" type="submit">Create Snapshot</button></form><pre class="labs-stage25-code"><?php echo labs_e(json_encode(['progress'=>$state['progress_percent'],'next_step'=>$state['next_step']['key'] ?? null,'blockers'=>count($state['blockers'])], JSON_PRETTY_PRINT)); ?></pre></aside></section>
<section class="labs-safe-note">Flow Board snapshots are Training Lab audit events only. They do not export private data or call external services.</section>

<section class="labs-card labs-stage280-panel">
  <?php $stage280Release = tl_stage280_release_candidate(); ?>
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Build 15</span><h2>Release Candidate QA Pack</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/release-candidate.php'); ?>">Release API</a></div>
  <div class="labs-stage130-check-grid"><?php foreach ($stage280Release['checks'] as $key => $ok): ?><div class="<?php echo $ok ? 'is-ok' : 'is-warn'; ?>"><span><?php echo labs_e(ucwords(str_replace('_',' ',$key))); ?></span><strong><?php echo $ok ? 'OK' : 'Check'; ?></strong></div><?php endforeach; ?></div>
</section>

<?php labs_page_end(['section' => 'app']); ?>
