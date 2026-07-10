<?php
/** Stage 886 trusted-session lifetime policy applied after assertion consumption. */
require_once __DIR__ . '/training-lab-stage886-account-integration.php';

if (!function_exists('tl_stage886_session_ttl_seconds')) {
    function tl_stage886_session_ttl_seconds(): int
    {
        $root = tl_security_config();
        $local = $root['account_integration'] ?? [];
        if (!is_array($local)) $local = [];
        $env = getenv('TL_ACCOUNT_BRIDGE_SESSION_TTL');
        $ttl = $env !== false && trim((string)$env) !== ''
            ? (int)$env
            : (int)($local['session_ttl_seconds'] ?? 28800);
        return max(900, min(86400, $ttl));
    }
}

if (!function_exists('tl_stage886_apply_session_policy')) {
    function tl_stage886_apply_session_policy(array $result): array
    {
        tl_security_session_start();
        $user = $_SESSION['training_lab_user'] ?? null;
        $linkId = (int)($_SESSION['_tl_stage886_link_id'] ?? ($user['account_link_id'] ?? 0));
        if (!is_array($user) || (string)($user['source'] ?? '') !== 'microgifter_assertion' || $linkId <= 0) return $result;

        $expires = time() + tl_stage886_session_ttl_seconds();
        $user['trust_expires_at'] = $expires;
        $_SESSION['training_lab_user'] = $user;
        $_SESSION['_tl_stage886_expires'] = $expires;

        $pdo = tl_db();
        if ($pdo && tl_stage886_schema_ready()) {
            try {
                $stmt = $pdo->prepare('UPDATE training_account_links SET trust_expires_at=?, updated_at=UTC_TIMESTAMP() WHERE id=? AND link_status=\'active\'');
                $stmt->execute([gmdate('Y-m-d H:i:s', $expires), $linkId]);
            } catch (Throwable $e) {
                tl_stage886_clear_principal();
                throw new TlHttpException('The trusted session could not be finalized.', 500, 'trusted_session_finalize_failed');
            }
        }

        $result['user'] = $user;
        $result['session_expires_at'] = gmdate('c', $expires);
        $result['session_ttl_seconds'] = tl_stage886_session_ttl_seconds();
        return $result;
    }
}
