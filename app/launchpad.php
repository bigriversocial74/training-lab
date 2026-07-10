<?php
require_once __DIR__ . '/../includes/labs-layout.php';

$page = ['title' => 'My Training | Training Lab', 'section' => 'app', 'active' => 'app-dashboard', 'required_role' => 'participant'];
tl_product_require_page_access($page);
$campaign = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($_GET['campaign'] ?? '')) ?: '';
$destination = '/app/index.php' . ($campaign !== '' ? '?campaign=' . rawurlencode($campaign) : '');
tl_product_redirect($destination);
