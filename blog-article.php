<?php
require_once __DIR__ . '/includes/training-lab-public-template.php';
tl_public_site_header('The Science of Consistency | Training Lab', 'Featured Training Lab article.', 'blog', 'Sign In', '/signin.php');
?>
<main class="tl-container tl-article-layout">
  <article class="tl-article-main">
    <span class="tl-tag">FEATURED</span>
    <h1>The Science of Consistency</h1>
    <div class="tl-blog-meta"><span>Team Training Lab</span><span>May 6, 2025</span><span>6 min read</span></div>
    <?php echo tl_public_img('blog_article', '', 'The Science of Consistency'); ?>
    <p>Consistency works because it removes uncertainty. When a task, proof requirement, and reward are clearly defined, participants know exactly what action matters and how progress will be measured.</p>
    <p>Training Lab turns that loop into a product workflow: launch the challenge, complete the action, submit proof, review the result, and reward verified behavior.</p>
    <p>The goal is not more content. The goal is repeatable action that creates a measurable record of improvement.</p>
  </article>
  <aside class="tl-article-sidebar"><div class="tl-panel-card"><h3>Related</h3><p><a class="tl-text-link" href="<?php echo tl_public_e(labs_url('/blog.php')); ?>">Back to all articles</a></p></div><div class="tl-panel-card"><?php echo tl_public_img('hero_task_reward'); ?><h3>Proof-based training</h3><p>Use tasks, proof, reviews, and rewards to build better habits.</p></div></aside>
</main>
<?php tl_public_site_footer(); ?>
