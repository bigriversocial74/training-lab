<?php
require_once __DIR__ . '/includes/training-lab-public-template.php';
require_once __DIR__ . '/includes/training-lab-account-bridge.php';
$result = null; $error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try { $result = tl_account_bridge_handle_auth_action(tl_request_data()); }
    catch (Throwable $e) { $error = $e->getMessage(); }
}
tl_public_site_header('Create Account | Training Lab', 'Create your Training Lab account.', 'signup', 'Sign In', '/signin.php');
?>
<main class="tl-container tl-auth-page">
  <section class="tl-auth-left">
    <h1>Create your<br>Training Lab account.</h1>
    <p>Launch action-based training in minutes. Verify proof, track progress, and reward consistency that drives real results.</p>
    <div class="tl-auth-illustration"><?php echo tl_public_img('hero_task_reward', '', 'Create a Training Lab challenge'); ?></div>
    <div class="tl-auth-feature-list">
      <?php echo tl_public_feature_item('check_list', 'Create a challenge', 'Set up campaigns and define the actions that matter.'); ?>
      <?php echo tl_public_feature_item('upload', 'Upload proof', 'Participants submit photo, video, or documents as proof.'); ?>
      <?php echo tl_public_feature_item('gift', 'Reward verified action', 'Automate rewards and celebrate consistent progress.'); ?>
    </div>
  </section>
  <aside class="tl-auth-form-card">
    <h2>Sign up for free</h2>
    <p class="tl-note">No credit card required.</p>
    <?php tl_public_auth_status($error, is_array($result) ? $result : null); ?>
    <form method="post">
      <input type="hidden" name="auth_action" value="create_training_and_microgifter">
      <input type="hidden" name="role" value="participant">
      <label>Full name<input name="name" required placeholder="Enter your full name"></label>
      <label>Work email<input type="email" name="email" required placeholder="you@company.com"></label>
      <label>Password<input type="password" name="password" placeholder="Create a strong password"></label>
      <label>Team or organization <span class="tl-muted-text">(optional)</span><input name="organization" placeholder="e.g. Acme Inc."></label>
      <div class="tl-auth-line"><label><input type="checkbox" required> I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.</label></div>
      <button class="tl-btn tl-btn-primary tl-btn-full" type="submit">Create account</button>
    </form>
    <div class="tl-auth-divider">or</div>
    <form method="post">
      <input type="hidden" name="auth_action" value="sync_microgifter">
      <button class="tl-btn tl-btn-secondary tl-btn-full" type="submit">Sign up with Microgifter</button>
    </form>
    <p class="tl-auth-bottom">Already have an account? <a href="<?php echo tl_public_e(labs_url('/signin.php')); ?>">Sign in</a></p>
  </aside>
</main>
<?php tl_public_site_footer(); ?>
