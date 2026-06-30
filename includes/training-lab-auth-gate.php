<?php
/**
 * Microgifter Training Lab auth gate extension.
 *
 * Add-only stage file. Existing template pages are not modified by this package.
 *
 * Purpose:
 * - Start a Training Lab session.
 * - Detect a session user.
 * - Provide role checks for new auth test/workflow pages.
 * - Return JSON auth status from new endpoints.
 *
 * This does NOT:
 * - create auth database tables
 * - store passwords
 * - replace existing Microgifter auth
 * - change the working DB config
 */

if (!function_exists('tl_auth_session_start')) {
    function tl_auth_session_start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            session_name('microgifter_training_lab');
            session_start();
        }
    }
}

if (!function_exists('tl_auth_safe_path')) {
    function tl_auth_safe_path(?string $path, string $fallback = '/admin/index.php'): string
    {
        $path = trim((string)$path);

        if ($path === '' || strpos($path, "\n") !== false || strpos($path, "\r") !== false) {
            return $fallback;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $fallback;
        }

        if ($path[0] !== '/') {
            return $fallback;
        }

        return $path;
    }
}

if (!function_exists('tl_auth_existing_microgifter_user')) {
    function tl_auth_existing_microgifter_user(): ?array
    {
        tl_auth_session_start();

        $possibleUserKeys = [
            'microgifter_user_id',
            'mg_user_id',
            'auth_user_id',
            'logged_in_user_id',
            'user_id',
        ];

        foreach ($possibleUserKeys as $key) {
            if (!empty($_SESSION[$key])) {
                $role = 'participant';

                if (!empty($_SESSION['is_admin']) || !empty($_SESSION['admin_user_id'])) {
                    $role = 'admin';
                } elseif (!empty($_SESSION['role'])) {
                    $role = strtolower((string)$_SESSION['role']);
                } elseif (!empty($_SESSION['user_role'])) {
                    $role = strtolower((string)$_SESSION['user_role']);
                }

                if (in_array($role, ['owner', 'manager', 'merchant_admin'], true)) {
                    $role = 'admin';
                }

                return [
                    'id' => (string)$_SESSION[$key],
                    'name' => (string)($_SESSION['name'] ?? $_SESSION['user_name'] ?? $_SESSION['email'] ?? 'Microgifter User'),
                    'email' => (string)($_SESSION['email'] ?? $_SESSION['user_email'] ?? ''),
                    'role' => $role === 'admin' ? 'admin' : 'participant',
                    'source' => 'existing_microgifter_session',
                ];
            }
        }

        return null;
    }
}

if (!function_exists('tl_auth_current_user')) {
    function tl_auth_current_user(): ?array
    {
        tl_auth_session_start();

        if (!empty($_SESSION['training_lab_user']) && is_array($_SESSION['training_lab_user'])) {
            return $_SESSION['training_lab_user'];
        }

        return tl_auth_existing_microgifter_user();
    }
}

if (!function_exists('tl_auth_login_session')) {
    function tl_auth_login_session(array $input): array
    {
        tl_auth_session_start();

        $email = trim((string)($input['email'] ?? 'training-user@microgifter.local'));
        $name = trim((string)($input['name'] ?? $input['display_name'] ?? 'Training Lab User'));
        $role = strtolower(trim((string)($input['role'] ?? 'participant')));

        if (function_exists('tl_account_bridge_normalize_role')) {
            $role = tl_account_bridge_normalize_role($role);
        } elseif (!in_array($role, ['participant', 'admin'], true)) {
            $role = 'participant';
        }

        if ($email === '') {
            $email = 'training-user@microgifter.local';
        }

        if ($name === '') {
            $name = $email;
        }

        $user = [
            'id' => 'tl-' . substr(hash('sha256', $email . '|' . $role), 0, 12),
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'source' => 'training_lab_session_only',
            'logged_in_at' => gmdate('c'),
        ];

        $_SESSION['training_lab_user'] = $user;

        return $user;
    }
}

if (!function_exists('tl_auth_logout_session')) {
    function tl_auth_logout_session(): void
    {
        tl_auth_session_start();
        unset($_SESSION['training_lab_user']);
    }
}

if (!function_exists('tl_auth_role_allowed')) {
    function tl_auth_role_allowed(?array $user, string $requiredRole = 'participant'): bool
    {
        if (!$user) {
            return false;
        }

        $role = strtolower((string)($user['role'] ?? 'participant'));

        if ($requiredRole === 'admin') {
            return $role === 'admin';
        }

        return in_array($role, ['participant', 'coach', 'reviewer', 'manager', 'admin'], true);
    }
}

if (!function_exists('tl_auth_require_page')) {
    function tl_auth_require_page(string $requiredRole = 'participant'): ?array
    {
        $user = tl_auth_current_user();

        if (tl_auth_role_allowed($user, $requiredRole)) {
            return $user;
        }

        return null;
    }
}

if (!function_exists('tl_auth_json')) {
    function tl_auth_json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
