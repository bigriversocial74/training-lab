<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
$state = tl_stage200_workflow_state((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0)));
$summary = $state['summary'] ?? [];
$counts = $summary['counts'] ?? [];
$next = $state['next_step'] ?? null;
$campaign = $state['campaign'] ?? null;
$userId = (int)($state['actor_user_id'] ?? 1);
$builds = tl_stage200_summary()['builds'];
$stage240Summary = tl_stage240_summary();
labs_page_start(['title' => 'Training Lab App | Dashboard', 'section' => 'app', 'active' => 'app-dashboard']);
?>
<?php if (function_exists('tl_stage880_render_identity_matching')) tl_stage880_render_identity_matching(max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('app-dashboard'); ?>
<?php if (function_exists('tl_stage800_render_merchant_account_bridge')) tl_stage800_render_merchant_account_bridge(); ?>
<?php if (function_exists('tl_stage840_render_customer_account_bridge')) tl_stage840_render_customer_account_bridge(max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage840_render_award_inbox')) tl_stage840_render_award_inbox(max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage760_render_merchant_sponsor_context')) tl_stage760_render_merchant_sponsor_context((string)($_GET['campaign'] ?? '')); ?>

<?php if (function_exists('tl_stage520_render_account_entry')) tl_stage520_render_account_entry(); ?>
<?php if (function_exists('tl_stage520_render_participant_mission')) tl_stage520_render_participant_mission((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage560_render_account_command')) tl_stage560_render_account_command(); ?>
<?php if (function_exists('tl_stage560_render_mission_runbook')) tl_stage560_render_mission_runbook((string)($_GET['campaign'] ?? ''), max(0, (int)($_GET['user_id'] ?? 0))); ?>


<section class="labs-page-title labs-stage200-title">
  <div>
    <span class="labs-eyebrow">Training Lab app</span>
    <h1>Build, run, review, and reward one connected training flow.</h1>
    <p class="labs-copy">This pass turns the cleaned core pages into an app workflow: campaign setup, participant mission control, task/proof completion, admin review, reward claim lifecycle, and QA snapshots.</p>
  </div>
  <div class="labs-actions">
    <a class="labs-btn" href="<?php echo labs_url('/app/participant-portal.php?user_id=' . $userId); ?>">Mission Control</a>
    <a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/app/task-runner.php?user_id=' . $userId); ?>">Run Tasks</a>
    <a class="labs-btn" href="<?php echo labs_url('/admin/command-center.php'); ?>">Admin Ops</a>
  </div>
</section>
<section class="labs-kpis labs-stage200-kpis">
  <div class="labs-kpi"><span>Progress</span><strong><?php echo (int)$state['progress_percent']; ?>%</strong><small>selected participant</small></div>
  <div class="labs-kpi"><span>Campaigns</span><strong><?php echo (int)($counts['campaigns'] ?? 0); ?></strong><small>training programs</small></div>
  <div class="labs-kpi"><span>Tasks</span><strong><?php echo (int)($counts['tasks'] ?? 0); ?></strong><small>configured actions</small></div>
  <div class="labs-kpi"><span>Pending Review</span><strong><?php echo (int)($counts['pending_proofs'] ?? 0); ?></strong><small>proof queue</small></div>
  <div class="labs-kpi"><span>Claimable</span><strong><?php echo (int)($state['rewards']['counts']['claimable'] ?? 0); ?></strong><small>rewards</small></div>
</section>
<section class="labs-flow-grid labs-stage200-grid">
  <article class="labs-card labs-stage200-primary">
    <div class="labs-card-headline">
      <div><span class="labs-eyebrow">Next best action</span><h2><?php echo $next ? labs_e((string)$next['label']) : 'Flow ready'; ?></h2></div>
      <?php if ($next): ?><a class="labs-btn labs-btn-primary" href="<?php echo labs_url((string)$next['href']); ?>">Open</a><?php endif; ?>
    </div>
    <p class="labs-copy"><?php echo $next ? labs_e((string)$next['detail']) : 'No blockers found in the core workflow.'; ?></p>
    <div class="labs-progress-meter labs-stage200-meter"><span style="width:<?php echo (int)$state['progress_percent']; ?>%"></span></div>
    <div class="labs-stage200-step-grid">
      <?php foreach ($state['steps'] as $step): ?>
        <a class="labs-stage200-step is-<?php echo labs_e((string)$step['status']); ?>" href="<?php echo labs_url((string)$step['href']); ?>">
          <span><?php echo labs_e((string)$step['status']); ?></span>
          <strong><?php echo labs_e((string)$step['label']); ?></strong>
          <small><?php echo labs_e((string)$step['detail']); ?></small>
        </a>
      <?php endforeach; ?>
    </div>
  </article>
  <aside class="labs-card">
    <h2>Stacked builds in this package</h2>
    <ol class="labs-stage200-build-list">
      <?php foreach ($builds as $build): ?><li><?php echo labs_e((string)$build); ?></li><?php endforeach; ?>
    </ol>
    <form action="<?php echo labs_url('/app/action-result.php'); ?>" method="post" class="labs-stage30-form">
      <input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="create_workflow_snapshot"><input type="hidden" name="user_id" value="<?php echo $userId; ?>">
      <button class="labs-btn" type="submit">Save Workflow Snapshot</button>
    </form>
  </aside>
</section>
<section class="labs-card">
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Selected campaign</span><h2><?php echo $campaign ? labs_e((string)$campaign['title']) : 'No campaign selected'; ?></h2></div><a class="labs-btn" href="<?php echo labs_url('/app/campaign-builder.php'); ?>">Campaign Builder</a></div>
  <div class="labs-stage200-brief-grid">
    <div><strong><?php echo labs_e((string)($campaign['status'] ?? 'setup_needed')); ?></strong><span>Status</span></div>
    <div><strong><?php echo labs_e((string)($state['campaign_ref'] ?: 'none')); ?></strong><span>Campaign ref</span></div>
    <div><strong><?php echo count($state['blockers'] ?? []); ?></strong><span>Open blockers</span></div>
    <div><strong><?php echo labs_e((string)($state['next_task']['title'] ?? 'No task loaded')); ?></strong><span>Next task</span></div>
  </div>
</section>
<section class="labs-safe-note">Stage 161–200 adds product workflow depth without creating another page factory. Existing core pages now share one workflow state engine and training-only action log.</section>

<section class="labs-card labs-stage240-panel">
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 201–240</span><h2>Current stacked app builds</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/product-readiness.php'); ?>">Product Readiness API</a></div>
  <ol class="labs-stage200-build-list"><?php foreach ($stage240Summary['builds'] as $build): ?><li><?php echo labs_e((string)$build); ?></li><?php endforeach; ?></ol>
</section>


<section class="labs-card labs-stage280-panel">
  <?php $stage280 = tl_stage280_summary(); ?>
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 241–280</span><h2>Release candidate app depth</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/release-candidate.php'); ?>">Release API</a></div>
  <div class="labs-kpis labs-stage200-kpis"><div class="labs-kpi"><span>Release Score</span><strong><?php echo (int)$stage280['release_candidate']['score']; ?>/100</strong><small><?php echo !empty($stage280['release_candidate']['accepted']) ? 'accepted' : 'needs checks'; ?></small></div><div class="labs-kpi"><span>Account</span><strong><?php echo (int)$stage280['account_ledger']['score']; ?>/100</strong><small>identity ledger</small></div><div class="labs-kpi"><span>Rewards</span><strong><?php echo (int)$stage280['reward_assurance']['score']; ?>/100</strong><small>claim assurance</small></div></div>
  <ol class="labs-stage200-build-list"><?php foreach ($stage280['builds'] as $build): ?><li><?php echo labs_e((string)$build); ?></li><?php endforeach; ?></ol>
</section>

<?php labs_page_end(['section' => 'app']); ?>
