<?php
declare(strict_types=1);
/**
 * Resend webhook verification, replay protection, and delivery reconciliation.
 *
 * The raw request body is verified before JSON parsing. Only hashes, timestamps,
 * event types, and allowlisted metadata are persisted. This service never sends
 * email, enables workers, changes campaign gates, or calls Microgifter.
 */
require_once __DIR__ . '/training-lab-resend-email-provider.php';
require_once __DIR__ . '/training-lab-pilot-communications.php';

if (!function_exists('tl_resend_webhook_value')) {
    function tl_resend_webhook_value(string $environmentName, string $configKey, string $default = ''): string
    {
        $value = getenv($environmentName);
        if ($value !== false && $value !== '') return trim((string)$value);
        $config = function_exists('tl_security_config') ? tl_security_config() : [];
        return trim((string)($config[$configKey] ?? $default));
    }
}

if (!function_exists('tl_resend_webhook_bool')) {
    function tl_resend_webhook_bool(string $environmentName, string $configKey, bool $default = false): bool
    {
        $value = tl_resend_webhook_value($environmentName, $configKey, $default ? 'true' : 'false');
        return function_exists('tl_security_bool') ? tl_security_bool($value, $default) : (bool)filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('tl_resend_webhook_int')) {
    function tl_resend_webhook_int(string $environmentName, string $configKey, int $default, int $minimum, int $maximum): int
    {
        return max($minimum, min($maximum, (int)tl_resend_webhook_value($environmentName, $configKey, (string)$default)));
    }
}

if (!function_exists('tl_resend_webhook_config')) {
    function tl_resend_webhook_config(): array
    {
        return [
            'enabled'=>tl_resend_webhook_bool('TL_RESEND_WEBHOOK_ENABLED', 'resend_webhook_enabled', false),
            'secret'=>tl_resend_webhook_value('TL_RESEND_WEBHOOK_SECRET', 'resend_webhook_secret'),
            'tolerance_seconds'=>tl_resend_webhook_int('TL_RESEND_WEBHOOK_TOLERANCE_SECONDS', 'resend_webhook_tolerance_seconds', 300, 60, 900),
            'max_body_bytes'=>tl_resend_webhook_int('TL_RESEND_WEBHOOK_MAX_BODY_BYTES', 'resend_webhook_max_body_bytes', 262144, 4096, 1048576),
            'provider_name'=>'resend',
        ];
    }
}

if (!function_exists('tl_resend_webhook_required_tables')) {
    function tl_resend_webhook_required_tables(): array
    {
        return [
            'training_notification_provider_events',
            'training_notification_provider_states',
            'training_notification_outbox',
            'training_notification_suppressions',
        ];
    }
}

if (!function_exists('tl_resend_webhook_tables_ready')) {
    function tl_resend_webhook_tables_ready(): bool
    {
        foreach (tl_resend_webhook_required_tables() as $table) {
            if (!tl_table_exists($table)) return false;
        }
        return true;
    }
}

if (!function_exists('tl_resend_webhook_secret_bytes')) {
    function tl_resend_webhook_secret_bytes(string $secret): string
    {
        $secret = trim($secret);
        if (!str_starts_with($secret, 'whsec_') || strlen($secret) < 16) {
            throw new TlHttpException('The Resend webhook signing secret is unavailable.', 503, 'resend_webhook_secret_unavailable');
        }
        $encoded = substr($secret, 6);
        $padding = strlen($encoded) % 4;
        if ($padding) $encoded .= str_repeat('=', 4 - $padding);
        $decoded = base64_decode($encoded, true);
        if (!is_string($decoded) || strlen($decoded) < 16) {
            throw new TlHttpException('The Resend webhook signing secret is invalid.', 503, 'resend_webhook_secret_invalid');
        }
        return $decoded;
    }
}

if (!function_exists('tl_resend_webhook_headers')) {
    function tl_resend_webhook_headers(array $server): array
    {
        return [
            'id'=>trim((string)($server['HTTP_SVIX_ID'] ?? $server['HTTP_WEBHOOK_ID'] ?? '')),
            'timestamp'=>trim((string)($server['HTTP_SVIX_TIMESTAMP'] ?? $server['HTTP_WEBHOOK_TIMESTAMP'] ?? '')),
            'signature'=>trim((string)($server['HTTP_SVIX_SIGNATURE'] ?? $server['HTTP_WEBHOOK_SIGNATURE'] ?? '')),
        ];
    }
}

if (!function_exists('tl_resend_webhook_verify')) {
    function tl_resend_webhook_verify(string $rawBody, array $headers, ?int $now = null): array
    {
        $config = tl_resend_webhook_config();
        if (!$config['enabled']) throw new TlHttpException('Resend webhook ingestion is disabled.', 503, 'resend_webhook_disabled');
        if ($rawBody === '' || strlen($rawBody) > (int)$config['max_body_bytes']) {
            throw new TlHttpException('The webhook body is empty or exceeds the safe size limit.', 413, 'resend_webhook_body_invalid');
        }
        $id = trim((string)($headers['id'] ?? ''));
        $timestamp = trim((string)($headers['timestamp'] ?? ''));
        $signatureHeader = trim((string)($headers['signature'] ?? ''));
        if ($id === '' || strlen($id) > 255 || !preg_match('/^[A-Za-z0-9_.:-]+$/', $id)) {
            throw new TlHttpException('The webhook message identifier is missing or invalid.', 400, 'resend_webhook_id_invalid');
        }
        if (!ctype_digit($timestamp)) throw new TlHttpException('The webhook timestamp is missing or invalid.', 400, 'resend_webhook_timestamp_invalid');
        if ($signatureHeader === '' || strlen($signatureHeader) > 4096) {
            throw new TlHttpException('The webhook signature is missing or invalid.', 401, 'resend_webhook_signature_invalid');
        }
        $timestampValue = (int)$timestamp;
        $clock = $now ?? time();
        if (abs($clock - $timestampValue) > (int)$config['tolerance_seconds']) {
            throw new TlHttpException('The webhook timestamp is outside the accepted window.', 401, 'resend_webhook_timestamp_stale');
        }
        $signedContent = $id . '.' . $timestamp . '.' . $rawBody;
        $expected = hash_hmac('sha256', $signedContent, tl_resend_webhook_secret_bytes((string)$config['secret']), true);
        $matched = false;
        foreach (preg_split('/\s+/', $signatureHeader) ?: [] as $candidate) {
            if (!str_starts_with($candidate, 'v1,')) continue;
            $encoded = substr($candidate, 3);
            $decoded = base64_decode($encoded, true);
            if (is_string($decoded) && strlen($decoded) === strlen($expected) && hash_equals($expected, $decoded)) {
                $matched = true;
                break;
            }
        }
        if (!$matched) throw new TlHttpException('The webhook signature could not be verified.', 401, 'resend_webhook_signature_invalid');
        return [
            'id'=>$id,
            'id_hash'=>hash('sha256', $id),
            'timestamp'=>$timestampValue,
            'payload_hash'=>hash('sha256', $rawBody),
        ];
    }
}

if (!function_exists('tl_resend_webhook_status')) {
    function tl_resend_webhook_status(string $eventType): array
    {
        return match ($eventType) {
            'email.sent'=>['status'=>'sent','rank'=>10,'supported'=>true],
            'email.delivery_delayed'=>['status'=>'delayed','rank'=>20,'supported'=>true],
            'email.delivered'=>['status'=>'delivered','rank'=>30,'supported'=>true],
            'email.failed'=>['status'=>'failed','rank'=>40,'supported'=>true],
            'email.suppressed'=>['status'=>'suppressed','rank'=>50,'supported'=>true],
            'email.bounced'=>['status'=>'bounced','rank'=>60,'supported'=>true],
            'email.complained'=>['status'=>'complained','rank'=>70,'supported'=>true],
            default=>['status'=>'ignored','rank'=>0,'supported'=>false],
        };
    }
}

if (!function_exists('tl_resend_webhook_datetime')) {
    function tl_resend_webhook_datetime(string $value): string
    {
        try {
            $date = new DateTimeImmutable($value !== '' ? $value : 'now', new DateTimeZone('UTC'));
            return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable $error) {
            return gmdate('Y-m-d H:i:s');
        }
    }
}

if (!function_exists('tl_resend_webhook_normalize')) {
    function tl_resend_webhook_normalize(string $rawBody): array
    {
        try { $payload = json_decode($rawBody, true, 64, JSON_THROW_ON_ERROR); }
        catch (JsonException $error) { throw new TlHttpException('The webhook JSON is invalid.', 400, 'resend_webhook_json_invalid'); }
        if (!is_array($payload)) throw new TlHttpException('The webhook JSON must be an object.', 400, 'resend_webhook_json_invalid');
        $eventType = strtolower(trim((string)($payload['type'] ?? '')));
        if ($eventType === '' || strlen($eventType) > 64 || !preg_match('/^[a-z0-9_.-]+$/', $eventType)) {
            throw new TlHttpException('The webhook event type is missing or invalid.', 422, 'resend_webhook_event_invalid');
        }
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $messageId = trim((string)($data['email_id'] ?? $data['id'] ?? ''));
        $messageHash = $messageId === '' ? '' : hash('sha256', $messageId);
        $status = tl_resend_webhook_status($eventType);
        $to = is_array($data['to'] ?? null) ? $data['to'] : [];
        $recipient = isset($to[0]) ? tl_resend_email((string)$to[0]) : '';
        $bounce = is_array($data['bounce'] ?? null) ? $data['bounce'] : [];
        $metadata = [
            'raw_payload_stored'=>false,
            'recipient_address_stored'=>false,
            'provider_message_id_stored'=>false,
            'recipient_count'=>min(100, count($to)),
            'bounce_type'=>substr(preg_replace('/[^a-z0-9_.-]/i', '', (string)($bounce['type'] ?? '')) ?: '', 0, 64),
            'bounce_subtype'=>substr(preg_replace('/[^a-z0-9_.-]/i', '', (string)($bounce['subType'] ?? $bounce['subtype'] ?? '')) ?: '', 0, 64),
        ];
        return [
            'event_type'=>$eventType,
            'delivery_status'=>$status['status'],
            'event_rank'=>(int)$status['rank'],
            'supported'=>(bool)$status['supported'],
            'provider_message_hash'=>$messageHash,
            'recipient_hash'=>$recipient === '' ? '' : hash('sha256', $recipient),
            'event_occurred_at'=>tl_resend_webhook_datetime((string)($payload['created_at'] ?? $data['created_at'] ?? '')),
            'metadata'=>$metadata,
        ];
    }
}

if (!function_exists('tl_resend_webhook_should_apply')) {
    function tl_resend_webhook_should_apply(?array $state, array $event): bool
    {
        if (!$state) return true;
        $currentTime = strtotime((string)$state['event_occurred_at']) ?: 0;
        $eventTime = strtotime((string)$event['event_occurred_at']) ?: 0;
        if ($eventTime > $currentTime) return true;
        if ($eventTime < $currentTime) return false;
        return (int)$event['event_rank'] > (int)$state['event_rank'];
    }
}

if (!function_exists('tl_resend_webhook_add_suppression')) {
    function tl_resend_webhook_add_suppression(PDO $pdo, array $outbox, string $deliveryStatus): bool
    {
        $recipientHash = trim((string)($outbox['recipient_hash'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $recipientHash)) return false;
        $type = match ($deliveryStatus) {
            'bounced'=>'hard_bounce',
            'complained'=>'complaint',
            default=>'policy',
        };
        $reason = match ($deliveryStatus) {
            'bounced'=>'Resend reported a permanent bounce.',
            'complained'=>'Resend reported a spam complaint.',
            default=>'Resend reported provider suppression.',
        };
        $stmt = $pdo->prepare("INSERT INTO training_notification_suppressions (public_id,account_link_id,email_hash,suppression_type,reason,status,created_by_user_id) VALUES (?,?,?,?,?,'active',NULL) ON DUPLICATE KEY UPDATE account_link_id=VALUES(account_link_id),suppression_type=VALUES(suppression_type),reason=VALUES(reason),status='active',released_by_user_id=NULL,released_at=NULL,updated_at=CURRENT_TIMESTAMP");
        $stmt->execute([tl_uuid(), (int)($outbox['account_link_id'] ?? 0) ?: null, $recipientHash, $type, $reason]);
        return true;
    }
}

if (!function_exists('tl_resend_webhook_apply_outbox')) {
    function tl_resend_webhook_apply_outbox(PDO $pdo, array $outbox, array $event): bool
    {
        $status = (string)$event['delivery_status'];
        $occurredAt = (string)$event['event_occurred_at'];
        $suppressionCreated = false;
        if ($status === 'sent') {
            $stmt = $pdo->prepare("UPDATE training_notification_outbox SET last_error_code=NULL,last_error_detail=NULL WHERE id=?");
            $stmt->execute([(int)$outbox['id']]);
        } elseif ($status === 'delayed') {
            $stmt = $pdo->prepare("UPDATE training_notification_outbox SET last_error_code='provider_delivery_delayed',last_error_detail='Resend reported a temporary delivery delay.',next_attempt_at=NULL WHERE id=?");
            $stmt->execute([(int)$outbox['id']]);
        } elseif ($status === 'delivered') {
            $stmt = $pdo->prepare("UPDATE training_notification_outbox SET outbox_status='delivered',delivered_at=?,last_error_code=NULL,last_error_detail=NULL,next_attempt_at=NULL,leased_at=NULL,lease_token_hash=NULL WHERE id=?");
            $stmt->execute([$occurredAt, (int)$outbox['id']]);
        } elseif ($status === 'failed') {
            $stmt = $pdo->prepare("UPDATE training_notification_outbox SET outbox_status='failed',last_error_code='provider_delivery_failed',last_error_detail='Resend reported a terminal delivery failure.',next_attempt_at=NULL,leased_at=NULL,lease_token_hash=NULL WHERE id=?");
            $stmt->execute([(int)$outbox['id']]);
        } elseif (in_array($status, ['suppressed','bounced','complained'], true)) {
            $suppressionCreated = tl_resend_webhook_add_suppression($pdo, $outbox, $status);
            $code = 'provider_' . $status;
            $detail = match ($status) {
                'bounced'=>'Resend reported a permanent bounce and the recipient was suppressed.',
                'complained'=>'Resend reported a spam complaint and the recipient was suppressed.',
                default=>'Resend reported provider suppression.',
            };
            $stmt = $pdo->prepare("UPDATE training_notification_outbox SET outbox_status='suppressed',last_error_code=?,last_error_detail=?,next_attempt_at=NULL,leased_at=NULL,lease_token_hash=NULL WHERE id=?");
            $stmt->execute([$code, $detail, (int)$outbox['id']]);
        }
        return $suppressionCreated;
    }
}

if (!function_exists('tl_resend_webhook_upsert_state')) {
    function tl_resend_webhook_upsert_state(PDO $pdo, array $outbox, int $eventId, array $event): void
    {
        $status = (string)$event['delivery_status'];
        $column = [
            'sent'=>'sent_at', 'delayed'=>'delayed_at', 'delivered'=>'delivered_at',
            'failed'=>'failed_at', 'suppressed'=>'suppressed_at',
            'bounced'=>'bounced_at', 'complained'=>'complained_at',
        ][$status];
        $occurredAt = (string)$event['event_occurred_at'];
        $existing = $pdo->prepare('SELECT id FROM training_notification_provider_states WHERE outbox_id=? LIMIT 1 FOR UPDATE');
        $existing->execute([(int)$outbox['id']]);
        $stateId = (int)$existing->fetchColumn();
        if ($stateId > 0) {
            $sql = "UPDATE training_notification_provider_states SET current_event_id=?,delivery_status=?,event_rank=?,event_occurred_at=?,last_event_at=?,{$column}=COALESCE({$column},?),updated_at=CURRENT_TIMESTAMP WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$eventId,$status,(int)$event['event_rank'],$occurredAt,$occurredAt,$occurredAt,$stateId]);
            return;
        }
        $fields = ['sent_at'=>null,'delayed_at'=>null,'delivered_at'=>null,'failed_at'=>null,'suppressed_at'=>null,'bounced_at'=>null,'complained_at'=>null];
        $fields[$column] = $occurredAt;
        $stmt = $pdo->prepare('INSERT INTO training_notification_provider_states (public_id,outbox_id,provider_message_hash,current_event_id,delivery_status,event_rank,event_occurred_at,first_event_at,last_event_at,sent_at,delayed_at,delivered_at,failed_at,suppressed_at,bounced_at,complained_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([tl_uuid(),(int)$outbox['id'],(string)$event['provider_message_hash'],$eventId,$status,(int)$event['event_rank'],$occurredAt,$occurredAt,$occurredAt,$fields['sent_at'],$fields['delayed_at'],$fields['delivered_at'],$fields['failed_at'],$fields['suppressed_at'],$fields['bounced_at'],$fields['complained_at']]);
    }
}

if (!function_exists('tl_resend_webhook_ingest')) {
    function tl_resend_webhook_ingest(string $rawBody, array $headers, ?int $now = null): array
    {
        $verified = tl_resend_webhook_verify($rawBody, $headers, $now);
        if (!tl_resend_webhook_tables_ready()) throw new TlHttpException('Import the Resend webhook reconciliation migration first.', 503, 'resend_webhook_schema_missing');
        $event = tl_resend_webhook_normalize($rawBody);
        $pdo = tl_require_db();
        $pdo->beginTransaction();
        try {
            $duplicate = $pdo->prepare('SELECT id,public_id,processing_status,duplicate_count FROM training_notification_provider_events WHERE svix_id_hash=? LIMIT 1 FOR UPDATE');
            $duplicate->execute([(string)$verified['id_hash']]);
            $existing = $duplicate->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $pdo->prepare('UPDATE training_notification_provider_events SET duplicate_count=duplicate_count+1,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(int)$existing['id']]);
                $pdo->commit();
                return ['ok'=>true,'duplicate'=>true,'status'=>(string)$existing['processing_status'],'event_confirmation'=>substr((string)$verified['id_hash'],0,12)];
            }
            $insert = $pdo->prepare("INSERT INTO training_notification_provider_events (public_id,provider_name,svix_id_hash,payload_hash,event_type,delivery_status,event_rank,provider_message_hash,processing_status,signature_timestamp,event_occurred_at,metadata_json) VALUES (?,'resend',?,?,?,?,?,?, 'ignored',?,?,?)");
            $insert->execute([tl_uuid(),(string)$verified['id_hash'],(string)$verified['payload_hash'],(string)$event['event_type'],(string)$event['delivery_status'],(int)$event['event_rank'],(string)$event['provider_message_hash'] ?: null,(int)$verified['timestamp'],(string)$event['event_occurred_at'],json_encode($event['metadata'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
            $eventId = (int)$pdo->lastInsertId();
            if (!$event['supported']) {
                $pdo->prepare("UPDATE training_notification_provider_events SET processing_status='ignored',error_code='event_not_subscribed',error_detail='The event type is not part of the Training Lab delivery lifecycle.',reconciled_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$eventId]);
                $pdo->commit();
                return ['ok'=>true,'duplicate'=>false,'status'=>'ignored','event_confirmation'=>substr((string)$verified['id_hash'],0,12)];
            }
            if ((string)$event['provider_message_hash'] === '') {
                $pdo->prepare("UPDATE training_notification_provider_events SET processing_status='orphaned',error_code='provider_message_missing',error_detail='The event did not include a provider email identifier.',reconciled_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$eventId]);
                $pdo->commit();
                return ['ok'=>true,'duplicate'=>false,'status'=>'orphaned','event_confirmation'=>substr((string)$verified['id_hash'],0,12)];
            }
            $outboxStmt = $pdo->prepare('SELECT * FROM training_notification_outbox WHERE provider_message_hash=? LIMIT 1 FOR UPDATE');
            $outboxStmt->execute([(string)$event['provider_message_hash']]);
            $outbox = $outboxStmt->fetch(PDO::FETCH_ASSOC);
            if (!$outbox) {
                $pdo->prepare("UPDATE training_notification_provider_events SET processing_status='orphaned',error_code='outbox_not_found',error_detail='No Training Lab outbox record matched the provider message hash.',reconciled_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$eventId]);
                $pdo->commit();
                return ['ok'=>true,'duplicate'=>false,'status'=>'orphaned','event_confirmation'=>substr((string)$verified['id_hash'],0,12)];
            }
            $stateStmt = $pdo->prepare('SELECT * FROM training_notification_provider_states WHERE outbox_id=? LIMIT 1 FOR UPDATE');
            $stateStmt->execute([(int)$outbox['id']]);
            $state = $stateStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!tl_resend_webhook_should_apply($state, $event)) {
                $pdo->prepare("UPDATE training_notification_provider_events SET outbox_id=?,account_link_id=?,recipient_hash=?,processing_status='ignored',error_code='out_of_order_older',error_detail='A newer provider event is already reconciled.',reconciled_at=CURRENT_TIMESTAMP WHERE id=?")->execute([(int)$outbox['id'],(int)($outbox['account_link_id'] ?? 0) ?: null,(string)($outbox['recipient_hash'] ?? '') ?: null,$eventId]);
                $pdo->commit();
                return ['ok'=>true,'duplicate'=>false,'status'=>'ignored','event_confirmation'=>substr((string)$verified['id_hash'],0,12)];
            }
            $suppressionCreated = tl_resend_webhook_apply_outbox($pdo, $outbox, $event);
            tl_resend_webhook_upsert_state($pdo, $outbox, $eventId, $event);
            $update = $pdo->prepare("UPDATE training_notification_provider_events SET outbox_id=?,account_link_id=?,recipient_hash=?,processing_status='reconciled',suppression_created=?,error_code=NULL,error_detail=NULL,reconciled_at=CURRENT_TIMESTAMP WHERE id=?");
            $update->execute([(int)$outbox['id'],(int)($outbox['account_link_id'] ?? 0) ?: null,(string)($outbox['recipient_hash'] ?? '') ?: null,$suppressionCreated ? 1 : 0,$eventId]);
            $pdo->commit();
            return ['ok'=>true,'duplicate'=>false,'status'=>'reconciled','delivery_status'=>(string)$event['delivery_status'],'event_confirmation'=>substr((string)$verified['id_hash'],0,12)];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}

if (!function_exists('tl_resend_webhook_readiness')) {
    function tl_resend_webhook_readiness(): array
    {
        $config = tl_resend_webhook_config();
        $secret = (string)$config['secret'];
        $secretPresent = str_starts_with($secret, 'whsec_') && strlen($secret) >= 16;
        $base = function_exists('tl_notifications_config') ? (string)tl_notifications_config()['public_base_url'] : '';
        $endpoint = $base !== '' ? rtrim($base, '/') . '/api/webhooks/resend.php' : '/api/webhooks/resend.php';
        return [
            'enabled'=>(bool)$config['enabled'],
            'secret_present'=>$secretPresent,
            'schema_ready'=>tl_resend_webhook_tables_ready(),
            'tolerance_seconds'=>(int)$config['tolerance_seconds'],
            'max_body_bytes'=>(int)$config['max_body_bytes'],
            'endpoint'=>$endpoint,
            'ready'=>(bool)$config['enabled'] && $secretPresent && tl_resend_webhook_tables_ready(),
            'secret_exposed'=>false,
            'raw_payload_storage'=>false,
            'recipient_address_storage'=>false,
        ];
    }
}

if (!function_exists('tl_resend_webhook_dashboard')) {
    function tl_resend_webhook_dashboard(array $user, int $limit = 100): array
    {
        if (function_exists('tl_product_role') && tl_product_role($user) !== 'admin') {
            throw new TlHttpException('Administrator webhook diagnostics access is required.', 403, 'resend_webhook_dashboard_forbidden');
        }
        $readiness = tl_resend_webhook_readiness();
        if (!$readiness['schema_ready']) return ['readiness'=>$readiness,'totals'=>[],'events'=>[],'states'=>[]];
        $pdo = tl_require_db();
        $totals = [
            'received'=>(int)$pdo->query('SELECT COUNT(*) FROM training_notification_provider_events')->fetchColumn(),
            'reconciled'=>(int)$pdo->query("SELECT COUNT(*) FROM training_notification_provider_events WHERE processing_status='reconciled'")->fetchColumn(),
            'orphaned'=>(int)$pdo->query("SELECT COUNT(*) FROM training_notification_provider_events WHERE processing_status='orphaned'")->fetchColumn(),
            'ignored'=>(int)$pdo->query("SELECT COUNT(*) FROM training_notification_provider_events WHERE processing_status='ignored'")->fetchColumn(),
            'duplicates'=>(int)$pdo->query('SELECT COALESCE(SUM(duplicate_count),0) FROM training_notification_provider_events')->fetchColumn(),
            'suppressions'=>(int)$pdo->query('SELECT COUNT(*) FROM training_notification_provider_events WHERE suppression_created=1')->fetchColumn(),
            'last_24h'=>(int)$pdo->query('SELECT COUNT(*) FROM training_notification_provider_events WHERE received_at>=UTC_TIMESTAMP()-INTERVAL 24 HOUR')->fetchColumn(),
        ];
        $limit = max(1, min(250, $limit));
        $events = $pdo->query("SELECT public_id,event_type,delivery_status,processing_status,duplicate_count,suppression_created,error_code,error_detail,event_occurred_at,received_at,LEFT(svix_id_hash,12) event_confirmation,LEFT(provider_message_hash,12) message_confirmation FROM training_notification_provider_events ORDER BY received_at DESC,id DESC LIMIT {$limit}")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $states = $pdo->query("SELECT s.public_id,s.delivery_status,s.event_occurred_at,s.first_event_at,s.last_event_at,LEFT(s.provider_message_hash,12) message_confirmation,o.public_id outbox_public_id,c.title campaign_title FROM training_notification_provider_states s JOIN training_notification_outbox o ON o.id=s.outbox_id JOIN training_campaigns c ON c.id=o.campaign_id ORDER BY s.last_event_at DESC,s.id DESC LIMIT {$limit}")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return ['readiness'=>$readiness,'totals'=>$totals,'events'=>$events,'states'=>$states];
    }
}
