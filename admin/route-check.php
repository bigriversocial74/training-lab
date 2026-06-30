<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-stage34-service.php';

$status = tl_db_status_summary();
$ops = tl_training_ops_summary();
$expectedRootEntries = ['admin','api','app','assets','config','database','includes','labs','index.php','signin.php','signup.php'];
$rootDir = dirname(__DIR__);
$rootChecks = [];
foreach ($expectedRootEntries as $entry) {
    $rootChecks[$entry] = file_exists($rootDir . '/' . $entry);
}
$nestedWarnings = [
    'examples/labs/examples/labs' => is_dir($rootDir . '/examples/labs'),
    'contactform/labs' => is_dir($rootDir . '/contactform/labs'),
    'examples/training-labs/labs' => is_dir($rootDir . '/examples/training-labs/labs'),
];
$activeBase = labs_base_path();

labs_page_start(['title'=>'Route Check | Training Lab','section'=>'admin','active'=>'admin-route-check']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-route-check'); ?>

<section class="labs-page-title labs-stage12-title">
  <div>
    <span class="labs-eyebrow">Direct-extract route check</span>
    <h1>Package structure and links are now visible from the admin shell.</h1>
    <p class="labs-copy">This page verifies that the zip was extracted directly into the active Training Lab folder and that app/admin links resolve from the current base path.</p>
  </div>
  <a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/admin/index.php'); ?>">Admin Overview</a>
</section>

<section class="labs-kpis">
  <div class="labs-kpi"><span class="labs-muted">Detected base path</span><strong><?php echo htmlspecialchars($activeBase === '' ? '/' : $activeBase, ENT_QUOTES, 'UTF-8'); ?></strong><small>derived from current request path</small></div>
  <div class="labs-kpi"><span class="labs-muted">DB mode</span><strong><?php echo !empty($status['connected']) ? 'Database' : 'Fallback'; ?></strong><small><?php echo !empty($status['all_tables_present']) ? 'all tables present' : 'table check pending'; ?></small></div>
  <div class="labs-kpi"><span class="labs-muted">Route links</span><strong>Base-safe</strong><small>admin/app/api links use labs_url()</small></div>
  <div class="labs-kpi"><span class="labs-muted">SQL</span><strong>None</strong><small>no new SQL required</small></div>
</section>

<section class="labs-flow-grid">
  <article class="labs-card">
    <h2>Expected extract-root files</h2>
    <p class="labs-muted">These should appear directly inside the active Training Lab folder after upload and extract. No wrapper folder is expected.</p>
    <div class="labs-route-grid">
      <?php foreach ($rootChecks as $entry => $present): ?>
        <div class="labs-route-card <?php echo $present ? 'is-ready' : 'is-missing'; ?>">
          <span><?php echo htmlspecialchars($entry, ENT_QUOTES, 'UTF-8'); ?></span>
          <strong><?php echo $present ? 'Present' : 'Missing'; ?></strong>
        </div>
      <?php endforeach; ?>
    </div>
  </article>

  <aside class="labs-card">
    <h2>Nested-folder guard</h2>
    <p class="labs-muted">These warning checks help catch accidental duplicate structures before another stage is built on the wrong path.</p>
    <div class="labs-panel-list labs-compact-list">
      <?php foreach ($nestedWarnings as $label => $exists): ?>
        <div class="labs-panel-item">
          <span class="labs-mini-icon"><?php echo $exists ? '!' : '✓'; ?></span>
          <div><strong><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></strong><p><?php echo $exists ? 'Warning: nested duplicate folder detected.' : 'Not detected.'; ?></p></div>
        </div>
      <?php endforeach; ?>
    </div>
  </aside>
</section>

<section class="labs-card">
  <h2>Direct route test links</h2>
  <div class="labs-admin-health-grid">
    <div class="labs-health-card"><span>Admin overview</span><strong>UI</strong><small><a href="<?php echo labs_url('/admin/index.php'); ?>">Open admin overview</a></small></div>
    <div class="labs-health-card"><span>DB health</span><strong>UI</strong><small><a href="<?php echo labs_url('/admin/db-health.php'); ?>">Open DB health</a></small></div>
    <div class="labs-health-card"><span>Ops JSON</span><strong>API</strong><small><a href="<?php echo labs_url('/api/training/ops-overview.php'); ?>">Open ops payload</a></small></div>
    <div class="labs-health-card"><span>App dashboard</span><strong>UI</strong><small><a href="<?php echo labs_url('/app/index.php'); ?>">Open participant app</a></small></div>
    <div class="labs-health-card"><span>Campaigns</span><strong><?php echo (int)($ops['campaigns']['total'] ?? 0); ?></strong><small>read-only count</small></div>
    <div class="labs-health-card"><span>Tables ready</span><strong><?php echo (int)($ops['tables_ready'] ?? 0); ?>/<?php echo (int)($ops['tables_expected'] ?? 0); ?></strong><small>schema diagnostics</small></div>
  </div>
</section>
<?php labs_page_end(['section'=>'admin']); ?>
