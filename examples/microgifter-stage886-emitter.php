<?php
/**
 * Reference-only Microgifter emitter for Stage 886.
 *
 * Copy/adapt this contract inside the Microgifter application. The authenticated
 * Microgifter server creates the assertion; browsers must never receive the
 * shared secret or choose their own role/user ID.
 */

if (!function_exists('microgifter_stage886_b64url')) {
    function microgifter_stage886_b64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

if (!function_exists('microgifter_stage886_build_assertion')) {
    function microgifter_stage886_build_assertion(array $authenticatedUser, array $context = []): string
    {
        $secret = (string)(getenv('TL_ACCOUNT_BRIDGE_SECRET') ?: '');
        if (strlen($secret) < 32) throw new RuntimeException('TL_ACCOUNT_BRIDGE_SECRET is not configured.');

        $userId = trim((string)($authenticatedUser['id'] ?? $authenticatedUser['user_id'] ?? ''));
        if ($userId === '') throw new RuntimeException('Authenticated Microgifter user ID is required.');

        $issuer = rtrim((string)(getenv('TL_ACCOUNT_BRIDGE_ISSUER') ?: 'https://microgifter.com'), '/');
        $audience = (string)(getenv('TL_ACCOUNT_BRIDGE_AUDIENCE') ?: 'training-lab');
        $now = time();
        $claims = [
            'iss' => $issuer,
            'aud' => $audience,
            'sub' => $userId,
            'jti' => bin2hex(random_bytes(24)),
            'iat' => $now,
            'exp' => $now + 180,
            'email' => strtolower(trim((string)($authenticatedUser['email'] ?? ''))),
            'name' => trim((string)($authenticatedUser['name'] ?? $authenticatedUser['display_name'] ?? 'Microgifter User')),
            'role' => (string)($authenticatedUser['role'] ?? 'participant'),
            'merchant_id' => (string)($context['merchant_id'] ?? $authenticatedUser['merchant_id'] ?? ''),
            'organization_id' => (string)($context['organization_id'] ?? $authenticatedUser['organization_id'] ?? ''),
        ];

        $payload = microgifter_stage886_b64url(json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $signature = microgifter_stage886_b64url(hash_hmac('sha256', 'v1.' . $payload, $secret, true));
        return 'v1.' . $payload . '.' . $signature;
    }
}

if (!function_exists('microgifter_stage886_launch_form')) {
    function microgifter_stage886_launch_form(string $assertion, string $trainingLabBaseUrl, string $next = '/account.php'): string
    {
        $action = rtrim($trainingLabBaseUrl, '/') . '/account-link.php';
        $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        return '<form id="training-lab-launch" method="post" action="' . $e($action) . '">'
            . '<input type="hidden" name="assertion" value="' . $e($assertion) . '">'
            . '<input type="hidden" name="next" value="' . $e($next) . '">'
            . '<button type="submit">Open Training Lab</button>'
            . '</form>';
    }
}

/*
Example inside an authenticated Microgifter controller:

$assertion = microgifter_stage886_build_assertion($currentUser, [
    'merchant_id' => $currentMerchantId,
    'organization_id' => $currentOrganizationId,
]);

echo microgifter_stage886_launch_form(
    $assertion,
    'https://labs.microgifter.com',
    '/app/index.php'
);
*/
