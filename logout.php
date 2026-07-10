<?php
require_once __DIR__ . '/includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/includes/training-lab-public-template.php';

$next = tl_auth_safe_path($_GET['next'] ?? $_POST['next'] ?? '/signin.php', '/signin.php');
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    try {
        $data = tl_security_request_data(false);
        tl_security_guard_auth_action('logout_training', $data);
        tl_auth_logout_session();
        header('Location: ' . $next, true, 303);
        exit;
    } catch (Throwable $e) {
        [$payload] = tl_security_error_payload($e);
        $error = (string)$payload['error'];
    }
}

tl_public_site_header('Sign Out | Training Lab', 'Confirm Training Lab sign out.', '', 'Sign In', '/signin.php');
?>
<main id="main-content" class="tl-container tl-auth-page">
  <aside class="tl-auth-form-card" style="margin-inline:auto">
    <h1>Sign out?</h1>
    <p class="tl-note">This clears the Training Lab session. It does not alter your Microgifter account.</p>
    <?php if (!empty($error)): ?><div class="tl-form-alert tl-form-error"><strong>Sign-out issue</strong><p><?php echo tl_public_e($error); ?></p></div><?php endif; ?>
    <form method="post">
      <?php echo tl_security_csrf_field(); ?>
      <input type="hidden" name="next" value="<?php echo tl_public_e($next); ?>">
      <button class="tl-btn tl-btn-primary tl-btn-full" type="submit">Confirm sign out</button>
    </form>
  </aside>
</main>
<?php tl_public_site_footer(); ?>
