<?php
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $result = tl_training_handle_app_action(tl_request_data() + ['training_action' => 'run_core_workflow_qa', 'confirm_training_action' => '1', 'log_event' => '1']);
        tl_app_json(['ok' => true, 'data' => $result]);
    }
    tl_app_json(['ok' => true, 'data' => tl_stage200_run_core_qa(['user_id' => max(0, (int)($_GET['user_id'] ?? 0))])]);
} catch (Throwable $e) {
    tl_app_json(['ok' => false, 'error' => $e->getMessage()], 400);
}
