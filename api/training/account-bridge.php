<?php
require_once __DIR__ . '/../../includes/training-lab-account-bridge.php';
try {
    $input = tl_request_data();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !empty($input['auth_action'])) {
        $result = tl_account_bridge_handle_auth_action($input);
    } else {
        $result = null;
    }
    tl_stage34_json(['ok' => true, 'action_result' => $result, 'account_bridge' => tl_account_bridge_current_context()]);
} catch (Throwable $e) {
    tl_stage34_json(['ok' => false, 'error' => $e->getMessage(), 'account_bridge' => tl_account_bridge_current_context()], 400);
}
