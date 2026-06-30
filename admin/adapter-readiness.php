<?php
require_once __DIR__ . '/../includes/labs-layout.php';
require_once __DIR__ . '/../includes/training-lab-stage34-service.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
require_once __DIR__ . '/../includes/training-lab-stage884-real-read-adapter.php';
require_once __DIR__ . '/../includes/training-lab-stage884-real-read-render.php';
require_once __DIR__ . '/../includes/training-lab-stage883-readonly-adapter.php';

$userId = max(0, (int)($_GET['user_id'] ?? 0));
labs_page_start(['title' => 'Adapter Readiness | Training Lab', 'section' => 'admin', 'active' => 'admin-adapter-readiness']);
?>
<?php if (function_exists('tl_design_render_logged_in_template')) tl_design_render_logged_in_template('admin-adapter-readiness'); ?>
<?php tl_stage884_render_real_read_adapter($userId); ?>
<?php labs_page_end(['section' => 'admin']); ?>
