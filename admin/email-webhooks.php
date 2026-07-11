<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-resend-webhooks.php';
$page = ['title'=>'Email Webhooks | Training Lab','section'=>'admin','active'=>'admin-email-webhooks','required_role'=>'admin'];
$user = tl_product_require_page_access($page);
$data = tl_resend_webhook_dashboard($user, 100);
$readiness = (array)($data['readiness'] ?? []);
$totals = array_merge(['received'=>0,'reconciled'=>0,'orphaned'=>0,'ignored'=>0,'duplicates'=>0,'suppressions'=>0,'last_24h'=>0], (array)($data['totals'] ?? []));
$events = (array)($data['events'] ?? []);
$states = (array)($data['states'] ?? []);
$checks = [
    ['label'=>'Webhook gate','passed'=>!empty($readiness['enabled']),'detail'=>'TL_RESEND_WEBHOOK_ENABLED must be explicitly enabled.'],
    ['label'=>'Signing secret','passed'=>!empty($readiness['secret_present']),'detail'=>'The whsec_ secret is read from protected configuration and never displayed.'],
    ['label'=>'Webhook schema','passed'=>!empty($readiness['schema_ready']),'detail'=>'Import database/notification_provider_webhooks_v1.sql.'],
    ['label'=>'Replay window','passed'=>(int)($readiness['tolerance_seconds'] ?? 0) >= 60,'detail'=>(int)($readiness['tolerance_seconds'] ?? 0) . ' seconds.'],
    ['label'=>'Raw payload retention','passed'=>empty($readiness['raw_payload_storage']),'detail'=>'Raw webhook bodies are verified and discarded.'],
    ['label'=>'Recipient privacy','passed'=>empty($readiness['recipient_address_storage']),'detail'=>'Recipient addresses are never stored in the webhook ledger.'],
];
labs_page_start($page);
?>
<section class="labs-product-hero">
  <article class="labs-product-hero-main">
    <span class="labs-product-kicker">Signed delivery reconciliation</span>
    <h1>Confirm final email outcomes without exposing provider payloads.</h1>
    <p>Resend events are verified from the raw body, protected against replay, correlated through SHA-256 message hashes, and reconciled in event-time order. Permanent bounces, complaints, and provider suppressions automatically protect the recipient from future sends.</p>
  </article>
  <aside class="labs-product-next">
    <div><span>Webhook readiness</span><h2><?php echo !empty($readiness['ready']) ? 'Ready' : 'Blocked'; ?></h2><p><?php echo labs_e((string)($readiness['endpoint'] ?? '/api/webhooks/resend.php')); ?></p></div>
    <a class="labs-btn" href="<?php echo labs_url('/admin/email-provider.php'); ?>">Email Provider</a>
  </aside>
</section>
<section class="labs-stats-grid">
  <article><span>Received</span><strong><?php echo (int)$totals['received']; ?></strong><small><?php echo (int)$totals['last_24h']; ?> in 24 hours</small></article>
  <article><span>Reconciled</span><strong><?php echo (int)$totals['reconciled']; ?></strong><small>Matched to outbox</small></article>
  <article><span>Orphaned</span><strong><?php echo (int)$totals['orphaned']; ?></strong><small>Needs investigation</small></article>
  <article><span>Duplicates</span><strong><?php echo (int)$totals['duplicates']; ?></strong><small>Safely ignored</small></article>
  <article><span>Suppressions</span><strong><?php echo (int)$totals['suppressions']; ?></strong><small>Automatic protection</small></article>
</section>
<section class="labs-product-layout">
  <article class="labs-product-card">
    <div class="labs-product-card-head"><div><span class="labs-product-kicker">Endpoint readiness</span><h2>Signature, schema, and privacy checks</h2></div><span class="labs-product-status is-<?php echo !empty($readiness['ready']) ? 'success' : 'danger'; ?>"><?php echo !empty($readiness['ready']) ? 'ready' : 'blocked'; ?></span></div>
    <div class="labs-readiness-list">
      <?php foreach ($checks as $check): ?><div class="labs-readiness-row"><div><strong><?php echo labs_e((string)$check['label']); ?></strong><p><?php echo labs_e((string)$check['detail']); ?></p></div><span class="labs-product-status is-<?php echo !empty($check['passed']) ? 'success' : 'neutral'; ?>"><?php echo !empty($check['passed']) ? 'pass' : 'pending'; ?></span></div><?php endforeach; ?>
    </div>
  </article>
  <aside class="labs-product-stack">
    <article class="labs-product-card"><span class="labs-product-kicker">Resend dashboard setup</span><h2>Subscribe only to delivery lifecycle events.</h2><p>Register the HTTPS endpoint for <code>email.sent</code>, <code>email.delivered</code>, <code>email.delivery_delayed</code>, <code>email.bounced</code>, <code>email.complained</code>, <code>email.failed</code>, and <code>email.suppressed</code>.</p></article>
    <article class="labs-product-card"><span class="labs-product-kicker">Emergency stop</span><h2>Disable webhook ingestion independently.</h2><p>Set <code>TL_RESEND_WEBHOOK_ENABLED=false</code>. This does not alter stored events, suppressions, campaign delivery, or the notification worker.</p></article>
  </aside>
</section>
<section class="labs-product-card">
  <div class="labs-product-card-head"><div><span class="labs-product-kicker">Recent webhook events</span><h2>Sanitized event ledger</h2><p>Only short hashes, statuses, timestamps, and allowlisted operational details are displayed.</p></div><span class="labs-product-status is-<?php echo (int)$totals['orphaned'] > 0 ? 'danger' : 'success'; ?>"><?php echo (int)$totals['orphaned']; ?> orphaned</span></div>
  <?php if (!$events): ?><div class="labs-guided-empty"><h3>No webhook events received.</h3><p>Events will appear after the signed endpoint is enabled and registered with Resend.</p></div><?php else: ?>
  <div class="labs-table-scroll"><table class="labs-table"><thead><tr><th>Event</th><th>Delivery</th><th>Processing</th><th>Message</th><th>Duplicates</th><th>Occurred</th></tr></thead><tbody>
    <?php foreach ($events as $row): ?><tr><td><strong><?php echo labs_e((string)$row['event_type']); ?></strong><small><?php echo labs_e((string)$row['event_confirmation']); ?></small></td><td><?php echo labs_e((string)$row['delivery_status']); ?></td><td><span class="labs-product-status is-<?php echo (string)$row['processing_status']==='reconciled'?'success':((string)$row['processing_status']==='orphaned'?'danger':'neutral'); ?>"><?php echo labs_e((string)$row['processing_status']); ?></span><?php if (!empty($row['error_code'])): ?><small><?php echo labs_e((string)$row['error_code']); ?></small><?php endif; ?></td><td><code><?php echo labs_e((string)$row['message_confirmation']); ?></code></td><td><?php echo (int)$row['duplicate_count']; ?></td><td><?php echo labs_e((string)$row['event_occurred_at']); ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>
<section class="labs-product-card">
  <div class="labs-product-card-head"><div><span class="labs-product-kicker">Current delivery state</span><h2>Latest reconciled outcome per outbox item</h2></div></div>
  <?php if (!$states): ?><div class="labs-guided-empty"><h3>No reconciled delivery states.</h3></div><?php else: ?>
  <div class="labs-table-scroll"><table class="labs-table"><thead><tr><th>Campaign</th><th>Status</th><th>Message</th><th>Outbox</th><th>Last event</th></tr></thead><tbody>
    <?php foreach ($states as $row): ?><tr><td><?php echo labs_e((string)$row['campaign_title']); ?></td><td><span class="labs-product-status is-<?php echo (string)$row['delivery_status']==='delivered'?'success':(in_array((string)$row['delivery_status'],['bounced','complained','failed','suppressed'],true)?'danger':'neutral'); ?>"><?php echo labs_e((string)$row['delivery_status']); ?></span></td><td><code><?php echo labs_e((string)$row['message_confirmation']); ?></code></td><td><code><?php echo labs_e(substr((string)$row['outbox_public_id'],0,12)); ?></code></td><td><?php echo labs_e((string)$row['last_event_at']); ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>
<section class="labs-safe-note">Webhook processing stores no raw request body, email address, subject, sender, authorization header, signing secret, or full provider message ID. The endpoint performs no outbound request and cannot enable campaign delivery or workers.</section>
<?php labs_page_end(['section'=>'admin']); ?>
