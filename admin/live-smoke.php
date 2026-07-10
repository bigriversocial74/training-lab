<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-stage34-service.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
require_once __DIR__ . '/../includes/training-lab-stage881-deployment-acceptance.php';
require_once __DIR__ . '/../includes/training-lab-stage882-live-smoke.php';

labs_page_start(['title' => 'Live Smoke | Training Lab', 'section' => 'admin', 'active' => 'admin-live-smoke']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-live-smoke'); ?>
<?php tl_stage882_render_live_smoke(); ?>
<section class="labs-card">
  <h2>Next: Production Runtime Acceptance</h2>
  <p class="labs-copy">Run the protected post-deployment gate for authorization, CSRF, schema, workflow consistency, and same-origin live route checks.</p>
  <a class="labs-btn labs-btn-primary" href="<?php echo htmlspecialchars(labs_url('/admin/runtime-acceptance.php'), ENT_QUOTES, 'UTF-8'); ?>">Open Runtime Acceptance</a>
</section>
<?php labs_page_end(['section' => 'admin']); ?>
