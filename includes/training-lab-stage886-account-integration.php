<?php
/**
 * Stage 886 Shared Microgifter Account Integration v1.
 *
 * Receives short-lived HMAC-signed identity assertions from Microgifter, rejects
 * replayed/expired/forged assertions, persists account links, and creates a
 * trusted Training Lab session without copying passwords or touching Microgifter
 * authentication tables.
 */
require_once __DIR__ . '/training-lab-db.php';
require_once __DIR__ . '/training-lab-security.php';

if (!function_exists('tl_stage886_config')) {
    function tl_stage886_config(): array
    {
        static $config = null;
        if (is_array($config)) return $config;

        $root = tl_security_config();
        $local = $root['account_integration'] ?? [];
        if (!is_array($local)) $local = [];

        $env = static function (string $name, string $fallback = ''): string {
            $value = getenv($name);
            return $value !== false && trim((string)$value) !== '' ? trim((string)$value) : $fallback;
        };

        $secret = $env('TL_ACCOUNT_BRIDGE_SECRET', (string)($local['secret'] ?? ''));
        $previousSecret = $env('TL_ACCOUNT_BRIDGE_PREVIOUS_SECRET', (string)($local['previous_secret'] ?? ''));
        $issuer = $env('TL_ACCOUNT_BRIDGE_ISSUER', (string)($local['issuer'] ?? 'https://microgifter.com'));
        $audience = $env('TL_ACCOUNT_BRIDGE_AUDIENCE', (string)($local['audience'] ?? 'training-lab'));
        $ttl = (int)($env('TL_ACCOUNT_BRIDGE_MAX_TTL', (string)($local['max_ttl_seconds'] ?? '300')));
        $skew = (int)($env('TL_ACCOUNT_BRIDGE_CLOCK_SKEW', (string)($local['clock_skew_seconds'] ?? '30')));

        return $config = [
            'secret' => $secret,
            'previous_secret' => $previousSecret,
            'issuer' => rtrim($issuer, '/'),
            'audience' => $audience,
            'max_ttl_seconds' => max(60, min(900, $ttl)),
            'clock_skew_seconds' => max(0, min(120, $skew)),
            'secret_configured' => strlen($secret) >= 32,
            'previous_secret_configured' => strlen($previousSecret) >= 32,
        ];
    }
}

if (!function_exists('tl_stage886_enabled')) {
    function tl_stage886_enabled(): bool
    {
        $config = tl_stage886_config();
        return !empty($config['secret_configured']) && $config['issuer'] !== '' && $config['audience'] !== '';
    }
}

if (!function_exists('tl_stage886_expected_columns')) {
    function tl_stage886_expected_columns(): array
    {
        return [
            'training_account_links' => [
                'id','public_id','identity_key','issuer','microgifter_user_id','training_numeric_user_id',
                'email','email_hash','display_name','normalized_role','merchant_id','organization_id',
                'link_status','last_authenticated_at','last_role_sync_at','trust_expires_at','revoked_at',
                'revoked_by_user_id','revoke_reason','metadata_json','created_at','updated_at',
            ],
            'training_auth_nonces' => [
                'id','public_id','account_link_id','nonce_hash','token_id_hash','issuer','subject_hash',
                'nonce_status','issued_at','expires_at','consumed_at','request_id','client_ip_hash',
                'user_agent_hash','failure_code','metadata_json','created_at','updated_at',
            ],
        ];
    }
}

if (!function_exists('tl_stage886_table_columns')) {
    function tl_stage886_table_columns(string $table): array
    {
        $expected = tl_stage886_expected_columns();
        if (!array_key_exists($table, $expected)) return [];
        $pdo = tl_db();
        if (!$pdo) return [];
        try {
            $stmt = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? ORDER BY ordinal_position');
            $stmt->execute([$table]);
            return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('tl_stage886_schema_status')) {
    function tl_stage886_schema_status(): array
    {
        $status = [];
        foreach (tl_stage886_expected_columns() as $table => $expected) {
            $actual = tl_stage886_table_columns($table);
            $missing = array_values(array_diff($expected, $actual));
            $status[$table] = [
                'exists' => $actual !== [],
                'expected_column_count' => count($expected),
                'actual_column_count' => count($actual),
                'missing_columns' => $missing,
                'ready' => $actual !== [] && $missing === [],
            ];
        }
        return $status;
    }
}

if (!function_exists('tl_stage886_schema_ready')) {
    function tl_stage886_schema_ready(): bool
    {
        foreach (tl_stage886_schema_status() as $table) if (empty($table['ready'])) return false;
        return true;
    }
}

if (!function_exists('tl_stage886_b64url_encode')) {
    function tl_stage886_b64url_encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

if (!function_exists('tl_stage886_b64url_decode')) {
    function tl_stage886_b64url_decode(string $value): string
    {
        if ($value === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            throw new TlHttpException('The account assertion is malformed.', 400, 'assertion_malformed');
        }
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
        if ($decoded === false) throw new TlHttpException('The account assertion could not be decoded.', 400, 'assertion_decode_failed');
        return $decoded;
    }
}

if (!function_exists('tl_stage886_normalize_role')) {
    function tl_stage886_normalize_role(string $role): string
    {
        $role = strtolower(trim($role));
        $aliases = [
            'owner'=>'admin','merchant_admin'=>'admin','merchant'=>'manager','operator'=>'manager',
            'trainer'=>'coach','mentor'=>'coach','user'=>'participant','customer'=>'participant',
        ];
        $role = $aliases[$role] ?? $role;
        return in_array($role, ['participant','coach','reviewer','manager','admin'], true) ? $role : 'participant';
    }
}

if (!function_exists('tl_stage886_clean_claim')) {
    function tl_stage886_clean_claim($value, int $max): string
    {
        $value = trim((string)$value);
        return mb_substr($value, 0, $max);
    }
}

if (!function_exists('tl_stage886_verify_assertion')) {
    function tl_stage886_verify_assertion(string $assertion): array
    {
        if (!tl_stage886_enabled()) {
            throw new TlHttpException('Shared account integration is not configured.', 503, 'account_integration_not_configured');
        }
        $assertion = trim($assertion);
        if ($assertion === '' || strlen($assertion) > 8192) {
            throw new TlHttpException('A valid account assertion is required.', 422, 'assertion_required');
        }

        $parts = explode('.', $assertion);
        if (count($parts) !== 3 || $parts[0] !== 'v1') {
            throw new TlHttpException('The account assertion format is not supported.', 400, 'assertion_format_invalid');
        }

        [, $payloadPart, $signaturePart] = $parts;
        $payloadJson = tl_stage886_b64url_decode($payloadPart);
        $providedSignature = tl_stage886_b64url_decode($signaturePart);
        $config = tl_stage886_config();
        $signedValue = 'v1.' . $payloadPart;
        $secretVersion = null;

        foreach ([
            'current' => (string)$config['secret'],
            'previous' => (string)$config['previous_secret'],
        ] as $version => $secret) {
            if (strlen($secret) < 32) continue;
            $expected = hash_hmac('sha256', $signedValue, $secret, true);
            if (hash_equals($expected, $providedSignature)) {
                $secretVersion = $version;
                break;
            }
        }
        if ($secretVersion === null) {
            throw new TlHttpException('The account assertion signature is invalid.', 401, 'assertion_signature_invalid');
        }

        try {
            $claims = json_decode($payloadJson, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new TlHttpException('The account assertion payload is invalid.', 400, 'assertion_payload_invalid');
        }
        if (!is_array($claims)) throw new TlHttpException('The account assertion payload must be an object.', 400, 'assertion_payload_invalid');

        $issuer = rtrim(tl_stage886_clean_claim($claims['iss'] ?? '', 191), '/');
        $audienceClaim = $claims['aud'] ?? '';
        $audiences = is_array($audienceClaim) ? array_map('strval', $audienceClaim) : [(string)$audienceClaim];
        $subject = tl_stage886_clean_claim($claims['sub'] ?? $claims['user_id'] ?? '', 191);
        $tokenId = tl_stage886_clean_claim($claims['jti'] ?? $claims['nonce'] ?? '', 191);
        $issuedAt = filter_var($claims['iat'] ?? null, FILTER_VALIDATE_INT);
        $expiresAt = filter_var($claims['exp'] ?? null, FILTER_VALIDATE_INT);
        $now = time();
        $skew = (int)$config['clock_skew_seconds'];

        if ($issuer === '' || !hash_equals((string)$config['issuer'], $issuer)) {
            throw new TlHttpException('The account assertion issuer is not trusted.', 401, 'assertion_issuer_invalid');
        }
        if (!in_array((string)$config['audience'], $audiences, true)) {
            throw new TlHttpException('The account assertion audience is invalid.', 401, 'assertion_audience_invalid');
        }
        if ($subject === '') throw new TlHttpException('The account assertion subject is missing.', 422, 'assertion_subject_missing');
        if (strlen($tokenId) < 16) throw new TlHttpException('The account assertion nonce is missing or too short.', 422, 'assertion_nonce_invalid');
        if ($issuedAt === false || $expiresAt === false || $expiresAt <= $issuedAt) {
            throw new TlHttpException('The account assertion timestamps are invalid.', 422, 'assertion_time_invalid');
        }
        if ($issuedAt > $now + $skew) throw new TlHttpException('The account assertion was issued in the future.', 401, 'assertion_not_yet_valid');
        if ($expiresAt < $now - $skew) throw new TlHttpException('The account assertion has expired.', 401, 'assertion_expired');
        if (($expiresAt - $issuedAt) > (int)$config['max_ttl_seconds']) {
            throw new TlHttpException('The account assertion lifetime is too long.', 401, 'assertion_ttl_invalid');
        }

        $email = strtolower(tl_stage886_clean_claim($claims['email'] ?? '', 254));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new TlHttpException('The account assertion email is invalid.', 422, 'assertion_email_invalid');
        }
        $name = tl_stage886_clean_claim($claims['name'] ?? $claims['display_name'] ?? $email ?: 'Microgifter User', 190);
        if ($name === '') $name = 'Microgifter User';

        return [
            'issuer' => $issuer,
            'audience' => (string)$config['audience'],
            'microgifter_user_id' => $subject,
            'token_id' => $tokenId,
            'issued_at' => (int)$issuedAt,
            'expires_at' => (int)$expiresAt,
            'email' => $email,
            'display_name' => $name,
            'role' => tl_stage886_normalize_role((string)($claims['role'] ?? 'participant')),
            'merchant_id' => tl_stage886_clean_claim($claims['merchant_id'] ?? '', 191),
            'organization_id' => tl_stage886_clean_claim($claims['organization_id'] ?? '', 191),
            'secret_version' => $secretVersion,
            'raw_claim_keys' => array_values(array_map('strval', array_keys($claims))),
        ];
    }
}

if (!function_exists('tl_stage886_request_fingerprint')) {
    function tl_stage886_request_fingerprint(): array
    {
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        $agent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        return [
            'client_ip_hash' => $ip === '' ? null : hash('sha256', $ip),
            'user_agent_hash' => $agent === '' ? null : hash('sha256', $agent),
        ];
    }
}

if (!function_exists('tl_stage886_json')) {
    function tl_stage886_json(array $value): string
    {
        try {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new TlHttpException('Account integration metadata could not be encoded.', 500, 'account_metadata_encode_failed');
        }
    }
}

if (!function_exists('tl_stage886_clear_principal')) {
    function tl_stage886_clear_principal(): void
    {
        tl_security_session_start();
        foreach (['training_lab_user','microgifter_user_id','mg_user_id','auth_user_id','logged_in_user_id','user_id','role','user_role','_tl_stage886_link_id','_tl_stage886_expires'] as $key) {
            unset($_SESSION[$key]);
        }
    }
}

if (!function_exists('tl_stage886_session_user')) {
    function tl_stage886_session_user(array $link, array $claims): array
    {
        tl_security_session_start();
        if (!headers_sent()) session_regenerate_id(true);
        $user = [
            'id' => (string)$link['microgifter_user_id'],
            'microgifter_user_id' => (string)$link['microgifter_user_id'],
            'numeric_user_id' => (int)$link['training_numeric_user_id'],
            'account_link_id' => (int)$link['id'],
            'name' => (string)$link['display_name'],
            'email' => (string)($link['email'] ?? ''),
            'role' => tl_stage886_normalize_role((string)$link['normalized_role']),
            'merchant_id' => (string)($link['merchant_id'] ?? ''),
            'organization_id' => (string)($link['organization_id'] ?? ''),
            'issuer' => (string)$link['issuer'],
            'audience' => (string)$claims['audience'],
            'source' => 'microgifter_assertion',
            'trust_expires_at' => (int)$claims['expires_at'],
            'logged_in_at' => gmdate('c'),
        ];
        $_SESSION['training_lab_user'] = $user;
        $_SESSION['microgifter_user_id'] = $user['microgifter_user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['_tl_stage886_link_id'] = $user['account_link_id'];
        $_SESSION['_tl_stage886_expires'] = $user['trust_expires_at'];
        return $user;
    }
}

if (!function_exists('tl_stage886_consume_assertion')) {
    function tl_stage886_consume_assertion(string $assertion): array
    {
        $claims = tl_stage886_verify_assertion($assertion);
        if (!tl_stage886_schema_ready()) {
            throw new TlHttpException('Stage 886 database tables are not ready. Import the Stage 886 SQL migration.', 503, 'account_schema_not_ready');
        }
        $pdo = tl_db();
        if (!$pdo) throw new TlHttpException('The account integration database is unavailable.', 503, 'account_database_unavailable');

        $identityKey = hash('sha256', $claims['issuer'] . '|' . $claims['microgifter_user_id']);
        $subjectHash = hash('sha256', $claims['microgifter_user_id']);
        $tokenIdHash = hash('sha256', $claims['token_id']);
        $nonceHash = hash('sha256', $claims['issuer'] . '|' . $claims['token_id']);
        $emailHash = $claims['email'] === '' ? null : hash('sha256', $claims['email']);
        $fingerprint = tl_stage886_request_fingerprint();
        $nonceId = null;
        $link = null;

        try {
            $pdo->beginTransaction();
            try {
                $nonce = $pdo->prepare('INSERT INTO training_auth_nonces
                    (public_id, account_link_id, nonce_hash, token_id_hash, issuer, subject_hash, nonce_status, issued_at, expires_at, consumed_at, request_id, client_ip_hash, user_agent_hash, metadata_json, created_at, updated_at)
                    VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())');
                $nonce->execute([
                    tl_uuid(), $nonceHash, $tokenIdHash, $claims['issuer'], $subjectHash, 'consumed',
                    gmdate('Y-m-d H:i:s', $claims['issued_at']), gmdate('Y-m-d H:i:s', $claims['expires_at']),
                    tl_security_request_id(), $fingerprint['client_ip_hash'], $fingerprint['user_agent_hash'],
                    tl_stage886_json(['secret_version'=>$claims['secret_version'],'claim_keys'=>$claims['raw_claim_keys']]),
                ]);
                $nonceId = (int)$pdo->lastInsertId();
            } catch (PDOException $e) {
                if ((string)$e->getCode() === '23000') {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    throw new TlHttpException('This account assertion has already been used.', 409, 'assertion_replayed');
                }
                throw $e;
            }

            $select = $pdo->prepare('SELECT * FROM training_account_links WHERE identity_key = ? LIMIT 1 FOR UPDATE');
            $select->execute([$identityKey]);
            $link = $select->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($link && in_array((string)$link['link_status'], ['revoked','suspended'], true)) {
                $reject = $pdo->prepare("UPDATE training_auth_nonces SET nonce_status='rejected', failure_code='account_link_{$link['link_status']}', updated_at=UTC_TIMESTAMP() WHERE id=?");
                $reject->execute([$nonceId]);
                $pdo->commit();
                throw new TlHttpException('This Training Lab account link is ' . $link['link_status'] . '.', 403, 'account_link_' . $link['link_status']);
            }

            $metadata = tl_stage886_json([
                'audience'=>$claims['audience'],
                'secret_version'=>$claims['secret_version'],
                'last_request_id'=>tl_security_request_id(),
            ]);
            if ($link) {
                $update = $pdo->prepare('UPDATE training_account_links SET
                    microgifter_user_id=?, email=?, email_hash=?, display_name=?, normalized_role=?, merchant_id=?, organization_id=?,
                    link_status=\'active\', last_authenticated_at=UTC_TIMESTAMP(), last_role_sync_at=UTC_TIMESTAMP(), trust_expires_at=?,
                    revoked_at=NULL, revoked_by_user_id=NULL, revoke_reason=NULL, metadata_json=?, updated_at=UTC_TIMESTAMP()
                    WHERE id=?');
                $update->execute([
                    $claims['microgifter_user_id'], $claims['email'] ?: null, $emailHash, $claims['display_name'], $claims['role'],
                    $claims['merchant_id'] ?: null, $claims['organization_id'] ?: null,
                    gmdate('Y-m-d H:i:s', $claims['expires_at']), $metadata, (int)$link['id'],
                ]);
            } else {
                $numericId = ctype_digit($claims['microgifter_user_id']) && (int)$claims['microgifter_user_id'] > 0
                    ? (int)$claims['microgifter_user_id']
                    : null;
                $insert = $pdo->prepare('INSERT INTO training_account_links
                    (public_id, identity_key, issuer, microgifter_user_id, training_numeric_user_id, email, email_hash, display_name, normalized_role, merchant_id, organization_id, link_status, last_authenticated_at, last_role_sync_at, trust_expires_at, metadata_json, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'active\', UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())');
                $insert->execute([
                    tl_uuid(), $identityKey, $claims['issuer'], $claims['microgifter_user_id'], $numericId,
                    $claims['email'] ?: null, $emailHash, $claims['display_name'], $claims['role'],
                    $claims['merchant_id'] ?: null, $claims['organization_id'] ?: null,
                    gmdate('Y-m-d H:i:s', $claims['expires_at']), $metadata,
                ]);
                $linkId = (int)$pdo->lastInsertId();
                if ($numericId === null) {
                    $numericId = 800000000000 + $linkId;
                    $setNumeric = $pdo->prepare('UPDATE training_account_links SET training_numeric_user_id=? WHERE id=?');
                    $setNumeric->execute([$numericId, $linkId]);
                }
            }

            $select->execute([$identityKey]);
            $link = $select->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$link) throw new RuntimeException('Account link could not be loaded after authentication.');

            $bindNonce = $pdo->prepare('UPDATE training_auth_nonces SET account_link_id=?, updated_at=UTC_TIMESTAMP() WHERE id=?');
            $bindNonce->execute([(int)$link['id'], $nonceId]);
            $pdo->commit();
        } catch (TlHttpException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw new TlHttpException('The shared account link could not be completed.', 500, 'account_link_failed');
        }

        $user = tl_stage886_session_user($link, $claims);
        return [
            'linked' => true,
            'account_link_public_id' => (string)$link['public_id'],
            'link_status' => (string)$link['link_status'],
            'user' => $user,
            'assertion_expires_at' => gmdate('c', $claims['expires_at']),
            'replay_protected' => true,
        ];
    }
}

if (!function_exists('tl_stage886_current_principal')) {
    function tl_stage886_current_principal(): ?array
    {
        tl_security_session_start();
        $sessionUser = $_SESSION['training_lab_user'] ?? null;
        if (!is_array($sessionUser) || (string)($sessionUser['source'] ?? '') !== 'microgifter_assertion') return null;
        if (!tl_stage886_enabled() || !tl_stage886_schema_ready()) {
            tl_stage886_clear_principal();
            return null;
        }
        $expires = (int)($sessionUser['trust_expires_at'] ?? $_SESSION['_tl_stage886_expires'] ?? 0);
        if ($expires <= time()) {
            tl_stage886_clear_principal();
            return null;
        }
        $linkId = (int)($sessionUser['account_link_id'] ?? $_SESSION['_tl_stage886_link_id'] ?? 0);
        if ($linkId <= 0) {
            tl_stage886_clear_principal();
            return null;
        }
        $pdo = tl_db();
        if (!$pdo) {
            tl_stage886_clear_principal();
            return null;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM training_account_links WHERE id=? AND link_status='active' LIMIT 1");
            $stmt->execute([$linkId]);
            $link = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            $link = null;
        }
        if (!$link || (string)$link['issuer'] !== (string)tl_stage886_config()['issuer']) {
            tl_stage886_clear_principal();
            return null;
        }
        $sessionUser['microgifter_user_id'] = (string)$link['microgifter_user_id'];
        $sessionUser['numeric_user_id'] = (int)$link['training_numeric_user_id'];
        $sessionUser['name'] = (string)$link['display_name'];
        $sessionUser['email'] = (string)($link['email'] ?? '');
        $sessionUser['role'] = tl_stage886_normalize_role((string)$link['normalized_role']);
        $sessionUser['merchant_id'] = (string)($link['merchant_id'] ?? '');
        $sessionUser['organization_id'] = (string)($link['organization_id'] ?? '');
        $_SESSION['training_lab_user'] = $sessionUser;
        $_SESSION['role'] = $sessionUser['role'];
        return $sessionUser;
    }
}

if (!function_exists('tl_stage886_update_link_status')) {
    function tl_stage886_update_link_status(array $input, array $actor): array
    {
        if (!tl_stage886_schema_ready()) throw new TlHttpException('Stage 886 database tables are not ready.', 503, 'account_schema_not_ready');
        $status = strtolower(trim((string)($input['link_status'] ?? $input['status'] ?? '')));
        if (!in_array($status, ['active','revoked','suspended'], true)) throw new TlHttpException('Invalid account link status.', 422, 'account_link_status_invalid');
        $publicId = trim((string)($input['account_link_public_id'] ?? $input['public_id'] ?? ''));
        if ($publicId === '') throw new TlHttpException('Account link reference is required.', 422, 'account_link_reference_required');
        $reason = mb_substr(trim((string)($input['reason'] ?? '')), 0, 255);
        $actorId = tl_security_numeric_user_id($actor);
        $pdo = tl_db();
        if (!$pdo) throw new TlHttpException('The account integration database is unavailable.', 503, 'account_database_unavailable');

        $stmt = $pdo->prepare('UPDATE training_account_links SET link_status=?, revoked_at=?, revoked_by_user_id=?, revoke_reason=?, updated_at=UTC_TIMESTAMP() WHERE public_id=?');
        $isActive = $status === 'active';
        $stmt->execute([
            $status,
            $isActive ? null : gmdate('Y-m-d H:i:s'),
            $isActive ? null : $actorId,
            $isActive ? null : ($reason !== '' ? $reason : ucfirst($status) . ' by Training Lab administrator'),
            $publicId,
        ]);
        if ($stmt->rowCount() < 1) throw new TlHttpException('Account link was not found or did not change.', 404, 'account_link_not_found');

        if ((int)($_SESSION['_tl_stage886_link_id'] ?? 0) > 0) {
            $current = $pdo->prepare('SELECT public_id FROM training_account_links WHERE id=? LIMIT 1');
            $current->execute([(int)$_SESSION['_tl_stage886_link_id']]);
            if ((string)$current->fetchColumn() === $publicId && !$isActive) tl_stage886_clear_principal();
        }
        return ['updated'=>true,'account_link_public_id'=>$publicId,'link_status'=>$status];
    }
}

if (!function_exists('tl_stage886_admin_summary')) {
    function tl_stage886_admin_summary(): array
    {
        $config = tl_stage886_config();
        $schema = tl_stage886_schema_status();
        $summary = [
            'stage' => 'Stage 886 Shared Microgifter Account Integration v1',
            'configured' => tl_stage886_enabled(),
            'schema_ready' => tl_stage886_schema_ready(),
            'ready' => tl_stage886_enabled() && tl_stage886_schema_ready(),
            'configuration' => [
                'issuer'=>$config['issuer'],
                'audience'=>$config['audience'],
                'max_ttl_seconds'=>$config['max_ttl_seconds'],
                'clock_skew_seconds'=>$config['clock_skew_seconds'],
                'secret_configured'=>$config['secret_configured'],
                'previous_secret_configured'=>$config['previous_secret_configured'],
            ],
            'schema' => $schema,
            'link_counts' => [],
            'nonce_counts' => [],
            'recent_links' => [],
            'recent_nonces' => [],
            'safe_boundaries' => [
                'no_password_copy'=>true,
                'no_microgifter_auth_table_writes'=>true,
                'hmac_signed_short_lived_assertions'=>true,
                'single_use_nonce_enforcement'=>true,
                'no_payment_processing'=>true,
                'no_wallet_mutation'=>true,
                'no_claim_redeem_mutation'=>true,
                'no_reward_issuing'=>true,
            ],
        ];
        if (!$summary['schema_ready']) return $summary;
        $pdo = tl_db();
        if (!$pdo) return $summary;
        try {
            foreach ($pdo->query('SELECT link_status, COUNT(*) total FROM training_account_links GROUP BY link_status')->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $summary['link_counts'][(string)$row['link_status']] = (int)$row['total'];
            }
            foreach ($pdo->query('SELECT nonce_status, COUNT(*) total FROM training_auth_nonces GROUP BY nonce_status')->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $summary['nonce_counts'][(string)$row['nonce_status']] = (int)$row['total'];
            }
            $summary['recent_links'] = $pdo->query('SELECT public_id, issuer, microgifter_user_id, training_numeric_user_id, email, display_name, normalized_role, merchant_id, organization_id, link_status, last_authenticated_at, trust_expires_at, revoked_at, revoke_reason, created_at, updated_at FROM training_account_links ORDER BY COALESCE(last_authenticated_at, created_at) DESC, id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $summary['recent_nonces'] = $pdo->query('SELECT public_id, account_link_id, issuer, nonce_status, issued_at, expires_at, consumed_at, request_id, failure_code, created_at FROM training_auth_nonces ORDER BY id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $summary['read_error'] = tl_security_debug_enabled() ? $e->getMessage() : 'Account integration diagnostics could not be loaded.';
        }
        return $summary;
    }
}

if (!function_exists('tl_stage886_status_class')) {
    function tl_stage886_status_class(string $status): string
    {
        return in_array($status, ['active','consumed','pass','ready'], true) ? 'good' : (in_array($status, ['pending','suspended'], true) ? 'warn' : 'bad');
    }
}

if (!function_exists('tl_stage886_render_admin')) {
    function tl_stage886_render_admin(array $summary, ?array $actionResult = null, ?string $error = null): void
    {
        $e = static fn($v): string => function_exists('labs_e') ? labs_e((string)$v) : htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        echo '<section class="labs-page-title"><div><span class="labs-eyebrow">Stage 886</span><h1>Shared Account Integration</h1><p class="labs-copy">Signed Microgifter identity handoff, persistent account links, replay protection, trusted roles, revocation, and session diagnostics.</p></div><div class="labs-actions"><a class="labs-btn" href="' . $e(function_exists('labs_url') ? labs_url('/api/training/account-integration-status.php') : '/api/training/account-integration-status.php') . '">View JSON</a></div></section>';
        echo '<section class="labs-kpis">';
        echo '<div class="labs-kpi"><span class="labs-muted">Configured</span><strong>' . (!empty($summary['configured']) ? 'Yes' : 'No') . '</strong><small>signed assertion secret</small></div>';
        echo '<div class="labs-kpi"><span class="labs-muted">Schema</span><strong>' . (!empty($summary['schema_ready']) ? 'Ready' : 'Import SQL') . '</strong><small>links + nonces</small></div>';
        echo '<div class="labs-kpi"><span class="labs-muted">Active links</span><strong>' . (int)($summary['link_counts']['active'] ?? 0) . '</strong><small>trusted identities</small></div>';
        echo '<div class="labs-kpi"><span class="labs-muted">Rejected assertions</span><strong>' . (int)($summary['nonce_counts']['rejected'] ?? 0) . '</strong><small>audit records</small></div>';
        echo '</section>';
        if ($actionResult) echo '<section class="labs-card labs-success-card"><h2>Account link updated</h2><pre class="labs-stage25-code">' . $e(json_encode($actionResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre></section>';
        if ($error) echo '<section class="labs-card labs-error-card"><h2>Action needs attention</h2><p class="labs-copy">' . $e($error) . '</p></section>';

        echo '<section class="labs-flow-grid">';
        echo '<article class="labs-card"><h2>Adapter configuration</h2><div class="labs-table-wrap"><table class="labs-table"><tbody>';
        foreach ((array)($summary['configuration'] ?? []) as $key => $value) echo '<tr><th>' . $e(str_replace('_',' ',ucwords((string)$key,'_'))) . '</th><td>' . $e(is_bool($value) ? ($value ? 'Yes' : 'No') : $value) . '</td></tr>';
        echo '</tbody></table></div></article>';
        echo '<article class="labs-card"><h2>Schema readiness</h2><div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>Table</th><th>Status</th><th>Missing columns</th></tr></thead><tbody>';
        foreach ((array)($summary['schema'] ?? []) as $table => $row) echo '<tr><td>' . $e($table) . '</td><td><span class="labs-pill is-' . (!empty($row['ready']) ? 'good' : 'bad') . '">' . (!empty($row['ready']) ? 'ready' : 'check') . '</span></td><td>' . $e(implode(', ', (array)($row['missing_columns'] ?? []))) . '</td></tr>';
        echo '</tbody></table></div></article>';
        echo '</section>';

        echo '<section class="labs-card"><h2>Linked identities</h2><div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>Identity</th><th>Role</th><th>Context</th><th>Status</th><th>Last authentication</th><th>Control</th></tr></thead><tbody>';
        foreach ((array)($summary['recent_links'] ?? []) as $row) {
            $status = (string)($row['link_status'] ?? 'pending');
            echo '<tr><td><strong>' . $e($row['display_name'] ?? 'Microgifter User') . '</strong><br><small>' . $e($row['email'] ?? '') . '<br>ID ' . $e($row['microgifter_user_id'] ?? '') . '</small></td><td>' . $e($row['normalized_role'] ?? 'participant') . '</td><td><small>Merchant: ' . $e($row['merchant_id'] ?? '—') . '<br>Org: ' . $e($row['organization_id'] ?? '—') . '</small></td><td><span class="labs-pill is-' . $e(tl_stage886_status_class($status)) . '">' . $e($status) . '</span></td><td>' . $e($row['last_authenticated_at'] ?? 'Never') . '</td><td><form method="post">' . tl_security_csrf_field() . '<input type="hidden" name="training_action" value="manage_account_link"><input type="hidden" name="account_link_public_id" value="' . $e($row['public_id'] ?? '') . '"><select name="link_status"><option value="active">Active</option><option value="suspended">Suspend</option><option value="revoked">Revoke</option></select><input name="reason" maxlength="255" placeholder="Reason"><button class="labs-btn" type="submit">Update</button></form></td></tr>';
        }
        if (empty($summary['recent_links'])) echo '<tr><td colspan="6">No linked identities yet.</td></tr>';
        echo '</tbody></table></div></section>';

        echo '<section class="labs-card"><h2>Recent assertion audit</h2><div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>Issued</th><th>Status</th><th>Expires</th><th>Request</th><th>Failure</th></tr></thead><tbody>';
        foreach ((array)($summary['recent_nonces'] ?? []) as $row) echo '<tr><td>' . $e($row['issued_at'] ?? '') . '</td><td><span class="labs-pill is-' . $e(tl_stage886_status_class((string)($row['nonce_status'] ?? ''))) . '">' . $e($row['nonce_status'] ?? '') . '</span></td><td>' . $e($row['expires_at'] ?? '') . '</td><td><small>' . $e($row['request_id'] ?? '') . '</small></td><td>' . $e($row['failure_code'] ?? '') . '</td></tr>';
        if (empty($summary['recent_nonces'])) echo '<tr><td colspan="5">No assertions consumed yet.</td></tr>';
        echo '</tbody></table></div></section>';
        echo '<section class="labs-safe-note">Passwords are never copied. Stage 886 does not issue rewards, mutate wallets, process payments, or write to Microgifter authentication tables.</section>';
    }
}
