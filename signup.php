<?php
require_once __DIR__ . '/includes/training-lab-public-template.php';
require_once __DIR__ . '/includes/training-lab-account-bridge.php';
$result = null;
$error = null;
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    try {
        $raw = tl_security_request_data(false);
        $action = preg_replace('/[^a-z0-9_\-]/i', '', (string)($raw['auth_action'] ?? 'create_training_and_microgifter'));
        tl_security_guard_auth_action($action, $raw);
        $result = tl_account_bridge_handle_auth_action(tl_security_normalize_auth_input($raw));
    } catch (Throwable $e) {
        [$payload] = tl_security_error_payload($e);
        $error = (string)$payload['error'];
    }
}
tl_public_site_header('Create Account | Training Lab', 'Create your Training Lab account.', 'signup', 'Sign In', '/signin.php');
?>
<main id="main-content" class="tl-container tl-auth-page">
  <section class="tl-auth-left">
    <h1>Create your<br>Training Lab account.</h1>
    <p>Launch action-based training, verify proof, track progress, and reward consistency through the Microgifter account adapter.</p>
    <div class="tl-auth-illustration"><?php echo tl_public_img('hero_task_reward', '', 'Create a Training Lab challenge'); ?></div>
    <div class="tl-auth-feature-list">
      <?php echo tl_public_feature_item('check_list', 'Create a challenge', 'Set up campaigns and define the actions that matter.'); ?>
      <?php echo tl_public_feature_item('upload', 'Submit proof records', 'Participants submit text or approved external links for review.'); ?>
      <?php echo tl_public_feature_item('gift', 'Reward verified action', 'Create eligibility events while production issuing remains adapter-gated.'); ?>
    </div>
  </section>
  <aside class="tl-auth-form-card">
    <h2>Create account</h2>
    <p class="tl-note">The connected Microgifter adapter must be available to create a production account. Otherwise the request remains adapter-pending.</p>
    <?php tl_public_auth_status($error, is_array($result) ? $result : null); ?>
    <form method="post">
      <?php echo tl_security_csrf_field(); ?>
      <input type="hidden" name="auth_action" value="create_training_and_microgifter">
      <label>Full name<input name="name" required maxlength="180" autocomplete="name" placeholder="Enter your full name"></label>
      <label>Work email<input type="email" name="email" required maxlength="254" autocomplete="email" placeholder="you@company.com"></label>
      <label>Password for connected Microgifter adapter<input type="password" name="password" minlength="12" maxlength="200" autocomplete="new-password" placeholder="At least 12 characters"></label>
      <label>Team or organization <span class="tl-muted-text">(optional)</span><input name="organization" maxlength="180" autocomplete="organization" placeholder="e.g. Acme Inc."></label>
      <div class="tl-auth-line"><label><input type="checkbox" required> I understand production account creation occurs only through the connected Microgifter adapter.</label></div>
      <button class="tl-btn tl-btn-primary tl-btn-full" type="submit">Create connected account</button>
    </form>
    <div class="tl-auth-divider">or</div>
    <form method="post">
      <?php echo tl_security_csrf_field(); ?>
      <input type="hidden" name="auth_action" value="sync_microgifter">
      <button class="tl-btn tl-btn-secondary tl-btn-full" type="submit">Use existing Microgifter session</button>
    </form>
    <p class="tl-auth-bottom">Already have an account? <a href="<?php echo tl_public_e(labs_url('/signin.php')); ?>">Sign in</a></p>
  </aside>
</main>
<?php tl_public_site_footer(); ?>
