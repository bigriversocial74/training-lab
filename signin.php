<?php
require_once __DIR__ . '/includes/training-lab-public-template.php';
require_once __DIR__ . '/includes/training-lab-account-bridge.php';
$result = null;
$error = null;
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    try {
        $raw = tl_security_request_data(false);
        $action = preg_replace('/[^a-z0-9_\-]/i', '', (string)($raw['auth_action'] ?? 'training_login'));
        tl_security_guard_auth_action($action, $raw);
        $result = tl_account_bridge_handle_auth_action(tl_security_normalize_auth_input($raw));
    } catch (Throwable $e) {
        [$payload] = tl_security_error_payload($e);
        $error = (string)$payload['error'];
    }
}
tl_public_site_header('Sign In | Training Lab', 'Sign in to Training Lab.', 'signin', 'Create account', '/signup.php');
?>
<main id="main-content" class="tl-container tl-auth-page">
  <section class="tl-auth-left">
    <h1>Welcome back.</h1>
    <p>Continue your challenge, track progress, and manage verified Training Lab rewards through your connected account.</p>
    <div class="tl-auth-illustration"><?php echo tl_public_img('auth_guy', '', 'Welcome back to Training Lab'); ?></div>
    <div class="tl-secure-strip">
      <div><?php echo tl_public_img('flame'); ?><span><strong>Continue your challenge</strong><br>Jump back in and stay consistent.</span></div>
      <div><?php echo tl_public_img('growth'); ?><span><strong>Track progress</strong><br>See streaks, steps, and milestones.</span></div>
      <div><?php echo tl_public_img('gift'); ?><span><strong>Unlock rewards</strong><br>Earn perks for verified action.</span></div>
    </div>
  </section>
  <aside class="tl-auth-form-card">
    <h2>Sign in</h2>
    <p class="tl-note">Use your connected Microgifter session. Demo participant sessions are available only on approved non-production deployments.</p>
    <?php tl_public_auth_status($error, is_array($result) ? $result : null); ?>
    <?php if (tl_security_demo_login_allowed()): ?>
      <form method="post">
        <?php echo tl_security_csrf_field(); ?>
        <input type="hidden" name="auth_action" value="training_login">
        <label>Email<input type="email" name="email" required autocomplete="email" placeholder="you@example.com"></label>
        <button class="tl-btn tl-btn-primary tl-btn-full" type="submit">Open participant session</button>
      </form>
      <div class="tl-auth-divider">or</div>
    <?php endif; ?>
    <form method="post">
      <?php echo tl_security_csrf_field(); ?>
      <input type="hidden" name="auth_action" value="sync_microgifter">
      <button class="tl-btn tl-btn-secondary tl-btn-full" type="submit">Continue with Microgifter</button>
    </form>
    <p class="tl-auth-bottom">Need an account? <a href="<?php echo tl_public_e(labs_url('/signup.php')); ?>">Create one</a></p>
    <div class="tl-auth-secure"><?php echo tl_public_img('verified'); ?><span>Privileged Training Lab actions require a trusted Microgifter role, CSRF validation, and an authenticated session.</span></div>
  </aside>
</main>
<?php tl_public_site_footer(); ?>
