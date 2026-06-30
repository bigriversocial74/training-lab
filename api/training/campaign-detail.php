<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
$id = isset($_GET['id']) ? preg_replace('/[^a-z0-9\-]/i', '', $_GET['id']) : 'movement-5';
tl_stage34_json(['campaign' => tl_stage34_campaign($id), 'tasks' => tl_stage34_tasks($id)]);
