<?php
/** Central security/runtime controls for Training Lab HTTP requests. */

if (!class_exists('TlHttpException')) {
    final class TlHttpException extends RuntimeException
    {
        private int $httpStatus;
        private string $errorCode;

        public function __construct(string $message, int $httpStatus = 400, string $errorCode = 'bad_request')
        {
            parent::__construct($message);
            $this->httpStatus = max(400, min(599, $httpStatus));
            $this->errorCode = preg_replace('/[^a-z0-9_\-]/i', '', $errorCode) ?: 'bad_request';
        }

        public function httpStatus(): int { return $this->httpStatus; }
        public function errorCode(): string { return $this->errorCode; }
    }
}

if (!function_exists('tl_security_bool')) {
    function tl_security_bool($value, bool $default = false): bool
    {
        if (is_bool($value)) return $value;
        if ($value === null || $value === '') return $default;
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed === null ? $default : $parsed;
    }
}

if (!function_exists('tl_security_is_https')) {
    function tl_security_is_https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
        if ((string)($_SERVER['SERVER_PORT'] ?? '') === '443') return true;
        $forwarded = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
        return $forwarded === 'https';
    }
}

if (!function_exists('tl_security_session_start')) {
    function tl_security_session_start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || headers_sent()) return;
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        session_name('microgifter_training_lab');
        session_set_cookie_params([
            'lifetime'=>0, 'path'=>'/', 'domain'=>'',
            'secure'=>tl_security_is_https(), 'httponly'=>true, 'samesite'=>'Lax',
        ]);
        session_start();
    }
}

if (!function_exists('tl_security_request_id')) {
    function tl_security_request_id(): string
    {
        static $id = null;
        if ($id !== null) return $id;
        $incoming = preg_replace('/[^a-zA-Z0-9._\-]/', '', (string)($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
        return $id = $incoming !== '' ? substr($incoming, 0, 80) : bin2hex(random_bytes(12));
    }
}

if (!function_exists('tl_security_headers')) {
    function tl_security_headers(bool $json = false): void
    {
        if (headers_sent()) return;
        header('X-Request-ID: ' . tl_security_request_id());
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'; object-src 'none'; img-src 'self' data:; font-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'");
        if (tl_security_is_https()) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }
    }
}

if (!function_exists('tl_security_config')) {
    function tl_security_config(): array
    {
        if (!function_exists('tl_db_config_load')) return [];
        $loaded = tl_db_config_load();
        $cfg = $loaded['config']['training_lab'] ?? [];
        return is_array($cfg) ? $cfg : [];
    }
}

if (!function_exists('tl_security_debug_enabled')) {
    function tl_security_debug_enabled(): bool
    {
        $env = getenv('TL_DEBUG');
        return $env !== false && $env !== '' ? tl_security_bool($env) : tl_security_bool(tl_security_config()['debug'] ?? false);
    }
}

if (!function_exists('tl_security_demo_login_allowed')) {
    function tl_security_demo_login_allowed(): bool
    {
        if (PHP_SAPI === 'cli') return true;
        $env = getenv('TL_ALLOW_DEMO_LOGIN');
        if ($env !== false && $env !== '') return tl_security_bool($env);
        $cfg = tl_security_config();
        if (array_key_exists('allow_demo_session_login', $cfg)) return tl_security_bool($cfg['allow_demo_session_login']);
        return function_exists('tl_db_config_ready') ? !tl_db_config_ready() : false;
    }
}

if (!function_exists('tl_security_csrf_token')) {
    function tl_security_csrf_token(): string
    {
        tl_security_session_start();
        $issued = (int)($_SESSION['_tl_csrf_issued'] ?? 0);
        if (empty($_SESSION['_tl_csrf']) || !is_string($_SESSION['_tl_csrf']) || time() - $issued > 7200) {
            $_SESSION['_tl_csrf'] = bin2hex(random_bytes(32));
            $_SESSION['_tl_csrf_issued'] = time();
        }
        return (string)$_SESSION['_tl_csrf'];
    }
}

if (!function_exists('tl_security_csrf_field')) {
    function tl_security_csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(tl_security_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('tl_security_verify_csrf')) {
    function tl_security_verify_csrf(?array $data = null): void
    {
        $provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_TL_CSRF_TOKEN'] ?? '');
        if ($provided === '') {
            $data = $data ?? $_POST;
            $provided = (string)($data['_csrf'] ?? $data['csrf_token'] ?? '');
        }
        if ($provided === '' || !hash_equals(tl_security_csrf_token(), $provided)) {
            throw new TlHttpException('The security token is missing or expired. Refresh the page and try again.', 419, 'csrf_failed');
        }
    }
}

if (!function_exists('tl_security_require_method')) {
    function tl_security_require_method(string $method): void
    {
        $method = strtoupper($method);
        $actual = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? (PHP_SAPI === 'cli' ? $method : 'GET')));
        if ($actual !== $method) {
            if (!headers_sent()) header('Allow: ' . $method);
            throw new TlHttpException('This endpoint requires ' . $method . '.', 405, 'method_not_allowed');
        }
    }
}

if (!function_exists('tl_security_validate_origin')) {
    function tl_security_validate_origin(): void
    {
        $host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
        if ($host === '') return;
        $source = (string)($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
        if ($source === '') return;
        $sourceHost = strtolower((string)(parse_url($source, PHP_URL_HOST) ?: ''));
        if ($sourceHost !== '' && !hash_equals($host, $sourceHost)) {
            throw new TlHttpException('Cross-site write request rejected.', 403, 'origin_rejected');
        }
    }
}

if (!function_exists('tl_security_request_data')) {
    function tl_security_request_data(bool $allowQuery = false): array
    {
        $maxBytes = 1048576;
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $maxBytes) throw new TlHttpException('Request payload is too large.', 413, 'payload_too_large');
        $type = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($type, 'application/json')) {
            $raw = file_get_contents('php://input');
            if ($raw === false || strlen($raw) > $maxBytes) throw new TlHttpException('Request body could not be read.', 400, 'invalid_body');
            if (trim($raw) === '') return [];
            try { $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR); }
            catch (JsonException $e) { throw new TlHttpException('Request JSON is invalid.', 400, 'invalid_json'); }
            if (!is_array($decoded)) throw new TlHttpException('Request JSON must be an object.', 400, 'invalid_json_shape');
            return $decoded;
        }
        if (!empty($_POST)) return $_POST;
        return $allowQuery ? $_GET : [];
    }
}

if (!function_exists('tl_security_developer_key_valid')) {
    function tl_security_developer_key_valid(): bool
    {
        $provided = (string)($_SERVER['HTTP_X_TRAINING_LAB_KEY'] ?? '');
        $expected = (string)(getenv('TL_DEVELOPER_KEY') ?: (tl_security_config()['developer_key'] ?? ''));
        return $provided !== '' && strlen($expected) >= 24 && hash_equals($expected, $provided);
    }
}

if (!function_exists('tl_security_current_user')) {
    function tl_security_current_user(): ?array
    {
        if (function_exists('tl_auth_current_user')) return tl_auth_current_user();
        tl_security_session_start();
        $user = $_SESSION['training_lab_user'] ?? null;
        return is_array($user) ? $user : null;
    }
}

if (!function_exists('tl_security_trusted_role')) {
    function tl_security_trusted_role(?array $user): string
    {
        if (!$user) return '';
        $trusted = in_array((string)($user['source'] ?? ''), ['existing_microgifter_session','microgifter_adapter','developer_key'], true) || !empty($user['microgifter_user_id']);
        if (!$trusted) return 'participant';
        $role = strtolower((string)($user['role'] ?? 'participant'));
        $aliases = ['owner'=>'admin','merchant_admin'=>'admin','merchant'=>'manager','operator'=>'manager','trainer'=>'coach','mentor'=>'coach','user'=>'participant'];
        $role = $aliases[$role] ?? $role;
        return in_array($role, ['participant','coach','reviewer','manager','admin'], true) ? $role : 'participant';
    }
}

if (!function_exists('tl_security_permission_for_action')) {
    function tl_security_permission_for_action(string $action): string
    {
        $action = strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $action));
        $map = [
            'join_campaign'=>'training.participate','complete_task'=>'training.participate','submit_proof'=>'training.participate','check_in'=>'training.participate','save_reflection'=>'training.participate','save_training_note'=>'training.note.create','claim_training_reward'=>'training.reward.claim','create_account_link_snapshot'=>'training.participate',
            'review_proof'=>'training.proof.review','stage885_review_proof'=>'training.proof.review','reconcile_participant_progress'=>'training.proof.review','mark_participant_focus'=>'training.proof.review','save_proof_quality_note'=>'training.proof.review','save_reviewer_quality_snapshot'=>'training.proof.review','create_review_sla_snapshot'=>'training.proof.review',
            'create_campaign'=>'training.campaign.manage','create_campaign_blueprint'=>'training.campaign.manage','seed_demo'=>'training.campaign.manage','update_campaign_status'=>'training.campaign.manage','update_campaign_plan'=>'training.campaign.manage','add_campaign_task'=>'training.campaign.manage','update_task_status'=>'training.campaign.manage','save_campaign_checkpoint'=>'training.campaign.manage','import_microgifter_campaign'=>'training.campaign.manage',
            'evaluate_rewards'=>'training.reward.manage','queue_reward_event'=>'training.reward.manage','offer_microgifter_reward'=>'training.reward.manage','retry_microgifter_reward_issue'=>'training.reward.manage','mark_reward_manual_issued'=>'training.reward.manage','cancel_training_reward'=>'training.reward.manage','reconcile_reward_lifecycle'=>'training.reward.manage','run_reward_assurance'=>'training.reward.manage',
            'backend_health_snapshot'=>'training.ops.qa','create_workflow_snapshot'=>'training.ops.qa','run_core_workflow_qa'=>'training.ops.qa','run_release_candidate_qa'=>'training.ops.qa',
        ];
        return $map[$action] ?? 'training.ops.qa';
    }
}

if (!function_exists('tl_security_role_permissions')) {
    function tl_security_role_permissions(string $role): array
    {
        $participant = ['training.campaign.view','training.participate','training.receipt.view','training.reward.claim','training.note.create'];
        $reviewer = array_values(array_unique(array_merge($participant, ['training.proof.review'])));
        $manager = array_values(array_unique(array_merge($reviewer, ['training.campaign.manage','training.reward.manage','training.ops.qa'])));
        if ($role === 'admin' || $role === 'manager') return $manager;
        if ($role === 'reviewer' || $role === 'coach') return $reviewer;
        return $participant;
    }
}

if (!function_exists('tl_security_authorize_action')) {
    function tl_security_authorize_action(string $action): array
    {
        if (tl_security_developer_key_valid()) return ['id'=>'developer-key','numeric_user_id'=>1,'role'=>'admin','source'=>'developer_key'];
        $user = tl_security_current_user();
        if (!$user) throw new TlHttpException('Authentication is required for Training Lab write actions.', 401, 'authentication_required');
        $role = tl_security_trusted_role($user);
        if (!in_array(tl_security_permission_for_action($action), tl_security_role_permissions($role), true)) {
            throw new TlHttpException('You do not have permission to perform this Training Lab action.', 403, 'permission_denied');
        }
        $user['role'] = $role;
        return $user;
    }
}

if (!function_exists('tl_security_rate_limit')) {
    function tl_security_rate_limit(string $bucket, int $limit = 60, int $window = 60): void
    {
        if (PHP_SAPI === 'cli') return;
        tl_security_session_start();
        $bucket = preg_replace('/[^a-z0-9_\-]/i', '', $bucket) ?: 'request';
        $now = time();
        $state = $_SESSION['_tl_rate'][$bucket] ?? ['start'=>$now,'count'=>0];
        if ($now - (int)$state['start'] >= $window) $state = ['start'=>$now,'count'=>0];
        $state['count'] = (int)$state['count'] + 1;
        $_SESSION['_tl_rate'][$bucket] = $state;
        if ($state['count'] > $limit) throw new TlHttpException('Too many requests. Please try again shortly.', 429, 'rate_limited');
    }
}

if (!function_exists('tl_security_numeric_user_id')) {
    function tl_security_numeric_user_id(array $user): int
    {
        if (!empty($user['numeric_user_id'])) return max(1, (int)$user['numeric_user_id']);
        $source = (string)($user['microgifter_user_id'] ?? $user['id'] ?? $user['email'] ?? 'training-user');
        if (ctype_digit($source) && (int)$source > 0) return (int)$source;
        return max(1, (int)substr(sprintf('%u', crc32($source)), 0, 9));
    }
}

if (!function_exists('tl_security_apply_actor')) {
    function tl_security_apply_actor(array $input, array $user): array
    {
        $id = tl_security_numeric_user_id($user);
        foreach (['user_id','owner_user_id','created_by_user_id','submitted_by_user_id'] as $key) $input[$key] = $id;
        if (in_array((string)($user['role'] ?? ''), ['coach','reviewer','manager','admin'], true)) $input['reviewer_user_id'] = $id;
        if (empty($input['participant_label'])) $input['participant_label'] = (string)($user['name'] ?? 'Training Participant');
        return $input;
    }
}

if (!function_exists('tl_security_guard_write')) {
    function tl_security_guard_write(string $action, ?array $data = null): array
    {
        tl_security_headers(false);
        tl_security_require_method('POST');
        tl_security_validate_origin();
        tl_security_rate_limit('write_' . $action, 60, 60);
        $user = tl_security_authorize_action($action);
        if (!tl_security_developer_key_valid()) tl_security_verify_csrf($data);
        return $user;
    }
}

if (!function_exists('tl_security_account_adapter_available')) {
    function tl_security_account_adapter_available(): bool
    {
        return function_exists('microgifter_create_training_user_account') || function_exists('microgifter_create_user_account');
    }
}

if (!function_exists('tl_security_guard_auth_action')) {
    function tl_security_guard_auth_action(string $action, ?array $data = null): void
    {
        tl_security_headers(false);
        tl_security_require_method('POST');
        tl_security_validate_origin();
        tl_security_rate_limit('auth_' . $action, in_array($action, ['training_login','create_training_and_microgifter'], true) ? 10 : 30, 300);
        if (!tl_security_developer_key_valid()) tl_security_verify_csrf($data);
        if ($action === 'training_login' && !tl_security_demo_login_allowed()) throw new TlHttpException('Standalone Training Lab session login is disabled on this deployment. Use the connected Microgifter sign-in.', 403, 'demo_login_disabled');
        if ($action === 'create_training_and_microgifter' && !tl_security_account_adapter_available() && !tl_security_demo_login_allowed()) throw new TlHttpException('Account creation is unavailable until the Microgifter account adapter is connected.', 503, 'account_adapter_unavailable');
    }
}

if (!function_exists('tl_security_normalize_auth_input')) {
    function tl_security_normalize_auth_input(array $input): array
    {
        $input['role'] = 'participant';
        unset($input['permissions'], $input['is_admin'], $input['admin_user_id'], $input['reviewer_user_id']);
        return $input;
    }
}

if (!function_exists('tl_security_error_payload')) {
    function tl_security_error_payload(Throwable $e): array
    {
        error_log(sprintf('[TrainingLab][%s] %s: %s in %s:%d', tl_security_request_id(), get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
        $status = $e instanceof TlHttpException ? $e->httpStatus() : 500;
        $payload = [
            'ok'=>false,
            'error'=>$status < 500 ? $e->getMessage() : 'The Training Lab could not complete this request.',
            'error_code'=>$e instanceof TlHttpException ? $e->errorCode() : 'internal_error',
            'request_id'=>tl_security_request_id(),
        ];
        if (tl_security_debug_enabled()) $payload['debug'] = ['type'=>get_class($e),'message'=>$e->getMessage()];
        return [$payload, $status];
    }
}

if (!function_exists('tl_security_json_response')) {
    function tl_security_json_response(array $payload, int $status = 200): void
    {
        tl_security_headers(true);
        http_response_code($status);
        $payload['request_id'] = $payload['request_id'] ?? tl_security_request_id();
        try { echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); }
        catch (JsonException $e) { http_response_code(500); echo '{"ok":false,"error":"Response encoding failed.","error_code":"json_encode_failed"}'; }
    }
}

if (!function_exists('tl_security_json_exception')) {
    function tl_security_json_exception(Throwable $e): void
    {
        [$payload, $status] = tl_security_error_payload($e);
        tl_security_json_response($payload, $status);
    }
}
