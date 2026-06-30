<?php
require_once __DIR__ . '/includes/training-lab-public-template.php';
require_once __DIR__ . '/includes/training-lab-account-bridge.php';
$result = null; $error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try { $result = tl_account_bridge_handle_auth_action(tl_request_data()); }
    catch (Throwable $e) { $error = $e->getMessage(); }
}
tl_public_site_header('Sign In | Training Lab', 'Sign in to Training Lab.', 'signin', 'Create account', '/signup.php');
?>
<main class="tl-container tl-auth-page">
  <section class="tl-auth-left">
    <h1>Welcome back.</h1>
    <p>Pick up where you left off. Continue your challenge, track your progress, and claim rewards for verified action.</p>
    <div class="tl-auth-illustration"><?php echo tl_public_img('auth_guy', '', 'Welcome back to Training Lab'); ?></div>
    <div class="tl-secure-strip">
      <div><?php echo tl_public_img('flame'); ?><span><strong>Continue your challenge</strong><br>Jump back in and stay consistent.</span></div>
      <div><?php echo tl_public_img('growth'); ?><span><strong>Track progress</strong><br>See streaks, steps, and milestones.</span></div>
      <div><?php echo tl_public_img('gift'); ?><span><strong>Unlock rewards</strong><br>Earn perks for verified action.</span></div>
    </div>
  </section>
  <aside class="tl-auth-form-card">
    <h2>Sign in</h2>
    <p class="tl-note">Glad to see you again.</p>
    <?php tl_public_auth_status($error, is_array($result) ? $result : null); ?>
    <form method="post">
      <input type="hidden" name="auth_action" value="training_login">
      <input type="hidden" name="role" value="participant">
      <label>Email<input type="email" name="email" required placeholder="you@example.com"></label>
      <label>Password<input type="password" name="password" placeholder="Enter your password"></label>
      <div class="tl-auth-line" style="justify-content:space-between"><label><input type="checkbox"> Remember me</label><a href="#">Forgot password?</a></div>
      <button class="tl-btn tl-btn-primary tl-btn-full" type="submit">Sign in</button>
    </form>
    <div class="tl-auth-divider">or</div>
    <form method="post">
      <input type="hidden" name="auth_action" value="sync_microgifter">
      <button class="tl-btn tl-btn-secondary tl-btn-full" type="submit">Sign in with Microgifter</button>
    </form>
    <p class="tl-auth-bottom">Need an account? <a href="<?php echo tl_public_e(labs_url('/signup.php')); ?>">Create one</a></p>
    <div class="tl-auth-secure"><?php echo tl_public_img('verified'); ?><span>Secure access for participants and teams. Your data is safe with us.</span></div>
  </aside>
</main>
<?php tl_public_site_footer(); ?>
