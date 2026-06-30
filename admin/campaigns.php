<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
require_once __DIR__ . '/../includes/training-lab-stage34-service.php';
$campaigns = tl_training_recent_campaign_snapshots(25);
labs_page_start(['title'=>'Admin Campaigns | Training Lab','section'=>'admin','active'=>'admin-campaigns']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-campaigns'); ?>
<?php if (function_exists('tl_stage640_render_campaign_data_quality')) tl_stage640_render_campaign_data_quality((string)($_GET['campaign'] ?? '')); ?>

<?php if (function_exists('tl_stage600_render_campaign_state_control')) tl_stage600_render_campaign_state_control((string)($_GET['campaign'] ?? '')); ?>


<section class="labs-page-title"><div><span class="labs-eyebrow">Campaign management preview</span><h1>Campaign records connect to the core campaign inspector.</h1><p class="labs-copy">This admin table links into campaign drilldown for tasks, participants, proofs, reviews, rewards, receipts, and events.</p></div><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/admin/campaign-inspector.php'); ?>">Open Inspector</a></section>
<section class="labs-card"><div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>Campaign</th><th>Status</th><th>Type</th><th>Visibility</th><th>Target Actions</th><th>Updated</th><th>Inspect</th></tr></thead><tbody><?php foreach($campaigns as $campaign): $ref = (string)($campaign['id'] ?? $campaign['slug'] ?? $campaign['public_id'] ?? ''); ?><tr><td><?php echo htmlspecialchars($campaign['title'] ?? 'Training Campaign'); ?></td><td><span class="labs-pill"><?php echo htmlspecialchars($campaign['status'] ?? 'draft'); ?></span></td><td><?php echo htmlspecialchars($campaign['type'] ?? 'training'); ?></td><td><?php echo htmlspecialchars($campaign['visibility'] ?? 'private'); ?></td><td><?php echo number_format((int)($campaign['target_action_count'] ?? 0)); ?></td><td><?php echo htmlspecialchars((string)($campaign['updated_at'] ?? 'None yet')); ?></td><td><a class="labs-btn" href="<?php echo labs_url('/admin/campaign-inspector.php' . ($ref !== '' ? '?campaign=' . urlencode($ref) : '')); ?>">Inspect</a></td></tr><?php endforeach; ?><?php if(!$campaigns): ?><tr><td colspan="7">No campaigns found yet.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="labs-safe-note">Campaign/review inspection is read-only. No campaign edits, approval writes, reward issuing, wallet balance changes, upload processing, or new SQL.</section>
<?php labs_page_end(['section'=>'admin']); ?>
