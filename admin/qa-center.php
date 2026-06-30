<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-stage34-service.php';
$qa = tl_training_qa_center_summary();
$checks = $qa['checks'] ?? [];
$pageStatus = $qa['page_status'] ?? [];
$rootStatus = $qa['root_status'] ?? [];
$authFindings = $qa['auth_gate_findings'] ?? [];
$reviewLoop = $qa['review_loop'] ?? [];

function tl_stage20_value($value): string
{
    if ($value === null || $value === '') return 'None yet';
    return (string)$value;
}
function tl_stage20_label($value): string
{
    return ucwords(str_replace('_', ' ', tl_stage20_value($value)));
}

labs_page_start(['title'=>'QA Center | Training Lab','section'=>'admin','active'=>'admin-qa-center']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-qa-center'); ?>

<section class="labs-page-title labs-stage20-title"><div><span class="labs-eyebrow">QA and build readiness center</span><h1>The core Training Lab package is checked before the next build.</h1><p class="labs-copy">This page verifies package structure, route files, preserved configs, table diagnostics, active-page auth-gate status, safe boundaries, and the internal code review loop for this build.</p></div><div class="labs-actions"><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/api/training/qa-center.php'); ?>">QA JSON</a><a class="labs-btn" href="<?php echo labs_url('/admin/command-center.php'); ?>">Command Center</a></div></section>
<section class="labs-stage13-status-band"><div class="labs-stage13-score"><span>Final score</span><strong><?php echo (int)($reviewLoop['final_score'] ?? $qa['score'] ?? 0); ?>%</strong><small><?php echo (int)($qa['checks_passed'] ?? 0); ?>/<?php echo (int)($qa['checks_total'] ?? 0); ?> QA checks</small></div><div class="labs-stage13-status-copy"><span class="labs-eyebrow">Review loop</span><h2>First pass <?php echo (int)($reviewLoop['first_pass_score'] ?? 0); ?>% → final <?php echo (int)($reviewLoop['final_score'] ?? 0); ?>%</h2><p>Issues were reviewed, fixes applied, PHP syntax checked, render smoke-tested, and repacked as a direct-extract full script.</p></div><div class="labs-stage13-status-actions"><a class="labs-btn labs-btn-primary" href="<?php echo labs_url('/admin/route-check.php'); ?>">Route Check</a><a class="labs-btn" href="<?php echo labs_url('/api/training/ops-overview.php'); ?>">Ops JSON</a></div></section>
<section class="labs-flow-grid"><article class="labs-card"><h2>QA checks</h2><div class="labs-admin-health-grid"><?php foreach ($checks as $key => $ok): ?><div class="labs-health-card"><span><?php echo htmlspecialchars(tl_stage20_label($key)); ?></span><strong><?php echo $ok ? 'Pass' : 'Check'; ?></strong><small><?php echo $ok ? 'ready' : 'needs review'; ?></small></div><?php endforeach; ?></div></article><aside class="labs-card"><h2>Fixes applied after review</h2><div class="labs-panel-list labs-compact-list"><?php foreach (($reviewLoop['fixes_applied'] ?? []) as $fix): ?><div class="labs-panel-item"><span class="labs-mini-icon">✓</span><div><strong>Applied</strong><p><?php echo htmlspecialchars($fix); ?></p></div></div><?php endforeach; ?></div></aside></section>
<section class="labs-flow-grid"><article class="labs-card"><h2>First-pass findings</h2><div class="labs-panel-list labs-compact-list"><?php foreach (($reviewLoop['first_pass_findings'] ?? []) as $finding): ?><div class="labs-panel-item"><span class="labs-mini-icon">!</span><div><strong>Finding</strong><p><?php echo htmlspecialchars($finding); ?></p></div></div><?php endforeach; ?></div></article><aside class="labs-card"><h2>Safe boundaries</h2><div class="labs-stage13-boundary-grid labs-stage20-boundaries"><span>No auth gates</span><span>No uploads</span><span>No payments</span><span>No wallet writes</span><span>No reward issuing</span><span>No claim/redeem</span><span>No duplicate auth</span><span>No SQL</span></div></aside></section>
<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Inspector route files</span><h2>Admin/API availability</h2></div></div><div class="labs-admin-health-grid"><?php foreach ($pageStatus as $path => $ok): ?><div class="labs-health-card"><span><?php echo htmlspecialchars($path); ?></span><strong><?php echo $ok ? 'Present' : 'Missing'; ?></strong><small>route file</small></div><?php endforeach; ?></div></section>
<section class="labs-flow-grid"><article class="labs-card"><h2>Extract-root files</h2><div class="labs-admin-health-grid"><?php foreach ($rootStatus as $path => $ok): ?><div class="labs-health-card"><span><?php echo htmlspecialchars($path); ?></span><strong><?php echo $ok ? 'Present' : 'Missing'; ?></strong><small>direct extract root</small></div><?php endforeach; ?></div></article><aside class="labs-card"><h2>Active page auth-gate check</h2><div class="labs-panel-list labs-compact-list"><?php foreach ($authFindings as $path => $row): ?><div class="labs-panel-item"><span class="labs-mini-icon"><?php echo empty($row['auth_gate_detected']) ? '✓' : '!'; ?></span><div><strong><?php echo htmlspecialchars($path); ?></strong><p><?php echo empty($row['auth_gate_detected']) ? 'No auth gate detected.' : 'Auth gate detected — review before deploying.'; ?></p></div></div><?php endforeach; ?></div></aside></section>
<?php labs_page_end(['section'=>'admin']); ?>
