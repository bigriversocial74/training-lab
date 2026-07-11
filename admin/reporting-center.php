<?php
require_once __DIR__ . '/../includes/labs-layout.php';
$page=['title'=>'Analytics | Training Lab','section'=>'admin','active'=>'admin-analytics','required_role'=>'manager'];
tl_product_require_page_access($page);
tl_product_redirect('/admin/analytics.php');
