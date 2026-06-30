<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
$state = tl_stage50_reporting_state();
$counts = $state['summary']['counts'] ?? [];
labs_page_start(['title' => 'Reporting Center | Training Lab', 'section' => 'admin', 'active' => 'admin-reporting-center']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-reporting-center'); ?>
<?php if (function_exists('tl_stage880_render_campaign_sync_health')) tl_stage880_render_campaign_sync_health(); ?>
<?php if (function_exists('tl_stage800_render_reward_inventory_board')) tl_stage800_render_reward_inventory_board(); ?>
<?php if (function_exists('tl_stage800_render_reward_campaign_import')) tl_stage800_render_reward_campaign_import(); ?>
<?php if (function_exists('tl_stage760_render_merchant_operations_console')) tl_stage760_render_merchant_operations_console(); ?>


<?php if (function_exists('tl_stage720_render_training_content_library')) tl_stage720_render_training_content_library(); ?>
<?php if (function_exists('tl_stage720_render_admin_training_quality_console')) tl_stage720_render_admin_training_quality_console(); ?>

<?php if (function_exists('tl_stage680_render_admin_communication_console')) tl_stage680_render_admin_communication_console(); ?>
<?php if (function_exists('tl_stage680_render_operator_daily_rhythm')) tl_stage680_render_operator_daily_rhythm(); ?>
<?php if (function_exists('tl_stage640_render_operator_health_dashboard')) tl_stage640_render_operator_health_dashboard(); ?>

<?php if (function_exists('tl_stage520_render_launch_snapshot')) tl_stage520_render_launch_snapshot(); ?>
<?php if (function_exists('tl_stage560_render_reporting_ledger')) tl_stage560_render_reporting_ledger(); ?>

<?php if (function_exists('tl_stage600_render_operator_command_snapshot')) tl_stage600_render_operator_command_snapshot(); ?>


<section class="labs-page-title labs-stage50-title"><div><span class="labs-eyebrow">Reporting center</span><h1>Review live Training Lab activity and create report snapshots.</h1><p class="labs-copy">Snapshots are stored as Training Lab events only. No report files are written to the server.</p></div><div class="labs-actions"><a class="labs-btn" href="<?php echo labs_url('/admin/command-center.php'); ?>">Demo Ops</a><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/app/flow-board.php'); ?>">Flow Board</a></div></section>
<section class="labs-kpis labs-stage50-kpis"><?php foreach (['campaigns','participants','proofs','reviews','receipts','reward_events','events'] as $key): ?><div class="labs-kpi"><span class="labs-muted"><?php echo labs_e(str_replace('_',' ',ucwords($key,'_'))); ?></span><strong><?php echo (int)($counts[$key] ?? 0); ?></strong><small>Training Lab table</small></div><?php endforeach; ?></section>
<section class="labs-flow-grid"><article class="labs-card"><h2>Create report snapshot</h2><form action="<?php echo labs_url('/admin/action-result.php'); ?>" method="post" class="labs-stage30-form"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="create_report_snapshot"><label>Actor user ID<input type="number" name="actor_user_id" value="1" min="1"></label><label>Snapshot label<input type="text" name="snapshot_label" value="Training Lab Functional Demo Snapshot"></label><button class="labs-btn labs-btn-primary" type="submit">Create Snapshot Event</button></form></article><aside class="labs-card"><h2>Recent snapshots</h2><div class="labs-stage50-event-list"><?php foreach ($state['snapshots'] as $event): $meta=json_decode((string)($event['metadata_json'] ?? '{}'), true) ?: []; ?><div><strong><?php echo labs_e((string)($meta['snapshot_label'] ?? 'Report snapshot')); ?></strong><p>Mode: <?php echo labs_e((string)($meta['mode'] ?? 'unknown')); ?></p><small><?php echo labs_e((string)$event['created_at']); ?></small></div><?php endforeach; if (!$state['snapshots']): ?><p class="labs-muted">No report snapshots yet.</p><?php endif; ?></div></aside></section>
<section class="labs-card"><h2>Reporting layers now connected</h2><div class="labs-stage13-link-grid"><a href="<?php echo labs_url('/admin/reporting-center.php'); ?>"><span>Metrics</span><strong>Read</strong></a><a href="<?php echo labs_url('/admin/qa-center.php'); ?>"><span>Build Review</span><strong>QA</strong></a><a href="<?php echo labs_url('/admin/event-timeline.php'); ?>"><span>Timeline</span><strong>Events</strong></a><a href="<?php echo labs_url('/admin/qa-center.php'); ?>"><span>QA Center</span><strong>Health</strong></a></div></section>
<section class="labs-safe-note">Reporting Center creates only training_report_snapshot events. It does not write files or export private data.</section>
<?php labs_page_end(['section' => 'admin']); ?>
