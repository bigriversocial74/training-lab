<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-pilot-communications-actions.php';
$page = ['title'=>'Pilot Communications | Training Lab','section'=>'admin','active'=>'admin-pilot-communications','required_role'=>'manager'];
$user = tl_product_require_page_access($page);
tl_security_session_start();
$flash = $_SESSION['tl_pilot_communications_flash'] ?? null;
unset($_SESSION['tl_pilot_communications_flash']);
$dashboard = tl_notifications_dashboard($user);
$rows = !empty($dashboard['schema_ready']) ? tl_notifications_outbox_rows($user, 120) : [];
labs_page_start($page);
?>
<section class="labs-product-hero">
  <article class="labs-product-hero-main">
    <span class="labs-product-kicker">Pilot operations</span>
    <h1>Communications that stay inside controlled campaign limits.</h1>
    <p>Synchronize trusted training events into an idempotent outbox, manage pilot limits, and review delivery health without creating a second contact system.</p>
  </article>
  <aside class="labs-product-next">
    <div><span>Delivery provider</span><h2><?php echo !empty($dashboard['provider']['can_process']) ? 'Ready' : 'Disabled'; ?></h2><p>External sending requires the worker, provider adapter, and campaign pilot to be explicitly enabled.</p></div>
    <a class="labs-btn" href="<?php echo labs_url('/admin/notification-templates.php'); ?>">Templates</a>
  </aside>
</section>

<?php if (is_array($flash)): ?>
<div class="labs-alert is-<?php echo labs_e((string)($flash['tone'] ?? 'info')); ?>" role="status"><?php echo labs_e((string)($flash['message'] ?? '')); ?></div>
<?php endif; ?>

<?php if (empty($dashboard['schema_ready'])): ?>
<section class="labs-product-card"><span class="labs-product-kicker">Migration required</span><h2>Import the communications schema first.</h2><p>Import <code>database/pilot_operations_communications_v1.sql</code> into the existing Training Lab database. This migration is additive and does not alter Microgifter account, wallet, gift, payment, claim, or redemption tables.</p></section>
<?php else: ?>
<section class="labs-product-stats" aria-label="Pilot communication summary">
<?php foreach (['participants'=>'Participants','notifications'=>'Outbox','queued'=>'Queued','delivered'=>'Delivered','failed'=>'Failed','blocked'=>'Blocked','suppressed'=>'Suppressed'] as $key=>$label): ?>
  <article class="labs-product-stat<?php echo in_array($key,['failed','blocked'],true) && (int)$dashboard['totals'][$key] > 0 ? ' is-action' : ''; ?>"><span><?php echo labs_e($label); ?></span><strong><?php echo (int)$dashboard['totals'][$key]; ?></strong><small>owner-scoped</small></article>
<?php endforeach; ?>
</section>

<section class="labs-product-card">
  <div class="labs-product-card-head"><div><span class="labs-product-kicker">Event synchronization</span><h2>Build the outbox from authoritative training records</h2><p>Synchronization is idempotent. Re-running it does not duplicate invitations, proof updates, review results, rewards, or delivery outcomes.</p></div></div>
  <form class="labs-form-grid labs-communications-sync" action="<?php echo labs_url('/admin/pilot-communications-action.php'); ?>" method="post">
    <?php echo tl_security_csrf_field(); ?>
    <input type="hidden" name="communications_action" value="sync_events">
    <label>Campaign<select name="campaign_id"><option value="">All owned campaigns</option><?php foreach ($dashboard['campaigns'] as $campaign): ?><option value="<?php echo labs_e((string)($campaign['slug'] ?: $campaign['public_id'])); ?>"><?php echo labs_e((string)$campaign['title']); ?></option><?php endforeach; ?></select></label>
    <label>Record limit<input type="number" name="limit" min="1" max="1000" value="250"></label>
    <label class="labs-check"><input type="checkbox" name="include_reminders" value="1"><span>Include today’s task reminders</span></label>
    <button class="labs-btn labs-btn-primary" type="submit">Synchronize Events</button>
  </form>
</section>

<section class="labs-communications-campaigns">
<?php foreach ($dashboard['campaigns'] as $campaign): ?>
  <article class="labs-product-card labs-communications-campaign">
    <div class="labs-product-card-head"><div><span class="labs-product-kicker"><?php echo labs_e((string)$campaign['status']); ?> campaign</span><h2><?php echo labs_e((string)$campaign['title']); ?></h2><p><?php echo (int)$campaign['participants']; ?> participants · <?php echo (int)$campaign['delivered']; ?> delivered · <?php echo (int)$campaign['failed']; ?> failed</p></div><span class="labs-product-status is-<?php echo (string)($campaign['pilot_status'] ?? 'draft') === 'active' ? 'success' : 'neutral'; ?>"><?php echo labs_e((string)($campaign['pilot_status'] ?? 'not configured')); ?></span></div>
    <form class="labs-form-grid" action="<?php echo labs_url('/admin/pilot-communications-action.php'); ?>" method="post">
      <?php echo tl_security_csrf_field(); ?>
      <input type="hidden" name="communications_action" value="save_pilot_control">
      <input type="hidden" name="campaign_id" value="<?php echo labs_e((string)($campaign['slug'] ?: $campaign['public_id'])); ?>">
      <label>Pilot status<select name="pilot_status"><?php foreach (['draft'=>'Draft','active'=>'Active','paused'=>'Paused','completed'=>'Completed'] as $value=>$label): ?><option value="<?php echo $value; ?>"<?php echo (string)($campaign['pilot_status'] ?? 'draft') === $value ? ' selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></label>
      <label>Maximum participants<input type="number" name="max_participants" min="1" max="10000" value="<?php echo max(1,(int)($campaign['max_participants'] ?? 25)); ?>"></label>
      <label>Daily notification limit<input type="number" name="daily_notification_limit" min="1" max="100000" value="<?php echo max(1,(int)($campaign['daily_notification_limit'] ?? 100)); ?>"></label>
      <label>Pause reason<input type="text" name="paused_reason" maxlength="255" value="<?php echo labs_e((string)($campaign['paused_reason'] ?? '')); ?>"></label>
      <label class="labs-check"><input type="checkbox" name="email_enabled" value="1"<?php echo !empty($campaign['email_enabled']) ? ' checked' : ''; ?>><span>Allow email for this pilot</span></label>
      <button class="labs-btn" type="submit">Save Pilot Control</button>
    </form>
  </article>
<?php endforeach; ?>
</section>

<section class="labs-product-card">
  <div class="labs-product-card-head"><div><span class="labs-product-kicker">Delivery history</span><h2>Recent notification outbox</h2><p>Recipient addresses, provider IDs, adapter payloads, cookies, and credentials are never displayed.</p></div><a class="labs-btn" href="<?php echo labs_url('/admin/notification-incidents.php'); ?>">Incident Queue</a></div>
  <?php if (!$rows): ?><div class="labs-guided-empty"><h3>No notification events yet.</h3><p>Activate a campaign pilot, synchronize events, and the outbox will appear here.</p></div><?php else: ?>
  <div class="labs-table-scroll"><table class="labs-table"><thead><tr><th>Campaign</th><th>Event</th><th>Status</th><th>Attempts</th><th>Recipient</th><th>Timing</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($rows as $row): ?><tr><td><?php echo labs_e((string)$row['campaign_title']); ?></td><td><?php echo labs_e(str_replace('_',' ',(string)$row['event_type'])); ?><small><?php echo labs_e((string)$row['message_class']); ?></small></td><td><span class="labs-product-status is-<?php echo in_array((string)$row['outbox_status'],['delivered'],true)?'success':(in_array((string)$row['outbox_status'],['failed','blocked'],true)?'danger':'neutral'); ?>"><?php echo labs_e((string)$row['outbox_status']); ?></span><?php if (!empty($row['last_error_detail'])): ?><small><?php echo labs_e((string)$row['last_error_detail']); ?></small><?php endif; ?></td><td><?php echo (int)$row['attempt_count']; ?>/<?php echo (int)$row['max_attempts']; ?></td><td><code><?php echo labs_e((string)$row['recipient_confirmation']); ?></code></td><td><small><?php echo labs_e((string)($row['delivered_at'] ?: $row['next_attempt_at'] ?: $row['scheduled_at'])); ?></small></td><td><div class="labs-actions"><?php if (in_array((string)$row['outbox_status'],['failed','blocked','suppressed'],true)): ?><form method="post" action="<?php echo labs_url('/admin/pilot-communications-action.php'); ?>"><?php echo tl_security_csrf_field(); ?><input type="hidden" name="communications_action" value="retry_notification"><input type="hidden" name="notification_id" value="<?php echo labs_e((string)$row['public_id']); ?>"><button class="labs-btn" type="submit">Retry</button></form><?php endif; ?><?php if (!in_array((string)$row['outbox_status'],['delivered','cancelled'],true)): ?><form method="post" action="<?php echo labs_url('/admin/pilot-communications-action.php'); ?>"><?php echo tl_security_csrf_field(); ?><input type="hidden" name="communications_action" value="cancel_notification"><input type="hidden" name="notification_id" value="<?php echo labs_e((string)$row['public_id']); ?>"><button class="labs-btn" type="submit">Cancel</button></form><?php endif; ?></div></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</section>
<?php endif; ?>
<section class="labs-safe-note">Pilot communications use existing Training Lab campaigns, participants, proofs, reviews, rewards, handoffs, and Microgifter account links. Email delivery remains disabled until both global worker gates and a provider adapter are explicitly enabled.</section>
<?php labs_page_end(['section'=>'admin']); ?>
