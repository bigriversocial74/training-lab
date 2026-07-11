<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-production-integration-closeout.php';
$page = ['title'=>'Production Integration Closeout | Training Lab','section'=>'admin','active'=>'admin-integration-closeout','required_role'=>'admin'];
$user = tl_product_require_page_access($page);
tl_security_session_start();
$flash = $_SESSION['tl_integration_closeout_flash'] ?? null;
unset($_SESSION['tl_integration_closeout_flash']);
$data = tl_closeout_dashboard($user, (string)($_GET['campaign'] ?? ''), (string)($_GET['run'] ?? ''));
$report = (array)$data['report'];
$selected = is_array($data['selected']) ? $data['selected'] : null;
$config = (array)$data['configuration'];
$campaign = is_array($report['campaign'] ?? null) ? $report['campaign'] : null;
$ready = !empty($report['ready']);
labs_page_start($page);
?>
<section class="labs-product-hero">
  <article class="labs-product-hero-main">
    <span class="labs-product-kicker">Section 20 · production closeout</span>
    <h1>Prove the complete Microgifter Training Lab v1 path with durable evidence.</h1>
    <p>This report connects signed account access, campaign publishing, enrollment, tasks, proof, review, completion, Resend delivery, signed webhook reconciliation, reward handoff delivery, and signed Microgifter lookup confirmation. Approval records evidence only; it never enables production gates.</p>
  </article>
  <aside class="labs-product-next">
    <div><span>Live evidence score</span><h2><?php echo (int)$report['score']; ?>%</h2><p><?php echo $ready ? 'Every mandatory check passes.' : (int)$report['failed'] . ' checks remain blocked.'; ?></p></div>
    <a class="labs-btn" href="<?php echo labs_url('/admin/product-acceptance.php'); ?>">Product Acceptance</a>
  </aside>
</section>
<?php if (is_array($flash)): ?><div class="labs-alert is-<?php echo labs_e((string)($flash['tone'] ?? 'info')); ?>" role="status"><?php echo labs_e((string)($flash['message'] ?? '')); ?></div><?php endif; ?>
<section class="labs-stats-grid">
  <article><span>Passed</span><strong><?php echo (int)$report['passed']; ?></strong><small>of <?php echo (int)$report['total']; ?> mandatory checks</small></article>
  <article><span>Campaign</span><strong><?php echo $campaign ? labs_e((string)$campaign['title']) : 'Missing'; ?></strong><small><?php echo $campaign ? labs_e((string)$campaign['status']) : 'Select evidence'; ?></small></article>
  <article><span>Account bridge</span><strong><?php echo !empty($report['account']) ? 'Linked' : 'Missing'; ?></strong><small><?php echo !empty($report['account']['identity_fingerprint']) ? labs_e((string)$report['account']['identity_fingerprint']) : 'No identity evidence'; ?></small></article>
  <article><span>Email pilot</span><strong><?php echo labs_e((string)($report['email_pilot']['status'] ?? 'missing')); ?></strong><small><?php echo (int)($report['email_pilot']['participant_delivered'] ?? 0); ?> delivered</small></article>
  <article><span>Reward handoff</span><strong><?php echo labs_e((string)($report['reward_handoff']['status'] ?? 'missing')); ?></strong><small><?php echo labs_e((string)($report['reward_handoff']['external_reference_fingerprint'] ?? 'no confirmation')); ?></small></article>
</section>
<section class="labs-product-layout">
  <article class="labs-product-card">
    <div class="labs-product-card-head"><div><span class="labs-product-kicker">Live evidence</span><h2>Mandatory production checks</h2><p>The report is read-only until an administrator explicitly records a snapshot.</p></div><span class="labs-product-status is-<?php echo $ready ? 'success' : 'warning'; ?>"><?php echo $ready ? 'ready' : 'blocked'; ?></span></div>
    <form method="get" class="labs-form-grid">
      <label class="is-wide"><span>Campaign evidence set</span><select name="campaign"><option value="">Automatically select the strongest completed campaign</option><?php foreach ((array)$data['campaigns'] as $row): $ref=(string)($row['public_id'] ?? $row['slug'] ?? $row['id']); ?><option value="<?php echo labs_e($ref); ?>" <?php echo $campaign && (int)$campaign['id']===(int)$row['id']?'selected':''; ?>><?php echo labs_e((string)$row['title']); ?> · <?php echo labs_e((string)$row['status']); ?></option><?php endforeach; ?></select></label>
      <div class="labs-actions is-wide"><button class="labs-btn" type="submit">Evaluate Campaign</button><a class="labs-btn" href="<?php echo labs_url('/api/training/integration-closeout.php' . ($campaign ? '?campaign=' . rawurlencode((string)$campaign['public_id']) : '')); ?>">JSON Report</a></div>
    </form>
    <?php foreach ((array)$report['categories'] as $category=>$summary): ?>
      <section class="labs-card" style="margin-top:1rem"><div class="labs-card-headline"><div><span class="labs-eyebrow"><?php echo labs_e(ucwords(str_replace('_',' ',$category))); ?></span><h3><?php echo (int)$summary['passed']; ?>/<?php echo (int)$summary['total']; ?> checks</h3></div><span class="labs-pill"><?php echo (int)$summary['percent']; ?>%</span></div>
      <div class="labs-readiness-list"><?php foreach ((array)$report['checks'] as $check): if ((string)$check['category']!==$category) continue; ?><div class="labs-readiness-row"><div><strong><?php echo labs_e((string)$check['label']); ?></strong><p><?php echo labs_e((string)($check['detail'] ?: ($check['observed'] . ' · required ' . $check['required']))); ?></p></div><span class="labs-product-status is-<?php echo !empty($check['passed'])?'success':'warning'; ?>"><?php echo !empty($check['passed'])?'pass':'blocked'; ?></span></div><?php endforeach; ?></div></section>
    <?php endforeach; ?>
  </article>
  <aside class="labs-product-stack">
    <article class="labs-product-card"><span class="labs-product-kicker">Record evidence</span><h2>Immutable closeout snapshot</h2><p>Recording requires the Section 20 schema and recording gate. Repeated identical evidence returns the existing snapshot.</p><div class="labs-readiness-list"><div class="labs-readiness-row"><div><strong>Schema</strong><p>Section 20 evidence tables</p></div><span class="labs-product-status is-<?php echo !empty($data['schema_ready'])?'success':'warning'; ?>"><?php echo !empty($data['schema_ready'])?'ready':'missing'; ?></span></div><div class="labs-readiness-row"><div><strong>Recording gate</strong><p>Explicit environment or private config setting</p></div><span class="labs-product-status is-<?php echo !empty($config['enabled'])?'success':'neutral'; ?>"><?php echo !empty($config['enabled'])?'enabled':'disabled'; ?></span></div><div class="labs-readiness-row"><div><strong>Approval gate</strong><p>Separate final administrator control</p></div><span class="labs-product-status is-<?php echo !empty($config['approval_enabled'])?'success':'neutral'; ?>"><?php echo !empty($config['approval_enabled'])?'enabled':'disabled'; ?></span></div></div>
    <form action="<?php echo labs_url('/admin/integration-closeout-action.php'); ?>" method="post"><?php echo tl_security_csrf_field(); ?><input type="hidden" name="closeout_action" value="record"><input type="hidden" name="campaign" value="<?php echo labs_e((string)($campaign['public_id'] ?? '')); ?>"><button class="labs-btn labs-btn-primary" type="submit">Record Current Evidence</button></form></article>
    <article class="labs-product-card"><span class="labs-product-kicker">Recorded reports</span><h2>Decision history</h2><div class="labs-panel-list"><?php foreach ((array)$data['runs'] as $run): ?><a class="labs-panel-item" href="<?php echo labs_url('/admin/integration-closeout.php?run=' . rawurlencode((string)$run['public_id'])); ?>"><span class="labs-mini-icon"><?php echo (int)$run['score_percent']; ?></span><div><strong><?php echo labs_e((string)($run['campaign_title'] ?? 'Integration closeout')); ?></strong><p><?php echo labs_e((string)$run['run_status']); ?> · <?php echo labs_e((string)$run['recorded_at']); ?></p></div></a><?php endforeach; ?><?php if (!$data['runs']): ?><p class="labs-muted">No closeout snapshot has been recorded.</p><?php endif; ?></div></article>
    <?php if ($selected): ?><article class="labs-product-card"><span class="labs-product-kicker">Selected decision</span><h2><?php echo labs_e((string)$selected['run_status']); ?> · <?php echo (int)$selected['score_percent']; ?>%</h2><p>Report fingerprint: <?php echo labs_e(substr((string)$selected['report_hash'],0,16)); ?></p><?php if ((string)$selected['run_status']==='recorded'): ?><form action="<?php echo labs_url('/admin/integration-closeout-action.php'); ?>" method="post" class="labs-form-grid"><?php echo tl_security_csrf_field(); ?><input type="hidden" name="closeout_action" value="approve"><input type="hidden" name="run_id" value="<?php echo labs_e((string)$selected['public_id']); ?>"><label class="is-wide"><span>Approval notes</span><textarea name="notes" rows="3" maxlength="1000"></textarea></label><button class="labs-btn labs-btn-primary" type="submit">Approve Complete v1</button></form><?php endif; ?><?php if (!in_array((string)$selected['run_status'],['approved','rejected'],true)): ?><form action="<?php echo labs_url('/admin/integration-closeout-action.php'); ?>" method="post" class="labs-form-grid"><?php echo tl_security_csrf_field(); ?><input type="hidden" name="closeout_action" value="reject"><input type="hidden" name="run_id" value="<?php echo labs_e((string)$selected['public_id']); ?>"><label class="is-wide"><span>Rejection reason</span><textarea name="notes" rows="3" maxlength="1000" required></textarea></label><button class="labs-btn labs-btn-danger" type="submit">Reject Closeout</button></form><?php endif; ?></article><?php endif; ?>
  </aside>
</section>
<section class="labs-safe-note">Production closeout reads existing evidence and writes only immutable Section 20 records. It does not send email, execute a worker, enable a feature gate, issue a Microgift, change a wallet, process a payment, claim, redeem, or mutate Microgifter data.</section>
<?php labs_page_end(['section'=>'admin']); ?>
