<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-stage34-service.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
require_once __DIR__ . '/../includes/training-lab-stage881-deployment-acceptance.php';

labs_page_start(['title' => 'Deployment Acceptance | Training Lab', 'section' => 'admin', 'active' => 'admin-deployment-acceptance']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-deployment-acceptance'); ?>
<?php tl_stage881_render_deployment_acceptance(); ?>
<?php labs_page_end(['section' => 'admin']); ?>
