<?php
require_once __DIR__ . '/includes/training-lab-public-template.php';
tl_public_site_header('Your Cart | Training Lab', 'Review your Training Lab plan selection.', 'cart', 'Sign In', '/signin.php');
?>
<main class="tl-container tl-page-shell">
  <section class="tl-cart-hero">
    <div><h1 class="tl-page-title">Your cart</h1><p class="tl-page-lead">Review your selection and ready your training program for success.</p><p><a class="tl-text-link" href="<?php echo tl_public_e(labs_url('/pricing.php')); ?>">← Continue shopping</a></p></div>
    <div><?php echo tl_public_img('hero_task_reward', '', 'Cart hero'); ?></div>
  </section>
  <section class="tl-two-col">
    <article class="tl-panel-card">
      <div class="tl-cart-table">
        <div class="tl-cart-item"><div class="tl-cart-icon"><?php echo tl_public_img('heart'); ?></div><div><h3>Team Plan <span class="tl-tag">Annual</span></h3><p>Everything your team needs to run proof-based training and drive consistent action.</p><div class="tl-blog-meta"><span>Up to 10 users</span><span>Billed annually</span><span>Save 20%</span></div></div><div class="tl-cart-price"><strong>$9.00</strong><small>/ user / month</small></div><div class="tl-cart-total"><strong>$108.00</strong><small>/ year</small></div><div class="tl-qty"><span>−</span><strong>10</strong><span>+</span></div></div>
        <div class="tl-cart-item"><div class="tl-cart-icon"><?php echo tl_public_img('upload'); ?></div><div><h3>Extra Proof Uploads</h3><p>Add more space to upload and store proof documents and media.</p><div class="tl-blog-meta"><span>100 GB</span><span>Renews annually</span></div></div><div class="tl-cart-price"><strong>$4.00</strong><small>/ month</small></div><div class="tl-cart-total"><strong>$48.00</strong><small>/ year</small></div><div class="tl-qty"><span>−</span><strong>1</strong><span>+</span></div></div>
      </div>
      <p><a class="tl-btn tl-btn-secondary" href="<?php echo tl_public_e(labs_url('/pricing.php')); ?>">＋ Add another plan or add-on</a></p>
      <p class="tl-page-lead" style="font-size:.95rem">Have a promo code? <a class="tl-text-link" href="<?php echo tl_public_e(labs_url('/checkout.php')); ?>">Apply at checkout.</a></p>
    </article>
    <aside class="tl-panel-card"><h2>Order summary</h2><div class="tl-summary-row"><span>Subtotal</span><strong>$156.00</strong></div><div class="tl-summary-row"><span>Discount (Annual)</span><strong>− $36.00</strong></div><div class="tl-summary-row total"><span>Total</span><strong>$120.00</strong></div><p class="tl-page-lead" style="font-size:.92rem">Billed annually. All prices in USD.</p><a class="tl-btn tl-btn-primary tl-btn-full" href="<?php echo tl_public_e(labs_url('/checkout.php')); ?>">Continue to checkout 🔒</a><p><a class="tl-btn tl-btn-secondary tl-btn-full" href="<?php echo tl_public_e(labs_url('/contact.php')); ?>">Request a quote</a></p><p class="tl-page-lead" style="font-size:.9rem">Secure checkout. Your data is safe with us.</p></aside>
  </section>
  <section class="tl-trust-band"><div><?php echo tl_public_img('verified'); ?><span><strong>Secure & Trusted</strong><p>Your payment and data are always protected.</p></span></div><div><?php echo tl_public_img('calendar'); ?><span><strong>Cancel Anytime</strong><p>No long-term contracts. Cancel anytime.</p></span></div><div><?php echo tl_public_img('heart'); ?><span><strong>Need Help?</strong><p>Our team is here to help you succeed.</p></span></div><div><?php echo tl_public_img('check_list'); ?><span><strong>Money-Back Guarantee</strong><p>Not satisfied? Get a full refund within 30 days.</p></span></div></section>
</main>
<?php tl_public_site_footer(); ?>
