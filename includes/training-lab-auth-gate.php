<?php
/** Training Lab session/auth bridge with trusted-role enforcement. */
require_once __DIR__ . '/training-lab-security.php';

if (!function_exists('tl_auth_session_start')) {
    function tl_auth_session_start(): void { tl_security_session_start(); }
}

if (!function_exists('tl_auth_safe_path')) {
    function tl_auth_safe_path(?string $path, string $fallback = '/admin/index.php'): string
    {
        $path = trim((string)$path);
        if ($path === '' || str_contains($path, "\n") || str_contains($path, "\r")) return $fallback;
        if (preg_match('#^(?:https?:)?//#i', $path) || !str_starts_with($path, '/')) return $fallback;
        if (str_contains($path, '\\') || str_contains($path, "\0")) return $fallback;
        return $path;
    }
}

if (!function_exists('tl_auth_clean_path')) {
    function tl_auth_clean_path(?string $path, string $fallback = '/admin/index.php'): string { return tl_auth_safe_path($path, $fallback); }
}

if (!function_exists('tl_auth_existing_microgifter_user')) {
    function tl_auth_existing_microgifter_user(): ?array
    {
        tl_auth_session_start();
        // Once Stage 886 signed identity is configured, raw legacy session fields are
        // no longer accepted as a trusted Microgifter identity source.
        if (function_exists('tl_stage886_ready') && tl_stage886_ready()) return null;
        foreach (['microgifter_user_id','mg_user_id','auth_user_id','logged_in_user_id','user_id'] as $key) {
            if (empty($_SESSION[$key])) continue;
            $role = strtolower((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'participant'));
            if (!empty($_SESSION['is_admin']) || !empty($_SESSION['admin_user_id'])) $role = 'admin';
            $aliases = ['owner'=>'admin','merchant_admin'=>'admin','merchant'=>'manager','operator'=>'manager','trainer'=>'coach','mentor'=>'coach','user'=>'participant'];
            $role = $aliases[$role] ?? $role;
            if (!in_array($role, ['participant','coach','reviewer','manager','admin'], true)) $role = 'participant';
            return [
                'id'=>(string)$_SESSION[$key],
                'microgifter_user_id'=>(string)$_SESSION[$key],
                'name'=>(string)($_SESSION['name'] ?? $_SESSION['user_name'] ?? $_SESSION['email'] ?? 'Microgifter User'),
                'email'=>(string)($_SESSION['email'] ?? $_SESSION['user_email'] ?? ''),
                'role'=>$role,
                'source'=>'existing_microgifter_session',
            ];
        }
        return null;
    }
}

if (!function_exists('tl_auth_current_user')) {
    function tl_auth_current_user(): ?array
    {
        tl_auth_session_start();
        $existing = tl_auth_existing_microgifter_user();
        if ($existing) return $existing;
        $local = $_SESSION['training_lab_user'] ?? null;
        if (!is_array($local)) return null;
        $source = (string)($local['source'] ?? 'training_lab_demo_session');
        if ($source === 'microgifter_adapter') {
            if (function_exists('tl_stage886_validate_current_session')) {
                return tl_stage886_validate_current_session($local);
            }
            $sessionExpiresAt = (int)($local['session_expires_at'] ?? $_SESSION['_tl_stage889_session_expires_at'] ?? 0);
            if ($sessionExpiresAt <= time()) {
                tl_auth_logout_session();
                return null;
            }
            $local['role'] = in_array((string)($local['role'] ?? ''), ['participant','coach','reviewer','manager','admin'], true) ? (string)$local['role'] : 'participant';
            return $local;
        }
        $local['role'] = 'participant';
        $local['source'] = $source;
        return $local;
    }
}

if (!function_exists('tl_auth_login_session')) {
    function tl_auth_login_session(array $input): array
    {
        if (!tl_security_demo_login_allowed()) throw new TlHttpException('Standalone Training Lab session login is disabled on this deployment. Use the connected Microgifter sign-in.', 403, 'demo_login_disabled');
        tl_auth_session_start();
        $email = strtolower(trim((string)($input['email'] ?? '')));
        $name = trim((string)($input['name'] ?? $input['display_name'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new TlHttpException('A valid email address is required.', 422, 'invalid_email');
        if ($name === '') $name = $email;
        $name = mb_substr($name, 0, 180);
        session_regenerate_id(true);
        $user = [
            'id'=>'tl-' . substr(hash('sha256', $email . '|participant'), 0, 18),
            'name'=>$name,
            'email'=>$email,
            'role'=>'participant',
            'source'=>'training_lab_demo_session',
            'logged_in_at'=>gmdate('c'),
        ];
        $_SESSION['training_lab_user'] = $user;
        return $user;
    }
}

if (!function_exists('tl_auth_logout_session')) {
    function tl_auth_logout_session(): void
    {
        tl_auth_session_start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'=>time() - 42000,
                'path'=>$params['path'] ?: '/',
                'domain'=>$params['domain'] ?? '',
                'secure'=>(bool)($params['secure'] ?? false),
                'httponly'=>true,
                'samesite'=>'Lax',
            ]);
        }
        session_destroy();
    }
}

if (!function_exists('tl_auth_logout')) {
    function tl_auth_logout(): void { tl_auth_logout_session(); }
}

if (!function_exists('tl_auth_role_allowed')) {
    function tl_auth_role_allowed(?array $user, string $requiredRole = 'participant'): bool
    {
        if (!$user) return false;
        $rank = ['participant'=>1,'coach'=>2,'reviewer'=>2,'manager'=>3,'admin'=>4];
        return ($rank[tl_security_trusted_role($user)] ?? 0) >= ($rank[$requiredRole] ?? 1);
    }
}

if (!function_exists('tl_auth_require_page')) {
    function tl_auth_require_page(string $requiredRole = 'participant'): ?array
    {
        $user = tl_auth_current_user();
        return tl_auth_role_allowed($user, $requiredRole) ? $user : null;
    }
}

if (!function_exists('tl_auth_json')) {
    function tl_auth_json(array $payload, int $status = 200): void
    {
        tl_security_json_response($payload, $status);
        exit;
    }
}
