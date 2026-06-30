<?php
require_once __DIR__ . '/includes/training-lab-public-template.php';
require_once __DIR__ . '/includes/training-lab-account-bridge.php';
require_once __DIR__ . '/includes/training-lab-app-service.php';
$result = null; $error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try { $result = tl_account_bridge_handle_auth_action(tl_request_data()); }
    catch (Throwable $e) { $error = $e->getMessage(); }
}
$ctx = tl_account_bridge_current_context();
$user = $ctx['user'] ?? null;
tl_public_site_header('Account | Training Lab', 'Your shared Microgifter and Training Lab account.', 'account', $user ? 'Open App' : 'Sign In', $user ? '/app/index.php' : '/signin.php');
?>
<?php if (function_exists('tl_stage800_render_merchant_account_bridge')) tl_stage800_render_merchant_account_bridge(); ?>
<?php if (function_exists('tl_stage840_render_customer_account_bridge')) tl_stage840_render_customer_account_bridge(max(0, (int)($_GET['user_id'] ?? 0))); ?>
<?php if (function_exists('tl_stage880_render_adapter_configuration_center')) tl_stage880_render_adapter_configuration_center(); ?>
<?php if (function_exists('tl_stage880_render_identity_matching')) tl_stage880_render_identity_matching(max(0, (int)($_GET['user_id'] ?? 0))); ?>
<main class="tl-container tl-page-shell">
  <section class="tl-account-hero">
    <div>
      <p class="tl-kicker">Account</p>
      <h1 class="tl-page-title">One account for Training Lab and Microgifter.</h1>
      <p class="tl-page-lead">Use the same account on labs.microgifter.com and microgifter.com. Training Lab keeps the session safe while the Microgifter adapter handles production account sync.</p>
      <div class="tl-account-actions">
        <form method="post"><input type="hidden" name="auth_action" value="sync_microgifter"><button class="tl-btn tl-btn-primary" type="submit">Sign in with Microgifter</button></form>
        <a class="tl-btn tl-btn-secondary" href="<?php echo tl_public_e(labs_url('/app/index.php')); ?>">Open App</a>
      </div>
    </div>
    <div class="tl-template-art"><?php echo tl_public_img('auth_guy', 'tl-template-main-art', 'Account visual'); ?></div>
  </section>
  <?php tl_public_auth_status($error, is_array($result) ? $result : null); ?>
  <section class="tl-account-kpis">
    <div><span>Status</span><strong><?php echo $user ? 'Signed In' : 'Guest'; ?></strong></div>
    <div><span>Role</span><strong><?php echo tl_public_e((string)($user['role'] ?? 'guest')); ?></strong></div>
    <div><span>Training ID</span><strong><?php echo (int)($user['numeric_user_id'] ?? 1); ?></strong></div>
    <div><span>Microgifter</span><strong><?php echo tl_public_e((string)($user['microgifter_account_status'] ?? 'not linked')); ?></strong></div>
  </section>
  <section class="tl-account-grid">
    <article class="tl-panel-card"><h2>Current identity</h2><pre class="tl-json-box"><?php echo tl_public_e(json_encode($user ?: ['authenticated' => false], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre><form method="post" class="tl-account-actions"><input type="hidden" name="auth_action" value="logout_training"><button class="tl-btn tl-btn-secondary" type="submit">Logout</button></form></article>
    <aside class="tl-panel-card"><h2>Role access</h2><p class="tl-page-lead" style="font-size:1rem">Your permissions follow the same account context across the lab workflow.</p><div class="tl-pill-tabs"><?php foreach (($ctx['permissions'] ?? []) as $perm): ?><span><?php echo tl_public_e((string)$perm); ?></span><?php endforeach; ?><?php if (empty($ctx['permissions'])): ?><span>Guest</span><?php endif; ?></div><a class="tl-btn tl-btn-primary" href="<?php echo tl_public_e(labs_url('/signin.php')); ?>">Sign in</a></aside>
  </section>
</main>
<?php tl_public_site_footer(); ?>
