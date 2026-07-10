<?php
require_once __DIR__ . '/../includes/labs-layout.php';

$page = ['title' => 'Task | Training Lab', 'section' => 'app', 'active' => 'app-task-runner', 'required_role' => 'participant'];
tl_product_require_page_access($page);
$campaign = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($_GET['campaign'] ?? '')) ?: '';
$task = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($_GET['task'] ?? '')) ?: '';
$query = [];
if ($campaign !== '') $query['campaign'] = $campaign;
if ($task !== '') $query['task'] = $task;
tl_product_redirect('/app/task-runner.php' . ($query ? '?' . http_build_query($query) : ''));
