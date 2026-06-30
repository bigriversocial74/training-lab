<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-stage34-service.php';
$filters = ['event_type' => $_GET['event_type'] ?? '', 'subject_type' => $_GET['subject_type'] ?? '', 'limit' => $_GET['limit'] ?? 60];
$timeline = tl_training_event_timeline_summary($filters);
$events = $timeline['events'] ?? [];
$breakdowns = $timeline['breakdowns'] ?? [];
$summary = $timeline['summary'] ?? [];

function tl_stage20_value($value): string
{
    if ($value === null || $value === '') return 'None yet';
    return (string)$value;
}
function tl_stage20_label($value): string
{
    return ucwords(str_replace('_', ' ', tl_stage20_value($value)));
}

labs_page_start(['title'=>'Event Timeline | Training Lab','section'=>'admin','active'=>'admin-event-timeline']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-event-timeline'); ?>

<section class="labs-page-title labs-stage20-title"><div><span class="labs-eyebrow">Event timeline</span><h1>Training Lab audit events are now centralized in one viewer.</h1><p class="labs-copy">Filter and inspect event rows across campaigns, tasks, participants, proofs, reviews, receipts, rewards, streaks, and system activity. This viewer performs no event writes.</p></div><div class="labs-actions"><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/api/training/event-timeline.php'); ?>">Timeline JSON</a><a class="labs-btn" href="<?php echo labs_url('/admin/qa-center.php'); ?>">QA Center</a></div></section>
<section class="labs-kpis labs-stage20-kpis"><div class="labs-kpi"><span class="labs-muted">Events shown</span><strong><?php echo number_format((int)($summary['event_count'] ?? 0)); ?></strong><small>read-only feed</small></div><div class="labs-kpi"><span class="labs-muted">Subject types</span><strong><?php echo number_format((int)($summary['subject_type_count'] ?? 0)); ?></strong><small>breakdown</small></div><div class="labs-kpi"><span class="labs-muted">Event types</span><strong><?php echo number_format((int)($summary['event_type_count'] ?? 0)); ?></strong><small>breakdown</small></div><div class="labs-kpi"><span class="labs-muted">Latest</span><strong><?php echo htmlspecialchars(tl_stage20_value($summary['latest_activity_at'] ?? null)); ?></strong><small>activity timestamp</small></div></section>
<section class="labs-card"><form method="get" class="labs-stage20-filter"><label>Subject type<select name="subject_type"><option value="">All subjects</option><?php foreach (['campaign','task','participant','proof','review','receipt','reward_rule','reward_event','streak','system'] as $type): ?><option value="<?php echo htmlspecialchars($type); ?>" <?php echo (($timeline['filters']['subject_type'] ?? '') === $type) ? 'selected' : ''; ?>><?php echo htmlspecialchars(tl_stage20_label($type)); ?></option><?php endforeach; ?></select></label><label>Event type<input type="text" name="event_type" value="<?php echo htmlspecialchars($timeline['filters']['event_type'] ?? ''); ?>" placeholder="optional event_type"></label><button class="labs-btn labs-btn-primary" type="submit">Apply Filters</button><a class="labs-btn" href="<?php echo labs_url('/admin/event-timeline.php'); ?>">Reset</a></form></section>
<section class="labs-flow-grid"><article class="labs-card"><h2>Event feed</h2><div class="labs-stage15-timeline"><?php foreach ($events as $event): ?><div><span><?php echo htmlspecialchars($event['event_type'] ?? 'event'); ?></span><strong><?php echo htmlspecialchars(tl_stage20_label($event['subject_type'] ?? 'subject') . ' #' . tl_stage20_value($event['subject_id'] ?? null)); ?></strong><small><?php echo htmlspecialchars(tl_stage20_value($event['created_at'] ?? null)); ?></small></div><?php endforeach; ?></div></article><aside class="labs-card"><h2>Breakdowns</h2><h3>By subject</h3><div class="labs-panel-list labs-compact-list"><?php foreach (($breakdowns['subject_type'] ?? []) as $key => $count): ?><div class="labs-panel-item"><span class="labs-mini-icon">S</span><div><strong><?php echo htmlspecialchars(tl_stage20_label($key)); ?></strong><p><?php echo number_format((int)$count); ?> events</p></div></div><?php endforeach; ?></div><h3>By event type</h3><div class="labs-panel-list labs-compact-list"><?php foreach (($breakdowns['event_type'] ?? []) as $key => $count): ?><div class="labs-panel-item"><span class="labs-mini-icon">E</span><div><strong><?php echo htmlspecialchars($key); ?></strong><p><?php echo number_format((int)$count); ?> events</p></div></div><?php endforeach; ?></div></aside></section>
<?php labs_page_end(['section'=>'admin']); ?>
