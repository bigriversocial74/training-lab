<?php
require_once __DIR__ . '/includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/includes/training-lab-stage886-account-integration.php';
require_once __DIR__ . '/includes/training-lab-stage886-session-policy.php';
require_once __DIR__ . '/includes/labs-layout.php';

$result = null;
$error = null;
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'POST') {
    try {
        tl_security_headers(false);
        tl_security_rate_limit('stage886_account_link', 20, 300);
        $input = tl_security_request_data(false);
        $assertion = (string)($input['assertion'] ?? $input['token'] ?? '');
        $result = tl_stage886_apply_session_policy(tl_stage886_consume_assertion($assertion));
        $next = tl_auth_safe_path((string)($input['next'] ?? '/account.php?linked=1'), '/account.php?linked=1');
        if (!headers_sent()) {
            header('Location: ' . (function_exists('labs_url') ? labs_url($next) : $next), true, 303);
            exit;
        }
    } catch (Throwable $e) {
        [$payload] = tl_security_error_payload($e);
        $error = (string)($payload['error'] ?? 'The shared account link could not be completed.');
    }
} elseif ($method !== 'GET') {
    $error = 'This account-link receiver accepts GET instructions or a signed POST assertion.';
}

labs_page_start(['title'=>'Connect Microgifter Account | Training Lab','section'=>'public','active'=>'account-link']);
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Stage 886</span>
    <h1>Connect your Microgifter account.</h1>
    <p class="labs-copy">Open Training Lab from your signed-in Microgifter account. Microgifter sends a short-lived, one-time signed identity assertion; no password is copied.</p>
  </div>
  <div class="labs-actions"><a class="labs-btn labs-btn-primary" href="https://microgifter.com/">Return to Microgifter</a></div>
</section>
<?php if ($result): ?>
<section class="labs-card labs-success-card">
  <h2>Account connected</h2>
  <p class="labs-copy">Your trusted Training Lab session is active.</p>
  <a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url('/account.php'), ENT_QUOTES, 'UTF-8'); ?>">Open account</a>
</section>
<?php elseif ($error): ?>
<section class="labs-card labs-error-card">
  <h2>Account link needs attention</h2>
  <p class="labs-copy"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
  <p class="labs-copy">Return to Microgifter and choose <strong>Open Training Lab</strong> again. Assertions expire quickly and can only be used once.</p>
</section>
<?php else: ?>
<section class="labs-flow-grid">
  <article class="labs-card"><span class="labs-eyebrow">1</span><h2>Sign in to Microgifter</h2><p class="labs-copy">Your identity and approved role remain managed by Microgifter.</p></article>
  <article class="labs-card"><span class="labs-eyebrow">2</span><h2>Open Training Lab</h2><p class="labs-copy">Microgifter posts a signed assertion directly to this receiver.</p></article>
  <article class="labs-card"><span class="labs-eyebrow">3</span><h2>Continue training</h2><p class="labs-copy">Training Lab verifies the signature, blocks replay, and creates a trusted session.</p></article>
</section>
<?php endif; ?>
<section class="labs-safe-note">The assertion receiver does not process payments, mutate wallets, create claims, redeem rewards, or write to Microgifter password/authentication tables.</section>
<?php labs_page_end(['section'=>'public']); ?>
