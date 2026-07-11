<?php
require_once __DIR__ . '/../includes/labs-layout.php';

$page = [
    'title' => 'Deployment Acceptance | Training Lab',
    'section' => 'admin',
    'active' => 'admin-deployment-acceptance',
    'required_role' => 'admin',
];
tl_product_require_page_access($page);
tl_product_redirect('/admin/live-acceptance.php', 302);
