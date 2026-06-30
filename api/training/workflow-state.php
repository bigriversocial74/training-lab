<?php
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
try {
    $campaign = (string)($_GET['campaign'] ?? $_POST['campaign'] ?? '');
    $userId = max(0, (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0));
    tl_app_json(['ok' => true, 'data' => tl_stage200_workflow_state($campaign, $userId)]);
} catch (Throwable $e) {
    tl_app_json(['ok' => false, 'error' => $e->getMessage()], 400);
}
