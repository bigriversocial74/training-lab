<?php
require_once __DIR__ . '/../includes/labs-layout.php';
$query = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
tl_product_redirect('/admin/campaign-builder.php' . ($query !== '' ? '?' . $query : ''), 302);
