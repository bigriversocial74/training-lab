<?php
require_once __DIR__ . '/training-lab-stage886-account-integration.php';

if (!function_exists('tl_stage886_admin_summary')) {
    function tl_stage886_admin_summary(): array
    {
        $pdo = tl_db();
        $counts = ['links'=>0,'active'=>0,'revoked'=>0,'suspended'=>0,'nonces'=>0,'replays'=>0];
        $recent = [];
        if ($pdo && tl_stage886_tables_ready()) {
            $counts['links'] = (int)$pdo->query('SELECT COUNT(*) FROM training_account_links')->fetchColumn();
            foreach (['active','revoked','suspended'] as $status) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM training_account_links WHERE link_status = ?');
                $stmt->execute([$status]);
                $counts[$status] = (int)$stmt->fetchColumn();
            }
            $counts['nonces'] = (int)$pdo->query('SELECT COUNT(*) FROM training_auth_nonces')->fetchColumn();
            $counts['replays'] = (int)$pdo->query("SELECT COUNT(*) FROM training_auth_nonces WHERE nonce_status = 'rejected'")->fetchColumn();
            $stmt = $pdo->query('SELECT public_id, microgifter_user_id, email, display_name, role, merchant_context, organization_context, link_status, last_authenticated_at, expires_at, revoked_at, created_at, updated_at FROM training_account_links ORDER BY updated_at DESC, id DESC LIMIT 50');
            $recent = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        }
        $cfg = tl_stage886_config();
        tl_security_session_start();
        $currentUser = $_SESSION['training_lab_user'] ?? null;
        return [
            'stage'=>'Stage 889 Shared Account Session Hardening v1',
            'configured'=>tl_stage886_ready(),
            'shared_secret_configured'=>strlen((string)$cfg['secret']) >= 32,
            'schema_ready'=>tl_stage886_tables_ready(),
            'issuer'=>$cfg['issuer'],
            'audience'=>$cfg['audience'],
            'max_ttl_seconds'=>$cfg['max_ttl'],
            'clock_skew_seconds'=>$cfg['clock_skew'],
            'session_ttl_seconds'=>$cfg['session_ttl'],
            'session_idle_ttl_seconds'=>$cfg['session_idle_ttl'],
            'current_session'=>tl_stage889_session_public_status(is_array($currentUser) ? $currentUser : null),
            'counts'=>$counts,
            'recent_links'=>$recent,
            'safe_boundaries'=>[
                'no_password_copy'=>true,
                'no_microgifter_auth_table_writes'=>true,
                'signed_short_lived_assertions'=>true,
                'assertion_and_session_lifetimes_separated'=>true,
                'persistent_links_until_revoked_or_suspended'=>true,
                'single_use_nonce_replay_protection'=>true,
                'revocation_checked_on_authenticated_requests'=>true,
                'legacy_raw_session_trust_disabled_when_configured'=>true,
                'no_payment_or_wallet_mutation'=>true,
                'no_claim_redeem_or_reward_issuing'=>true,
            ],
        ];
    }
}

if (!function_exists('tl_stage886_revoke_link')) {
    function tl_stage886_revoke_link(string $publicId, int $actorUserId): array
    {
        if (!tl_stage886_tables_ready()) throw new TlHttpException('Stage 886 database migration is required.', 503, 'stage886_schema_missing');
        if (!preg_match('/^[a-f0-9-]{36}$/i', $publicId)) throw new TlHttpException('Account link reference is invalid.', 422, 'account_link_invalid');
        $pdo = tl_db();
        $stmt = $pdo->prepare("UPDATE training_account_links SET link_status='revoked', revoked_at=UTC_TIMESTAMP(), updated_at=UTC_TIMESTAMP() WHERE public_id=? AND link_status<>'revoked'");
        $stmt->execute([$publicId]);
        if ($stmt->rowCount() < 1) throw new TlHttpException('Account link was not found or is already revoked.', 404, 'account_link_not_found');
        tl_account_bridge_log('training_stage886_account_link_revoked', ['account_link_public_id'=>$publicId], $actorUserId);
        return ['public_id'=>$publicId,'link_status'=>'revoked'];
    }
}
