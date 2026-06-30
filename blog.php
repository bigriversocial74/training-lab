<?php
require_once __DIR__ . '/includes/training-lab-public-template.php';
tl_public_site_header('Training Lab Blog | Microgifter', 'Training Lab insights and strategy.', 'blog', 'Sign In', '/signin.php');
?>
<main class="tl-container tl-page-shell">
  <section class="tl-blog-hero">
    <div><h1>Training Lab Blog</h1><p>Insights, strategies, and stories to help you build consistency, prove progress, earn rewards, and create habits that last with action-based training.</p></div>
    <div><?php echo tl_public_img('hero_task_reward', '', 'Training Lab blog'); ?></div>
  </section>
  <article class="tl-featured-post">
    <?php echo tl_public_img('blog_article', '', 'Featured article'); ?>
    <div><span class="tl-tag">FEATURED</span><h2>The Science of Consistency</h2><p>Why consistency matters more than motivation—and how small, repeatable actions compound into real results over time.</p><div class="tl-blog-meta"><span>Team Training Lab</span><span>May 6, 2025</span><span>6 min read</span></div><p style="margin-top:24px"><a class="tl-btn tl-btn-secondary" href="<?php echo tl_public_e(labs_url('/blog-article.php')); ?>">Read Article</a></p></div>
  </article>
  <div class="tl-pill-tabs"><span class="active">All Articles</span><span>Consistency</span><span>Proof & Verification</span><span>Rewards</span><span>Habits</span><span>Programs</span><span>Team Training</span></div>
  <section class="tl-blog-cards">
    <article><?php echo tl_public_img('blog_article', '', 'Consistency article'); ?><div><span class="tl-tag">Consistency</span><h3>7 Daily Habits That Build Long-Term Consistency</h3><p>Simple daily habits that help you stay on track and build momentum.</p><div class="tl-blog-meta"><span>Apr 28, 2025</span><span>5 min read</span></div></div></article>
    <article><?php echo tl_public_img('hero_task_reward', '', 'Proof-based training article'); ?><div><span class="tl-tag">Proof & Verification</span><h3>Why Proof-Based Training Drives Better Results</h3><p>How verified actions create accountability and unlock real progress.</p><div class="tl-blog-meta"><span>Apr 21, 2025</span><span>4 min read</span></div></div></article>
    <article><?php echo tl_public_img('receipt_visual', '', 'Rewards article'); ?><div><span class="tl-tag">Rewards</span><h3>Rewards That Reinforce the Right Behaviors</h3><p>Designing reward systems that motivate and sustain engagement.</p><div class="tl-blog-meta"><span>Apr 14, 2025</span><span>6 min read</span></div></div></article>
  </section>
  <section class="tl-section"><div class="tl-newsletter-cta"><?php echo tl_public_img('flask'); ?><div><h2>Get insights that drive results.</h2><p>Subscribe to the Training Lab newsletter for tips on consistency, verification, rewards, and building better habits.</p></div><form class="tl-form-inline"><input aria-label="Email" placeholder="Enter your email"><button class="tl-btn tl-btn-primary" type="button">Subscribe</button></form></div></section>
</main>
<?php tl_public_site_footer(); ?>
