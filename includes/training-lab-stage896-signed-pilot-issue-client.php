<?php
declare(strict_types=1);

/**
 * Stage 896 — signed, pilot-only Microgifter reward issue client.
 *
 * The production adapter function is registered only when the dedicated issue
 * endpoint, host allowlist, shared secret, and cURL transport are ready. Runtime
 * calls are additionally restricted to the single active Stage 896 pilot.
 */
require_once __DIR__ . '/training-lab-stage893-legacy-action-guard.php';

if (!function_exists('tl_stage896_issue_bool')) {
    function tl_stage896_issue_bool($value, bool $default = false): bool
    {
        if (is_bool($value)) return $value;
        if ($value === null || $value === '') return $default;
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed === null ? $default : $parsed;
    }
}

if (!function_exists('tl_stage896_issue_root_config')) {
    function tl_stage896_issue_root_config(): array
    {
        if (function_exists('tl_security_config')) {
            $config = tl_security_config();
            return is_array($config) ? $config : [];
        }
        if (function_exists('tl_db_config_load')) {
            $loaded = tl_db_config_load();
            $config = $loaded['config']['training_lab'] ?? [];
            return is_array($config) ? $config : [];
        }
        return [];
    }
}

if (!function_exists('tl_stage896_issue_allowed_hosts')) {
    function tl_stage896_issue_allowed_hosts($value): array
    {
        $hosts = is_array($value)
            ? $value
            : (preg_split('/\s*,\s*/', trim((string)$value), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $normalized = [];
        foreach ($hosts as $host) {
            $host = strtolower(trim((string)$host));
            if ($host !== '' && preg_match('/^[a-z0-9.-]+$/', $host)) $normalized[] = $host;
        }
        if (!$normalized) $normalized = ['microgifter.com', 'www.microgifter.com'];
        return array_values(array_unique($normalized));
    }
}

if (!function_exists('tl_stage896_issue_endpoint_status')) {
    function tl_stage896_issue_endpoint_status(string $url, array $allowedHosts): array
    {
        $parts = $url !== '' ? parse_url($url) : false;
        $scheme = is_array($parts) ? strtolower((string)($parts['scheme'] ?? '')) : '';
        $host = is_array($parts) ? strtolower((string)($parts['host'] ?? '')) : '';
        $path = is_array($parts) ? (string)($parts['path'] ?? '') : '';
        $credentialsAbsent = is_array($parts) && !isset($parts['user']) && !isset($parts['pass']);
        $fragmentAbsent = is_array($parts) && !isset($parts['fragment']);
        $pathValid = str_ends_with($path, '/api/integrations/training-lab-reward-pilot-issue.php');
        $hostAllowed = $host !== '' && in_array($host, $allowedHosts, true);
        return [
            'url_present'=>$url !== '',
            'https'=>$scheme === 'https',
            'host'=>$host,
            'host_allowed'=>$hostAllowed,
            'path_valid'=>$pathValid,
            'credentials_absent'=>$credentialsAbsent,
            'fragment_absent'=>$fragmentAbsent,
            'valid'=>$url !== '' && $scheme === 'https' && $hostAllowed && $pathValid && $credentialsAbsent && $fragmentAbsent,
        ];
    }
}

if (!function_exists('tl_stage896_issue_config')) {
    function tl_stage896_issue_config(): array
    {
        $root = tl_stage896_issue_root_config();
        $enabled = getenv('TL_MICROGIFTER_PILOT_ISSUE_ENABLED');
        $url = getenv('TL_MICROGIFTER_PILOT_ISSUE_URL');
        $secret = getenv('TL_MICROGIFTER_PILOT_ISSUE_SECRET');
        $hosts = getenv('TL_MICROGIFTER_PILOT_ISSUE_ALLOWED_HOSTS');
        $timeout = getenv('TL_MICROGIFTER_PILOT_ISSUE_TIMEOUT_SECONDS');
        $connectTimeout = getenv('TL_MICROGIFTER_PILOT_ISSUE_CONNECT_TIMEOUT_SECONDS');
        $maxResponse = getenv('TL_MICROGIFTER_PILOT_ISSUE_MAX_RESPONSE_BYTES');

        $resolvedUrl = trim((string)($url !== false && $url !== ''
            ? $url
            : ($root['microgifter_pilot_issue_url'] ?? 'https://microgifter.com/api/integrations/training-lab-reward-pilot-issue.php')));
        $resolvedSecret = trim((string)($secret !== false && $secret !== ''
            ? $secret
            : ($root['microgifter_pilot_issue_secret'] ?? '')));
        $allowedHosts = tl_stage896_issue_allowed_hosts($hosts !== false && $hosts !== ''
            ? $hosts
            : ($root['microgifter_pilot_issue_allowed_hosts'] ?? ['microgifter.com','www.microgifter.com']));
        $endpoint = tl_stage896_issue_endpoint_status($resolvedUrl, $allowedHosts);
        $isEnabled = tl_stage896_issue_bool($enabled !== false
            ? $enabled
            : ($root['microgifter_pilot_issue_enabled'] ?? false), false);
        $curlAvailable = function_exists('curl_init');

        return [
            'enabled'=>$isEnabled,
            'url'=>$resolvedUrl,
            'endpoint'=>$endpoint,
            'allowed_hosts'=>$allowedHosts,
            'secret'=>$resolvedSecret,
            'secret_present'=>strlen($resolvedSecret) >= 32,
            'timeout_seconds'=>max(2, min(30, (int)($timeout !== false && $timeout !== ''
                ? $timeout
                : ($root['microgifter_pilot_issue_timeout_seconds'] ?? 10)))),
            'connect_timeout_seconds'=>max(1, min(10, (int)($connectTimeout !== false && $connectTimeout !== ''
                ? $connectTimeout
                : ($root['microgifter_pilot_issue_connect_timeout_seconds'] ?? 3)))),
            'max_response_bytes'=>max(4096, min(1048576, (int)($maxResponse !== false && $maxResponse !== ''
                ? $maxResponse
                : ($root['microgifter_pilot_issue_max_response_bytes'] ?? 131072)))),
            'curl_available'=>$curlAvailable,
            'ready'=>$isEnabled && !empty($endpoint['valid']) && strlen($resolvedSecret) >= 32 && $curlAvailable,
        ];
    }
}

if (!function_exists('tl_stage896_issue_canonical')) {
    function tl_stage896_issue_canonical(string $timestamp, string $nonce, string $rawBody): string
    {
        return "training-lab-reward-issue-v1\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $rawBody);
    }
}

if (!function_exists('tl_stage896_issue_signature')) {
    function tl_stage896_issue_signature(string $secret, string $timestamp, string $nonce, string $rawBody): string
    {
        return hash_hmac('sha256', tl_stage896_issue_canonical($timestamp, $nonce, $rawBody), $secret);
    }
}

if (!function_exists('tl_stage896_issue_reference')) {
    function tl_stage896_issue_reference($value, int $max = 190): string
    {
        $value = trim((string)$value);
        if ($value === '') return '';
        if (strlen($value) > $max || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new RuntimeException('A Stage 896 issue reference is invalid.');
        }
        return $value;
    }
}

if (!function_exists('tl_stage896_issue_handoff_context')) {
    function tl_stage896_issue_handoff_context(array $payload): array
    {
        if (!function_exists('tl_stage896_config') || empty(tl_stage896_config()['enabled'])) {
            throw new RuntimeException('The Stage 896 limited pilot is disabled.');
        }
        if (!function_exists('tl_stage892_config') || !empty(tl_stage892_config()['worker_enabled'])) {
            throw new RuntimeException('The scheduled reward worker must remain disabled during Stage 896.');
        }
        if (!function_exists('tl_stage890_table_ready') || !tl_stage890_table_ready()) {
            throw new RuntimeException('The durable reward handoff schema is unavailable.');
        }
        $idempotencyKey = tl_stage896_issue_reference($payload['idempotency_key'] ?? '');
        if ($idempotencyKey === '') throw new RuntimeException('The Stage 896 handoff idempotency key is required.');
        $pdo = tl_require_db();
        $stmt = $pdo->prepare('SELECT * FROM training_reward_handoffs WHERE idempotency_key=? LIMIT 1');
        $stmt->execute([$idempotencyKey]);
        $handoff = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$handoff) throw new RuntimeException('The Stage 896 reward handoff was not found.');
        if ((string)$handoff['handoff_status'] !== 'processing') {
            throw new RuntimeException('Only the currently processing Stage 896 handoff may call the issue endpoint.');
        }
        $metadata = function_exists('tl_stage890_json_decode')
            ? tl_stage890_json_decode($handoff['metadata_json'] ?? null)
            : [];
        $pilot = is_array($metadata['stage896_pilot'] ?? null) ? $metadata['stage896_pilot'] : [];
        if (!$pilot || (string)($pilot['status'] ?? '') !== 'processing' || empty($pilot['pilot_id'])) {
            throw new RuntimeException('The reward handoff is not reserved by an active Stage 896 pilot.');
        }
        if (function_exists('tl_stage896_active_pilots')) {
            $active = tl_stage896_active_pilots($pdo);
            if (count($active) !== 1 || (int)($active[0]['id'] ?? 0) !== (int)$handoff['id']) {
                throw new RuntimeException('Stage 896 requires exactly one active pilot handoff.');
            }
        }
        return ['pdo'=>$pdo,'handoff'=>$handoff,'pilot'=>$pilot];
    }
}

if (!function_exists('tl_stage896_issue_payload')) {
    function tl_stage896_issue_payload(array $payload, array $context): array
    {
        $handoff = (array)$context['handoff'];
        $pilot = (array)$context['pilot'];
        $recipient = trim((string)($payload['microgifter_user_id'] ?? ''));
        if ($recipient === '' || !ctype_digit($recipient) || (int)$recipient < 1) {
            throw new RuntimeException('A valid linked Microgifter recipient is required.');
        }
        if (!hash_equals((string)$handoff['microgifter_user_id'], $recipient)) {
            throw new RuntimeException('The Stage 896 payload recipient does not match the reserved handoff.');
        }
        $template = tl_stage896_issue_reference($payload['linked_microgift_template_id'] ?? '');
        if ($template === '') throw new RuntimeException('A published Microgift template reference is required.');
        $merchantContext = tl_stage896_issue_reference($payload['merchant_context'] ?? '');
        if ($merchantContext === '') throw new RuntimeException('A signed merchant workspace context is required.');
        $currency = strtoupper(trim((string)($payload['currency'] ?? 'USD')));
        $value = max(0, (int)($payload['value_cents'] ?? 0));
        $limit = function_exists('tl_stage896_config') ? (int)tl_stage896_config()['max_value_cents'] : 0;
        if ($value > $limit || $currency !== 'USD') {
            throw new RuntimeException('The Stage 896 reward exceeds the local value or currency boundary.');
        }
        return [
            'contract'=>'training_lab_reward_issue_pilot_v1',
            'source'=>'training_lab',
            'pilot_only'=>true,
            'readback_required'=>true,
            'pilot_id'=>(string)$pilot['pilot_id'],
            'idempotency_key'=>(string)$handoff['idempotency_key'],
            'training_handoff_public_id'=>(string)$handoff['public_id'],
            'training_reward_public_id'=>tl_stage896_issue_reference($payload['training_reward_public_id'] ?? ''),
            'microgifter_user_id'=>(int)$recipient,
            'merchant_context'=>$merchantContext,
            'linked_microgift_template_id'=>$template,
            'linked_catalog_product_id'=>tl_stage896_issue_reference($payload['linked_catalog_product_id'] ?? ''),
            'reward_label'=>mb_substr(trim((string)($payload['reward_label'] ?? 'Training Reward')), 0, 190),
            'value_cents'=>$value,
            'currency'=>$currency,
        ];
    }
}

if (!function_exists('tl_stage896_issue_transport')) {
    function tl_stage896_issue_transport(array $payload): array
    {
        $config = tl_stage896_issue_config();
        if (empty($config['ready'])) throw new RuntimeException('The signed Microgifter pilot issue client is not ready.');
        try {
            $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('The pilot issue request could not be encoded.');
        }
        $timestamp = (string)time();
        $nonce = bin2hex(random_bytes(24));
        $signature = tl_stage896_issue_signature((string)$config['secret'], $timestamp, $nonce, $rawBody);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Microgifter-Training-Lab-Issue-Timestamp: ' . $timestamp,
            'X-Microgifter-Training-Lab-Issue-Nonce: ' . $nonce,
            'X-Microgifter-Training-Lab-Issue-Signature: ' . $signature,
        ];
        $handle = curl_init();
        if ($handle === false) throw new RuntimeException('The signed pilot issue transport is unavailable.');
        $options = [
            CURLOPT_URL=>(string)$config['url'],
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>$rawBody,
            CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_HEADER=>false,
            CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_MAXREDIRS=>0,
            CURLOPT_CONNECTTIMEOUT=>(int)$config['connect_timeout_seconds'],
            CURLOPT_TIMEOUT=>(int)$config['timeout_seconds'],
            CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_USERAGENT=>'Microgifter-Training-Lab-Pilot-Issue/1.0',
        ];
        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTPS')) $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
        curl_setopt_array($handle, $options);
        $response = curl_exec($handle);
        $httpStatus = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $transportError = curl_error($handle);
        curl_close($handle);
        if (!is_string($response)) {
            throw new RuntimeException('The signed Microgifter pilot issue request failed.' . ($transportError !== '' ? ' Transport error.' : ''));
        }
        if (strlen($response) > (int)$config['max_response_bytes']) {
            throw new RuntimeException('The signed Microgifter pilot issue response was too large.');
        }
        try {
            $decoded = json_decode($response, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('The signed Microgifter pilot issue returned invalid JSON.');
        }
        if (!is_array($decoded)) throw new RuntimeException('The signed Microgifter pilot issue returned an invalid response.');
        if ($httpStatus < 200 || $httpStatus >= 300 || empty($decoded['ok']) || !is_array($decoded['data'] ?? null)) {
            $code = preg_replace('/[^a-z0-9_\-]/i', '', (string)($decoded['error_code'] ?? 'pilot_issue_rejected')) ?: 'pilot_issue_rejected';
            throw new RuntimeException('The signed Microgifter pilot issue was rejected: ' . $code . '.');
        }
        return (array)$decoded['data'];
    }
}

if (!function_exists('tl_stage896_issue_summary')) {
    function tl_stage896_issue_summary(): array
    {
        $config = tl_stage896_issue_config();
        return [
            'stage'=>'Stage 896 Signed Pilot Issue Client v1',
            'enabled'=>!empty($config['enabled']),
            'ready'=>!empty($config['ready']),
            'endpoint'=>[
                'host'=>(string)($config['endpoint']['host'] ?? ''),
                'https'=>!empty($config['endpoint']['https']),
                'host_allowed'=>!empty($config['endpoint']['host_allowed']),
                'path_valid'=>!empty($config['endpoint']['path_valid']),
            ],
            'secret_present'=>!empty($config['secret_present']),
            'curl_available'=>!empty($config['curl_available']),
            'safe_boundaries'=>[
                'pilot_only_runtime_guard'=>true,
                'single_active_handoff_required'=>true,
                'scheduled_worker_disabled_required'=>true,
                'https_only'=>true,
                'redirects_disabled'=>true,
                'tls_peer_and_host_verification'=>true,
                'shared_secret_not_returned'=>true,
            ],
        ];
    }
}

$config = tl_stage896_issue_config();
if (!empty($config['ready']) && !function_exists('microgifter_training_issue_reward')) {
    function microgifter_training_issue_reward(array $payload): array
    {
        $context = tl_stage896_issue_handoff_context($payload);
        $request = tl_stage896_issue_payload($payload, $context);
        $result = tl_stage896_issue_transport($request);
        if (empty($result['issued']) || (string)($result['delivery_status'] ?? '') !== 'delivered') {
            throw new RuntimeException('Microgifter did not confirm the Stage 896 pilot delivery.');
        }
        $external = tl_stage896_issue_reference($result['external_reference'] ?? $result['microgift_instance_id'] ?? '');
        if ($external === '') throw new RuntimeException('Microgifter did not return a pilot delivery reference.');
        return [
            'microgift_instance_id'=>$external,
            'linked_microgift_instance_id'=>$external,
            'external_reference'=>$external,
            'delivery_status'=>'delivered',
            'duplicate'=>!empty($result['duplicate']),
            'readback_required'=>true,
        ];
    }
}
