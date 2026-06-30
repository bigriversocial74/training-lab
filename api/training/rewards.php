<?php
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
require_once __DIR__ . '/../../includes/training-lab-microgifter-rewards.php';
try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $result = tl_training_handle_app_action(tl_request_data());
        $userId = max(1, (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0));
        tl_app_json(['ok' => true, 'data' => $result, 'rewards' => tl_mg_stage160_user_summary($userId ?: null), 'workflow_state' => tl_stage200_workflow_state('', $userId)]);
    }
    $userId = max(1, (int)($_GET['user_id'] ?? 0));
    tl_app_json(['ok' => true, 'data' => tl_mg_stage160_user_summary($userId ?: null), 'workflow_state' => tl_stage200_workflow_state('', $userId)]);
} catch (Throwable $e) {
    tl_app_json(['ok' => false, 'error' => $e->getMessage()], 400);
}
