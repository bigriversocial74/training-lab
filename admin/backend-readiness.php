<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
$admin = tl_stage200_admin_state();
$qa = tl_stage200_run_core_qa(['user_id' => tl_stage200_actor_id()]);
$route = $admin['route_readiness'];
$score = $admin['operations_score'];
$stage240Ready = tl_stage240_product_readiness();
labs_page_start(['title' => 'Backend Readiness | Training Lab', 'section' => 'admin', 'active' => 'admin-backend-readiness']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-backend-readiness'); ?>
<?php if (function_exists('tl_stage880_render_adapter_configuration_center')) tl_stage880_render_adapter_configuration_center(); ?>
<?php if (function_exists('tl_stage880_render_identity_matching')) tl_stage880_render_identity_matching(max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage880_render_campaign_sync_health')) tl_stage880_render_campaign_sync_health(); ?>
<?php if (function_exists('tl_stage880_render_award_handoff_queue')) tl_stage880_render_award_handoff_queue(max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage880_render_adapter_sync_api_gate')) tl_stage880_render_adapter_sync_api_gate(max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage800_render_merchant_account_bridge')) tl_stage800_render_merchant_account_bridge(); ?>
<?php if (function_exists('tl_stage800_render_reward_campaign_import')) tl_stage800_render_reward_campaign_import(); ?>
<?php if (function_exists('tl_stage840_render_user_award_api_gate')) tl_stage840_render_user_award_api_gate(max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage760_render_merchant_operations_console')) tl_stage760_render_merchant_operations_console(); ?>
<?php if (function_exists('tl_stage760_render_merchant_sponsor_context')) tl_stage760_render_merchant_sponsor_context((string)($_GET['campaign'] ?? '')); ?>


<?php if (function_exists('tl_stage720_render_admin_training_quality_console')) tl_stage720_render_admin_training_quality_console(); ?>

<?php if (function_exists('tl_stage680_render_operator_daily_rhythm')) tl_stage680_render_operator_daily_rhythm(); ?>
<?php if (function_exists('tl_stage640_render_operator_health_dashboard')) tl_stage640_render_operator_health_dashboard(); ?>

<?php if (function_exists('tl_stage520_render_launch_snapshot')) tl_stage520_render_launch_snapshot(); ?>
<?php if (function_exists('tl_stage560_render_reporting_ledger')) tl_stage560_render_reporting_ledger(); ?>

<?php if (function_exists('tl_stage600_render_operator_command_snapshot')) tl_stage600_render_operator_command_snapshot(); ?>


<section class="labs-page-title labs-stage200-title"><div><span class="labs-eyebrow">Backend readiness</span><h1>QA the real Training Lab app flow.</h1><p class="labs-copy">This console checks routes, tables, account context, participant flow, reward lifecycle, and safety boundaries after the stacked Stage 161–200 app build.</p></div><div class="labs-actions"><a class="labs-btn" href="<?php echo labs_url('/api/training/core-workflow-qa.php'); ?>">QA API</a><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/admin/command-center.php'); ?>">Command Center</a></div></section>
<section class="labs-kpis labs-stage200-kpis"><div class="labs-kpi"><span>QA Score</span><strong><?php echo (int)$qa['score']; ?>/100</strong><small><?php echo !empty($qa['accepted']) ? 'accepted' : 'needs review'; ?></small></div><div class="labs-kpi"><span>Ops Score</span><strong><?php echo (int)$score['score']; ?>/100</strong><small><?php echo (int)$score['passed']; ?>/<?php echo (int)$score['total']; ?></small></div><div class="labs-kpi"><span>Routes</span><strong><?php echo (int)$route['score']; ?>%</strong><small><?php echo (int)$route['ready']; ?>/<?php echo (int)$route['total']; ?></small></div></section>
<section class="labs-flow-grid labs-stage200-grid"><article class="labs-card"><h2>Core QA checks</h2><div class="labs-stage130-check-grid"><?php foreach ($qa['checks'] as $key => $ok): ?><div class="<?php echo $ok ? 'is-ok' : 'is-warn'; ?>"><span><?php echo labs_e(ucwords(str_replace('_',' ',$key))); ?></span><strong><?php echo $ok ? 'OK' : 'Check'; ?></strong></div><?php endforeach; ?></div></article><aside class="labs-card"><h2>Run and save</h2><form action="<?php echo labs_url('/admin/action-result.php'); ?>" method="post" class="labs-stage30-form"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="run_core_workflow_qa"><input type="hidden" name="log_event" value="1"><button class="labs-btn labs-btn-primary" type="submit">Run QA Event</button></form><form action="<?php echo labs_url('/admin/action-result.php'); ?>" method="post" class="labs-stage30-form"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="create_workflow_snapshot"><button class="labs-btn" type="submit">Create Snapshot</button></form></aside></section>
<section class="labs-card"><h2>Route readiness</h2><div class="labs-stage130-check-grid"><?php foreach ($route['items'] as $key => $item): ?><div class="<?php echo $item['exists'] ? 'is-ok' : 'is-warn'; ?>"><span><?php echo labs_e((string)$item['route']); ?></span><strong><?php echo $item['exists'] ? 'Ready' : 'Missing'; ?></strong></div><?php endforeach; ?></div></section>
<?php if (function_exists('tl_design_render_asset_mosaic')) tl_design_render_asset_mosaic(); ?>


<section class="labs-card labs-stage380-panel">
  <?php $stage380 = function_exists('tl_stage380_design_summary') ? tl_stage380_design_summary() : []; $family = $stage380['runtime_page_family_audit'] ?? []; $source = $stage380['template_source_audit'] ?? []; ?>
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 361–380</span><h2>Logged-in Design System Gate</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/template-fidelity.php'); ?>">Template Fidelity API</a></div>
  <div class="labs-li-template-quality-grid">
    <article><span>Design Score</span><strong><?php echo (int)($stage380['score'] ?? 0); ?>/100</strong></article>
    <article><span>Contexts Checked</span><strong><?php echo (int)($family['contexts_checked'] ?? 0); ?></strong></article>
    <article><span>Runtime Issues</span><strong><?php echo (int)($family['issue_count'] ?? 0); ?></strong></article>
    <article><span>Source Issues</span><strong><?php echo (int)($source['issue_count'] ?? 0); ?></strong></article>
  </div>
  <p class="labs-copy">This gate confirms the app/admin template precedent is now shared, runtime-bound, mobile-polished, and still uses the same Microgifter account context.</p>
</section>



<section class="labs-card labs-stage400-panel">
  <?php $stage400 = function_exists('tl_stage400_design_summary') ? tl_stage400_design_summary() : []; $experience = $stage400['experience_audit'] ?? []; $routes400 = $experience['priority_routes'] ?? []; ?>
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 381–400</span><h2>Guided Experience Readiness Gate</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/experience-readiness.php'); ?>">Experience API</a></div>
  <div class="labs-stage400-readiness-grid">
    <article><span>Experience Score</span><strong><?php echo (int)($stage400['score'] ?? 0); ?>/100</strong><small><?php echo !empty($stage400['accepted']) ? 'accepted' : 'needs review'; ?></small></article>
    <article><span>Guided Deck</span><strong><?php echo (int)($experience['issue_count'] ?? 0); ?></strong><small>open issues</small></article>
    <article><span>Route Groups</span><strong><?php echo count($routes400); ?></strong><small>app, admin, API</small></article>
    <article><span>Shared Account</span><strong>Simple</strong><small>labs.microgifter.com + microgifter.com</small></article>
  </div>
  <?php if (!empty($routes400)): ?>
  <div class="labs-stage400-route-stack">
    <?php foreach ($routes400 as $group => $routes): ?>
      <div><code><?php echo labs_e((string)$group); ?>: <?php echo labs_e(implode(', ', (array)$routes)); ?></code><strong>tracked</strong></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>



<section class="labs-card labs-stage420-panel">
  <?php $stage420 = function_exists('tl_stage420_ux_summary') ? tl_stage420_ux_summary() : []; $matrix420 = $stage420['decision_matrix'] ?? []; $audit420 = $stage420['ux_audit'] ?? []; $lanes420 = $matrix420['lanes'] ?? []; ?>
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 401–420</span><h2>Operational Cockpit + UX Command Gate</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/ux-command.php'); ?>">UX Command API</a></div>
  <div class="labs-stage420-command-grid">
    <article><span>UX Score</span><strong><?php echo (int)($stage420['score'] ?? 0); ?>/100</strong><small><?php echo !empty($stage420['accepted']) ? 'accepted' : 'needs review'; ?></small></article>
    <article><span>Decision Matrix</span><strong><?php echo (int)($matrix420['score'] ?? 0); ?>/100</strong><small>participant/admin routing</small></article>
    <article><span>Route Contract</span><strong><?php echo (int)($audit420['issue_count'] ?? 0); ?></strong><small>open issues</small></article>
    <article><span>Account Model</span><strong>Shared</strong><small>labs.microgifter.com + microgifter.com</small></article>
  </div>
  <?php if (!empty($lanes420)): ?>
    <div class="labs-stage420-decision-stack">
      <?php foreach ($lanes420 as $key => $lane): ?>
        <article><div><span><?php echo labs_e(ucwords(str_replace('_',' ', (string)$key))); ?></span><strong><?php echo labs_e((string)($lane['decision'] ?? 'ready')); ?></strong><small><?php echo labs_e((string)($lane['owner'] ?? 'owner')); ?> · <?php echo labs_e((string)($lane['next_route'] ?? '/')); ?></small></div><code><?php echo (int)($lane['score'] ?? 0); ?>/100</code></article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>



<section class="labs-card labs-stage440-panel">
  <?php $stage440 = function_exists('tl_stage440_release_summary') ? tl_stage440_release_summary() : []; $release440 = $stage440['route_observability_audit'] ?? []; $contract440 = $stage440['release_contract'] ?? []; ?>
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 421–440</span><h2>Production Readiness + Release Command Gate</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/release-command.php'); ?>">Release Command API</a></div>
  <div class="labs-stage440-release-grid">
    <article><span>Release Score</span><strong><?php echo (int)($stage440['score'] ?? 0); ?>/100</strong><small><?php echo !empty($stage440['accepted']) ? 'accepted' : 'needs review'; ?></small></article>
    <article><span>Route Issues</span><strong><?php echo (int)($release440['issue_count'] ?? 0); ?></strong><small>contract and observability</small></article>
    <article><span>Repo Target</span><strong>training-lab</strong><small><?php echo labs_e((string)($contract440['repository_baseline']['expected_repo'] ?? 'standalone repo')); ?></small></article>
    <article><span>SQL Boundary</span><strong>No SQL</strong><small>existing tables only</small></article>
  </div>
  <?php if (!empty($contract440['primary_api_routes'])): ?>
    <div class="labs-stage440-contract-stack">
      <?php foreach (['primary_public_routes','primary_app_routes','primary_admin_routes','primary_api_routes'] as $bucket): ?>
        <div><code><?php echo labs_e($bucket); ?>: <?php echo labs_e(implode(', ', (array)($contract440[$bucket] ?? []))); ?></code><strong>tracked</strong></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>



<section class="labs-card labs-stage460-panel">
  <?php $stage460 = function_exists('tl_stage460_deployment_summary') ? tl_stage460_deployment_summary() : []; $deploy460 = $stage460['deployment_audit'] ?? []; $contract460 = $stage460['deployment_contract'] ?? []; ?>
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 441–460</span><h2>Deployment Handoff + Standalone Repo Gate</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/deployment-handoff.php'); ?>">Deployment Handoff API</a></div>
  <div class="labs-stage460-deploy-grid">
    <article><span>Deploy Score</span><strong><?php echo (int)($stage460['score'] ?? 0); ?>/100</strong><small><?php echo !empty($stage460['accepted']) ? 'accepted' : 'needs review'; ?></small></article>
    <article><span>Handoff Issues</span><strong><?php echo (int)($deploy460['issue_count'] ?? 0); ?></strong><small>root, config, image, API checks</small></article>
    <article><span>Repo Target</span><strong>training-lab</strong><small><?php echo labs_e((string)($contract460['repo'] ?? 'bigriversocial74/training-lab')); ?></small></article>
    <article><span>Config Boundary</span><strong>Preserved</strong><small>live config stays in place</small></article>
  </div>
  <?php if (!empty($contract460['root_must_contain'])): ?>
    <div class="labs-stage460-contract-stack">
      <div><code>root: <?php echo labs_e(implode(', ', (array)$contract460['root_must_contain'])); ?></code><strong>direct extract</strong></div>
      <div><code>config: <?php echo labs_e(implode(', ', (array)$contract460['config_files_preserved'])); ?></code><strong>preserved</strong></div>
      <div><code>branch: <?php echo labs_e((string)($contract460['recommended_next_branch'] ?? 'stage-441-480-deployment-acceptance')); ?></code><strong>recommended</strong></div>
    </div>
  <?php endif; ?>
</section>

<section class="labs-card labs-stage480-panel">
  <?php $stage480 = function_exists('tl_stage480_acceptance_summary') ? tl_stage480_acceptance_summary() : []; $matrix480 = $stage480['acceptance_matrix'] ?? []; $lanes480 = $matrix480['lanes'] ?? []; $pkg480 = $stage480['package_audit'] ?? []; ?>
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 461–480</span><h2>Operator Acceptance + Launch Checklist Gate</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/acceptance-suite.php'); ?>">Acceptance Suite API</a></div>
  <div class="labs-stage480-acceptance-grid">
    <article><span>Acceptance Score</span><strong><?php echo (int)($stage480['score'] ?? 0); ?>/100</strong><small><?php echo !empty($stage480['accepted']) ? 'accepted' : 'needs review'; ?></small></article>
    <article><span>Lane Issues</span><strong><?php echo (int)($matrix480['issue_count'] ?? 0); ?></strong><small>public, app, admin, API lanes</small></article>
    <article><span>Package Issues</span><strong><?php echo (int)($pkg480['issue_count'] ?? 0); ?></strong><small>source and readiness markers</small></article>
    <article><span>Launch Safety</span><strong>No SQL</strong><small>no unsafe production actions</small></article>
  </div>
  <?php if (!empty($lanes480)): ?>
    <div class="labs-stage480-lane-stack">
      <?php foreach ($lanes480 as $key => $lane): ?>
        <article><div><span><?php echo labs_e(ucwords(str_replace('_',' ', (string)$key))); ?></span><strong><?php echo labs_e((string)($lane['expect'] ?? 'accepted')); ?></strong><small><?php echo labs_e((string)($lane['owner'] ?? 'owner')); ?></small></div><code><?php echo (int)($lane['score'] ?? 0); ?>/100</code></article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="labs-safe-note">Readiness QA logs a Training Lab event when submitted. It does not change config, reset data, delete data, or call external services.</section>

<section class="labs-card labs-stage240-panel">
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Build 10</span><h2>Product Readiness Self-Test</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/product-readiness.php'); ?>">Readiness API</a></div>
  <div class="labs-kpis labs-stage200-kpis"><div class="labs-kpi"><span>Product Score</span><strong><?php echo (int)$stage240Ready['score']; ?>/100</strong><small><?php echo !empty($stage240Ready['accepted']) ? 'accepted' : 'needs checks'; ?></small></div><div class="labs-kpi"><span>Campaign Ops</span><strong><?php echo (int)$stage240Ready['campaign_ops']['score']; ?>/100</strong><small>plan/task depth</small></div><div class="labs-kpi"><span>Review SLA</span><strong><?php echo (int)$stage240Ready['review_sla']['score']; ?>/100</strong><small>queue health</small></div></div>
  <div class="labs-stage130-check-grid"><?php foreach ($stage240Ready['checks'] as $key => $ok): ?><div class="<?php echo $ok ? 'is-ok' : 'is-warn'; ?>"><span><?php echo labs_e(ucwords(str_replace('_',' ',$key))); ?></span><strong><?php echo $ok ? 'OK' : 'Check'; ?></strong></div><?php endforeach; ?></div>
  <form action="<?php echo labs_url('/admin/action-result.php'); ?>" method="post" class="labs-stage30-form"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="run_product_self_test"><button class="labs-btn labs-btn-primary" type="submit">Run Product Self-Test</button></form>
</section>


<section class="labs-card labs-stage280-panel">
  <?php $stage280Release = tl_stage280_release_candidate(); ?>
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Build 15</span><h2>Release Candidate QA Pack</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/release-candidate.php'); ?>">Release API</a></div>
  <div class="labs-kpis labs-stage200-kpis"><div class="labs-kpi"><span>Release Score</span><strong><?php echo (int)$stage280Release['score']; ?>/100</strong><small><?php echo !empty($stage280Release['accepted']) ? 'accepted' : 'needs checks'; ?></small></div><div class="labs-kpi"><span>Routes</span><strong><?php echo (int)$stage280Release['routes']['ready']; ?>/<?php echo (int)$stage280Release['routes']['total']; ?></strong><small>core/API</small></div><div class="labs-kpi"><span>Tables</span><strong><?php echo (int)$stage280Release['tables']['ready']; ?>/<?php echo (int)$stage280Release['tables']['total']; ?></strong><small>Training Lab</small></div></div>
  <form action="<?php echo labs_url('/admin/action-result.php'); ?>" method="post" class="labs-stage30-form"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="run_release_candidate_qa"><button class="labs-btn labs-btn-primary" type="submit">Save Release QA Snapshot</button></form>
</section>


<section class="labs-card labs-design-readiness-card">
  <?php $designHealth = tl_design_assets_health(); ?>
  <div class="labs-card-headline"><div><span class="labs-eyebrow">Design assets</span><h2>Design Asset Readiness</h2></div><a class="labs-btn" href="<?php echo labs_url('/api/training/design-assets.php'); ?>">Design API</a></div>
  <div class="labs-kpis labs-stage200-kpis"><div class="labs-kpi"><span>Asset Score</span><strong><?php echo (int)$designHealth['score']; ?>/100</strong><small><?php echo !empty($designHealth['accepted']) ? 'all files present' : 'missing assets'; ?></small></div><div class="labs-kpi"><span>Images</span><strong><?php echo (int)$designHealth['present']; ?>/<?php echo (int)$designHealth['total']; ?></strong><small>registered</small></div><div class="labs-kpi"><span>Groups</span><strong><?php echo count($designHealth['groups']); ?></strong><small>admin/app/icons/marketing</small></div></div>
</section>

<?php labs_page_end(['section' => 'admin']); ?>
