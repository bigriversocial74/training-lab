<?php
/** Stage 886 persistent account links, nonce replay protection, and trusted sessions. */
require_once __DIR__ . '/training-lab-stage886-identity-token.php';
require_once __DIR__ . '/training-lab-db.php';

if (!function_exists('tl_stage886_tables_ready')) {
    function tl_stage886_tables_ready(): bool
    {
        return tl_table_exists('training_account_links') && tl_table_exists('training_auth_nonces');
    }
}

if (!function_exists('tl_stage886_ip_hash')) {
    function tl_stage886_ip_hash(): ?string
    {
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        return $ip === '' ? null : hash('sha256', $ip);
    }
}

if (!function_exists('tl_stage886_user_agent_hash')) {
    function tl_stage886_user_agent_hash(): ?string
    {
        $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        return $ua === '' ? null : hash('sha256', $ua);
    }
}

if (!function_exists('tl_stage886_training_user_id')) {
    function tl_stage886_training_user_id(string $microgifterUserId): int
    {
        if (ctype_digit($microgifterUserId) && (int)$microgifterUserId > 0) return (int)$microgifterUserId;
        return max(1, (int)substr(sprintf('%u', crc32('mg|' . $microgifterUserId)), 0, 9));
    }
}

if (!function_exists('tl_stage886_consume_nonce')) {
    function tl_stage886_consume_nonce(PDO $pdo, array $claims): void
    {
        $nonceHash = hash('sha256', (string)$claims['nonce']);
        $stmt = $pdo->prepare('SELECT id, nonce_status, expires_at FROM training_auth_nonces WHERE nonce_hash = ? FOR UPDATE');
        $stmt->execute([$nonceHash]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) throw new TlHttpException('Identity assertion has already been used.', 409, 'identity_replay_rejected');
        $insert = $pdo->prepare('INSERT INTO training_auth_nonces (nonce_hash, issuer, audience, microgifter_user_id, nonce_status, issued_at, expires_at, consumed_at, request_ip_hash, user_agent_hash, metadata_json) VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), ?, ?, ?)');
        $insert->execute([
            $nonceHash,
            (string)$claims['issuer'],
            (string)$claims['audience'],
            (string)$claims['microgifter_user_id'],
            'consumed',
            gmdate('Y-m-d H:i:s', (int)$claims['issued_at']),
            gmdate('Y-m-d H:i:s', (int)$claims['expires_at']),
            tl_stage886_ip_hash(),
            tl_stage886_user_agent_hash(),
            json_encode(['token_id'=>(string)($claims['token_id'] ?? '')], JSON_THROW_ON_ERROR),
        ]);
    }
}

if (!function_exists('tl_stage886_upsert_link')) {
    function tl_stage886_upsert_link(PDO $pdo, array $claims): array
    {
        $microgifterUserId = (string)$claims['microgifter_user_id'];
        $trainingUserId = tl_stage886_training_user_id($microgifterUserId);
        $publicId = tl_uuid();
        $metadata = json_encode([
            'source'=>'signed_identity_handoff',
            'last_assertion_issued_at'=>gmdate('c', (int)$claims['issued_at']),
            'last_assertion_expires_at'=>gmdate('c', (int)$claims['expires_at']),
        ], JSON_THROW_ON_ERROR);
        $stmt = $pdo->prepare("INSERT INTO training_account_links (public_id, microgifter_user_id, training_user_id, email, display_name, role, merchant_context, organization_context, issuer, audience, link_status, last_authenticated_at, expires_at, metadata_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', UTC_TIMESTAMP(), NULL, ?) ON DUPLICATE KEY UPDATE training_user_id=VALUES(training_user_id), email=VALUES(email), display_name=VALUES(display_name), role=VALUES(role), merchant_context=VALUES(merchant_context), organization_context=VALUES(organization_context), issuer=VALUES(issuer), audience=VALUES(audience), last_authenticated_at=UTC_TIMESTAMP(), expires_at=NULL, metadata_json=VALUES(metadata_json)");
        $stmt->execute([
            $publicId,
            $microgifterUserId,
            $trainingUserId,
            (string)$claims['email'] ?: null,
            (string)$claims['name'],
            (string)$claims['role'],
            (string)$claims['merchant_context'] ?: null,
            (string)$claims['organization_context'] ?: null,
            (string)$claims['issuer'],
            (string)$claims['audience'],
            $metadata,
        ]);
        $read = $pdo->prepare('SELECT * FROM training_account_links WHERE microgifter_user_id = ? LIMIT 1');
        $read->execute([$microgifterUserId]);
        $link = $read->fetch(PDO::FETCH_ASSOC);
        if (!$link) throw new RuntimeException('Account link could not be loaded after synchronization.');
        if (!in_array((string)$link['link_status'], ['active','pending'], true)) {
            throw new TlHttpException('This Training Lab account link is not active.', 403, 'account_link_inactive');
        }
        return $link;
    }
}

if (!function_exists('tl_stage889_session_deadlines')) {
    function tl_stage889_session_deadlines(?int $now = null): array
    {
        $now = $now ?? time();
        $cfg = tl_stage886_config();
        return [
            'started_at'=>$now,
            'expires_at'=>$now + (int)$cfg['session_ttl'],
            'last_seen_at'=>$now,
            'idle_ttl'=>(int)$cfg['session_idle_ttl'],
        ];
    }
}

if (!function_exists('tl_stage889_session_expiry_reason')) {
    function tl_stage889_session_expiry_reason(array $user, ?int $now = null): ?string
    {
        $now = $now ?? time();
        $sessionExpiresAt = (int)($user['session_expires_at'] ?? ($_SESSION['_tl_stage889_session_expires_at'] ?? 0));
        if ($sessionExpiresAt <= 0 || $sessionExpiresAt <= $now) return 'absolute_timeout';

        $cfg = tl_stage886_config();
        $idleTtl = (int)($user['session_idle_ttl'] ?? ($_SESSION['_tl_stage889_session_idle_ttl'] ?? $cfg['session_idle_ttl']));
        if ($idleTtl > 0) {
            $lastSeenAt = (int)($user['session_last_seen_at'] ?? ($_SESSION['_tl_stage889_session_last_seen_at'] ?? 0));
            if ($lastSeenAt <= 0 || ($lastSeenAt + $idleTtl) <= $now) return 'idle_timeout';
        }
        return null;
    }
}

if (!function_exists('tl_stage889_session_public_status')) {
    function tl_stage889_session_public_status(?array $user = null, ?int $now = null): array
    {
        $now = $now ?? time();
        $cfg = tl_stage886_config();
        if (!$user || (string)($user['source'] ?? '') !== 'microgifter_adapter') {
            return [
                'active'=>false,
                'source'=>(string)($user['source'] ?? ''),
                'assertion_expires_at'=>null,
                'session_started_at'=>null,
                'session_expires_at'=>null,
                'session_last_seen_at'=>null,
                'session_idle_ttl_seconds'=>(int)$cfg['session_idle_ttl'],
                'expiry_reason'=>null,
            ];
        }
        $assertionExpiresAt = (int)($user['identity_assertion_expires_at'] ?? $user['identity_expires_at'] ?? 0);
        $sessionStartedAt = (int)($user['session_started_at'] ?? 0);
        $sessionExpiresAt = (int)($user['session_expires_at'] ?? ($_SESSION['_tl_stage889_session_expires_at'] ?? 0));
        $sessionLastSeenAt = (int)($user['session_last_seen_at'] ?? ($_SESSION['_tl_stage889_session_last_seen_at'] ?? 0));
        $reason = tl_stage889_session_expiry_reason($user, $now);
        return [
            'active'=>$reason === null,
            'source'=>'microgifter_adapter',
            'assertion_expires_at'=>$assertionExpiresAt > 0 ? gmdate('c', $assertionExpiresAt) : null,
            'session_started_at'=>$sessionStartedAt > 0 ? gmdate('c', $sessionStartedAt) : null,
            'session_expires_at'=>$sessionExpiresAt > 0 ? gmdate('c', $sessionExpiresAt) : null,
            'session_last_seen_at'=>$sessionLastSeenAt > 0 ? gmdate('c', $sessionLastSeenAt) : null,
            'session_idle_ttl_seconds'=>(int)($user['session_idle_ttl'] ?? $cfg['session_idle_ttl']),
            'expiry_reason'=>$reason,
        ];
    }
}

if (!function_exists('tl_stage886_create_trusted_session')) {
    function tl_stage886_create_trusted_session(array $link, array $claims): array
    {
        tl_security_session_start();
        session_regenerate_id(true);
        $deadlines = tl_stage889_session_deadlines();
        $user = [
            'id'=>(string)$claims['microgifter_user_id'],
            'microgifter_user_id'=>(string)$claims['microgifter_user_id'],
            'numeric_user_id'=>(int)$link['training_user_id'],
            'account_link_id'=>(int)$link['id'],
            'account_link_public_id'=>(string)$link['public_id'],
            'name'=>(string)$claims['name'],
            'email'=>(string)$claims['email'],
            'role'=>tl_account_bridge_normalize_role((string)$claims['role']),
            'source'=>'microgifter_adapter',
            'merchant_context'=>(string)$claims['merchant_context'],
            'organization_context'=>(string)$claims['organization_context'],
            'identity_issuer'=>(string)$claims['issuer'],
            'identity_audience'=>(string)$claims['audience'],
            'identity_assertion_issued_at'=>(int)$claims['issued_at'],
            'identity_assertion_expires_at'=>(int)$claims['expires_at'],
            'identity_expires_at'=>(int)$claims['expires_at'],
            'session_started_at'=>(int)$deadlines['started_at'],
            'session_expires_at'=>(int)$deadlines['expires_at'],
            'session_last_seen_at'=>(int)$deadlines['last_seen_at'],
            'session_idle_ttl'=>(int)$deadlines['idle_ttl'],
            'logged_in_at'=>gmdate('c'),
        ];
        $_SESSION['training_lab_user'] = $user;
        $_SESSION['_tl_stage886_link_id'] = (int)$link['id'];
        $_SESSION['_tl_stage886_expires_at'] = (int)$claims['expires_at'];
        $_SESSION['_tl_stage889_session_expires_at'] = (int)$deadlines['expires_at'];
        $_SESSION['_tl_stage889_session_last_seen_at'] = (int)$deadlines['last_seen_at'];
        $_SESSION['_tl_stage889_session_idle_ttl'] = (int)$deadlines['idle_ttl'];
        return $user;
    }
}

if (!function_exists('tl_stage886_accept_handoff')) {
    function tl_stage886_accept_handoff(string $token): array
    {
        if (!tl_stage886_tables_ready()) throw new TlHttpException('Stage 886 database migration is required.', 503, 'stage886_schema_missing');
        $claims = tl_stage886_verify_token($token);
        $pdo = tl_db();
        if (!$pdo) throw new TlHttpException('Training Lab database is unavailable.', 503, 'database_unavailable');
        try {
            $pdo->beginTransaction();
            tl_stage886_consume_nonce($pdo, $claims);
            $link = tl_stage886_upsert_link($pdo, $claims);
            if ((string)$link['link_status'] !== 'active') throw new TlHttpException('This Training Lab account link is not active.', 403, 'account_link_inactive');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        $user = tl_stage886_create_trusted_session($link, $claims);
        tl_account_bridge_log('training_stage886_identity_accepted', [
            'account_link_id'=>(int)$link['id'],
            'issuer'=>(string)$claims['issuer'],
            'role'=>(string)$user['role'],
            'assertion_expires_at'=>(int)$claims['expires_at'],
            'session_expires_at'=>(int)$user['session_expires_at'],
        ], (int)$link['training_user_id']);
        return ['user'=>$user,'link'=>tl_stage886_public_link($link)];
    }
}

if (!function_exists('tl_stage886_public_link')) {
    function tl_stage886_public_link(array $link): array
    {
        return [
            'public_id'=>(string)($link['public_id'] ?? ''),
            'microgifter_user_id'=>(string)($link['microgifter_user_id'] ?? ''),
            'training_user_id'=>(int)($link['training_user_id'] ?? 0),
            'email'=>(string)($link['email'] ?? ''),
            'display_name'=>(string)($link['display_name'] ?? ''),
            'role'=>(string)($link['role'] ?? 'participant'),
            'merchant_context'=>(string)($link['merchant_context'] ?? ''),
            'organization_context'=>(string)($link['organization_context'] ?? ''),
            'link_status'=>(string)($link['link_status'] ?? ''),
            'last_authenticated_at'=>$link['last_authenticated_at'] ?? null,
            'expires_at'=>$link['expires_at'] ?? null,
            'persistent'=>empty($link['expires_at']),
        ];
    }
}

if (!function_exists('tl_stage886_validate_current_session')) {
    function tl_stage886_validate_current_session(?array $user): ?array
    {
        if (!$user || (string)($user['source'] ?? '') !== 'microgifter_adapter') return $user;
        if (tl_stage889_session_expiry_reason($user) !== null) {
            tl_auth_logout_session();
            return null;
        }
        $pdo = tl_db();
        $linkId = (int)($user['account_link_id'] ?? $_SESSION['_tl_stage886_link_id'] ?? 0);
        if (!$pdo || $linkId <= 0 || !tl_table_exists('training_account_links')) {
            tl_auth_logout_session();
            return null;
        }
        $stmt = $pdo->prepare('SELECT * FROM training_account_links WHERE id = ? LIMIT 1');
        $stmt->execute([$linkId]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$link || (string)$link['link_status'] !== 'active') {
            tl_auth_logout_session();
            return null;
        }
        $now = time();
        $user['role'] = tl_account_bridge_normalize_role((string)$link['role']);
        $user['merchant_context'] = (string)($link['merchant_context'] ?? '');
        $user['organization_context'] = (string)($link['organization_context'] ?? '');
        $user['session_last_seen_at'] = $now;
        $_SESSION['_tl_stage889_session_last_seen_at'] = $now;
        $_SESSION['training_lab_user'] = $user;
        return $user;
    }
}
