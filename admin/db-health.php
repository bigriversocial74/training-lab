<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-stage34-service.php';

$status = tl_db_status_summary();
$config = $status['config'] ?? [];
$rowCounts = $status['row_counts'] ?? [];
$tables = $status['tables'] ?? [];
$missing = $status['missing_tables'] ?? [];
$tableHealth = tl_training_table_diagnostics();
$ops = tl_training_ops_summary();
$latestEvents = tl_training_latest_events(5);
$totalRows = (int)($ops['total_training_rows'] ?? 0);
$readyTables = (int)($ops['tables_ready'] ?? 0);
$expectedTables = (int)($ops['tables_expected'] ?? count($tables));

function tl_admin_count(array $group, string $key): int
{
    return (int)($group[$key] ?? $group[strtolower($key)] ?? 0);
}

function tl_admin_money_from_cents(int $cents): string
{
    return '$' . number_format($cents / 100, 2);
}

labs_page_start(['title'=>'DB Health | Training Lab','section'=>'admin','active'=>'admin-db-health']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-db-health'); ?>

<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Database diagnostics</span>
    <h1>Database health, ops visibility, and direct-extract route confidence.</h1>
    <p class="labs-copy">This page checks the current examples/labs configuration, Training Lab table health, schema readiness, route-safe links, and live read-only counts without writing to the database.</p>
  </div>
  <a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/api/training/ops-overview.php'); ?>">View Ops JSON</a>
</section>

<section class="labs-kpis">
  <div class="labs-kpi"><span class="labs-muted">Config</span><strong><?php echo !empty($status['config_ready']) ? 'Ready' : 'Check'; ?></strong><small><?php echo htmlspecialchars($config['source'] ?? 'examples/labs/labs/config.php'); ?></small></div>
  <div class="labs-kpi"><span class="labs-muted">Connection</span><strong><?php echo !empty($status['connected']) ? 'Connected' : 'Fallback'; ?></strong><small><?php echo !empty($status['connected']) ? 'database mode' : 'demo fallback'; ?></small></div>
  <div class="labs-kpi"><span class="labs-muted">Tables</span><strong><?php echo $readyTables; ?>/<?php echo $expectedTables; ?></strong><small><?php echo empty($missing) ? 'schemas ready' : 'missing table(s)'; ?></small></div>
  <div class="labs-kpi"><span class="labs-muted">Training rows</span><strong><?php echo number_format($totalRows); ?></strong><small>read-only aggregate</small></div>
</section>

<section class="labs-flow-grid">
  <article class="labs-card">
    <h2>Read-only ops snapshot</h2>
    <div class="labs-admin-health-grid">
      <div class="labs-health-card"><span>Campaigns</span><strong><?php echo (int)($ops['campaigns']['total'] ?? 0); ?></strong><small>Active: <?php echo tl_admin_count($ops['campaigns']['status_counts'] ?? [], 'active'); ?> · Draft: <?php echo tl_admin_count($ops['campaigns']['status_counts'] ?? [], 'draft'); ?></small></div>
      <div class="labs-health-card"><span>Tasks</span><strong><?php echo (int)($ops['tasks']['total'] ?? 0); ?></strong><small>Proof required: <?php echo (int)($ops['tasks']['proof_required'] ?? 0); ?></small></div>
      <div class="labs-health-card"><span>Participants</span><strong><?php echo (int)($ops['participants']['total'] ?? 0); ?></strong><small>Active: <?php echo tl_admin_count($ops['participants']['status_counts'] ?? [], 'active'); ?> · Completed: <?php echo tl_admin_count($ops['participants']['status_counts'] ?? [], 'completed'); ?></small></div>
      <div class="labs-health-card"><span>Proof queue</span><strong><?php echo (int)($ops['proof_submissions']['total'] ?? 0); ?></strong><small>Submitted: <?php echo tl_admin_count($ops['proof_submissions']['status_counts'] ?? [], 'submitted'); ?> · In review: <?php echo tl_admin_count($ops['proof_submissions']['status_counts'] ?? [], 'in_review'); ?></small></div>
      <div class="labs-health-card"><span>Reviews</span><strong><?php echo (int)($ops['reviews']['total'] ?? 0); ?></strong><small>Approved: <?php echo tl_admin_count($ops['reviews']['decision_counts'] ?? [], 'approved'); ?> · Rejected: <?php echo tl_admin_count($ops['reviews']['decision_counts'] ?? [], 'rejected'); ?></small></div>
      <div class="labs-health-card"><span>Reward bridge</span><strong><?php echo (int)($ops['reward_events']['total'] ?? 0); ?></strong><small>Preview value: <?php echo tl_admin_money_from_cents((int)($ops['reward_events']['preview_value_cents'] ?? 0)); ?></small></div>
    </div>
  </article>

  <aside class="labs-card">
    <h2>Database boundary</h2>
    <p class="labs-muted">This screen is read-only. It does not process uploads, payments, wallet writes, Microgifter reward issuing, claims, redemptions, or independent auth gates.</p>
    <div class="labs-panel-list labs-compact-list">
      <div class="labs-panel-item"><span class="labs-mini-icon">✓</span><div><strong>No auth gate added</strong><p>Active admin/app pages remain ungated for this build.</p></div></div>
      <div class="labs-panel-item"><span class="labs-mini-icon">✓</span><div><strong>No SQL required</strong><p>Uses the existing imported Training Lab tables only.</p></div></div>
      <div class="labs-panel-item"><span class="labs-mini-icon">✓</span><div><strong>Config untouched</strong><p>Reads the current labs/config.php path without moving files.</p></div></div>
    </div>
  </aside>
</section>

<section class="labs-flow-grid">
  <article class="labs-card">
    <h2>Config check</h2>
    <div class="labs-table-wrap">
      <table class="labs-table">
        <tbody>
          <tr><th>Expected path</th><td><?php echo htmlspecialchars($config['expected_path'] ?? ''); ?></td></tr>
          <tr><th>File exists</th><td><?php echo !empty($config['file_exists']) ? 'Yes' : 'No'; ?></td></tr>
          <tr><th>Loaded</th><td><?php echo !empty($config['loaded']) ? 'Yes' : 'No'; ?></td></tr>
          <tr><th>Host present</th><td><?php echo !empty($config['host_present']) ? 'Yes' : 'No'; ?></td></tr>
          <tr><th>Database present</th><td><?php echo !empty($config['database_name_present']) ? 'Yes' : 'No'; ?></td></tr>
          <tr><th>Username present</th><td><?php echo !empty($config['username_present']) ? 'Yes' : 'No'; ?></td></tr>
          <tr><th>Password present</th><td><?php echo !empty($config['password_present']) ? 'Yes' : 'No'; ?></td></tr>
          <?php if (!empty($config['error'])): ?><tr><th>Error</th><td><?php echo htmlspecialchars($config['error']); ?></td></tr><?php endif; ?>
          <?php if (!empty($status['connection_error'])): ?><tr><th>Connection error</th><td><?php echo htmlspecialchars($status['connection_error']); ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </article>

  <aside class="labs-card">
    <h2>Latest training events</h2>
    <?php if ($latestEvents): ?>
      <div class="labs-panel-list labs-compact-list">
        <?php foreach ($latestEvents as $event): ?>
          <div class="labs-panel-item">
            <span class="labs-mini-icon">•</span>
            <div><strong><?php echo htmlspecialchars($event['event_type'] ?? 'event'); ?></strong><p><?php echo htmlspecialchars(($event['subject_type'] ?? 'system') . ' · ' . ($event['created_at'] ?? 'not dated')); ?></p></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="labs-muted">No database events found yet. The event log is available once Training Lab actions create read-only audit rows.</p>
    <?php endif; ?>
  </aside>
</section>

<section class="labs-card">
  <h2>Training table health</h2>
  <div class="labs-table-wrap">
    <table class="labs-table">
      <thead><tr><th>Table</th><th>Status</th><th>Rows</th><th>Schema</th><th>Missing columns</th><th>Last activity</th></tr></thead>
      <tbody>
        <?php foreach ($tables as $table => $exists): $health = $tableHealth[$table] ?? []; $missingColumns = $health['missing_columns'] ?? []; ?>
          <tr>
            <td><?php echo htmlspecialchars($table); ?></td>
            <td><span class="labs-pill"><?php echo $exists ? 'Present' : 'Missing'; ?></span></td>
            <td><?php echo $exists && isset($rowCounts[$table]) ? number_format((int)$rowCounts[$table]) : '—'; ?></td>
            <td><span class="labs-pill"><?php echo !empty($health['schema_ready']) ? 'Ready' : 'Check'; ?></span><small class="labs-muted"> <?php echo (int)($health['actual_column_count'] ?? 0); ?>/<?php echo (int)($health['expected_column_count'] ?? 0); ?></small></td>
            <td><?php echo empty($missingColumns) ? '—' : htmlspecialchars(implode(', ', $missingColumns)); ?></td>
            <td><?php echo htmlspecialchars($health['last_activity_at'] ?? '—'); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php labs_page_end(['section'=>'admin']); ?>
