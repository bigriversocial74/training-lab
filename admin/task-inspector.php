<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-stage34-service.php';

$taskRef = isset($_GET['task']) ? tl_stage20_clean_ref((string)$_GET['task']) : null;
$inspector = tl_training_task_inspector_summary($taskRef);
$task = $inspector['task'] ?? [];
$summary = $inspector['summary'] ?? [];
$proofs = $inspector['proofs'] ?? [];
$participants = $inspector['participants'] ?? [];
$reviews = $inspector['reviews'] ?? [];
$rewardEvents = $inspector['reward_events'] ?? [];
$timeline = $inspector['timeline'] ?? [];

function tl_stage20_value($value): string
{
    if ($value === null || $value === '') return 'None yet';
    return (string)$value;
}
function tl_stage20_label($value): string
{
    return ucwords(str_replace('_', ' ', tl_stage20_value($value)));
}

labs_page_start(['title'=>'Task Inspector | Training Lab','section'=>'admin','active'=>'admin-task-inspector']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-task-inspector'); ?>

<section class="labs-page-title labs-stage20-title">
  <div><span class="labs-eyebrow">Task inspector</span><h1>Inspect task-level training activity and related proof history.</h1><p class="labs-copy">Inspect a campaign task, proof submissions, participants, reviews, reward previews, and timeline context. This page does not complete tasks, unlock steps, process uploads, issue rewards, or change wallets.</p></div>
  <div class="labs-actions"><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/api/training/task-inspector.php' . (!empty($task['public_id']) ? '?task=' . urlencode((string)$task['public_id']) : '')); ?>">Task JSON</a><a class="labs-btn" href="<?php echo labs_url('/admin/campaign-inspector.php' . (!empty($task['campaign_slug']) ? '?campaign=' . urlencode((string)$task['campaign_slug']) : '')); ?>">Campaign Inspector</a></div>
</section>
<section class="labs-kpis labs-stage20-kpis">
  <div class="labs-kpi"><span class="labs-muted">Task status</span><strong><?php echo htmlspecialchars(tl_stage20_label($task['status'] ?? 'active')); ?></strong><small>read-only</small></div>
  <div class="labs-kpi"><span class="labs-muted">Proofs</span><strong><?php echo number_format((int)($summary['proof_count'] ?? 0)); ?></strong><small>linked submissions</small></div>
  <div class="labs-kpi"><span class="labs-muted">Reviews</span><strong><?php echo number_format((int)($summary['review_count'] ?? 0)); ?></strong><small>decision rows</small></div>
  <div class="labs-kpi"><span class="labs-muted">Reward events</span><strong><?php echo number_format((int)($summary['reward_event_count'] ?? 0)); ?></strong><small>preview only</small></div>
</section>
<section class="labs-flow-grid">
  <article class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Task detail</span><h2><?php echo htmlspecialchars($task['title'] ?? 'Training task'); ?></h2></div><span class="labs-pill"><?php echo htmlspecialchars(tl_stage20_label($task['task_type'] ?? 'task')); ?></span></div><div class="labs-stage15-detail-grid"><div><span>Campaign</span><strong><?php echo htmlspecialchars($task['campaign_title'] ?? 'Training Campaign'); ?></strong></div><div><span>Position</span><strong><?php echo htmlspecialchars(tl_stage20_value($task['position_no'] ?? null)); ?></strong></div><div><span>Day</span><strong><?php echo htmlspecialchars(tl_stage20_value($task['day_no'] ?? null)); ?></strong></div><div><span>Proof required</span><strong><?php echo !empty($task['proof_required']) ? 'Yes' : 'No'; ?></strong></div><div><span>Duration</span><strong><?php echo htmlspecialchars(tl_stage20_value($task['expected_duration_minutes'] ?? null)); ?> min</strong></div><div><span>Updated</span><strong><?php echo htmlspecialchars(tl_stage20_value($task['updated_at'] ?? null)); ?></strong></div></div><div class="labs-stage15-proof-text"><span class="labs-eyebrow">Instructions</span><p><?php echo nl2br(htmlspecialchars(tl_stage20_value($task['instructions'] ?? null))); ?></p></div></article>
  <aside class="labs-card"><h2>Safety boundary</h2><div class="labs-stage13-boundary-grid labs-stage20-boundaries"><span>No task writes</span><span>No unlocks</span><span>No uploads</span><span>No rewards</span><span>No wallet changes</span><span>No SQL</span></div></aside>
</section>
<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Proofs</span><h2>Proof submissions for this task</h2></div></div><div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>Proof</th><th>Type</th><th>Status</th><th>Submitted</th><th>Inspect</th></tr></thead><tbody><?php foreach ($proofs as $proof): ?><tr><td><?php echo htmlspecialchars($proof['public_id'] ?? 'proof'); ?></td><td><?php echo htmlspecialchars(tl_stage20_label($proof['proof_type'] ?? 'text')); ?></td><td><span class="labs-pill"><?php echo htmlspecialchars(tl_stage20_label($proof['status'] ?? 'submitted')); ?></span></td><td><?php echo htmlspecialchars(tl_stage20_value($proof['submitted_at'] ?? $proof['created_at'] ?? null)); ?></td><td><a class="labs-btn" href="<?php echo labs_url('/admin/review-inspector.php' . (!empty($proof['public_id']) ? '?proof=' . urlencode((string)$proof['public_id']) : '')); ?>">Inspect</a></td></tr><?php endforeach; ?><?php if (!$proofs): ?><tr><td colspan="5">No proof rows found for this task yet.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="labs-flow-grid"><article class="labs-card"><h2>Participants</h2><div class="labs-panel-list labs-compact-list"><?php foreach ($participants as $participant): ?><div class="labs-panel-item"><span class="labs-mini-icon">P</span><div><strong><?php echo htmlspecialchars($participant['participant_label'] ?? ('Participant #' . ($participant['id'] ?? ''))); ?></strong><p><?php echo htmlspecialchars(tl_stage20_label($participant['status'] ?? 'active') . ' · ' . tl_stage20_value($participant['joined_at'] ?? null)); ?></p></div><a class="labs-btn" href="<?php echo labs_url('/admin/participant-inspector.php' . (!empty($participant['public_id']) ? '?participant=' . urlencode((string)$participant['public_id']) : '')); ?>">Open</a></div><?php endforeach; ?><?php if (!$participants): ?><div class="labs-panel-item"><span class="labs-mini-icon">—</span><div><strong>No participants</strong><p>No participant rows found.</p></div></div><?php endif; ?></div></article><aside class="labs-card"><h2>Timeline</h2><div class="labs-stage15-timeline"><?php foreach ($timeline as $item): ?><div><span><?php echo htmlspecialchars($item['type'] ?? 'event'); ?></span><strong><?php echo htmlspecialchars($item['label'] ?? 'Timeline item'); ?></strong><small><?php echo htmlspecialchars(tl_stage20_value($item['at'] ?? null)); ?></small></div><?php endforeach; ?></div></aside></section>
<?php labs_page_end(['section'=>'admin']); ?>
