<?php
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
require_once __DIR__ . '/../../includes/training-lab-microgifter-rewards.php';
try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $result = tl_training_handle_app_action(tl_request_data());
        tl_app_json(['ok' => true, 'data' => $result, 'bridge' => tl_mg_stage160_bridge_summary()]);
    }
    tl_app_json(['ok' => true, 'data' => tl_mg_stage160_bridge_summary()]);
} catch (Throwable $e) {
    tl_app_json(['ok' => false, 'error' => $e->getMessage()], 400);
}
