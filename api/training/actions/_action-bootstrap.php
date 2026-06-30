<?php
require_once __DIR__ . '/../../../includes/training-lab-actions.php';
function tl_action_wrap(callable $fn): void {
    try {
        $result = $fn(tl_request_data());
        tl_json_response(['ok' => true, 'mode' => tl_db_ready() ? 'database' : 'demo-fallback', 'data' => $result]);
    } catch (Throwable $e) {
        tl_json_response(['ok' => false, 'error' => $e->getMessage()], 400);
    }
}
