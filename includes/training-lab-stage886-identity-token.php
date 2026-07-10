<?php
/** Stage 886 signed Microgifter identity assertion helpers. */
require_once __DIR__ . '/training-lab-security.php';
require_once __DIR__ . '/training-lab-account-bridge.php';

if (!function_exists('tl_stage886_b64url_encode')) {
    function tl_stage886_b64url_encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

if (!function_exists('tl_stage886_b64url_decode')) {
    function tl_stage886_b64url_decode(string $value): string
    {
        if ($value === '' || preg_match('/[^A-Za-z0-9_-]/', $value)) {
            throw new TlHttpException('Identity assertion encoding is invalid.', 401, 'identity_token_invalid');
        }
        $padding = (4 - (strlen($value) % 4)) % 4;
        $decoded = base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
        if ($decoded === false) throw new TlHttpException('Identity assertion encoding is invalid.', 401, 'identity_token_invalid');
        return $decoded;
    }
}

if (!function_exists('tl_stage886_config')) {
    function tl_stage886_config(): array
    {
        $cfg = tl_security_config();
        $issuer = trim((string)(getenv('TL_IDENTITY_ISSUER') ?: ($cfg['identity_issuer'] ?? 'microgifter.com')));
        $audience = trim((string)(getenv('TL_IDENTITY_AUDIENCE') ?: ($cfg['identity_audience'] ?? 'training-lab')));
        $secret = (string)(getenv('TL_IDENTITY_SHARED_SECRET') ?: ($cfg['identity_shared_secret'] ?? ''));
        $ttl = max(30, min(600, (int)(getenv('TL_IDENTITY_MAX_TTL') ?: ($cfg['identity_max_ttl_seconds'] ?? 180))));
        $clockSkew = max(0, min(120, (int)(getenv('TL_IDENTITY_CLOCK_SKEW') ?: ($cfg['identity_clock_skew_seconds'] ?? 30))));
        return ['issuer'=>$issuer,'audience'=>$audience,'secret'=>$secret,'max_ttl'=>$ttl,'clock_skew'=>$clockSkew];
    }
}

if (!function_exists('tl_stage886_ready')) {
    function tl_stage886_ready(): bool
    {
        $cfg = tl_stage886_config();
        return $cfg['issuer'] !== '' && $cfg['audience'] !== '' && strlen((string)$cfg['secret']) >= 32;
    }
}

if (!function_exists('tl_stage886_decode_json_part')) {
    function tl_stage886_decode_json_part(string $part): array
    {
        try {
            $decoded = json_decode(tl_stage886_b64url_decode($part), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new TlHttpException('Identity assertion JSON is invalid.', 401, 'identity_token_invalid');
        }
        if (!is_array($decoded)) throw new TlHttpException('Identity assertion structure is invalid.', 401, 'identity_token_invalid');
        return $decoded;
    }
}

if (!function_exists('tl_stage886_verify_token')) {
    function tl_stage886_verify_token(string $token, ?int $now = null): array
    {
        $cfg = tl_stage886_config();
        if (!tl_stage886_ready()) throw new TlHttpException('Shared identity integration is not configured.', 503, 'identity_integration_unavailable');
        $parts = explode('.', trim($token));
        if (count($parts) !== 3) throw new TlHttpException('Identity assertion format is invalid.', 401, 'identity_token_invalid');
        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = tl_stage886_decode_json_part($encodedHeader);
        $payload = tl_stage886_decode_json_part($encodedPayload);
        if (($header['alg'] ?? '') !== 'HS256' || ($header['typ'] ?? '') !== 'TL-ID') {
            throw new TlHttpException('Identity assertion algorithm is not allowed.', 401, 'identity_algorithm_rejected');
        }
        $expected = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, (string)$cfg['secret'], true);
        $provided = tl_stage886_b64url_decode($encodedSignature);
        if (!hash_equals($expected, $provided)) throw new TlHttpException('Identity assertion signature is invalid.', 401, 'identity_signature_invalid');

        $now = $now ?? time();
        $issuer = (string)($payload['iss'] ?? '');
        $audience = (string)($payload['aud'] ?? '');
        $issuedAt = (int)($payload['iat'] ?? 0);
        $expiresAt = (int)($payload['exp'] ?? 0);
        $nonce = trim((string)($payload['nonce'] ?? ''));
        $subject = trim((string)($payload['sub'] ?? ''));
        if (!hash_equals((string)$cfg['issuer'], $issuer)) throw new TlHttpException('Identity assertion issuer is invalid.', 401, 'identity_issuer_invalid');
        if (!hash_equals((string)$cfg['audience'], $audience)) throw new TlHttpException('Identity assertion audience is invalid.', 401, 'identity_audience_invalid');
        if ($issuedAt <= 0 || $expiresAt <= 0 || $expiresAt <= $issuedAt) throw new TlHttpException('Identity assertion timestamps are invalid.', 401, 'identity_time_invalid');
        if ($issuedAt > $now + (int)$cfg['clock_skew']) throw new TlHttpException('Identity assertion is not active yet.', 401, 'identity_not_active');
        if ($expiresAt < $now - (int)$cfg['clock_skew']) throw new TlHttpException('Identity assertion has expired.', 401, 'identity_expired');
        if (($expiresAt - $issuedAt) > (int)$cfg['max_ttl']) throw new TlHttpException('Identity assertion lifetime is too long.', 401, 'identity_ttl_invalid');
        if ($nonce === '' || strlen($nonce) < 16 || strlen($nonce) > 191) throw new TlHttpException('Identity assertion nonce is invalid.', 401, 'identity_nonce_invalid');
        if ($subject === '' || strlen($subject) > 191) throw new TlHttpException('Identity assertion subject is invalid.', 401, 'identity_subject_invalid');

        $email = strtolower(trim((string)($payload['email'] ?? '')));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new TlHttpException('Identity assertion email is invalid.', 401, 'identity_email_invalid');
        $role = tl_account_bridge_normalize_role((string)($payload['role'] ?? 'participant'));
        return [
            'issuer'=>$issuer,
            'audience'=>$audience,
            'microgifter_user_id'=>$subject,
            'email'=>$email,
            'name'=>mb_substr(trim((string)($payload['name'] ?? $email ?: 'Microgifter User')), 0, 191),
            'role'=>$role,
            'merchant_context'=>mb_substr(trim((string)($payload['merchant'] ?? '')), 0, 191),
            'organization_context'=>mb_substr(trim((string)($payload['organization'] ?? '')), 0, 191),
            'issued_at'=>$issuedAt,
            'expires_at'=>$expiresAt,
            'nonce'=>$nonce,
            'token_id'=>mb_substr(trim((string)($payload['jti'] ?? '')), 0, 191),
        ];
    }
}
