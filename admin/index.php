<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
$admin = tl_stage200_admin_state();
$score = $admin['operations_score'];
$counts = $admin['flow']['counts'] ?? [];
labs_page_start(['title' => 'Admin Overview | Training Lab', 'section' => 'admin', 'active' => 'admin-overview']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-overview'); ?>
<?php if (function_exists('tl_stage800_render_merchant_account_bridge')) tl_stage800_render_merchant_account_bridge(); ?>
<?php if (function_exists('tl_stage760_render_merchant_operations_console')) tl_stage760_render_merchant_operations_console(); ?>


<?php if (function_exists('tl_stage680_render_operator_daily_rhythm')) tl_stage680_render_operator_daily_rhythm(); ?>
<?php if (function_exists('tl_stage640_render_operator_health_dashboard')) tl_stage640_render_operator_health_dashboard(); ?>

<?php if (function_exists('tl_stage520_render_admin_operations')) tl_stage520_render_admin_operations(); ?>
<?php if (function_exists('tl_stage560_render_review_reward_assurance')) tl_stage560_render_review_reward_assurance(); ?>

<?php if (function_exists('tl_stage600_render_operator_command_snapshot')) tl_stage600_render_operator_command_snapshot(); ?>


<section class="labs-page-title labs-stage200-title"><div><span class="labs-eyebrow">Admin overview</span><h1>The backend control layer for the Training Lab app.</h1><p class="labs-copy">A concise view of readiness, review load, reward lifecycle, routes, and core app health.</p></div><div class="labs-actions"><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/admin/command-center.php'); ?>">Command Center</a><a class="labs-btn" href="<?php echo labs_url('/api/training/ops-overview.php'); ?>">Ops JSON</a></div></section>
<section class="labs-kpis labs-stage200-kpis"><div class="labs-kpi"><span>Score</span><strong><?php echo (int)$score['score']; ?>/100</strong><small>operations readiness</small></div><div class="labs-kpi"><span>Routes</span><strong><?php echo (int)$admin['route_readiness']['score']; ?>%</strong><small><?php echo (int)$admin['route_readiness']['ready']; ?>/<?php echo (int)$admin['route_readiness']['total']; ?></small></div><div class="labs-kpi"><span>Proof Queue</span><strong><?php echo count($admin['pending_proofs'] ?? []); ?></strong><small>pending proof</small></div><div class="labs-kpi"><span>Rewards</span><strong><?php echo (int)($admin['reward_bridge']['counts']['total'] ?? 0); ?></strong><small>tracked rewards</small></div></section>
<section class="labs-stage121-core-grid"><a class="labs-stage121-core-link" href="<?php echo labs_url('/admin/command-center.php'); ?>"><strong>Command Center</strong><span>Operate the full backend flow.</span></a><a class="labs-stage121-core-link" href="<?php echo labs_url('/admin/review-workbench.php'); ?>"><strong>Review Workbench</strong><span>Handle proof decisions.</span></a><a class="labs-stage121-core-link" href="<?php echo labs_url('/admin/reward-bridge.php'); ?>"><strong>Reward Bridge</strong><span>Manage claim and sync lifecycle.</span></a><a class="labs-stage121-core-link" href="<?php echo labs_url('/admin/backend-readiness.php'); ?>"><strong>Backend Readiness</strong><span>Run QA and inspect route health.</span></a></section>
<section class="labs-safe-note">Admin Overview is a control surface for existing Training Lab tables. No reset, delete, payment, or wallet mutation actions are added.</section>
<?php labs_page_end(['section' => 'admin']); ?>
