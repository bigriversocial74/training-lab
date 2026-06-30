<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
require_once __DIR__ . '/../includes/training-lab-stage34-service.php';
$id = isset($_GET['id']) ? preg_replace('/[^a-z0-9\-]/i', '', $_GET['id']) : 'movement-5';
$campaign = tl_stage34_campaign($id);
$tasks = tl_stage34_tasks($campaign['id']);
labs_page_start(['title'=>$campaign['title'].' | Training Lab','section'=>'app','active'=>'app-campaigns']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('app-campaign-detail'); ?>
<?php if (function_exists('tl_stage800_render_reward_campaign_import')) tl_stage800_render_reward_campaign_import(); ?>
<?php if (function_exists('tl_stage880_render_campaign_sync_health')) tl_stage880_render_campaign_sync_health(); ?>
<?php if (function_exists('tl_stage800_render_assignment_preview')) tl_stage800_render_assignment_preview((string)($_GET['campaign'] ?? ($_GET['id'] ?? ''))); ?>
<?php if (function_exists('tl_stage760_render_reward_package_builder')) tl_stage760_render_reward_package_builder((string)($_GET['campaign'] ?? ($_GET['id'] ?? ''))); ?>
<?php if (function_exists('tl_stage760_render_merchant_sponsor_context')) tl_stage760_render_merchant_sponsor_context((string)($_GET['campaign'] ?? ($_GET['id'] ?? ''))); ?>


<?php if (function_exists('tl_stage720_render_challenge_template_selection')) tl_stage720_render_challenge_template_selection(); ?>
<?php if (function_exists('tl_stage640_render_campaign_data_quality')) tl_stage640_render_campaign_data_quality((string)($_GET['campaign'] ?? '')); ?>

<?php if (function_exists('tl_stage520_render_campaign_builder')) tl_stage520_render_campaign_builder((string)($_GET['campaign'] ?? '')); ?>
<?php if (function_exists('tl_stage560_render_campaign_publish_planner')) tl_stage560_render_campaign_publish_planner((string)($_GET['campaign'] ?? '')); ?>

<?php if (function_exists('tl_stage600_render_campaign_state_control')) tl_stage600_render_campaign_state_control((string)($_GET['campaign'] ?? ($_GET['id'] ?? ''))); ?>


<section class="labs-page-title">
  <div><span class="labs-eyebrow">Campaign detail</span><h1><?php echo htmlspecialchars($campaign['title']); ?></h1><p class="labs-copy"><?php echo htmlspecialchars($campaign['description']); ?></p></div>
  <a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/app/proof-upload.php'); ?>">Submit Demo Proof</a>
</section>
<section class="labs-kpis">
  <div class="labs-kpi"><span class="labs-muted">Owner</span><strong><?php echo htmlspecialchars($campaign['owner']); ?></strong><small>organization mapping later</small></div>
  <div class="labs-kpi"><span class="labs-muted">Audience</span><strong><?php echo htmlspecialchars($campaign['audience']); ?></strong><small>participant group</small></div>
  <div class="labs-kpi"><span class="labs-muted">Proof</span><strong data-demo-proof-status>Not submitted</strong><small>browser state</small></div>
  <div class="labs-kpi"><span class="labs-muted">Review</span><strong data-demo-review-status>Not submitted</strong><small>browser state</small></div>
</section>
<section class="labs-flow-grid">
  <article class="labs-card">
    <h2>Task sequence</h2>
    <div class="labs-panel-list">
      <?php foreach ($tasks as $task): ?>
      <div class="labs-panel-item"><span class="labs-mini-icon"><?php echo (int)$task['day']; ?></span><div><strong><?php echo htmlspecialchars($task['title']); ?></strong><p><?php echo htmlspecialchars($task['status']); ?> · <?php echo htmlspecialchars($task['proof']); ?></p></div><span class="labs-pill"><?php echo htmlspecialchars($task['proof']); ?></span></div>
      <?php endforeach; ?>
    </div>
  </article>
  <aside class="labs-card">
    <h2>Training Lab integration contract</h2>
    <p class="labs-muted">The page consumes the same service data as the JSON API. This makes the UI ready for database replacement later without changing the page structure again.</p>
    <div class="labs-progress-track"><div class="labs-progress-fill" data-demo-progress-fill style="width:<?php echo (int)$campaign['progress']; ?>%"></div></div><br>
    <span class="labs-pill" data-demo-progress-label><?php echo (int)$campaign['progress']; ?>% complete</span>
    <br><br><a class="labs-btn" href="<?php echo labs_url('/api/training/campaign-detail.php?id=' . urlencode((string)$campaign['id'])); ?>">View JSON Contract</a>
  </aside>
</section>
<?php labs_page_end(['section'=>'app']); ?>
