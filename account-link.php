<?php
require_once __DIR__ . '/includes/labs-layout.php';
require_once __DIR__ . '/includes/training-lab-stage886-account-integration.php';

$result = null;
$error = null;
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    try {
        tl_security_rate_limit('stage886_browser_account_link', 20, 300);
        $input = tl_security_request_data(false);
        $token = trim((string)($input['identity_assertion'] ?? $input['token'] ?? ''));
        if ($token === '') throw new TlHttpException('Signed identity assertion is required.', 422, 'identity_token_required');
        $result = tl_stage886_accept_handoff($token);
    } catch (Throwable $e) {
        [$payload] = tl_security_error_payload($e);
        $error = (string)($payload['error'] ?? 'Account link failed.');
    }
}

labs_page_start(['title'=>'Connect Microgifter Account | Training Lab','section'=>'public','active'=>'account-link']);
?>
<section class="labs-page-title">
  <div><span class="labs-eyebrow">Stage 886</span><h1>Connect your Microgifter account</h1><p class="labs-copy">This page accepts a short-lived signed identity assertion from Microgifter. Passwords are never copied into Training Lab.</p></div>
</section>
<?php if ($result): ?>
<section class="labs-card labs-success-card"><h2>Account connected</h2><p class="labs-copy">Signed in as <?php echo labs_e((string)($result['user']['name'] ?? 'Microgifter User')); ?>.</p><a class="labs-btn labs-btn-primary" href="<?php echo labs_e(labs_url('/app/index.php')); ?>">Open Training Lab</a></section>
<?php elseif ($error): ?>
<section class="labs-card labs-error-card"><h2>Account link needs attention</h2><p class="labs-copy"><?php echo labs_e($error); ?></p></section>
<?php else: ?>
<section class="labs-card"><h2>Waiting for Microgifter</h2><p class="labs-copy">Open this page using the signed “Open Training Lab” action inside Microgifter.</p></section>
<?php endif; ?>
<section class="labs-safe-note">Signed assertion only. No password transfer, payment action, wallet mutation, claim/redeem mutation, or reward issuing occurs here.</section>
<?php labs_page_end(['section'=>'public']); ?>
