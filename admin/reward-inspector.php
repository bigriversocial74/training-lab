<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
require_once __DIR__ . '/../includes/training-lab-stage34-service.php';
$rewardRef = isset($_GET['reward']) ? tl_stage20_clean_ref((string)$_GET['reward']) : null;
$inspector = tl_training_reward_inspector_summary($rewardRef);
$rule = $inspector['rule'] ?? [];
$summary = $inspector['summary'] ?? [];
$rules = $inspector['rules'] ?? [];
$events = $inspector['events'] ?? [];
$receipts = $inspector['receipts'] ?? [];
$reviews = $inspector['reviews'] ?? [];
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

labs_page_start(['title'=>'Reward Inspector | Training Lab','section'=>'admin','active'=>'admin-reward-inspector']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-reward-inspector'); ?>
<?php if (function_exists('tl_stage880_render_campaign_sync_health')) tl_stage880_render_campaign_sync_health(); ?>
<?php if (function_exists('tl_stage800_render_reward_inventory_board')) tl_stage800_render_reward_inventory_board(); ?>
<?php if (function_exists('tl_stage760_render_reward_package_builder')) tl_stage760_render_reward_package_builder((string)($_GET['campaign'] ?? '')); ?>

<?php if (function_exists('tl_stage640_render_reward_audit_assurance')) tl_stage640_render_reward_audit_assurance(); ?>

<?php if (function_exists('tl_stage600_render_reward_operations')) tl_stage600_render_reward_operations(); ?>


<section class="labs-page-title labs-stage20-title"><div><span class="labs-eyebrow">Reward inspector</span><h1>Training reward rules and simulated reward events are visible without issuing real Microgifter rewards.</h1><p class="labs-copy">This page separates Training Lab reward previews from the real wallet, claim, redeem, and Microgifter reward systems. Everything here is read-only visibility.</p></div><div class="labs-actions"><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/api/training/reward-inspector.php' . (!empty($rule['public_id']) ? '?reward=' . urlencode((string)$rule['public_id']) : '')); ?>">Reward JSON</a><a class="labs-btn" href="<?php echo labs_url('/admin/participant-inspector.php'); ?>">Participants</a></div></section>
<section class="labs-stage13-status-band"><div class="labs-stage13-score"><span>Preview value</span><strong><?php echo htmlspecialchars($summary['preview_value_display'] ?? '$0.00'); ?></strong><small>no wallet balance change</small></div><div class="labs-stage13-status-copy"><span class="labs-eyebrow">Reward rule</span><h2><?php echo htmlspecialchars($rule['rule_name'] ?? 'Reward Rule'); ?></h2><p><?php echo htmlspecialchars(($rule['campaign_title'] ?? 'Training Campaign') . ' · ' . ($rule['reward_label'] ?? 'Reward preview')); ?></p></div><div class="labs-stage13-status-actions"><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/admin/campaign-inspector.php' . (!empty($rule['campaign_slug']) ? '?campaign=' . urlencode((string)$rule['campaign_slug']) : '')); ?>">Campaign</a><a class="labs-btn" href="<?php echo labs_url('/admin/event-timeline.php'); ?>">Timeline</a></div></section>
<section class="labs-kpis labs-stage20-kpis"><div class="labs-kpi"><span class="labs-muted">Rules</span><strong><?php echo number_format((int)($summary['rule_count'] ?? 0)); ?></strong><small>campaign rules</small></div><div class="labs-kpi"><span class="labs-muted">Reward events</span><strong><?php echo number_format((int)($summary['reward_event_count'] ?? 0)); ?></strong><small>preview rows</small></div><div class="labs-kpi"><span class="labs-muted">Receipts</span><strong><?php echo number_format((int)($summary['receipt_count'] ?? 0)); ?></strong><small>read-only</small></div><div class="labs-kpi"><span class="labs-muted">Reviews</span><strong><?php echo number_format((int)($summary['review_count'] ?? 0)); ?></strong><small>context only</small></div></section>
<section class="labs-flow-grid"><article class="labs-card"><h2>Rule detail</h2><div class="labs-stage15-detail-grid"><div><span>Trigger</span><strong><?php echo htmlspecialchars(tl_stage20_label($rule['trigger_type'] ?? 'sequence_completed')); ?></strong></div><div><span>Threshold</span><strong><?php echo number_format((int)($rule['threshold_count'] ?? 0)); ?></strong></div><div><span>Reward type</span><strong><?php echo htmlspecialchars(tl_stage20_label($rule['reward_type'] ?? 'badge')); ?></strong></div><div><span>Value</span><strong><?php echo htmlspecialchars(tl_training_money_display((int)($rule['reward_value_cents'] ?? 0))); ?></strong></div><div><span>Status</span><strong><?php echo htmlspecialchars(tl_stage20_label($rule['status'] ?? 'active')); ?></strong></div><div><span>Updated</span><strong><?php echo htmlspecialchars(tl_stage20_value($rule['updated_at'] ?? null)); ?></strong></div></div><div class="labs-stage13-boundary-grid labs-stage20-boundaries"><span>No issuing</span><span>No wallet writes</span><span>No claim/redeem</span><span>No payments</span><span>No new SQL</span><span>Training-only preview</span></div></article><aside class="labs-card"><h2>Rule list</h2><div class="labs-panel-list labs-compact-list"><?php foreach ($rules as $row): ?><div class="labs-panel-item"><span class="labs-mini-icon">R</span><div><strong><?php echo htmlspecialchars($row['rule_name'] ?? 'Reward rule'); ?></strong><p><?php echo htmlspecialchars(tl_stage20_label($row['status'] ?? 'active') . ' · ' . tl_training_money_display((int)($row['reward_value_cents'] ?? 0))); ?></p></div></div><?php endforeach; ?></div></aside></section>
<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Reward events</span><h2>Eligibility and issue bridge preview</h2></div></div><div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>Event</th><th>Status</th><th>Value</th><th>Reason</th><th>Created</th></tr></thead><tbody><?php foreach ($events as $event): ?><tr><td><?php echo htmlspecialchars($event['public_id'] ?? 'reward-event'); ?></td><td><span class="labs-pill"><?php echo htmlspecialchars(tl_stage20_label($event['status'] ?? 'eligible')); ?></span></td><td><?php echo htmlspecialchars(tl_training_money_display((int)($event['value_cents'] ?? 0))); ?></td><td><?php echo htmlspecialchars(tl_stage20_value($event['eligibility_reason'] ?? null)); ?></td><td><?php echo htmlspecialchars(tl_stage20_value($event['created_at'] ?? null)); ?></td></tr><?php endforeach; ?><?php if (!$events): ?><tr><td colspan="5">No reward event rows found.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="labs-card"><h2>Reward timeline</h2><div class="labs-stage15-timeline"><?php foreach ($timeline as $item): ?><div><span><?php echo htmlspecialchars($item['type'] ?? 'event'); ?></span><strong><?php echo htmlspecialchars($item['label'] ?? 'Timeline item'); ?></strong><small><?php echo htmlspecialchars(tl_stage20_value($item['at'] ?? null)); ?></small></div><?php endforeach; ?></div></section>
<?php labs_page_end(['section'=>'admin']); ?>
