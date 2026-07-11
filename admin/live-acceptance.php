<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-production-readiness.php';

$page = [
    'title' => 'Production Readiness | Training Lab',
    'section' => 'admin',
    'active' => 'admin-live-acceptance',
    'required_role' => 'admin',
];
tl_product_require_page_access($page);
$report = tl_production_readiness_report();
labs_page_start($page);
?>
<section class="labs-product-hero">
  <article class="labs-product-hero-main">
    <span class="labs-product-kicker">Production deployment</span>
    <h1><?php echo $report['ready'] ? 'The target environment is ready for live acceptance.' : 'Production readiness is blocked.'; ?></h1>
    <p>Validate runtime, private configuration, database schema, product acceptance, release tools, and reward-delivery gates before replacing live files.</p>
  </article>
  <aside class="labs-product-next">
    <div>
      <span>Environment score</span>
      <h2><?php echo (int)$report['score']; ?>%</h2>
      <p><?php echo count($report['failed']); ?> blocking check<?php echo count($report['failed']) === 1 ? '' : 's'; ?>.</p>
    </div>
    <a class="labs-btn" href="<?php echo htmlspecialchars(labs_url('/admin/product-acceptance.php'), ENT_QUOTES, 'UTF-8'); ?>">Product Acceptance</a>
  </aside>
</section>

<section class="labs-product-stats" aria-label="Production readiness category summary">
<?php foreach ($report['categories'] as $name => $category): ?>
  <article class="labs-product-stat<?php echo (int)$category['percent'] < 100 ? ' is-action' : ''; ?>">
    <span><?php echo labs_e(ucwords(str_replace('_', ' ', (string)$name))); ?></span>
    <strong><?php echo (int)$category['percent']; ?>%</strong>
    <small><?php echo (int)$category['passed']; ?>/<?php echo (int)$category['total']; ?> checks</small>
  </article>
<?php endforeach; ?>
</section>

<section class="labs-product-card">
  <div class="labs-product-card-head">
    <div>
      <span class="labs-product-kicker">Environment checklist</span>
      <h2>Resolve every blocker before live deployment</h2>
      <p>This dashboard performs reads only. It does not change configuration, import SQL, run workers, issue rewards, or call Microgifter.</p>
    </div>
  </div>
  <div class="labs-acceptance-list">
  <?php foreach ($report['checks'] as $check): ?>
    <article class="labs-acceptance-row">
      <span class="labs-product-status is-<?php echo $check['passed'] ? 'success' : 'danger'; ?>"><?php echo $check['passed'] ? 'Pass' : 'Blocked'; ?></span>
      <div>
        <strong><?php echo labs_e((string)$check['label']); ?></strong>
        <p><?php echo labs_e((string)$check['detail']); ?></p>
      </div>
    </article>
  <?php endforeach; ?>
  </div>
</section>

<section class="labs-product-layout">
  <article class="labs-product-card">
    <span class="labs-product-kicker">Release package</span>
    <h2>Build and verify before upload</h2>
    <pre class="labs-command"><code>php ./bin/build-release-package.php --release=&lt;main-commit&gt;
php ./bin/verify-release-package.php --file=./dist/training-lab-&lt;main-commit&gt;.zip</code></pre>
    <p>The archive contains one outer <code>labs/</code> folder and never contains the active private config.</p>
  </article>
  <article class="labs-product-card">
    <span class="labs-product-kicker">Live smoke test</span>
    <h2>Run from the deployed server</h2>
    <pre class="labs-command"><code>php ./bin/product-acceptance.php
TL_PUBLIC_BASE_URL=https://labs.example.com \
php ./bin/live-acceptance.php --require-role-sessions</code></pre>
    <p>Provide valid role sessions through the protected environment variables documented in the deployment guide. Cookie values are never printed.</p>
  </article>
</section>

<section class="labs-safe-note">Keep every Stage 890–899 reward-processing, pilot, canary, and scheduler gate at its prior disabled or limited state during this product deployment.</section>
<?php labs_page_end(['section' => 'admin']); ?>
