<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-limited-email-pilot.php';
$page = ['title'=>'Limited Email Pilot | Training Lab','section'=>'admin','active'=>'admin-email-pilot','required_role'=>'admin'];
$user = tl_product_require_page_access($page);
tl_security_session_start();
$flash = $_SESSION['tl_limited_email_pilot_flash'] ?? null;
unset($_SESSION['tl_limited_email_pilot_flash']);
$data = tl_limited_email_pilot_dashboard($user, (string)($_GET['run'] ?? ''));
$readiness = (array)$data['readiness'];
$config = (array)($readiness['configuration'] ?? []);
$selected = is_array($data['selected']) ? $data['selected'] : null;
$metrics = array_merge(['total_messages'=>0,'accepted'=>0,'delivered'=>0,'delayed'=>0,'bounced'=>0,'complained'=>0,'provider_failed'=>0,'provider_suppressed'=>0,'missing_webhook'=>0,'orphaned'=>0,'delivery_rate_percent'=>0], (array)$data['metrics']);
$breaches = (array)$data['breaches'];
$status = (string)($selected['run_status'] ?? 'none');
$runRef = (string)($selected['public_id'] ?? '');
$canary = (string)($selected['canary_effective_status'] ?? $selected['canary_status'] ?? 'not_sent');
$providerReady = !empty($readiness['provider']['configured']);
$webhookReady = !empty($readiness['webhook']['ready']);
$ready = !empty($config['enabled']) && !empty($readiness['schema_ready']) && $providerReady && $webhookReady && !empty($readiness['general_worker_disabled']);
labs_page_start($page);
?>
<section class="labs-product-hero">
  <article class="labs-product-hero-main">
    <span class="labs-product-kicker">Section 18 · controlled graduation</span>
    <h1>Move from one administrator canary to one bounded participant cohort.</h1>
    <p>Every pilot requires a fixed cohort, a webhook-confirmed canary, administrator approval, capped batches, automatic health pauses, and a clean graduation score. The unrestricted notification worker remains disabled.</p>
  </article>
  <aside class="labs-product-next">
    <div><span>Section 18 readiness</span><h2><?php echo $ready ? 'Ready' : 'Blocked'; ?></h2><p><?php echo $selected ? labs_e((string)$selected['campaign_title']) . ' · ' . labs_e($status) : 'No pilot selected'; ?></p></div>
    <a class="labs-btn" href="<?php echo labs_url('/admin/email-webhooks.php'); ?>">Email Webhooks</a>
  </aside>
</section>
<?php if (is_array($flash)): ?><div class="labs-alert is-<?php echo labs_e((string)($flash['tone'] ?? 'info')); ?>" role="status"><?php echo labs_e((string)($flash['message'] ?? '')); ?></div><?php endif; ?>
<section class="labs-stats-grid">
  <article><span>Run status</span><strong><?php echo labs_e($status); ?></strong><small>Canary: <?php echo labs_e($canary); ?></small></article>
  <article><span>Cohort</span><strong><?php echo count((array)$data['members']); ?></strong><small>Maximum 10</small></article>
  <article><span>Accepted</span><strong><?php echo (int)$metrics['accepted']; ?></strong><small>Provider accepted</small></article>
  <article><span>Delivered</span><strong><?php echo (int)$metrics['delivered']; ?></strong><small><?php echo (int)$metrics['delivery_rate_percent']; ?>% confirmed</small></article>
  <article><span>Health breaches</span><strong><?php echo count($breaches); ?></strong><small><?php echo $breaches ? labs_e(implode(', ', $breaches)) : 'None'; ?></small></article>
</section>
<section class="labs-product-layout">
  <article class="labs-product-card">
    <div class="labs-product-card-head"><div><span class="labs-product-kicker">Launch controls</span><h2>Create or select a limited pilot</h2><p>Only one open pilot may exist at a time.</p></div><span class="labs-product-status is-<?php echo $ready ? 'success' : 'danger'; ?>"><?php echo $ready ? 'ready' : 'blocked'; ?></span></div>
    <?php if (!$selected): ?>
      <?php if (!$data['campaigns']): ?><div class="labs-guided-empty"><h3>No campaign is available.</h3><p>Create a Training Lab campaign before starting an email pilot.</p></div><?php else: ?>
      <form action="<?php echo labs_url('/admin/email-pilot-action.php'); ?>" method="post" class="labs-form-grid">
        <?php echo tl_security_csrf_field(); ?><input type="hidden" name="pilot_action" value="create">
        <label><span>Campaign</span><select name="campaign_id" required><?php foreach ($data['campaigns'] as $campaign): ?><option value="<?php echo labs_e((string)$campaign['id']); ?>"><?php echo labs_e((string)$campaign['title']); ?> · <?php echo labs_e((string)$campaign['status']); ?></option><?php endforeach; ?></select></label>
        <label><span>Cohort size</span><input type="number" name="cohort_limit" min="1" max="10" value="5" required></label>
        <label><span>Batch size</span><input type="number" name="batch_limit" min="1" max="3" value="1" required></label>
        <label><span>Daily limit</span><input type="number" name="daily_limit" min="1" max="100" value="10" required></label>
        <div class="labs-actions"><button class="labs-btn labs-btn-primary" type="submit">Create Limited Pilot</button></div>
      </form>
      <?php endif; ?>
    <?php else: ?>
      <div class="labs-readiness-list">
        <div class="labs-readiness-row"><div><strong>Section 18 gate</strong><p>Must be explicitly enabled.</p></div><span class="labs-product-status is-<?php echo !empty($config['enabled']) ? 'success' : 'neutral'; ?>"><?php echo !empty($config['enabled']) ? 'pass' : 'pending'; ?></span></div>
        <div class="labs-readiness-row"><div><strong>Processing gate</strong><p>Required only after approval.</p></div><span class="labs-product-status is-<?php echo !empty($config['processing_enabled']) ? 'success' : 'neutral'; ?>"><?php echo !empty($config['processing_enabled']) ? 'enabled' : 'disabled'; ?></span></div>
        <div class="labs-readiness-row"><div><strong>Provider and webhook</strong><p>Resend and signed delivery reconciliation.</p></div><span class="labs-product-status is-<?php echo $providerReady && $webhookReady ? 'success' : 'danger'; ?>"><?php echo $providerReady && $webhookReady ? 'pass' : 'blocked'; ?></span></div>
        <div class="labs-readiness-row"><div><strong>General worker</strong><p>Must remain disabled during the limited pilot.</p></div><span class="labs-product-status is-<?php echo !empty($readiness['general_worker_disabled']) ? 'success' : 'danger'; ?>"><?php echo !empty($readiness['general_worker_disabled']) ? 'disabled' : 'enabled'; ?></span></div>
      </div>
      <div class="labs-actions">
        <?php if (in_array($status,['draft','paused'],true)): ?><form action="<?php echo labs_url('/admin/email-pilot-action.php'); ?>" method="post"><?php echo tl_security_csrf_field(); ?><input type="hidden" name="pilot_action" value="send_canary"><input type="hidden" name="run_id" value="<?php echo labs_e($runRef); ?>"><button class="labs-btn labs-btn-primary" type="submit">Send Administrator Canary</button></form><?php endif; ?>
        <?php if (in_array($status,['canary_sent','canary_confirmed'],true)): ?><form action="<?php echo labs_url('/admin/email-pilot-action.php'); ?>" method="post"><?php echo tl_security_csrf_field(); ?><input type="hidden" name="pilot_action" value="approve"><input type="hidden" name="run_id" value="<?php echo labs_e($runRef); ?>"><button class="labs-btn" type="submit">Confirm Canary + Approve</button></form><?php endif; ?>
        <?php if ($status==='approved'): ?><form action="<?php echo labs_url('/admin/email-pilot-action.php'); ?>" method="post"><?php echo tl_security_csrf_field(); ?><input type="hidden" name="pilot_action" value="start"><input type="hidden" name="run_id" value="<?php echo labs_e($runRef); ?>"><button class="labs-btn labs-btn-primary" type="submit">Start Participant Pilot</button></form><?php endif; ?>
        <?php if ($status==='running'): ?><form action="<?php echo labs_url('/admin/email-pilot-action.php'); ?>" method="post"><?php echo tl_security_csrf_field(); ?><input type="hidden" name="pilot_action" value="process"><input type="hidden" name="run_id" value="<?php echo labs_e($runRef); ?>"><input type="hidden" name="limit" value="<?php echo (int)min(3,(int)$selected['batch_limit']); ?>"><button class="labs-btn labs-btn-primary" type="submit">Process Next Bounded Batch</button></form><?php endif; ?>
        <?php if (in_array($status,['running','paused'],true)): ?><form action="<?php echo labs_url('/admin/email-pilot-action.php'); ?>" method="post"><?php echo tl_security_csrf_field(); ?><input type="hidden" name="pilot_action" value="evaluate"><input type="hidden" name="run_id" value="<?php echo labs_e($runRef); ?>"><button class="labs-btn" type="submit">Evaluate Graduation</button></form><?php endif; ?>
      </div>
    <?php endif; ?>
  </article>
  <aside class="labs-product-stack">
    <article class="labs-product-card"><span class="labs-product-kicker">Automatic pause</span><h2>One critical event stops the pilot.</h2><p>Bounces, complaints, provider failures, provider suppressions, orphaned events, stale delays, and missing webhook confirmations pause campaign email and disable its pilot control.</p></article>
    <?php if ($selected && in_array($status,['running','approved','canary_confirmed'],true)): ?><article class="labs-product-card"><span class="labs-product-kicker">Emergency stop</span><h2>Pause immediately</h2><form action="<?php echo labs_url('/admin/email-pilot-action.php'); ?>" method="post" class="labs-form-stack"><?php echo tl_security_csrf_field(); ?><input type="hidden" name="pilot_action" value="pause"><input type="hidden" name="run_id" value="<?php echo labs_e($runRef); ?>"><label><span>Reason</span><input type="text" name="reason" maxlength="255" value="Administrator emergency stop." required></label><button class="labs-btn" type="submit">Pause Pilot</button></form></article><?php endif; ?>
  </aside>
</section>
<?php if ($selected): ?>
<section class="labs-product-card">
  <div class="labs-product-card-head"><div><span class="labs-product-kicker">Fixed cohort</span><h2>Eligible pilot members</h2><p>Addresses are never displayed. Recipient confirmations are short SHA-256 prefixes.</p></div><span class="labs-product-status is-neutral"><?php echo count($data['members']); ?> members</span></div>
  <div class="labs-table-scroll"><table class="labs-table"><thead><tr><th>Participant</th><th>User</th><th>Status</th><th>Recipient</th><th>Activated</th></tr></thead><tbody><?php foreach ($data['members'] as $member): ?><tr><td><?php echo (int)$member['participant_id']; ?></td><td><?php echo (int)$member['user_id']; ?></td><td><?php echo labs_e((string)$member['member_status']); ?></td><td><code><?php echo labs_e((string)$member['recipient_confirmation']); ?></code></td><td><?php echo labs_e((string)($member['activated_at'] ?? 'pending')); ?></td></tr><?php endforeach; ?></tbody></table></div>
</section>
<section class="labs-product-layout">
  <article class="labs-product-card">
    <div class="labs-product-card-head"><div><span class="labs-product-kicker">Graduation evidence</span><h2>Latest scored checks</h2></div></div>
    <?php if (!$data['checks']): ?><div class="labs-guided-empty"><h3>No evaluation has been recorded.</h3><p>Evaluate after participant delivery and webhook reconciliation.</p></div><?php else: ?><div class="labs-readiness-list"><?php foreach (array_slice($data['checks'],0,20) as $check): ?><div class="labs-readiness-row"><div><strong><?php echo labs_e((string)$check['check_label']); ?></strong><p><?php echo labs_e((string)$check['observed_value']); ?> · required <?php echo labs_e((string)$check['required_value']); ?></p></div><span class="labs-product-status is-<?php echo (string)$check['check_status']==='passed'?'success':'danger'; ?>"><?php echo labs_e((string)$check['check_status']); ?></span></div><?php endforeach; ?></div><?php endif; ?>
  </article>
  <aside class="labs-product-stack">
    <article class="labs-product-card"><span class="labs-product-kicker">Final decision</span><h2>Graduate or reject</h2><p>Graduation is blocked unless every scored check passes. Rejection always requires a reason.</p>
      <form action="<?php echo labs_url('/admin/email-pilot-action.php'); ?>" method="post" class="labs-form-stack"><?php echo tl_security_csrf_field(); ?><input type="hidden" name="run_id" value="<?php echo labs_e($runRef); ?>"><label><span>Decision notes</span><textarea name="notes" maxlength="500" rows="4"></textarea></label><div class="labs-actions"><button class="labs-btn labs-btn-primary" type="submit" name="pilot_action" value="graduate">Graduate Pilot</button><button class="labs-btn" type="submit" name="pilot_action" value="reject">Reject Pilot</button></div></form>
    </article>
  </aside>
</section>
<section class="labs-product-card">
  <div class="labs-product-card-head"><div><span class="labs-product-kicker">Immutable timeline</span><h2>Pilot operations and incidents</h2></div></div>
  <?php if (!$data['events']): ?><div class="labs-guided-empty"><h3>No pilot events recorded.</h3></div><?php else: ?><div class="labs-table-scroll"><table class="labs-table"><thead><tr><th>Event</th><th>Severity</th><th>Summary</th><th>Created</th></tr></thead><tbody><?php foreach ($data['events'] as $event): ?><tr><td><?php echo labs_e((string)$event['event_type']); ?></td><td><span class="labs-product-status is-<?php echo (string)$event['severity']==='success'?'success':((string)$event['severity']==='critical'?'danger':'neutral'); ?>"><?php echo labs_e((string)$event['severity']); ?></span></td><td><?php echo labs_e((string)$event['event_summary']); ?></td><td><?php echo labs_e((string)$event['created_at']); ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<?php endif; ?>
<section class="labs-safe-note">Section 18 never displays recipient addresses, does not schedule the unrestricted notification worker, does not bypass preferences or suppressions, and creates no Microgifter account, wallet, payment, gift, claim, redemption, or reward-delivery authority.</section>
<?php labs_page_end(['section'=>'admin']); ?>
