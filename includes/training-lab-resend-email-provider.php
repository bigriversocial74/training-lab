<?php
declare(strict_types=1);
/**
 * Resend email provider adapter for Training Lab.
 *
 * This file may be loaded before training-lab-pilot-communications.php so it
 * can provide the enhanced provider state and delivery processor. It does not
 * create recipients, contacts, rewards, wallets, gifts, claims, or campaigns.
 * The only external request is a bounded HTTPS POST to Resend's fixed email
 * endpoint after every Training Lab and campaign gate has passed.
 */

if (!class_exists('TlNotificationProviderFailure')) {
    final class TlNotificationProviderFailure extends RuntimeException
    {
        private string $providerCode;
        private bool $retryable;
        private int $httpStatus;
        private string $responseCode;

        public function __construct(string $message, string $providerCode, bool $retryable, int $httpStatus = 0, string $responseCode = '')
        {
            parent::__construct($message);
            $this->providerCode = preg_replace('/[^a-z0-9_-]/i', '', $providerCode) ?: 'provider_error';
            $this->retryable = $retryable;
            $this->httpStatus = max(0, min(599, $httpStatus));
            $this->responseCode = substr(preg_replace('/[^a-z0-9_.-]/i', '', $responseCode) ?: '', 0, 64);
        }

        public function providerCode(): string { return $this->providerCode; }
        public function retryable(): bool { return $this->retryable; }
        public function httpStatus(): int { return $this->httpStatus; }
        public function responseCode(): string { return $this->responseCode; }
    }
}

if (!function_exists('tl_resend_value')) {
    function tl_resend_value(string $environmentName, string $configKey, string $default = ''): string
    {
        $environmentValue = getenv($environmentName);
        if ($environmentValue !== false && $environmentValue !== '') return trim((string)$environmentValue);
        $config = function_exists('tl_security_config') ? tl_security_config() : [];
        return trim((string)($config[$configKey] ?? $default));
    }
}

if (!function_exists('tl_resend_bool')) {
    function tl_resend_bool(string $environmentName, string $configKey, bool $default = false): bool
    {
        $raw = tl_resend_value($environmentName, $configKey, $default ? 'true' : 'false');
        return function_exists('tl_security_bool') ? tl_security_bool($raw, $default) : filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('tl_resend_int')) {
    function tl_resend_int(string $environmentName, string $configKey, int $default, int $minimum, int $maximum): int
    {
        return max($minimum, min($maximum, (int)tl_resend_value($environmentName, $configKey, (string)$default)));
    }
}

if (!function_exists('tl_resend_config')) {
    function tl_resend_config(): array
    {
        return [
            'api_url'=>'https://api.resend.com/emails',
            'api_key'=>tl_resend_value('TL_RESEND_API_KEY', 'resend_api_key'),
            'from_email'=>strtolower(tl_resend_value('TL_NOTIFICATION_FROM_EMAIL', 'notification_from_email')),
            'from_name'=>tl_resend_value('TL_NOTIFICATION_FROM_NAME', 'notification_from_name', 'Microgifter Training Lab'),
            'reply_to'=>strtolower(tl_resend_value('TL_NOTIFICATION_REPLY_TO', 'notification_reply_to')),
            'test_recipient'=>strtolower(tl_resend_value('TL_NOTIFICATION_TEST_RECIPIENT', 'notification_test_recipient')),
            'test_delivery_enabled'=>tl_resend_bool('TL_NOTIFICATION_TEST_DELIVERY_ENABLED', 'notification_test_delivery_enabled', false),
            'timeout_seconds'=>tl_resend_int('TL_RESEND_TIMEOUT_SECONDS', 'resend_timeout_seconds', 10, 2, 30),
            'connect_timeout_seconds'=>tl_resend_int('TL_RESEND_CONNECT_TIMEOUT_SECONDS', 'resend_connect_timeout_seconds', 3, 1, 10),
            'max_response_bytes'=>tl_resend_int('TL_RESEND_MAX_RESPONSE_BYTES', 'resend_max_response_bytes', 65536, 4096, 262144),
        ];
    }
}

if (!function_exists('tl_resend_email')) {
    function tl_resend_email(string $email): string
    {
        $email = strtolower(trim($email));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? substr($email, 0, 254) : '';
    }
}

if (!function_exists('tl_resend_header_text')) {
    function tl_resend_header_text(string $value, int $maximum = 191): string
    {
        $value = trim(str_replace(["\r", "\n", "\0"], ' ', $value));
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        return substr($value, 0, $maximum);
    }
}

if (!function_exists('tl_resend_sender')) {
    function tl_resend_sender(array $configuration): string
    {
        $email = tl_resend_email((string)($configuration['from_email'] ?? ''));
        if ($email === '') return '';
        $name = tl_resend_header_text((string)($configuration['from_name'] ?? ''), 120);
        return $name === '' ? $email : $name . ' <' . $email . '>';
    }
}

if (!function_exists('tl_resend_retryable')) {
    function tl_resend_retryable(int $status, string $code, int $curlError = 0): bool
    {
        if ($curlError > 0) {
            $transient = [
                defined('CURLE_COULDNT_RESOLVE_HOST') ? CURLE_COULDNT_RESOLVE_HOST : 6,
                defined('CURLE_COULDNT_CONNECT') ? CURLE_COULDNT_CONNECT : 7,
                defined('CURLE_OPERATION_TIMEDOUT') ? CURLE_OPERATION_TIMEDOUT : 28,
                defined('CURLE_SEND_ERROR') ? CURLE_SEND_ERROR : 55,
                defined('CURLE_RECV_ERROR') ? CURLE_RECV_ERROR : 56,
                defined('CURLE_PARTIAL_FILE') ? CURLE_PARTIAL_FILE : 18,
                defined('CURLE_HTTP2') ? CURLE_HTTP2 : 16,
            ];
            return in_array($curlError, $transient, true);
        }
        if ($code === 'invalid_idempotent_request') return false;
        if ($code === 'concurrent_idempotent_requests') return true;
        return in_array($status, [408, 409, 425, 429], true) || $status >= 500;
    }
}

if (!function_exists('tl_resend_error_code')) {
    function tl_resend_error_code(string $providerCode, int $status, int $curlError = 0): string
    {
        if ($curlError > 0) return 'resend_transport_' . $curlError;
        $providerCode = preg_replace('/[^a-z0-9_-]/i', '', strtolower($providerCode)) ?: 'http_' . $status;
        return substr('resend_' . $providerCode, 0, 96);
    }
}

if (!function_exists('tl_resend_safe_message')) {
    function tl_resend_safe_message(string $message, string $fallback = 'The email provider rejected the request.'): string
    {
        $message = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $message) ?? '');
        $message = preg_replace('/(?:re_[a-z0-9_-]{8,}|bearer\s+\S+|authorization\s*[:=]\s*\S+)/i', '[redacted]', $message) ?? $message;
        return substr($message !== '' ? $message : $fallback, 0, 255);
    }
}

if (!function_exists('tl_resend_readiness')) {
    function tl_resend_readiness(): array
    {
        $provider = function_exists('tl_notifications_config') ? (string)tl_notifications_config()['provider_name'] : tl_resend_value('TL_NOTIFICATION_PROVIDER', 'notification_provider', 'adapter');
        $configuration = tl_resend_config();
        $from = tl_resend_email((string)$configuration['from_email']);
        $replyToRaw = (string)$configuration['reply_to'];
        $replyTo = $replyToRaw === '' ? '' : tl_resend_email($replyToRaw);
        $testRecipient = tl_resend_email((string)$configuration['test_recipient']);
        $apiKey = (string)$configuration['api_key'];
        $keyPresent = strlen($apiKey) >= 12 && str_starts_with($apiKey, 're_');
        $curlReady = function_exists('curl_init');
        $senderDomain = $from !== '' ? substr(strrchr($from, '@') ?: '', 1) : '';
        $configured = $provider === 'resend' && $curlReady && $keyPresent && $from !== '' && ($replyToRaw === '' || $replyTo !== '');
        return [
            'provider_name'=>$provider,
            'configured'=>$configured,
            'curl_available'=>$curlReady,
            'api_key_present'=>$keyPresent,
            'from_address_valid'=>$from !== '',
            'sender_domain'=>$senderDomain,
            'reply_to_valid'=>$replyToRaw === '' || $replyTo !== '',
            'reply_to_configured'=>$replyTo !== '',
            'test_delivery_enabled'=>(bool)$configuration['test_delivery_enabled'],
            'test_recipient_configured'=>$testRecipient !== '',
            'test_recipient_confirmation'=>$testRecipient === '' ? '' : substr(hash('sha256', $testRecipient), 0, 12),
            'can_test'=>$configured && (bool)$configuration['test_delivery_enabled'] && $testRecipient !== '',
            'endpoint_locked'=>true,
            'api_key_exposed'=>false,
            'recipient_exposed'=>false,
        ];
    }
}

if (!function_exists('tl_resend_normalize_payload')) {
    function tl_resend_normalize_payload(array $payload): array
    {
        $to = tl_resend_email((string)($payload['to'] ?? ''));
        if ($to === '') throw new TlNotificationProviderFailure('A valid recipient address is required.', 'recipient_invalid', false, 422, 'validation');
        $subject = tl_resend_header_text((string)($payload['subject'] ?? ''), 255);
        if ($subject === '') throw new TlNotificationProviderFailure('A subject is required.', 'subject_invalid', false, 422, 'validation');
        $text = trim((string)($payload['text'] ?? ''));
        if ($text === '' || strlen($text) > 20000) throw new TlNotificationProviderFailure('Plain-text email content is required and must be under 20,000 bytes.', 'body_invalid', false, 422, 'validation');
        $idempotency = trim((string)($payload['idempotency_key'] ?? ''));
        if ($idempotency === '' || strlen($idempotency) > 256 || preg_match('/[\x00-\x20\x7F]/', $idempotency)) {
            throw new TlNotificationProviderFailure('A valid provider idempotency key is required.', 'idempotency_invalid', false, 422, 'validation');
        }
        return ['to'=>$to,'subject'=>$subject,'text'=>$text,'idempotency_key'=>$idempotency];
    }
}

if (!function_exists('tl_resend_send')) {
    function tl_resend_send(array $payload): array
    {
        $configuration = tl_resend_config();
        $readiness = tl_resend_readiness();
        if (!$readiness['configured']) {
            return ['ok'=>false,'error'=>'The Resend provider is not fully configured.','error_code'=>'resend_not_configured','retryable'=>false,'http_status'=>0,'code'=>'not_configured'];
        }
        try {
            $normalized = tl_resend_normalize_payload($payload);
        } catch (TlNotificationProviderFailure $error) {
            return ['ok'=>false,'error'=>$error->getMessage(),'error_code'=>$error->providerCode(),'retryable'=>$error->retryable(),'http_status'=>$error->httpStatus(),'code'=>$error->responseCode()];
        }

        $request = [
            'from'=>tl_resend_sender($configuration),
            'to'=>[$normalized['to']],
            'subject'=>$normalized['subject'],
            'text'=>$normalized['text'],
        ];
        $replyTo = tl_resend_email((string)$configuration['reply_to']);
        if ($replyTo !== '') $request['reply_to'] = $replyTo;

        try {
            $json = json_encode($request, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            return ['ok'=>false,'error'=>'The provider request could not be encoded.','error_code'=>'resend_request_encode_failed','retryable'=>false,'http_status'=>0,'code'=>'encode_failed'];
        }

        $response = '';
        $responseTooLarge = false;
        $headers = [];
        $handle = curl_init((string)$configuration['api_url']);
        if ($handle === false) return ['ok'=>false,'error'=>'The provider transport could not be initialized.','error_code'=>'resend_transport_init_failed','retryable'=>true,'http_status'=>0,'code'=>'transport'];
        curl_setopt_array($handle, [
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>$json,
            CURLOPT_HTTPHEADER=>[
                'Authorization: Bearer ' . (string)$configuration['api_key'],
                'Content-Type: application/json',
                'Accept: application/json',
                'Idempotency-Key: ' . $normalized['idempotency_key'],
                'User-Agent: Microgifter-Training-Lab/1.0',
            ],
            CURLOPT_RETURNTRANSFER=>false,
            CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_MAXREDIRS=>0,
            CURLOPT_CONNECTTIMEOUT=>(int)$configuration['connect_timeout_seconds'],
            CURLOPT_TIMEOUT=>(int)$configuration['timeout_seconds'],
            CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_PROTOCOLS=>defined('CURLPROTO_HTTPS') ? CURLPROTO_HTTPS : 2,
            CURLOPT_REDIR_PROTOCOLS=>defined('CURLPROTO_HTTPS') ? CURLPROTO_HTTPS : 2,
            CURLOPT_WRITEFUNCTION=>static function ($curl, string $chunk) use (&$response, &$responseTooLarge, $configuration): int {
                if (strlen($response) + strlen($chunk) > (int)$configuration['max_response_bytes']) {
                    $responseTooLarge = true;
                    return 0;
                }
                $response .= $chunk;
                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION=>static function ($curl, string $line) use (&$headers): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                return strlen($line);
            },
        ]);
        $executed = curl_exec($handle);
        $curlError = curl_errno($handle);
        $curlMessage = curl_error($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($responseTooLarge) {
            return ['ok'=>false,'error'=>'The provider response exceeded the safe size limit.','error_code'=>'resend_response_too_large','retryable'=>true,'http_status'=>$status,'code'=>'response_too_large'];
        }
        if ($executed === false || $curlError > 0) {
            return [
                'ok'=>false,
                'error'=>tl_resend_safe_message($curlMessage, 'The provider transport failed.'),
                'error_code'=>tl_resend_error_code('', 0, $curlError),
                'retryable'=>tl_resend_retryable(0, '', $curlError),
                'http_status'=>0,
                'code'=>'transport_' . $curlError,
            ];
        }

        $decoded = [];
        if ($response !== '') {
            try { $decoded = json_decode($response, true, 32, JSON_THROW_ON_ERROR); }
            catch (JsonException $error) { $decoded = []; }
        }
        if ($status >= 200 && $status < 300) {
            $messageId = is_array($decoded) ? trim((string)($decoded['id'] ?? '')) : '';
            if ($messageId === '') return ['ok'=>false,'error'=>'The provider accepted the request without a message identifier.','error_code'=>'resend_message_id_missing','retryable'=>true,'http_status'=>$status,'code'=>'invalid_response'];
            return ['ok'=>true,'message_id'=>$messageId,'code'=>'accepted','http_status'=>$status,'retryable'=>false,'provider'=>'resend'];
        }

        $providerCode = is_array($decoded) ? (string)($decoded['name'] ?? $decoded['type'] ?? $decoded['code'] ?? '') : '';
        $message = is_array($decoded) ? (string)($decoded['message'] ?? '') : '';
        $retryable = tl_resend_retryable($status, $providerCode);
        return [
            'ok'=>false,
            'error'=>tl_resend_safe_message($message),
            'error_code'=>tl_resend_error_code($providerCode, $status),
            'retryable'=>$retryable,
            'http_status'=>$status,
            'code'=>substr($providerCode !== '' ? $providerCode : 'http_' . $status, 0, 64),
            'retry_after_seconds'=>isset($headers['retry-after']) && ctype_digit($headers['retry-after']) ? min(86400, (int)$headers['retry-after']) : null,
        ];
    }
}

if (!function_exists('training_lab_send_notification_email')) {
    define('TL_TRAINING_LAB_BUILTIN_EMAIL_ADAPTER', 'resend');
    function training_lab_send_notification_email(array $payload): array
    {
        $provider = function_exists('tl_notifications_config') ? (string)tl_notifications_config()['provider_name'] : tl_resend_value('TL_NOTIFICATION_PROVIDER', 'notification_provider', 'adapter');
        if ($provider !== 'resend') {
            return ['ok'=>false,'error'=>'The configured notification provider is not supported by the built-in adapter.','error_code'=>'notification_provider_unsupported','retryable'=>false,'http_status'=>0,'code'=>'unsupported_provider'];
        }
        return tl_resend_send($payload);
    }
}

if (!function_exists('tl_notifications_provider_state')) {
    function tl_notifications_provider_state(): array
    {
        $configuration = tl_notifications_config();
        $providerName = (string)$configuration['provider_name'];
        $builtIn = defined('TL_TRAINING_LAB_BUILTIN_EMAIL_ADAPTER') ? (string)TL_TRAINING_LAB_BUILTIN_EMAIL_ADAPTER : '';
        $externalAdapter = function_exists('training_lab_send_notification_email') && $builtIn === '';
        $resend = tl_resend_readiness();
        $adapterAvailable = $providerName === 'resend' ? (bool)$resend['configured'] : $externalAdapter;
        return [
            'delivery_enabled'=>(bool)$configuration['delivery_enabled'],
            'worker_enabled'=>(bool)$configuration['worker_enabled'],
            'adapter_available'=>$adapterAvailable,
            'provider_name'=>$providerName,
            'configured'=>$providerName === 'resend' ? (bool)$resend['configured'] : $externalAdapter,
            'can_test'=>$providerName === 'resend' && (bool)$resend['can_test'],
            'can_process'=>(bool)$configuration['delivery_enabled'] && (bool)$configuration['worker_enabled'] && $adapterAvailable,
            'diagnostics'=>$providerName === 'resend' ? $resend : [
                'provider_name'=>$providerName,
                'configured'=>$externalAdapter,
                'api_key_exposed'=>false,
                'recipient_exposed'=>false,
            ],
            'safe_boundaries'=>[
                'fixed_https_endpoint'=>$providerName !== 'resend' || (bool)$resend['endpoint_locked'],
                'no_php_mail_fallback'=>true,
                'no_raw_provider_response_storage'=>true,
                'provider_message_id_hashed'=>true,
                'no_password_or_cookie_payloads'=>true,
                'no_microgifter_api_calls'=>true,
            ],
        ];
    }
}

if (!function_exists('tl_notifications_provider_failure')) {
    function tl_notifications_provider_failure(array $result): TlNotificationProviderFailure
    {
        return new TlNotificationProviderFailure(
            tl_resend_safe_message((string)($result['error'] ?? 'Notification provider rejected the message.')),
            (string)($result['error_code'] ?? 'notification_provider_rejected'),
            !empty($result['retryable']),
            (int)($result['http_status'] ?? 0),
            (string)($result['code'] ?? '')
        );
    }
}

if (!function_exists('tl_notifications_process_one')) {
    function tl_notifications_process_one(PDO $pdo, int $outboxId): array
    {
        $provider = tl_notifications_provider_state();
        if (!$provider['can_process']) throw new TlHttpException('Notification delivery is disabled or the provider adapter is unavailable.', 503, 'notification_provider_blocked');
        $lease = bin2hex(random_bytes(24));
        $leaseHash = hash('sha256', $lease);
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT o.*,c.owner_user_id,c.title campaign_title,c.slug campaign_slug,pc.pilot_status,pc.email_enabled,pc.daily_notification_limit FROM training_notification_outbox o JOIN training_campaigns c ON c.id=o.campaign_id LEFT JOIN training_pilot_controls pc ON pc.campaign_id=o.campaign_id WHERE o.id=? LIMIT 1 FOR UPDATE");
            $stmt->execute([$outboxId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new TlHttpException('Notification outbox item was not found.', 404, 'notification_not_found');
            if (!in_array((string)$row['outbox_status'], ['queued','failed'], true)) throw new TlHttpException('Notification is not eligible for processing.', 409, 'notification_not_processable');
            if ((int)$row['attempt_count'] >= (int)$row['max_attempts']) throw new TlHttpException('Notification reached its retry limit.', 409, 'notification_retry_exhausted');
            if ((string)($row['pilot_status'] ?? '') !== 'active' || empty($row['email_enabled'])) throw new TlHttpException('Campaign communications are paused.', 409, 'pilot_not_active');
            $attemptNo = (int)$row['attempt_count'] + 1;
            $upd = $pdo->prepare("UPDATE training_notification_outbox SET outbox_status='processing',attempt_count=?,leased_at=CURRENT_TIMESTAMP,lease_token_hash=?,last_error_code=NULL,last_error_detail=NULL WHERE id=?");
            $upd->execute([$attemptNo,$leaseHash,$outboxId]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }

        try {
            $account = tl_notifications_account_link($pdo, (int)$row['user_id']);
            if (!$account || (int)$account['id'] !== (int)$row['account_link_id']) throw new TlHttpException('The recipient account link is unavailable.', 409, 'account_link_unavailable');
            $email = tl_notifications_email((string)$account['email']);
            $emailHash = tl_notifications_email_hash($email);
            if ($email === '' || !hash_equals((string)$row['recipient_hash'], $emailHash)) throw new TlHttpException('The recipient address changed and must be resynchronized.', 409, 'recipient_changed');
            if (tl_notifications_is_suppressed($pdo, (int)$account['id'], $emailHash)) throw new TlHttpException('The recipient is suppressed.', 409, 'recipient_suppressed');
            $preference = tl_notifications_preference($pdo, (int)$account['id']);
            if ((string)$row['message_class'] === 'reminder' && empty($preference['reminder_enabled'])) throw new TlHttpException('The recipient disabled reminders.', 409, 'reminders_disabled');
            if ((string)$row['message_class'] === 'transactional' && empty($preference['transactional_enabled'])) throw new TlHttpException('The recipient disabled transactional notifications.', 409, 'transactional_disabled');
            $template = tl_notifications_template($pdo, (int)$row['owner_user_id'], (string)$row['event_type']);
            if (!$template) throw new TlHttpException('The notification template is unavailable.', 409, 'template_missing');
            $context = json_decode((string)($row['context_json'] ?? '{}'), true);
            if (!is_array($context)) $context = [];
            $context['unsubscribe_url'] = (string)$row['message_class'] === 'reminder' ? tl_notifications_unsubscribe_url((int)$account['id']) : '';
            $rendered = tl_notifications_render($template, $context);
            $payload = [
                'to'=>$email,
                'subject'=>$rendered['subject'],
                'text'=>$rendered['text'],
                'idempotency_key'=>(string)$row['idempotency_key'],
                'metadata'=>[
                    'notification_public_id'=>(string)$row['public_id'],
                    'campaign_id'=>(int)$row['campaign_id'],
                    'event_type'=>(string)$row['event_type'],
                    'message_class'=>(string)$row['message_class'],
                    'no_passwords'=>true,
                    'no_cookies'=>true,
                    'no_wallet_or_reward_mutation'=>true,
                ],
            ];
            $result = training_lab_send_notification_email($payload);
            if (!is_array($result) || empty($result['ok'])) throw tl_notifications_provider_failure(is_array($result) ? $result : []);
            $providerId = trim((string)($result['message_id'] ?? $result['id'] ?? ''));
            $providerHash = $providerId === '' ? null : hash('sha256', $providerId);
            $pdo->beginTransaction();
            $lock = $pdo->prepare('SELECT id,lease_token_hash FROM training_notification_outbox WHERE id=? LIMIT 1 FOR UPDATE');
            $lock->execute([$outboxId]);
            $current = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$current || !hash_equals((string)$current['lease_token_hash'], $leaseHash)) throw new RuntimeException('Notification lease was lost.');
            tl_notifications_record_attempt($pdo,$outboxId,$attemptNo,'delivered',['provider_name'=>$provider['provider_name'],'provider_message_hash'=>$providerHash,'response_code'=>(string)($result['code'] ?? 'accepted')]);
            $done = $pdo->prepare("UPDATE training_notification_outbox SET outbox_status='delivered',delivered_at=CURRENT_TIMESTAMP,provider_message_hash=?,leased_at=NULL,lease_token_hash=NULL,next_attempt_at=NULL WHERE id=?");
            $done->execute([$providerHash,$outboxId]);
            $pdo->commit();
            return ['id'=>$outboxId,'status'=>'delivered'];
        } catch (Throwable $error) {
            if ($error instanceof TlNotificationProviderFailure) {
                $errorCode = $error->providerCode();
                $errorDetail = tl_resend_safe_message($error->getMessage());
                $retryable = $error->retryable();
                $responseCode = $error->responseCode();
            } else {
                [$errorCode,$errorDetail] = tl_notifications_safe_error($error);
                $retryable = !in_array($errorCode, ['recipient_changed','account_link_unavailable','template_missing','pilot_not_active'], true);
                $responseCode = '';
            }
            $suppressed = in_array($errorCode, ['recipient_suppressed','reminders_disabled','transactional_disabled'], true);
            $pdo->beginTransaction();
            try {
                tl_notifications_record_attempt($pdo,$outboxId,$attemptNo,$suppressed ? 'suppressed' : 'failed',['provider_name'=>$provider['provider_name'],'response_code'=>$responseCode,'error_code'=>$errorCode,'error_detail'=>$errorDetail]);
                $next = !$suppressed && $retryable ? gmdate('Y-m-d H:i:s', time() + tl_notifications_retry_delay($attemptNo)) : null;
                $status = $suppressed ? 'suppressed' : 'failed';
                $upd = $pdo->prepare('UPDATE training_notification_outbox SET outbox_status=?,last_error_code=?,last_error_detail=?,next_attempt_at=?,leased_at=NULL,lease_token_hash=NULL WHERE id=?');
                $upd->execute([$status,$errorCode,$errorDetail,$next,$outboxId]);
                $pdo->commit();
            } catch (Throwable $inner) {
                if ($pdo->inTransaction()) $pdo->rollBack();
            }
            return ['id'=>$outboxId,'status'=>$suppressed ? 'suppressed' : 'failed','error_code'=>$errorCode,'retryable'=>$retryable];
        }
    }
}

if (!function_exists('tl_notifications_process_batch')) {
    function tl_notifications_process_batch(int $limit = 0): array
    {
        $pdo = tl_require_db();
        if (!tl_notifications_tables_ready()) throw new TlHttpException('Import the Pilot Operations + Communications migration first.', 503, 'notification_schema_missing');
        $provider = tl_notifications_provider_state();
        if (!$provider['can_process']) throw new TlHttpException('Notification delivery remains disabled until the worker and provider adapter are explicitly enabled.', 503, 'notification_provider_blocked');
        $limit = $limit > 0 ? max(1, min(100, $limit)) : (int)tl_notifications_config()['batch_size'];
        $stmt = $pdo->query("SELECT id FROM training_notification_outbox WHERE ((outbox_status='queued' AND (next_attempt_at IS NULL OR next_attempt_at<=CURRENT_TIMESTAMP)) OR (outbox_status='failed' AND next_attempt_at IS NOT NULL AND next_attempt_at<=CURRENT_TIMESTAMP)) AND attempt_count<max_attempts ORDER BY scheduled_at,id LIMIT " . $limit);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $results = ['delivered'=>0,'failed'=>0,'suppressed'=>0];
        foreach ($ids as $id) {
            $result = tl_notifications_process_one($pdo, $id);
            $status = (string)($result['status'] ?? 'failed');
            $results[$status] = ($results[$status] ?? 0) + 1;
        }
        return $results + ['processed'=>count($ids)];
    }
}

if (!function_exists('tl_resend_send_test')) {
    function tl_resend_send_test(array $user): array
    {
        if (!function_exists('tl_product_role') || tl_product_role($user) !== 'admin') throw new TlHttpException('Administrator provider test access is required.', 403, 'email_provider_test_forbidden');
        $state = tl_notifications_provider_state();
        if (empty($state['can_test'])) throw new TlHttpException('Provider test delivery is disabled or incomplete.', 503, 'email_provider_test_blocked');
        $configuration = tl_resend_config();
        $recipient = tl_resend_email((string)$configuration['test_recipient']);
        if ($recipient === '') throw new TlHttpException('A verified administrator test recipient is required.', 503, 'email_provider_test_recipient_missing');
        $requestId = function_exists('tl_security_request_id') ? tl_security_request_id() : bin2hex(random_bytes(12));
        $result = training_lab_send_notification_email([
            'to'=>$recipient,
            'subject'=>'Training Lab provider test',
            'text'=>"Training Lab provider test completed.\n\nThis message contains no participant, campaign, reward, wallet, claim, or provider credential data.\nRequest: " . substr($requestId, 0, 32),
            'idempotency_key'=>'training-lab-provider-test-' . bin2hex(random_bytes(16)),
            'metadata'=>['test_delivery'=>true,'no_participant_data'=>true],
        ]);
        if (empty($result['ok'])) throw tl_notifications_provider_failure($result);
        $messageHash = hash('sha256', (string)($result['message_id'] ?? ''));
        if (function_exists('tl_db') && ($pdo = tl_db()) instanceof PDO && function_exists('tl_log_event')) {
            tl_log_event($pdo, tl_campaign_user_id($user), 'notification_provider', 0, 'notification_provider_test_delivered', [
                'provider'=>'resend',
                'recipient_confirmation'=>substr(hash('sha256', $recipient), 0, 12),
                'provider_message_confirmation'=>substr($messageHash, 0, 12),
                'raw_response_stored'=>false,
            ]);
        }
        return [
            'ok'=>true,
            'provider'=>'resend',
            'recipient_confirmation'=>substr(hash('sha256', $recipient), 0, 12),
            'provider_message_confirmation'=>substr($messageHash, 0, 12),
            'code'=>(string)($result['code'] ?? 'accepted'),
        ];
    }
}
