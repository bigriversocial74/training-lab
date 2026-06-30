<?php
require_once __DIR__ . '/includes/training-lab-public-template.php';
tl_public_site_header('Checkout | Training Lab', 'Checkout for your Training Lab plan.', 'checkout', 'Sign In', '/signin.php');
?>
<main class="tl-container tl-page-shell">
  <section class="tl-cart-hero"><div><h1 class="tl-page-title">Checkout</h1><p class="tl-page-lead">Complete your Training Lab setup and prepare your team for proof-based progress.</p></div><div><?php echo tl_public_img('checkout_visual', '', 'Checkout visual'); ?></div></section>
  <section class="tl-checkout-grid">
    <article class="tl-panel-card"><h2>Billing details</h2><form class="tl-form-grid"><label>Full name<input placeholder="Enter your name"></label><label>Work email<input placeholder="you@company.com"></label><label class="full">Company<input placeholder="e.g. Acme Inc."></label><label>Plan<select><option>Team Plan Annual</option></select></label><label>Users<input value="10"></label><label class="full">Notes<textarea placeholder="Anything we should know?"></textarea></label><a class="tl-btn tl-btn-primary tl-btn-full full" href="<?php echo tl_public_e(labs_url('/success.php')); ?>">Complete setup</a></form></article>
    <aside class="tl-panel-card"><h2>Order summary</h2><div class="tl-summary-row"><span>Team Plan</span><strong>$108.00</strong></div><div class="tl-summary-row"><span>Extra uploads</span><strong>$48.00</strong></div><div class="tl-summary-row"><span>Discount</span><strong>− $36.00</strong></div><div class="tl-summary-row total"><span>Total</span><strong>$120.00</strong></div><div class="tl-auth-secure"><?php echo tl_public_img('verified'); ?><span>Secure checkout. No production payment processing is enabled in this training build.</span></div></aside>
  </section>
</main>
<?php tl_public_site_footer(); ?>
