<?php
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
$campaign = (string)($_GET['campaign'] ?? '');
$userId = max(0, (int)($_GET['user_id'] ?? 0));
tl_app_json(['ok' => true, 'data' => tl_app_flow_summary(), 'workflow_state' => tl_stage200_workflow_state($campaign, $userId)]);
