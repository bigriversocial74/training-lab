<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-resend-email-provider.php';
require_once __DIR__ . '/../includes/training-lab-pilot-communications.php';
$page = ['title'=>'Email Provider | Training Lab','section'=>'admin','active'=>'admin-email-provider','required_role'=>'admin'];
$user = tl_product_require_page_access($page);
tl_security_session_start();
$flash = $_SESSION['tl_email_provider_flash'] ?? null;
unset($_SESSION['tl_email_provider_flash']);
$provider = tl_notifications_provider_state();
$diagnostics = (array)($provider['diagnostics'] ?? []);
$checks = [
    ['label'=>'Provider selected','passed'=>(string)$provider['provider_name']==='resend','detail'=>'TL_NOTIFICATION_PROVIDER must be resend.'],
    ['label'=>'cURL available','passed'=>!empty($diagnostics['curl_available']),'detail'=>'The PHP cURL extension is required.'],
    ['label'=>'API key present','passed'=>!empty($diagnostics['api_key_present']),'detail'=>'The key is read from protected configuration and is never displayed.'],
    ['label'=>'Sender address valid','passed'=>!empty($diagnostics['from_address_valid']),'detail'=>'Sender domain: ' . ((string)($diagnostics['sender_domain'] ?? '') ?: 'not configured')],
    ['label'=>'Reply-to valid','passed'=>!empty($diagnostics['reply_to_valid']),'detail'=>!empty($diagnostics['reply_to_configured']) ? 'A separate reply-to address is configured.' : 'Reply-to is optional.'],
    ['label'=>'Test recipient configured','passed'=>!empty($diagnostics['test_recipient_configured']),'detail'=>'Confirmation: ' . ((string)($diagnostics['test_recipient_confirmation'] ?? '') ?: 'not configured')],
    ['label'=>'Test delivery gate','passed'=>!empty($diagnostics['test_delivery_enabled']),'detail'=>'This gate enables only the fixed administrator test recipient.'],
    ['label'=>'Campaign delivery gate','passed'=>!empty($provider['delivery_enabled']),'detail'=>'Independent from provider test delivery.'],
    ['label'=>'Worker gate','passed'=>!empty($provider['worker_enabled']),'detail'=>'Independent from provider test delivery.'],
];
labs_page_start($page);
?>
<section class="labs-product-hero">
  <article class="labs-product-hero-main">
    <span class="labs-product-kicker">Controlled email delivery</span>
    <h1>Verify the provider before enabling any campaign email.</h1>
    <p>The built-in Resend adapter sends plain-text messages through one fixed HTTPS endpoint. Credentials, recipient addresses, provider payloads, and full provider message IDs are never shown here.</p>
  </article>
  <aside class="labs-product-next">
    <div><span>Provider readiness</span><h2><?php echo !empty($provider['configured']) ? 'Configured' : 'Blocked'; ?></h2><p><?php echo labs_e((string)$provider['provider_name']); ?> · campaign delivery <?php echo !empty($provider['can_process']) ? 'ready' : 'disabled'; ?></p></div>
    <a class="labs-btn" href="<?php echo labs_url('/admin/email-webhooks.php'); ?>">Email Webhooks</a>
  </aside>
</section>
<?php if (is_array($flash)): ?><div class="labs-alert is-<?php echo labs_e((string)($flash['tone'] ?? 'info')); ?>" role="status"><?php echo labs_e((string)($flash['message'] ?? '')); ?></div><?php endif; ?>
<section class="labs-product-layout">
  <article class="labs-product-card">
    <div class="labs-product-card-head"><div><span class="labs-product-kicker">Provider diagnostics</span><h2>Configuration and safety gates</h2><p>These checks expose presence and validity only. Secret values and recipient addresses remain private.</p></div><span class="labs-product-status is-<?php echo !empty($provider['configured']) ? 'success' : 'danger'; ?>"><?php echo !empty($provider['configured']) ? 'ready' : 'blocked'; ?></span></div>
    <div class="labs-readiness-list">
      <?php foreach ($checks as $check): ?>
        <div class="labs-readiness-row"><div><strong><?php echo labs_e((string)$check['label']); ?></strong><p><?php echo labs_e((string)$check['detail']); ?></p></div><span class="labs-product-status is-<?php echo !empty($check['passed']) ? 'success' : 'neutral'; ?>"><?php echo !empty($check['passed']) ? 'pass' : 'pending'; ?></span></div>
      <?php endforeach; ?>
    </div>
  </article>
  <aside class="labs-product-stack">
    <article class="labs-product-card">
      <span class="labs-product-kicker">Safe test</span><h2>Send one administrator test</h2>
      <p>The recipient is fixed in protected configuration. The form accepts no address and contains no participant, campaign, proof, reward, wallet, claim, or provider credential data.</p>
      <form action="<?php echo labs_url('/admin/email-provider-action.php'); ?>" method="post" class="labs-actions">
        <?php echo tl_security_csrf_field(); ?>
        <input type="hidden" name="provider_action" value="send_test">
        <button class="labs-btn labs-btn-primary" type="submit"<?php echo empty($provider['can_test']) ? ' disabled' : ''; ?>>Send Provider Test</button>
      </form>
      <?php if (empty($provider['can_test'])): ?><p class="labs-help">Configure the provider, fixed test recipient, and test-delivery gate before this button becomes available.</p><?php endif; ?>
    </article>
    <article class="labs-product-card">
      <span class="labs-product-kicker">Delivery reconciliation</span><h2>Provider acceptance is not final delivery.</h2>
      <p>After the Section 17 migration and signed endpoint are enabled, use webhook monitoring to confirm sent, delivered, delayed, bounced, complained, failed, and suppressed outcomes.</p>
      <a class="labs-btn" href="<?php echo labs_url('/admin/email-webhooks.php'); ?>">Open Webhook Monitoring</a>
    </article>
    <article class="labs-product-card">
      <span class="labs-product-kicker">Activation boundary</span><h2>Test success does not enable campaigns.</h2>
      <p>Campaign email still requires the global delivery gate, worker gate, an active campaign pilot, campaign email enabled, recipient preferences, suppression checks, and clean webhook acceptance.</p>
      <a class="labs-btn" href="<?php echo labs_url('/admin/pilot-communications.php'); ?>">Pilot Communications</a>
    </article>
  </aside>
</section>
<section class="labs-safe-note">The adapter permits only <code>https://api.resend.com/emails</code>, does not follow redirects, verifies TLS, caps response size, uses an idempotency key, and stores only normalized status codes plus a SHA-256 hash of a successful provider message ID.</section>
<?php labs_page_end(['section'=>'admin']); ?>
