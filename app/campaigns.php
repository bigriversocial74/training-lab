<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
require_once __DIR__ . '/../includes/training-lab-stage34-service.php';
$campaigns = tl_stage34_campaigns();
$dash = tl_stage34_dashboard();
labs_page_start(['title'=>'Campaigns | Training Lab','section'=>'app','active'=>'app-campaigns']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('app-campaigns'); ?>
<?php if (function_exists('tl_stage760_render_merchant_sponsor_context')) tl_stage760_render_merchant_sponsor_context((string)($_GET['campaign'] ?? '')); ?>

<?php if (function_exists('tl_stage640_render_campaign_data_quality')) tl_stage640_render_campaign_data_quality((string)($_GET['campaign'] ?? '')); ?>

<?php if (function_exists('tl_stage520_render_campaign_builder')) tl_stage520_render_campaign_builder((string)($_GET['campaign'] ?? '')); ?>
<?php if (function_exists('tl_stage560_render_campaign_publish_planner')) tl_stage560_render_campaign_publish_planner((string)($_GET['campaign'] ?? '')); ?>

<?php if (function_exists('tl_stage600_render_campaign_state_control')) tl_stage600_render_campaign_state_control((string)($_GET['campaign'] ?? '')); ?>


<section class="labs-page-title">
  <div><span class="labs-eyebrow">Training campaigns</span><h1>Campaigns connected to the Training Lab workflow.</h1><p class="labs-copy">Review active Training Lab campaigns, open details, and inspect linked tasks, participants, proof submissions, reviews, receipts, and simulated reward events.</p></div>
  <a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/app/campaign-detail.php?id=movement-5'); ?>">Open Active Campaign</a>
</section>
<section class="labs-kpis">
  <div class="labs-kpi"><span class="labs-muted">Campaigns</span><strong><?php echo count($campaigns); ?></strong><small>read-only seed</small></div>
  <div class="labs-kpi"><span class="labs-muted">Active</span><strong><?php echo $dash['active_campaigns']; ?></strong><small>in demo</small></div>
  <div class="labs-kpi"><span class="labs-muted">Actions</span><strong data-demo-completed-actions><?php echo $dash['completed_actions']; ?></strong><small>of <?php echo $dash['total_actions']; ?></small></div>
  <div class="labs-kpi"><span class="labs-muted">Reward</span><strong data-demo-reward-status><?php echo htmlspecialchars($dash['reward_status']); ?></strong><small>browser state can update</small></div>
</section>
<section class="labs-flow-grid">
  <?php foreach ($campaigns as $campaign): ?>
  <article class="labs-card labs-campaign-card">
    <span class="labs-pill"><?php echo htmlspecialchars($campaign['status']); ?></span>
    <h2><?php echo htmlspecialchars($campaign['title']); ?></h2>
    <p class="labs-muted"><?php echo htmlspecialchars($campaign['description']); ?></p>
    <div class="labs-progress-track"><div class="labs-progress-fill" style="width:<?php echo (int)$campaign['progress']; ?>%"></div></div>
    <div class="labs-mini-row"><span>Progress</span><strong><?php echo (int)$campaign['completed_actions']; ?>/<?php echo (int)$campaign['total_actions']; ?></strong></div>
    <div class="labs-mini-row"><span>Reward</span><strong><?php echo htmlspecialchars($campaign['reward']); ?></strong></div>
    <div class="labs-mini-row"><span>Due</span><strong><?php echo htmlspecialchars($campaign['due']); ?></strong></div>
    <br>
    <a class="labs-btn" href="<?php echo labs_url('/app/campaign-detail.php?id=' . urlencode((string)$campaign['id'])); ?>">View Campaign</a>
  </article>
  <?php endforeach; ?>
</section>
<section class="labs-safe-note">Campaign list uses the standalone Training Lab service layer and Training Lab tables only.</section>
<?php labs_page_end(['section'=>'app']); ?>
