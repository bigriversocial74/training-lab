<?php
/** Shared request/bootstrap helpers for Training Lab HTTP routes. */
require_once __DIR__ . '/training-lab-security.php';
require_once __DIR__ . '/training-lab-auth-gate.php';

if (!function_exists('tl_route_action_name')) {
    function tl_route_action_name(?string $fallback = null): string
    {
        $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''), '.php');
        $action = str_replace('-', '_', strtolower($script));
        return preg_replace('/[^a-z0-9_]/', '', $action) ?: ($fallback ?: 'training_action');
    }
}

if (!function_exists('tl_route_write_input')) {
    function tl_route_write_input(?string $action = null): array
    {
        $data = tl_security_request_data(false);
        $action = $action ?: (string)($data['training_action'] ?? $data['action'] ?? tl_route_action_name());
        $action = preg_replace('/[^a-z0-9_\-]/i', '', $action) ?: 'training_action';
        $user = tl_security_guard_write($action, $data);
        return tl_security_apply_actor($data, $user);
    }
}

if (!function_exists('tl_route_auth_input')) {
    function tl_route_auth_input(?string $action = null): array
    {
        $data = tl_security_request_data(false);
        $action = $action ?: (string)($data['auth_action'] ?? $data['action'] ?? tl_route_action_name());
        $action = preg_replace('/[^a-z0-9_\-]/i', '', $action) ?: 'training_login';
        tl_security_guard_auth_action($action, $data);
        return tl_security_normalize_auth_input($data);
    }
}
