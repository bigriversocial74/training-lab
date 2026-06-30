<?php
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        throw new RuntimeException('Training app actions require POST.');
    }
    $result = tl_training_handle_app_action(tl_request_data());
    tl_app_json(['ok' => true, 'data' => $result, 'flow' => tl_app_flow_summary()]);
} catch (Throwable $e) {
    tl_app_json(['ok' => false, 'error' => $e->getMessage()], 400);
}
