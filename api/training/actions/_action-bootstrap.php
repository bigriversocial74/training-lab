<?php
require_once __DIR__ . '/../../../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../../../includes/training-lab-actions.php';

if (!function_exists('tl_action_wrap')) {
    function tl_action_wrap(callable $fn): void
    {
        try {
            $action = tl_route_action_name();
            $data = tl_route_write_input($action);
            $result = $fn($data);
            tl_security_json_response([
                'ok'=>true,
                'mode'=>tl_db_ready() ? 'database' : 'demo-fallback',
                'data'=>$result,
            ]);
        } catch (Throwable $e) {
            tl_security_json_exception($e);
        }
    }
}
