<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
require_once __DIR__ . '/../includes/training-lab-stage34-service.php';
$participantRef = isset($_GET['participant']) ? tl_stage20_clean_ref((string)$_GET['participant']) : null;
$inspector = tl_training_participant_inspector_summary($participantRef);
$participant = $inspector['participant'] ?? [];
$summary = $inspector['summary'] ?? [];
$proofs = $inspector['proofs'] ?? [];
$reviews = $inspector['reviews'] ?? [];
$receipts = $inspector['receipts'] ?? [];
$rewardEvents = $inspector['reward_events'] ?? [];
$streaks = $inspector['streaks'] ?? [];
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

labs_page_start(['title'=>'Participant Inspector | Training Lab','section'=>'admin','active'=>'admin-participant-inspector']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-participant-inspector'); ?>
<?php if (function_exists('tl_stage880_render_identity_matching')) tl_stage880_render_identity_matching(max(0, (int)($_GET['user_id'] ?? 0))); ?>

<?php if (function_exists('tl_stage720_render_admin_training_quality_console')) tl_stage720_render_admin_training_quality_console(); ?>

<?php if (function_exists('tl_stage680_render_admin_communication_console')) tl_stage680_render_admin_communication_console(); ?>
<?php if (function_exists('tl_stage680_render_mission_followup_logic')) tl_stage680_render_mission_followup_logic(); ?>
<?php if (function_exists('tl_stage640_render_participant_data_quality')) tl_stage640_render_participant_data_quality((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>

<?php if (function_exists('tl_stage600_render_participant_timeline')) tl_stage600_render_participant_timeline((string)($_GET['campaign'] ?? ''), (int)($_GET['user_id'] ?? 0)); ?>


<section class="labs-page-title labs-stage20-title"><div><span class="labs-eyebrow">Participant inspector</span><h1>Participant progress is visible without profile or wallet writes.</h1><p class="labs-copy">Review participation history, submitted proofs, review outcomes, receipts, reward previews, streak status, and timeline context for one Training Lab participant.</p></div><div class="labs-actions"><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/api/training/participant-inspector.php' . (!empty($participant['public_id']) ? '?participant=' . urlencode((string)$participant['public_id']) : '')); ?>">Participant JSON</a><a class="labs-btn" href="<?php echo labs_url('/admin/review-queue.php'); ?>">Review Queue</a></div></section>
<section class="labs-stage13-status-band"><div class="labs-stage13-score"><span>Reward preview</span><strong><?php echo htmlspecialchars($summary['preview_reward_value_display'] ?? '$0.00'); ?></strong><small>no wallet writes</small></div><div class="labs-stage13-status-copy"><span class="labs-eyebrow">Current participant</span><h2><?php echo htmlspecialchars($participant['participant_label'] ?? 'Participant'); ?></h2><p><?php echo htmlspecialchars(($participant['campaign_title'] ?? 'Training Campaign') . ' · ' . tl_stage20_label($participant['status'] ?? 'active')); ?></p></div><div class="labs-stage13-status-actions"><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/admin/campaign-inspector.php' . (!empty($participant['campaign_slug']) ? '?campaign=' . urlencode((string)$participant['campaign_slug']) : '')); ?>">Campaign</a><a class="labs-btn" href="<?php echo labs_url('/admin/reward-inspector.php'); ?>">Rewards</a></div></section>
<section class="labs-kpis labs-stage20-kpis"><div class="labs-kpi"><span class="labs-muted">Proofs</span><strong><?php echo number_format((int)($summary['proof_count'] ?? 0)); ?></strong><small>submitted rows</small></div><div class="labs-kpi"><span class="labs-muted">Reviews</span><strong><?php echo number_format((int)($summary['review_count'] ?? 0)); ?></strong><small>decision rows</small></div><div class="labs-kpi"><span class="labs-muted">Receipts</span><strong><?php echo number_format((int)($summary['receipt_count'] ?? 0)); ?></strong><small>read-only</small></div><div class="labs-kpi"><span class="labs-muted">Streaks</span><strong><?php echo number_format((int)($summary['streak_count'] ?? 0)); ?></strong><small>progress summary</small></div></section>
<section class="labs-flow-grid"><article class="labs-card"><h2>Participant detail</h2><div class="labs-stage15-detail-grid"><div><span>Public ID</span><strong><?php echo htmlspecialchars(tl_stage20_value($participant['public_id'] ?? null)); ?></strong></div><div><span>User ID</span><strong><?php echo htmlspecialchars(tl_stage20_value($participant['user_id'] ?? null)); ?></strong></div><div><span>Status</span><strong><?php echo htmlspecialchars(tl_stage20_label($participant['status'] ?? 'active')); ?></strong></div><div><span>Joined</span><strong><?php echo htmlspecialchars(tl_stage20_value($participant['joined_at'] ?? null)); ?></strong></div><div><span>Completed</span><strong><?php echo htmlspecialchars(tl_stage20_value($participant['completed_at'] ?? null)); ?></strong></div><div><span>Updated</span><strong><?php echo htmlspecialchars(tl_stage20_value($participant['updated_at'] ?? null)); ?></strong></div></div></article><aside class="labs-card"><h2>Streak status</h2><div class="labs-panel-list labs-compact-list"><?php foreach ($streaks as $streak): ?><div class="labs-panel-item"><span class="labs-mini-icon">↟</span><div><strong><?php echo number_format((int)($streak['current_streak_days'] ?? 0)); ?> current days</strong><p><?php echo number_format((int)($streak['completed_action_count'] ?? 0)); ?> completed actions · longest <?php echo number_format((int)($streak['longest_streak_days'] ?? 0)); ?></p></div></div><?php endforeach; ?><?php if (!$streaks): ?><div class="labs-panel-item"><span class="labs-mini-icon">—</span><div><strong>No streak rows</strong><p>No progress summary found yet.</p></div></div><?php endif; ?></div></aside></section>
<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Submitted proofs</span><h2>Participant proof history</h2></div></div><div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>Proof</th><th>Type</th><th>Status</th><th>Submitted</th><th>Inspect</th></tr></thead><tbody><?php foreach ($proofs as $proof): ?><tr><td><?php echo htmlspecialchars($proof['public_id'] ?? 'proof'); ?></td><td><?php echo htmlspecialchars(tl_stage20_label($proof['proof_type'] ?? 'text')); ?></td><td><span class="labs-pill"><?php echo htmlspecialchars(tl_stage20_label($proof['status'] ?? 'submitted')); ?></span></td><td><?php echo htmlspecialchars(tl_stage20_value($proof['submitted_at'] ?? null)); ?></td><td><a class="labs-btn" href="<?php echo labs_url('/admin/review-inspector.php' . (!empty($proof['public_id']) ? '?proof=' . urlencode((string)$proof['public_id']) : '')); ?>">Inspect</a></td></tr><?php endforeach; ?><?php if (!$proofs): ?><tr><td colspan="5">No proofs found for this participant yet.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="labs-flow-grid"><article class="labs-card"><h2>Reward previews</h2><div class="labs-panel-list labs-compact-list"><?php foreach ($rewardEvents as $event): ?><div class="labs-panel-item"><span class="labs-mini-icon">$</span><div><strong><?php echo htmlspecialchars(tl_stage20_label($event['status'] ?? 'eligible')); ?></strong><p><?php echo htmlspecialchars(tl_stage20_value($event['eligibility_reason'] ?? 'Reward preview')); ?></p></div><span class="labs-pill"><?php echo htmlspecialchars(tl_training_money_display((int)($event['value_cents'] ?? 0))); ?></span></div><?php endforeach; ?><?php if (!$rewardEvents): ?><div class="labs-panel-item"><span class="labs-mini-icon">—</span><div><strong>No reward events</strong><p>No preview event is linked yet.</p></div></div><?php endif; ?></div></article><aside class="labs-card"><h2>Timeline</h2><div class="labs-stage15-timeline"><?php foreach ($timeline as $item): ?><div><span><?php echo htmlspecialchars($item['type'] ?? 'event'); ?></span><strong><?php echo htmlspecialchars($item['label'] ?? 'Timeline item'); ?></strong><small><?php echo htmlspecialchars(tl_stage20_value($item['at'] ?? null)); ?></small></div><?php endforeach; ?></div></aside></section>
<?php labs_page_end(['section'=>'admin']); ?>
