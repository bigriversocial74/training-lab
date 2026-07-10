<?php
declare(strict_types=1);

/**
 * Stage 894 — signed read-only Microgifter reward lookup client.
 *
 * The expected Stage 893 adapter function is registered only when every client
 * requirement is configured. The client never sends authentication sessions,
 * passwords, developer keys, or identity assertions.
 */

if (!function_exists('tl_stage894_bool')) {
    function tl_stage894_bool($value, bool $default = false): bool
    {
        if (is_bool($value)) return $value;
        if ($value === null || $value === '') return $default;
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed === null ? $default : $parsed;
    }
}

if (!function_exists('tl_stage894_root_config')) {
    function tl_stage894_root_config(): array
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

if (!function_exists('tl_stage894_allowed_hosts')) {
    function tl_stage894_allowed_hosts($value): array
    {
        if (is_array($value)) $hosts = $value;
        else $hosts = preg_split('/\s*,\s*/', trim((string)$value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $normalized = [];
        foreach ($hosts as $host) {
            $host = strtolower(trim((string)$host));
            if ($host !== '' && preg_match('/^[a-z0-9.-]+$/', $host)) $normalized[] = $host;
        }
        if (!$normalized) $normalized = ['microgifter.com', 'www.microgifter.com'];
        return array_values(array_unique($normalized));
    }
}

if (!function_exists('tl_stage894_endpoint_status')) {
    function tl_stage894_endpoint_status(string $url, array $allowedHosts): array
    {
        $parts = $url !== '' ? parse_url($url) : false;
        $scheme = is_array($parts) ? strtolower((string)($parts['scheme'] ?? '')) : '';
        $host = is_array($parts) ? strtolower((string)($parts['host'] ?? '')) : '';
        $hasCredentials = is_array($parts) && (isset($parts['user']) || isset($parts['pass']));
        $hasFragment = is_array($parts) && isset($parts['fragment']);
        $allowed = $host !== '' && in_array($host, $allowedHosts, true);
        return [
            'url_present'=>$url !== '',
            'https'=>$scheme === 'https',
            'host'=>$host,
            'host_allowed'=>$allowed,
            'credentials_absent'=>!$hasCredentials,
            'fragment_absent'=>!$hasFragment,
            'valid'=>$url !== '' && $scheme === 'https' && $allowed && !$hasCredentials && !$hasFragment,
        ];
    }
}

if (!function_exists('tl_stage894_config')) {
    function tl_stage894_config(): array
    {
        $root = tl_stage894_root_config();
        $enabled = getenv('TL_MICROGIFTER_REWARD_LOOKUP_ENABLED');
        $url = getenv('TL_MICROGIFTER_REWARD_LOOKUP_URL');
        $secret = getenv('TL_MICROGIFTER_REWARD_LOOKUP_SECRET');
        $timeout = getenv('TL_MICROGIFTER_REWARD_LOOKUP_TIMEOUT_SECONDS');
        $connectTimeout = getenv('TL_MICROGIFTER_REWARD_LOOKUP_CONNECT_TIMEOUT_SECONDS');
        $hosts = getenv('TL_MICROGIFTER_REWARD_LOOKUP_ALLOWED_HOSTS');
        $maxResponse = getenv('TL_MICROGIFTER_REWARD_LOOKUP_MAX_RESPONSE_BYTES');

        $resolvedUrl = trim((string)($url !== false && $url !== '' ? $url : ($root['microgifter_reward_lookup_url'] ?? 'https://microgifter.com/api/integrations/training-lab-reward-lookup.php')));
        $resolvedSecret = trim((string)($secret !== false && $secret !== '' ? $secret : ($root['microgifter_reward_lookup_secret'] ?? '')));
        $allowedHosts = tl_stage894_allowed_hosts($hosts !== false && $hosts !== '' ? $hosts : ($root['microgifter_reward_lookup_allowed_hosts'] ?? ['microgifter.com','www.microgifter.com']));
        $endpoint = tl_stage894_endpoint_status($resolvedUrl, $allowedHosts);
        $curlAvailable = function_exists('curl_init');
        $isEnabled = tl_stage894_bool($enabled !== false ? $enabled : ($root['microgifter_reward_lookup_enabled'] ?? false), false);

        return [
            'enabled'=>$isEnabled,
            'url'=>$resolvedUrl,
            'endpoint'=>$endpoint,
            'allowed_hosts'=>$allowedHosts,
            'secret'=>$resolvedSecret,
            'secret_present'=>strlen($resolvedSecret) >= 32,
            'timeout_seconds'=>max(2, min(30, (int)($timeout !== false && $timeout !== '' ? $timeout : ($root['microgifter_reward_lookup_timeout_seconds'] ?? 8)))),
            'connect_timeout_seconds'=>max(1, min(10, (int)($connectTimeout !== false && $connectTimeout !== '' ? $connectTimeout : ($root['microgifter_reward_lookup_connect_timeout_seconds'] ?? 3)))),
            'max_response_bytes'=>max(4096, min(1048576, (int)($maxResponse !== false && $maxResponse !== '' ? $maxResponse : ($root['microgifter_reward_lookup_max_response_bytes'] ?? 131072)))),
            'curl_available'=>$curlAvailable,
            'ready'=>$isEnabled && !empty($endpoint['valid']) && strlen($resolvedSecret) >= 32 && $curlAvailable,
        ];
    }
}

if (!function_exists('tl_stage894_canonical_request')) {
    function tl_stage894_canonical_request(string $timestamp, string $nonce, string $rawBody): string
    {
        return "training-lab-reward-lookup-v1\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $rawBody);
    }
}

if (!function_exists('tl_stage894_signature')) {
    function tl_stage894_signature(string $secret, string $timestamp, string $nonce, string $rawBody): string
    {
        return hash_hmac('sha256', tl_stage894_canonical_request($timestamp, $nonce, $rawBody), $secret);
    }
}

if (!function_exists('tl_stage894_validate_payload')) {
    function tl_stage894_validate_payload(array $payload): array
    {
        if ((string)($payload['contract'] ?? '') !== 'training_lab_reward_reconciliation_v1') {
            throw new InvalidArgumentException('Unsupported Training Lab reward lookup contract.');
        }
        if ((string)($payload['source'] ?? '') !== 'training_lab' || ($payload['read_only'] ?? null) !== true) {
            throw new InvalidArgumentException('The Microgifter reward lookup must be a read-only Training Lab request.');
        }
        $microgifterUserId = trim((string)($payload['microgifter_user_id'] ?? ''));
        if ($microgifterUserId === '' || !ctype_digit($microgifterUserId) || (int)$microgifterUserId < 1) {
            throw new InvalidArgumentException('A valid linked Microgifter user ID is required.');
        }
        $idempotencyKey = trim((string)($payload['idempotency_key'] ?? ''));
        $externalReference = trim((string)($payload['external_reference'] ?? ''));
        if ($idempotencyKey === '' && $externalReference === '') {
            throw new InvalidArgumentException('An idempotency key or external reference is required.');
        }
        if (strlen($idempotencyKey) > 190 || strlen($externalReference) > 190) {
            throw new InvalidArgumentException('A reward lookup reference is too long.');
        }
        return $payload;
    }
}

if (!function_exists('tl_stage894_lookup')) {
    function tl_stage894_lookup(array $payload): array
    {
        $config = tl_stage894_config();
        if (empty($config['ready'])) {
            throw new RuntimeException('The signed Microgifter reward lookup client is not ready.');
        }
        $payload = tl_stage894_validate_payload($payload);
        try {
            $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('The reward lookup request could not be encoded.');
        }

        $timestamp = (string)time();
        $nonce = bin2hex(random_bytes(24));
        $signature = tl_stage894_signature((string)$config['secret'], $timestamp, $nonce, $rawBody);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Microgifter-Training-Lab-Timestamp: ' . $timestamp,
            'X-Microgifter-Training-Lab-Nonce: ' . $nonce,
            'X-Microgifter-Training-Lab-Signature: ' . $signature,
        ];

        $handle = curl_init();
        if ($handle === false) throw new RuntimeException('The signed reward lookup transport is unavailable.');
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
            CURLOPT_USERAGENT=>'Microgifter-Training-Lab-Reward-Lookup/1.0',
        ];
        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTPS')) $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
        curl_setopt_array($handle, $options);
        $response = curl_exec($handle);
        $httpStatus = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $transportError = curl_error($handle);
        curl_close($handle);

        if (!is_string($response)) {
            throw new RuntimeException('The signed Microgifter reward lookup request failed.' . ($transportError !== '' ? ' Transport error.' : ''));
        }
        if (strlen($response) > (int)$config['max_response_bytes']) {
            throw new RuntimeException('The signed Microgifter reward lookup response was too large.');
        }
        try {
            $decoded = json_decode($response, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('The signed Microgifter reward lookup returned invalid JSON.');
        }
        if (!is_array($decoded)) throw new RuntimeException('The signed Microgifter reward lookup returned an invalid response.');
        if ($httpStatus < 200 || $httpStatus >= 300 || empty($decoded['ok']) || !is_array($decoded['data'] ?? null)) {
            $remoteCode = preg_replace('/[^a-z0-9_\-]/i', '', (string)($decoded['error_code'] ?? 'lookup_rejected')) ?: 'lookup_rejected';
            throw new RuntimeException('The signed Microgifter reward lookup was rejected: ' . $remoteCode . '.');
        }

        $result = (array)$decoded['data'];
        $found = !empty($result['found']);
        $status = strtolower(trim((string)($result['delivery_status'] ?? $result['status'] ?? 'unknown')));
        if ($found && $status !== 'delivered') {
            throw new RuntimeException('Microgifter returned a found reward without confirmed delivery status.');
        }
        if (!$found && !in_array($status, ['not_found','unknown'], true)) {
            throw new RuntimeException('Microgifter returned an inconsistent missing-reward status.');
        }
        $result['read_only'] = true;
        return $result;
    }
}

if (!function_exists('tl_stage894_summary')) {
    function tl_stage894_summary(): array
    {
        $config = tl_stage894_config();
        return [
            'stage'=>'Stage 894 Signed Reward Lookup Client v1',
            'enabled'=>!empty($config['enabled']),
            'ready'=>!empty($config['ready']),
            'endpoint'=>[
                'host'=>(string)($config['endpoint']['host'] ?? ''),
                'https'=>!empty($config['endpoint']['https']),
                'host_allowed'=>!empty($config['endpoint']['host_allowed']),
            ],
            'secret_present'=>!empty($config['secret_present']),
            'curl_available'=>!empty($config['curl_available']),
            'timeout_seconds'=>(int)$config['timeout_seconds'],
            'connect_timeout_seconds'=>(int)$config['connect_timeout_seconds'],
            'safe_boundaries'=>[
                'https_only'=>true,
                'redirects_disabled'=>true,
                'tls_peer_and_host_verification'=>true,
                'server_controlled_host_allowlist'=>true,
                'shared_secret_not_returned'=>true,
                'read_only_payload_only'=>true,
            ],
        ];
    }
}

if (!function_exists('tl_stage894_render_admin_panel')) {
    function tl_stage894_render_admin_panel(): void
    {
        if (!function_exists('labs_e') || !function_exists('labs_url')) return;
        $data = tl_stage894_summary();
        $endpoint = (array)$data['endpoint'];
        echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 894</span><h2>Signed Reward Lookup Client</h2><p class="labs-copy">Verify Microgifter delivery through an HTTPS, HMAC-signed, identity-bound read request before a quarantined handoff can be finalized.</p></div><a class="labs-btn" href="' . labs_e(labs_url('/api/training/reward-delivery-reconciliation.php')) . '">Reconciliation Status</a></div>';
        echo '<div class="labs-kpis"><div class="labs-kpi"><span>Client</span><strong>' . (!empty($data['enabled']) ? 'Enabled' : 'Disabled') . '</strong><small>explicit feature gate</small></div><div class="labs-kpi"><span>Ready</span><strong>' . (!empty($data['ready']) ? 'Yes' : 'No') . '</strong><small>all requirements</small></div><div class="labs-kpi"><span>Endpoint</span><strong>' . (!empty($endpoint['host_allowed']) ? 'Allowed' : 'Check') . '</strong><small>' . labs_e((string)($endpoint['host'] ?: 'not configured')) . '</small></div><div class="labs-kpi"><span>Secret</span><strong>' . (!empty($data['secret_present']) ? 'Present' : 'Missing') . '</strong><small>never displayed</small></div></div>';
        echo '<div class="labs-safe-note">The client enforces HTTPS, verifies the certificate and hostname, rejects redirects, uses bounded timeouts, and sends no session or account credentials.</div></section>';
    }
}

// Register the Stage 893 adapter only when all Stage 894 requirements are ready.
if (!function_exists('microgifter_training_reward_lookup')) {
    $stage894RegistrationConfig = tl_stage894_config();
    if (!empty($stage894RegistrationConfig['ready'])) {
        function microgifter_training_reward_lookup(array $payload): array
        {
            return tl_stage894_lookup($payload);
        }
    }
    unset($stage894RegistrationConfig);
}
