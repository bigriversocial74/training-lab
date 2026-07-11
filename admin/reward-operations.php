<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
require_once __DIR__ . '/../includes/training-lab-microgifter-rewards.php';
require_once __DIR__ . '/../includes/training-lab-stage890-reward-handoff-outbox.php';
require_once __DIR__ . '/../includes/training-lab-stage891-reward-handoff-recovery.php';
require_once __DIR__ . '/../includes/training-lab-stage891-terminal-failure-panel.php';
require_once __DIR__ . '/../includes/training-lab-stage892-scheduled-worker.php';
require_once __DIR__ . '/../includes/training-lab-stage894-reconciliation-bootstrap.php';
require_once __DIR__ . '/../includes/training-lab-stage895-integration-acceptance.php';
require_once __DIR__ . '/../includes/training-lab-stage896-limited-reward-pilot.php';
require_once __DIR__ . '/../includes/training-lab-stage897-controlled-batch-rollout.php';
require_once __DIR__ . '/../includes/training-lab-stage898-worker-canary-monitoring.php';
require_once __DIR__ . '/../includes/training-lab-stage899-limited-scheduled-processing.php';
$page=['title'=>'Advanced Reward Operations | Training Lab','section'=>'admin','active'=>'admin-reward-operations','required_role'=>'admin'];
tl_product_require_page_access($page);
labs_page_start($page);
?>
<section class="labs-product-hero"><article class="labs-product-hero-main"><span class="labs-product-kicker">Advanced reward operations</span><h1>Operate the guarded Microgifter delivery pipeline.</h1><p>This administrator-only console preserves recovery, reconciliation, acceptance, pilot, batch, canary, and limited scheduler controls.</p></article><aside class="labs-product-next"><div><span>Safety boundary</span><h2>Production gates remain explicit</h2><p>Merchant fulfillment reporting cannot activate delivery or bypass signed-account and idempotency controls.</p></div><a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/admin/reward-bridge.php'),ENT_QUOTES,'UTF-8'); ?>">Merchant Fulfillment</a></aside></section>
<?php if(function_exists('tl_stage880_render_adapter_configuration_center')) tl_stage880_render_adapter_configuration_center(); ?>
<?php if(function_exists('tl_stage880_render_campaign_sync_health')) tl_stage880_render_campaign_sync_health(); ?>
<?php if(function_exists('tl_stage890_render_admin_panel')) tl_stage890_render_admin_panel(); ?>
<?php if(function_exists('tl_stage891_render_admin_panel')) tl_stage891_render_admin_panel(); ?>
<?php if(function_exists('tl_stage891_render_terminal_failure_panel')) tl_stage891_render_terminal_failure_panel(); ?>
<?php if(function_exists('tl_stage892_render_admin_panel')) tl_stage892_render_admin_panel(); ?>
<?php if(function_exists('tl_stage894_render_admin_panel')) tl_stage894_render_admin_panel(); ?>
<?php if(function_exists('tl_stage895_render_reward_bridge_panel')) tl_stage895_render_reward_bridge_panel(); ?>
<?php if(function_exists('tl_stage896_render_reward_bridge_panel')) tl_stage896_render_reward_bridge_panel(); ?>
<?php if(function_exists('tl_stage897_render_reward_bridge_panel')) tl_stage897_render_reward_bridge_panel(); ?>
<?php if(function_exists('tl_stage898_render_reward_bridge_panel')) tl_stage898_render_reward_bridge_panel(); ?>
<?php if(function_exists('tl_stage899_render_reward_bridge_panel')) tl_stage899_render_reward_bridge_panel(); ?>
<?php if(function_exists('tl_stage893_render_admin_panel_guarded')) tl_stage893_render_admin_panel_guarded(); ?>
<section class="labs-safe-note">This page does not weaken any Stage 890–899 production gate. Live delivery still requires every existing configuration, account-link, signing, idempotency, lease, reconciliation, and rollout condition.</section>
<?php labs_page_end(['section'=>'admin']); ?>
