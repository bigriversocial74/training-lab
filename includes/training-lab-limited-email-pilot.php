<?php
declare(strict_types=1);
/**
 * Limited live email pilot and graduation controls.
 *
 * This layer does not create recipients or a second notification system. It
 * snapshots an intentionally small cohort, sends one fixed administrator
 * canary through the existing Resend adapter, processes only member outbox
 * rows, evaluates signed webhook outcomes, and pauses on health violations.
 */
require_once __DIR__ . '/training-lab-resend-webhooks.php';

if (!function_exists('tl_limited_email_pilot_value')) {
    function tl_limited_email_pilot_value(string $environmentName, string $configKey, string $default = ''): string
    {
        $value = getenv($environmentName);
        if ($value !== false && $value !== '') return trim((string)$value);
        $config = function_exists('tl_security_config') ? tl_security_config() : [];
        return trim((string)($config[$configKey] ?? $default));
    }
}

if (!function_exists('tl_limited_email_pilot_bool')) {
    function tl_limited_email_pilot_bool(string $environmentName, string $configKey, bool $default = false): bool
    {
        $raw = tl_limited_email_pilot_value($environmentName, $configKey, $default ? 'true' : 'false');
        return function_exists('tl_security_bool') ? tl_security_bool($raw, $default) : (bool)filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('tl_limited_email_pilot_int')) {
    function tl_limited_email_pilot_int(string $environmentName, string $configKey, int $default, int $minimum, int $maximum): int
    {
        return max($minimum, min($maximum, (int)tl_limited_email_pilot_value($environmentName, $configKey, (string)$default)));
    }
}

if (!function_exists('tl_limited_email_pilot_config')) {
    function tl_limited_email_pilot_config(): array
    {
        return [
            'enabled'=>tl_limited_email_pilot_bool('TL_LIMITED_EMAIL_PILOT_ENABLED', 'limited_email_pilot_enabled', false),
            'processing_enabled'=>tl_limited_email_pilot_bool('TL_LIMITED_EMAIL_PILOT_PROCESSING_ENABLED', 'limited_email_pilot_processing_enabled', false),
            'maximum_cohort'=>tl_limited_email_pilot_int('TL_LIMITED_EMAIL_PILOT_MAXIMUM_COHORT', 'limited_email_pilot_maximum_cohort', 10, 1, 10),
            'maximum_batch'=>tl_limited_email_pilot_int('TL_LIMITED_EMAIL_PILOT_MAXIMUM_BATCH', 'limited_email_pilot_maximum_batch', 3, 1, 3),
            'webhook_timeout_seconds'=>tl_limited_email_pilot_int('TL_LIMITED_EMAIL_PILOT_WEBHOOK_TIMEOUT_SECONDS', 'limited_email_pilot_webhook_timeout_seconds', 900, 120, 7200),
            'delay_timeout_seconds'=>tl_limited_email_pilot_int('TL_LIMITED_EMAIL_PILOT_DELAY_TIMEOUT_SECONDS', 'limited_email_pilot_delay_timeout_seconds', 1800, 300, 21600),
            'minimum_delivery_rate_percent'=>tl_limited_email_pilot_int('TL_LIMITED_EMAIL_PILOT_MINIMUM_DELIVERY_RATE_PERCENT', 'limited_email_pilot_minimum_delivery_rate_percent', 100, 90, 100),
        ];
    }
}

if (!function_exists('tl_limited_email_pilot_required_tables')) {
    function tl_limited_email_pilot_required_tables(): array
    {
        return [
            'training_notification_pilot_runs',
            'training_notification_pilot_members',
            'training_notification_pilot_checks',
            'training_notification_pilot_events',
            'training_notification_outbox',
            'training_notification_attempts',
            'training_notification_provider_events',
            'training_notification_provider_states',
            'training_notification_suppressions',
            'training_pilot_controls',
            'training_account_links',
        ];
    }
}

if (!function_exists('tl_limited_email_pilot_tables_ready')) {
    function tl_limited_email_pilot_tables_ready(): bool
    {
        foreach (tl_limited_email_pilot_required_tables() as $table) {
            if (!tl_table_exists($table)) return false;
        }
        return true;
    }
}

if (!function_exists('tl_limited_email_pilot_admin')) {
    function tl_limited_email_pilot_admin(array $user): int
    {
        $role = function_exists('tl_product_role') ? tl_product_role($user) : (string)($user['role'] ?? '');
        if ($role !== 'admin') throw new TlHttpException('Administrator pilot access is required.', 403, 'limited_email_pilot_admin_required');
        return tl_campaign_user_id($user);
    }
}

if (!function_exists('tl_limited_email_pilot_clean')) {
    function tl_limited_email_pilot_clean(string $value, int $maximum = 255): string
    {
        $value = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '');
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        return mb_substr($value, 0, $maximum);
    }
}

if (!function_exists('tl_limited_email_pilot_event')) {
    function tl_limited_email_pilot_event(PDO $pdo, int $runId, string $type, string $summary, string $severity = 'info', array $metadata = [], ?int $actorId = null): void
    {
        $allowedSeverity = in_array($severity, ['info','warning','critical','success'], true) ? $severity : 'info';
        $allowedMetadata = [];
        foreach (['status','reason_code','processed','delivered','failed','paused','decision','cohort','batch'] as $key) {
            if (array_key_exists($key, $metadata)) $allowedMetadata[$key] = is_scalar($metadata[$key]) ? $metadata[$key] : null;
        }
        $stmt = $pdo->prepare('INSERT INTO training_notification_pilot_events (public_id,pilot_run_id,event_type,severity,event_summary,metadata_json,actor_user_id) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([tl_uuid(),$runId,substr(preg_replace('/[^a-z0-9_-]/i', '', $type) ?: 'pilot_event',0,96),$allowedSeverity,tl_limited_email_pilot_clean($summary,255),$allowedMetadata ? json_encode($allowedMetadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : null,$actorId]);
    }
}

if (!function_exists('tl_limited_email_pilot_run')) {
    function tl_limited_email_pilot_run(PDO $pdo, array $user, string $runRef, bool $lock = false): array
    {
        tl_limited_email_pilot_admin($user);
        $runRef = trim($runRef);
        if ($runRef === '') throw new TlHttpException('Select a pilot run.', 422, 'limited_email_pilot_run_required');
        $where = ctype_digit($runRef) ? 'r.id=?' : 'r.public_id=?';
        $sql = "SELECT r.*,c.title campaign_title,c.slug campaign_slug,c.public_id campaign_public_id,c.status campaign_status FROM training_notification_pilot_runs r JOIN training_campaigns c ON c.id=r.campaign_id WHERE {$where} LIMIT 1" . ($lock ? ' FOR UPDATE' : '');
        $stmt = $pdo->prepare($sql);
        $stmt->execute([ctype_digit($runRef) ? (int)$runRef : $runRef]);
        $run = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$run) throw new TlHttpException('The limited email pilot run was not found.', 404, 'limited_email_pilot_not_found');
        return $run;
    }
}

if (!function_exists('tl_limited_email_pilot_create')) {
    function tl_limited_email_pilot_create(array $user, array $input): array
    {
        $actorId = tl_limited_email_pilot_admin($user);
        if (!tl_limited_email_pilot_tables_ready()) throw new TlHttpException('Import the limited email pilot migration first.', 503, 'limited_email_pilot_schema_missing');
        $configuration = tl_limited_email_pilot_config();
        $cohortLimit = max(1, min((int)$configuration['maximum_cohort'], (int)($input['cohort_limit'] ?? 5)));
        $batchLimit = max(1, min((int)$configuration['maximum_batch'], (int)($input['batch_limit'] ?? 1)));
        $dailyLimit = max(1, min(100, (int)($input['daily_limit'] ?? max($cohortLimit, 10))));
        $pdo = tl_require_db();
        $pdo->beginTransaction();
        try {
            $campaign = tl_notifications_campaign($pdo, $user, (string)($input['campaign_id'] ?? $input['campaign'] ?? ''), true);
            $open = $pdo->prepare("SELECT id FROM training_notification_pilot_runs WHERE run_status IN ('draft','canary_sent','canary_confirmed','approved','running','paused') LIMIT 1 FOR UPDATE");
            $open->execute();
            if ($open->fetchColumn()) throw new TlHttpException('Only one limited live email pilot may be open at a time.', 409, 'limited_email_pilot_open_run_exists');
            $publicId = tl_uuid();
            $insert = $pdo->prepare("INSERT INTO training_notification_pilot_runs (public_id,campaign_id,owner_user_id,run_status,cohort_limit,batch_limit,daily_limit,minimum_delivery_rate_percent,webhook_timeout_seconds,delay_timeout_seconds,maximum_bounces,maximum_complaints,maximum_provider_failures,maximum_orphaned_events,created_by_user_id,updated_by_user_id) VALUES (?,?,?,'draft',?,?,?,?,?,?,0,0,0,0,?,?)");
            $insert->execute([$publicId,(int)$campaign['id'],(int)$campaign['owner_user_id'],$cohortLimit,$batchLimit,$dailyLimit,(int)$configuration['minimum_delivery_rate_percent'],(int)$configuration['webhook_timeout_seconds'],(int)$configuration['delay_timeout_seconds'],$actorId,$actorId]);
            $runId = (int)$pdo->lastInsertId();

            $participants = $pdo->prepare("SELECT id,user_id FROM training_participants WHERE campaign_id=? AND status IN ('invited','active') ORDER BY CASE WHEN status='active' THEN 0 ELSE 1 END,id LIMIT 100");
            $participants->execute([(int)$campaign['id']]);
            $selected = 0;
            foreach ($participants->fetchAll(PDO::FETCH_ASSOC) ?: [] as $participant) {
                if ($selected >= $cohortLimit) break;
                $account = tl_notifications_account_link($pdo, (int)$participant['user_id']);
                if (!$account) continue;
                $emailHash = tl_notifications_email_hash((string)$account['email']);
                if ($emailHash === '' || tl_notifications_is_suppressed($pdo, (int)$account['id'], $emailHash)) continue;
                $member = $pdo->prepare("INSERT IGNORE INTO training_notification_pilot_members (public_id,pilot_run_id,participant_id,user_id,account_link_id,recipient_hash,member_status,selected_by_user_id) VALUES (?,?,?,?,?,?,'eligible',?)");
                $member->execute([tl_uuid(),$runId,(int)$participant['id'],(int)$participant['user_id'],(int)$account['id'],$emailHash,$actorId]);
                if ($member->rowCount() > 0) $selected++;
            }
            if ($selected < 1) throw new TlHttpException('No eligible participants with active account links are available for this pilot.', 409, 'limited_email_pilot_no_eligible_members');
            tl_limited_email_pilot_event($pdo,$runId,'pilot_created','A limited email pilot was created with a fixed cohort.','info',['cohort'=>$selected,'batch'=>$batchLimit],$actorId);
            $pdo->commit();
            return ['public_id'=>$publicId,'campaign_id'=>(int)$campaign['id'],'members'=>$selected,'status'=>'draft'];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}

if (!function_exists('tl_limited_email_pilot_canary_account')) {
    function tl_limited_email_pilot_canary_account(PDO $pdo): array
    {
        $configuration = tl_resend_config();
        $recipient = tl_resend_email((string)$configuration['test_recipient']);
        if ($recipient === '') throw new TlHttpException('Configure the fixed administrator test recipient first.', 503, 'limited_email_pilot_test_recipient_missing');
        $stmt = $pdo->prepare("SELECT id,training_user_id,email,display_name FROM training_account_links WHERE link_status='active' AND LOWER(email)=? ORDER BY updated_at DESC,id DESC LIMIT 1");
        $stmt->execute([$recipient]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$account) throw new TlHttpException('The fixed administrator test recipient must match an active Training Lab account link.', 409, 'limited_email_pilot_test_account_unlinked');
        return $account;
    }
}

if (!function_exists('tl_limited_email_pilot_send_canary')) {
    function tl_limited_email_pilot_send_canary(array $user, string $runRef): array
    {
        $actorId = tl_limited_email_pilot_admin($user);
        $configuration = tl_limited_email_pilot_config();
        if (!$configuration['enabled']) throw new TlHttpException('The limited email pilot gate is disabled.', 503, 'limited_email_pilot_disabled');
        $readiness = tl_resend_readiness();
        if (empty($readiness['can_test'])) throw new TlHttpException('The fixed Resend administrator test gate is not ready.', 503, 'limited_email_pilot_canary_not_ready');
        if (!tl_resend_webhook_readiness()['ready']) throw new TlHttpException('Signed webhook reconciliation must be ready before sending a pilot canary.', 503, 'limited_email_pilot_webhook_not_ready');
        $pdo = tl_require_db();
        $pdo->beginTransaction();
        try {
            $run = tl_limited_email_pilot_run($pdo,$user,$runRef,true);
            if (!in_array((string)$run['run_status'], ['draft','paused'], true)) throw new TlHttpException('The pilot is not eligible for a new canary.', 409, 'limited_email_pilot_canary_not_allowed');
            $account = tl_limited_email_pilot_canary_account($pdo);
            $attemptStmt = $pdo->prepare("SELECT COUNT(*) FROM training_notification_pilot_events WHERE pilot_run_id=? AND event_type='canary_sent'");
            $attemptStmt->execute([(int)$run['id']]);
            $canaryAttempt = (int)$attemptStmt->fetchColumn() + 1;
            $idempotency = hash('sha256','training-limited-email-pilot-canary-v1|' . (string)$run['public_id'] . '|' . $canaryAttempt);
            $recipientHash = tl_notifications_email_hash((string)$account['email']);
            $outboxPublicId = tl_uuid();
            $outbox = $pdo->prepare("INSERT INTO training_notification_outbox (public_id,campaign_id,participant_id,user_id,account_link_id,template_id,event_type,source_type,source_id,message_class,channel,recipient_hash,idempotency_key,outbox_status,attempt_count,max_attempts,scheduled_at,context_json) VALUES (?,?,NULL,?,?,NULL,'pilot_canary','pilot_canary',?,'transactional','email',?,?,'processing',1,1,CURRENT_TIMESTAMP,?)");
            $context = json_encode(['pilot_run_public_id'=>(string)$run['public_id'],'no_participant_data'=>true,'no_reward_data'=>true], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $outbox->execute([$outboxPublicId,(int)$run['campaign_id'],(int)$account['training_user_id'],(int)$account['id'],(int)$run['id'],$recipientHash,$idempotency,$context]);
            $outboxId = (int)$pdo->lastInsertId();
            $pdo->commit();

            $result = tl_resend_send([
                'to'=>(string)$account['email'],
                'subject'=>'Training Lab limited email pilot canary',
                'text'=>"This is the controlled administrator canary for the Training Lab limited email pilot.\n\nCampaign: " . (string)$run['campaign_title'] . "\nPilot confirmation: " . substr((string)$run['public_id'],0,12) . "\n\nNo participant, proof, reward, wallet, claim, or credential data is included.",
                'idempotency_key'=>$idempotency,
            ]);
            $providerId = trim((string)($result['message_id'] ?? ''));
            $providerHash = $providerId === '' ? '' : hash('sha256',$providerId);
            $pdo->beginTransaction();
            if (empty($result['ok']) || $providerHash === '') {
                $failure = is_array($result) ? $result : [];
                tl_notifications_record_attempt($pdo,$outboxId,1,'failed',['provider_name'=>'resend','response_code'=>(string)($failure['code'] ?? ''),'error_code'=>(string)($failure['error_code'] ?? 'canary_send_failed'),'error_detail'=>tl_limited_email_pilot_clean((string)($failure['error'] ?? 'The canary was rejected.'),255)]);
                $pdo->prepare("UPDATE training_notification_outbox SET outbox_status='failed',last_error_code='pilot_canary_failed',last_error_detail='The administrator canary was rejected.',leased_at=NULL,lease_token_hash=NULL,next_attempt_at=NULL WHERE id=?")->execute([$outboxId]);
                $pdo->prepare("UPDATE training_notification_pilot_runs SET run_status='paused',canary_status='failed',canary_outbox_id=?,paused_at=CURRENT_TIMESTAMP,paused_reason='The administrator canary was rejected.',paused_by_user_id=?,updated_by_user_id=? WHERE id=?")->execute([$outboxId,$actorId,$actorId,(int)$run['id']]);
                tl_limited_email_pilot_event($pdo,(int)$run['id'],'canary_failed','The administrator canary was rejected by the provider.','critical',['reason_code'=>(string)($failure['error_code'] ?? 'canary_send_failed')],$actorId);
                $pdo->commit();
                return ['ok'=>false,'status'=>'failed','error_code'=>(string)($failure['error_code'] ?? 'canary_send_failed')];
            }
            tl_notifications_record_attempt($pdo,$outboxId,1,'delivered',['provider_name'=>'resend','provider_message_hash'=>$providerHash,'response_code'=>(string)($result['code'] ?? 'accepted')]);
            $pdo->prepare("UPDATE training_notification_outbox SET outbox_status='delivered',delivered_at=CURRENT_TIMESTAMP,provider_message_hash=?,leased_at=NULL,lease_token_hash=NULL,next_attempt_at=NULL WHERE id=?")->execute([$providerHash,$outboxId]);
            $pdo->prepare("UPDATE training_notification_pilot_runs SET run_status='canary_sent',canary_status='accepted',canary_outbox_id=?,canary_provider_message_hash=?,canary_sent_at=CURRENT_TIMESTAMP,canary_confirmed_at=NULL,paused_at=NULL,paused_reason=NULL,updated_by_user_id=? WHERE id=?")->execute([$outboxId,$providerHash,$actorId,(int)$run['id']]);
            tl_limited_email_pilot_event($pdo,(int)$run['id'],'canary_sent','The fixed administrator canary was accepted by Resend and is waiting for signed delivery confirmation.','info',['status'=>'accepted'], $actorId);
            $pdo->commit();
            return ['ok'=>true,'status'=>'accepted','run_public_id'=>(string)$run['public_id'],'message_confirmation'=>substr($providerHash,0,12)];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}

if (!function_exists('tl_limited_email_pilot_canary_state')) {
    function tl_limited_email_pilot_canary_state(PDO $pdo, array $run): string
    {
        if (empty($run['canary_outbox_id'])) return (string)$run['canary_status'];
        $stmt = $pdo->prepare('SELECT delivery_status FROM training_notification_provider_states WHERE outbox_id=? LIMIT 1');
        $stmt->execute([(int)$run['canary_outbox_id']]);
        return (string)($stmt->fetchColumn() ?: $run['canary_status']);
    }
}

if (!function_exists('tl_limited_email_pilot_refresh_canary')) {
    function tl_limited_email_pilot_refresh_canary(PDO $pdo, array $user, array $run): array
    {
        $actorId = tl_limited_email_pilot_admin($user);
        $state = tl_limited_email_pilot_canary_state($pdo,$run);
        if ($state === 'delivered' && (string)$run['canary_status'] !== 'delivered') {
            $pdo->prepare("UPDATE training_notification_pilot_runs SET run_status='canary_confirmed',canary_status='delivered',canary_confirmed_at=CURRENT_TIMESTAMP,updated_by_user_id=? WHERE id=?")->execute([$actorId,(int)$run['id']]);
            tl_limited_email_pilot_event($pdo,(int)$run['id'],'canary_confirmed','The signed webhook confirmed final canary delivery.','success',['status'=>'delivered'],$actorId);
        } elseif (in_array($state,['failed','bounced','complained','suppressed'],true) && !in_array((string)$run['canary_status'],['failed','bounced','complained','suppressed'],true)) {
            $pdo->prepare("UPDATE training_notification_pilot_runs SET run_status='paused',canary_status=?,paused_at=CURRENT_TIMESTAMP,paused_reason='The administrator canary reported a terminal provider outcome.',paused_by_user_id=?,updated_by_user_id=? WHERE id=?")->execute([$state,$actorId,$actorId,(int)$run['id']]);
            tl_limited_email_pilot_event($pdo,(int)$run['id'],'canary_terminal','The administrator canary reported a terminal provider outcome.','critical',['status'=>$state],$actorId);
        } elseif ($state === 'delayed' && (string)$run['canary_status'] !== 'delayed') {
            $pdo->prepare("UPDATE training_notification_pilot_runs SET canary_status='delayed',updated_by_user_id=? WHERE id=?")->execute([$actorId,(int)$run['id']]);
            tl_limited_email_pilot_event($pdo,(int)$run['id'],'canary_delayed','The administrator canary is delayed.','warning',['status'=>'delayed'],$actorId);
        }
        return tl_limited_email_pilot_run($pdo,$user,(string)$run['id'],true);
    }
}

if (!function_exists('tl_limited_email_pilot_metrics')) {
    function tl_limited_email_pilot_metrics(PDO $pdo, array $run): array
    {
        $webhookTimeout = max(120,(int)$run['webhook_timeout_seconds']);
        $delayTimeout = max(300,(int)$run['delay_timeout_seconds']);
        $sql = "SELECT COUNT(DISTINCT o.id) total_messages,
            SUM(CASE WHEN o.provider_message_hash IS NOT NULL THEN 1 ELSE 0 END) accepted,
            SUM(CASE WHEN ps.delivery_status='sent' THEN 1 ELSE 0 END) sent,
            SUM(CASE WHEN ps.delivery_status='delivered' THEN 1 ELSE 0 END) delivered,
            SUM(CASE WHEN ps.delivery_status='delayed' THEN 1 ELSE 0 END) delayed,
            SUM(CASE WHEN ps.delivery_status='bounced' THEN 1 ELSE 0 END) bounced,
            SUM(CASE WHEN ps.delivery_status='complained' THEN 1 ELSE 0 END) complained,
            SUM(CASE WHEN ps.delivery_status='failed' THEN 1 ELSE 0 END) provider_failed,
            SUM(CASE WHEN ps.delivery_status='suppressed' THEN 1 ELSE 0 END) provider_suppressed,
            SUM(CASE WHEN o.provider_message_hash IS NOT NULL AND ps.id IS NULL AND TIMESTAMPDIFF(SECOND,o.updated_at,UTC_TIMESTAMP())>{$webhookTimeout} THEN 1 ELSE 0 END) missing_webhook,
            SUM(CASE WHEN ps.delivery_status='delayed' AND TIMESTAMPDIFF(SECOND,ps.last_event_at,UTC_TIMESTAMP())>{$delayTimeout} THEN 1 ELSE 0 END) stale_delays,
            SUM(CASE WHEN o.outbox_status='failed' AND o.provider_message_hash IS NULL THEN 1 ELSE 0 END) local_failed
            FROM training_notification_outbox o
            JOIN training_notification_pilot_members pm ON pm.pilot_run_id=? AND pm.user_id=o.user_id AND pm.member_status IN ('eligible','active','completed')
            LEFT JOIN training_notification_provider_states ps ON ps.outbox_id=o.id
            WHERE o.campaign_id=? AND o.source_type<>'pilot_canary' AND o.created_at>=COALESCE(?,o.created_at)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([(int)$run['id'],(int)$run['campaign_id'],$run['started_at'] ?: $run['created_at']]);
        $metrics = array_map('intval',$stmt->fetch(PDO::FETCH_ASSOC) ?: []);
        $orphan = $pdo->prepare("SELECT COUNT(*) FROM training_notification_provider_events WHERE processing_status='orphaned' AND received_at>=?");
        $orphan->execute([$run['started_at'] ?: $run['created_at']]);
        $metrics['orphaned'] = (int)$orphan->fetchColumn();
        $accepted = max(0,(int)($metrics['accepted'] ?? 0));
        $delivered = max(0,(int)($metrics['delivered'] ?? 0));
        $metrics['delivery_rate_percent'] = $accepted > 0 ? (int)floor(($delivered / $accepted) * 100) : 0;
        return array_merge([
            'total_messages'=>0,'accepted'=>0,'sent'=>0,'delivered'=>0,'delayed'=>0,'bounced'=>0,'complained'=>0,
            'provider_failed'=>0,'provider_suppressed'=>0,'missing_webhook'=>0,'stale_delays'=>0,'local_failed'=>0,'orphaned'=>0,
            'delivery_rate_percent'=>0,
        ],$metrics);
    }
}

if (!function_exists('tl_limited_email_pilot_threshold_breaches')) {
    function tl_limited_email_pilot_threshold_breaches(array $run, array $metrics): array
    {
        $breaches = [];
        if ((int)$metrics['bounced'] > (int)$run['maximum_bounces']) $breaches[] = 'bounce_threshold';
        if ((int)$metrics['complained'] > (int)$run['maximum_complaints']) $breaches[] = 'complaint_threshold';
        if (((int)$metrics['provider_failed'] + (int)$metrics['local_failed']) > (int)$run['maximum_provider_failures']) $breaches[] = 'provider_failure_threshold';
        if ((int)$metrics['orphaned'] > (int)$run['maximum_orphaned_events']) $breaches[] = 'orphan_threshold';
        if ((int)$metrics['provider_suppressed'] > 0) $breaches[] = 'provider_suppression';
        if ((int)$metrics['missing_webhook'] > 0) $breaches[] = 'missing_webhook_confirmation';
        if ((int)$metrics['stale_delays'] > 0) $breaches[] = 'delivery_delay_timeout';
        return array_values(array_unique($breaches));
    }
}

if (!function_exists('tl_limited_email_pilot_pause_internal')) {
    function tl_limited_email_pilot_pause_internal(PDO $pdo, array $run, string $reason, ?int $actorId = null, string $eventType = 'pilot_paused'): void
    {
        $reason = tl_limited_email_pilot_clean($reason,255);
        $pdo->prepare("UPDATE training_notification_pilot_runs SET run_status='paused',paused_at=CURRENT_TIMESTAMP,paused_reason=?,paused_by_user_id=?,updated_by_user_id=COALESCE(?,updated_by_user_id) WHERE id=?")->execute([$reason,$actorId,$actorId,(int)$run['id']]);
        $pdo->prepare("UPDATE training_pilot_controls SET pilot_status='paused',email_enabled=0,paused_reason=?,updated_by_user_id=COALESCE(?,updated_by_user_id),updated_at=CURRENT_TIMESTAMP WHERE campaign_id=?")->execute([$reason,$actorId,(int)$run['campaign_id']]);
        tl_limited_email_pilot_event($pdo,(int)$run['id'],$eventType,$reason,'critical',['paused'=>true],$actorId);
    }
}

if (!function_exists('tl_limited_email_pilot_auto_pause')) {
    function tl_limited_email_pilot_auto_pause(PDO $pdo, array $run, ?int $actorId = null): array
    {
        $metrics = tl_limited_email_pilot_metrics($pdo,$run);
        $breaches = tl_limited_email_pilot_threshold_breaches($run,$metrics);
        if ($breaches && (string)$run['run_status'] === 'running') {
            tl_limited_email_pilot_pause_internal($pdo,$run,'Automatic pause: ' . implode(', ',$breaches) . '.',$actorId,'pilot_auto_paused');
        }
        return ['metrics'=>$metrics,'breaches'=>$breaches];
    }
}

if (!function_exists('tl_limited_email_pilot_approval_checks')) {
    function tl_limited_email_pilot_approval_checks(PDO $pdo, array $run): array
    {
        $provider = tl_notifications_provider_state();
        $webhook = tl_resend_webhook_readiness();
        $memberStmt = $pdo->prepare("SELECT COUNT(*) FROM training_notification_pilot_members WHERE pilot_run_id=? AND member_status IN ('eligible','active')");
        $memberStmt->execute([(int)$run['id']]);
        $members = (int)$memberStmt->fetchColumn();
        $canaryState = tl_limited_email_pilot_canary_state($pdo,$run);
        return [
            ['key'=>'section18_enabled','label'=>'Limited pilot gate enabled','passed'=>!empty(tl_limited_email_pilot_config()['enabled']),'observed'=>!empty(tl_limited_email_pilot_config()['enabled'])?'enabled':'disabled','required'=>'enabled'],
            ['key'=>'general_worker_disabled','label'=>'General notification worker disabled','passed'=>empty(tl_notifications_config()['worker_enabled']),'observed'=>empty(tl_notifications_config()['worker_enabled'])?'disabled':'enabled','required'=>'disabled'],
            ['key'=>'provider_ready','label'=>'Resend provider configured','passed'=>!empty($provider['configured']),'observed'=>!empty($provider['configured'])?'ready':'blocked','required'=>'ready'],
            ['key'=>'webhook_ready','label'=>'Signed webhook reconciliation ready','passed'=>!empty($webhook['ready']),'observed'=>!empty($webhook['ready'])?'ready':'blocked','required'=>'ready'],
            ['key'=>'canary_delivered','label'=>'Administrator canary delivered','passed'=>$canaryState==='delivered','observed'=>$canaryState,'required'=>'delivered'],
            ['key'=>'cohort_bounded','label'=>'Cohort is within the approved limit','passed'=>$members>=1 && $members<=(int)$run['cohort_limit'] && $members<=10,'observed'=>(string)$members,'required'=>'1-' . min(10,(int)$run['cohort_limit'])],
        ];
    }
}

if (!function_exists('tl_limited_email_pilot_persist_checks')) {
    function tl_limited_email_pilot_persist_checks(PDO $pdo, array $run, array $checks, ?int $actorId): string
    {
        $group = tl_uuid();
        $stmt = $pdo->prepare("INSERT INTO training_notification_pilot_checks (public_id,check_group_id,pilot_run_id,check_key,check_label,check_status,observed_value,required_value,detail,evaluated_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?)");
        foreach ($checks as $check) {
            $status = !empty($check['passed']) ? 'passed' : ((string)($check['status'] ?? '') === 'pending' ? 'pending' : 'failed');
            $stmt->execute([tl_uuid(),$group,(int)$run['id'],substr((string)$check['key'],0,96),tl_limited_email_pilot_clean((string)$check['label'],191),$status,tl_limited_email_pilot_clean((string)($check['observed'] ?? ''),191),tl_limited_email_pilot_clean((string)($check['required'] ?? ''),191),tl_limited_email_pilot_clean((string)($check['detail'] ?? ''),500),$actorId]);
        }
        return $group;
    }
}

if (!function_exists('tl_limited_email_pilot_approve')) {
    function tl_limited_email_pilot_approve(array $user, string $runRef): array
    {
        $actorId = tl_limited_email_pilot_admin($user);
        $pdo = tl_require_db();
        $pdo->beginTransaction();
        try {
            $run = tl_limited_email_pilot_run($pdo,$user,$runRef,true);
            $run = tl_limited_email_pilot_refresh_canary($pdo,$user,$run);
            $checks = tl_limited_email_pilot_approval_checks($pdo,$run);
            tl_limited_email_pilot_persist_checks($pdo,$run,$checks,$actorId);
            $failed = array_filter($checks,static fn(array $check): bool => empty($check['passed']));
            if ($failed) throw new TlHttpException('The pilot cannot be approved until every canary and readiness check passes.', 409, 'limited_email_pilot_approval_blocked');
            $pdo->prepare("UPDATE training_notification_pilot_runs SET run_status='approved',approved_by_user_id=?,approved_at=CURRENT_TIMESTAMP,paused_at=NULL,paused_reason=NULL,updated_by_user_id=? WHERE id=?")->execute([$actorId,$actorId,(int)$run['id']]);
            tl_limited_email_pilot_event($pdo,(int)$run['id'],'pilot_approved','The administrator approved the bounded participant pilot.','success',['status'=>'approved'],$actorId);
            $pdo->commit();
            return ['public_id'=>(string)$run['public_id'],'status'=>'approved'];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}

if (!function_exists('tl_limited_email_pilot_start')) {
    function tl_limited_email_pilot_start(array $user, string $runRef): array
    {
        $actorId = tl_limited_email_pilot_admin($user);
        $config = tl_limited_email_pilot_config();
        if (!$config['enabled'] || !$config['processing_enabled']) throw new TlHttpException('Both limited pilot gates must be explicitly enabled.', 503, 'limited_email_pilot_processing_disabled');
        if (empty(tl_notifications_config()['delivery_enabled'])) throw new TlHttpException('The notification delivery gate must be enabled for the approved limited pilot.', 503, 'notification_delivery_disabled');
        if (!empty(tl_notifications_config()['worker_enabled'])) throw new TlHttpException('Keep the unrestricted notification worker disabled during the limited pilot.', 409, 'general_notification_worker_must_remain_disabled');
        $pdo = tl_require_db();
        $pdo->beginTransaction();
        try {
            $run = tl_limited_email_pilot_run($pdo,$user,$runRef,true);
            if ((string)$run['run_status'] !== 'approved') throw new TlHttpException('The pilot must be approved before participant delivery starts.', 409, 'limited_email_pilot_not_approved');
            $checks = tl_limited_email_pilot_approval_checks($pdo,$run);
            if (array_filter($checks,static fn(array $check): bool => empty($check['passed']))) throw new TlHttpException('Pilot readiness changed after approval.', 409, 'limited_email_pilot_readiness_changed');
            $controlPublic = tl_uuid();
            $control = $pdo->prepare("INSERT INTO training_pilot_controls (public_id,campaign_id,owner_user_id,pilot_status,email_enabled,max_participants,daily_notification_limit,paused_reason,started_at,created_by_user_id,updated_by_user_id) VALUES (?,?,?,'active',1,?,?,NULL,CURRENT_TIMESTAMP,?,?) ON DUPLICATE KEY UPDATE pilot_status='active',email_enabled=1,max_participants=VALUES(max_participants),daily_notification_limit=VALUES(daily_notification_limit),paused_reason=NULL,started_at=COALESCE(started_at,CURRENT_TIMESTAMP),completed_at=NULL,updated_by_user_id=VALUES(updated_by_user_id),updated_at=CURRENT_TIMESTAMP");
            $control->execute([$controlPublic,(int)$run['campaign_id'],(int)$run['owner_user_id'],(int)$run['cohort_limit'],(int)$run['daily_limit'],$actorId,$actorId]);
            $pdo->prepare("UPDATE training_notification_pilot_members SET member_status='active',activated_at=COALESCE(activated_at,CURRENT_TIMESTAMP) WHERE pilot_run_id=? AND member_status='eligible'")->execute([(int)$run['id']]);
            $pdo->prepare("UPDATE training_notification_pilot_runs SET run_status='running',started_by_user_id=?,started_at=CURRENT_TIMESTAMP,paused_at=NULL,paused_reason=NULL,updated_by_user_id=? WHERE id=?")->execute([$actorId,$actorId,(int)$run['id']]);
            tl_limited_email_pilot_event($pdo,(int)$run['id'],'pilot_started','The bounded participant pilot started. The general notification worker remains disabled.','success',['status'=>'running','cohort'=>(int)$run['cohort_limit'],'batch'=>(int)$run['batch_limit']],$actorId);
            $pdo->commit();
            return ['public_id'=>(string)$run['public_id'],'status'=>'running'];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}

if (!function_exists('tl_limited_email_pilot_pause')) {
    function tl_limited_email_pilot_pause(array $user, string $runRef, string $reason): array
    {
        $actorId = tl_limited_email_pilot_admin($user);
        $pdo = tl_require_db();
        $pdo->beginTransaction();
        try {
            $run = tl_limited_email_pilot_run($pdo,$user,$runRef,true);
            tl_limited_email_pilot_pause_internal($pdo,$run,$reason !== '' ? $reason : 'The administrator paused the limited email pilot.',$actorId);
            $pdo->commit();
            return ['public_id'=>(string)$run['public_id'],'status'=>'paused'];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}

if (!function_exists('tl_limited_email_pilot_candidate_ids')) {
    function tl_limited_email_pilot_candidate_ids(PDO $pdo, array $run, int $limit): array
    {
        $limit = max(1,min((int)$run['batch_limit'],min(3,$limit)));
        $today = $pdo->prepare("SELECT COUNT(*) FROM training_notification_attempts a JOIN training_notification_outbox o ON o.id=a.outbox_id JOIN training_notification_pilot_members pm ON pm.pilot_run_id=? AND pm.user_id=o.user_id WHERE o.campaign_id=? AND o.source_type<>'pilot_canary' AND a.started_at>=UTC_DATE()");
        $today->execute([(int)$run['id'],(int)$run['campaign_id']]);
        $remaining = max(0,(int)$run['daily_limit'] - (int)$today->fetchColumn());
        if ($remaining < 1) return [];
        $limit = min($limit,$remaining);
        $sql = "SELECT DISTINCT o.id FROM training_notification_outbox o
            JOIN training_notification_pilot_members pm ON pm.pilot_run_id=? AND pm.user_id=o.user_id AND pm.member_status='active'
            WHERE o.campaign_id=? AND o.source_type<>'pilot_canary'
              AND ((o.outbox_status='queued' AND (o.next_attempt_at IS NULL OR o.next_attempt_at<=CURRENT_TIMESTAMP)) OR (o.outbox_status='failed' AND o.next_attempt_at IS NOT NULL AND o.next_attempt_at<=CURRENT_TIMESTAMP))
              AND o.attempt_count<o.max_attempts
            ORDER BY o.scheduled_at,o.id LIMIT {$limit}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([(int)$run['id'],(int)$run['campaign_id']]);
        return array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}

if (!function_exists('tl_limited_email_pilot_process')) {
    function tl_limited_email_pilot_process(array $user, string $runRef, int $limit = 1): array
    {
        $actorId = tl_limited_email_pilot_admin($user);
        $config = tl_limited_email_pilot_config();
        if (!$config['enabled'] || !$config['processing_enabled']) throw new TlHttpException('Limited pilot processing is disabled.', 503, 'limited_email_pilot_processing_disabled');
        if (empty(tl_notifications_config()['delivery_enabled'])) throw new TlHttpException('Notification delivery is disabled.', 503, 'notification_delivery_disabled');
        if (!empty(tl_notifications_config()['worker_enabled'])) throw new TlHttpException('The unrestricted notification worker must remain disabled.', 409, 'general_notification_worker_must_remain_disabled');
        $pdo = tl_require_db();
        $pdo->beginTransaction();
        try {
            $run = tl_limited_email_pilot_run($pdo,$user,$runRef,true);
            if ((string)$run['run_status'] !== 'running') throw new TlHttpException('The pilot is not running.', 409, 'limited_email_pilot_not_running');
            $health = tl_limited_email_pilot_auto_pause($pdo,$run,$actorId);
            if ($health['breaches']) {
                $pdo->commit();
                return ['status'=>'paused','processed'=>0,'breaches'=>$health['breaches']];
            }
            $ids = tl_limited_email_pilot_candidate_ids($pdo,$run,min((int)$config['maximum_batch'],$limit));
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        if (!$ids) return ['status'=>'idle','processed'=>0,'results'=>[]];

        $previousWorker = getenv('TL_NOTIFICATION_WORKER_ENABLED');
        putenv('TL_NOTIFICATION_WORKER_ENABLED=true');
        $results = [];
        try {
            foreach ($ids as $id) $results[] = tl_notifications_process_one($pdo,$id);
        } finally {
            if ($previousWorker === false) putenv('TL_NOTIFICATION_WORKER_ENABLED');
            else putenv('TL_NOTIFICATION_WORKER_ENABLED=' . $previousWorker);
        }
        $pdo->beginTransaction();
        try {
            $run = tl_limited_email_pilot_run($pdo,$user,$runRef,true);
            $health = tl_limited_email_pilot_auto_pause($pdo,$run,$actorId);
            tl_limited_email_pilot_event($pdo,(int)$run['id'],'pilot_batch_processed','A bounded pilot delivery batch was processed.','info',['processed'=>count($results),'delivered'=>count(array_filter($results,static fn(array $row): bool => (string)($row['status'] ?? '')==='delivered')),'failed'=>count(array_filter($results,static fn(array $row): bool => (string)($row['status'] ?? '')==='failed'))],$actorId);
            $pdo->commit();
            return ['status'=>$health['breaches'] ? 'paused' : 'running','processed'=>count($results),'results'=>$results,'metrics'=>$health['metrics'],'breaches'=>$health['breaches']];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}

if (!function_exists('tl_limited_email_pilot_graduation_checks')) {
    function tl_limited_email_pilot_graduation_checks(PDO $pdo, array $run): array
    {
        $metrics = tl_limited_email_pilot_metrics($pdo,$run);
        $breaches = tl_limited_email_pilot_threshold_breaches($run,$metrics);
        $approval = tl_limited_email_pilot_approval_checks($pdo,$run);
        return array_merge($approval,[
            ['key'=>'participant_delivery_present','label'=>'At least one participant message was provider-accepted','passed'=>(int)$metrics['accepted']>0,'observed'=>(string)$metrics['accepted'],'required'=>'>=1'],
            ['key'=>'all_webhooks_reconciled','label'=>'No participant messages are missing webhook confirmation','passed'=>(int)$metrics['missing_webhook']===0,'observed'=>(string)$metrics['missing_webhook'],'required'=>'0'],
            ['key'=>'no_health_breaches','label'=>'No bounce, complaint, failure, suppression, orphan, or stale-delay breach','passed'=>count($breaches)===0,'observed'=>$breaches ? implode(',',$breaches) : 'none','required'=>'none'],
            ['key'=>'delivery_rate','label'=>'Webhook-confirmed delivery rate meets the pilot requirement','passed'=>(int)$metrics['delivery_rate_percent']>=(int)$run['minimum_delivery_rate_percent'],'observed'=>(string)$metrics['delivery_rate_percent'] . '%','required'=>(string)$run['minimum_delivery_rate_percent'] . '%'],
        ]);
    }
}

if (!function_exists('tl_limited_email_pilot_evaluate')) {
    function tl_limited_email_pilot_evaluate(array $user, string $runRef): array
    {
        $actorId = tl_limited_email_pilot_admin($user);
        $pdo = tl_require_db();
        $pdo->beginTransaction();
        try {
            $run = tl_limited_email_pilot_run($pdo,$user,$runRef,true);
            $run = tl_limited_email_pilot_refresh_canary($pdo,$user,$run);
            $health = tl_limited_email_pilot_auto_pause($pdo,$run,$actorId);
            $run = tl_limited_email_pilot_run($pdo,$user,(string)$run['id'],true);
            $checks = tl_limited_email_pilot_graduation_checks($pdo,$run);
            $group = tl_limited_email_pilot_persist_checks($pdo,$run,$checks,$actorId);
            $passed = count(array_filter($checks,static fn(array $check): bool => !empty($check['passed'])));
            tl_limited_email_pilot_event($pdo,(int)$run['id'],'pilot_evaluated','The limited pilot acceptance checks were evaluated.',$passed===count($checks)?'success':'warning',['status'=>$passed===count($checks)?'passed':'blocked'],$actorId);
            $pdo->commit();
            return ['check_group_id'=>$group,'passed'=>$passed,'total'=>count($checks),'ready'=>$passed===count($checks),'checks'=>$checks,'metrics'=>$health['metrics'],'breaches'=>$health['breaches']];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}

if (!function_exists('tl_limited_email_pilot_graduate')) {
    function tl_limited_email_pilot_graduate(array $user, string $runRef, string $notes = ''): array
    {
        $actorId = tl_limited_email_pilot_admin($user);
        $pdo = tl_require_db();
        $pdo->beginTransaction();
        try {
            $run = tl_limited_email_pilot_run($pdo,$user,$runRef,true);
            $checks = tl_limited_email_pilot_graduation_checks($pdo,$run);
            tl_limited_email_pilot_persist_checks($pdo,$run,$checks,$actorId);
            if (array_filter($checks,static fn(array $check): bool => empty($check['passed']))) throw new TlHttpException('The pilot cannot graduate until every acceptance check passes.', 409, 'limited_email_pilot_graduation_blocked');
            $decision = tl_limited_email_pilot_clean($notes !== '' ? $notes : 'The limited live email pilot met every graduation requirement.',500);
            $pdo->prepare("UPDATE training_notification_pilot_runs SET run_status='graduated',graduated_by_user_id=?,graduated_at=CURRENT_TIMESTAMP,decision_notes=?,updated_by_user_id=? WHERE id=?")->execute([$actorId,$decision,$actorId,(int)$run['id']]);
            $pdo->prepare("UPDATE training_notification_pilot_members SET member_status='completed',completed_at=CURRENT_TIMESTAMP WHERE pilot_run_id=? AND member_status='active'")->execute([(int)$run['id']]);
            $pdo->prepare("UPDATE training_pilot_controls SET pilot_status='completed',email_enabled=0,completed_at=CURRENT_TIMESTAMP,updated_by_user_id=?,updated_at=CURRENT_TIMESTAMP WHERE campaign_id=?")->execute([$actorId,(int)$run['campaign_id']]);
            tl_limited_email_pilot_event($pdo,(int)$run['id'],'pilot_graduated','The limited live email pilot graduated with a clean acceptance record.','success',['decision'=>'graduated'],$actorId);
            $pdo->commit();
            return ['public_id'=>(string)$run['public_id'],'status'=>'graduated'];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}

if (!function_exists('tl_limited_email_pilot_reject')) {
    function tl_limited_email_pilot_reject(array $user, string $runRef, string $notes): array
    {
        $actorId = tl_limited_email_pilot_admin($user);
        $notes = tl_limited_email_pilot_clean($notes,500);
        if ($notes === '') throw new TlHttpException('A rejection reason is required.', 422, 'limited_email_pilot_rejection_reason_required');
        $pdo = tl_require_db();
        $pdo->beginTransaction();
        try {
            $run = tl_limited_email_pilot_run($pdo,$user,$runRef,true);
            $pdo->prepare("UPDATE training_notification_pilot_runs SET run_status='rejected',rejected_by_user_id=?,rejected_at=CURRENT_TIMESTAMP,decision_notes=?,updated_by_user_id=? WHERE id=?")->execute([$actorId,$notes,$actorId,(int)$run['id']]);
            $pdo->prepare("UPDATE training_pilot_controls SET pilot_status='paused',email_enabled=0,paused_reason=?,updated_by_user_id=?,updated_at=CURRENT_TIMESTAMP WHERE campaign_id=?")->execute([$notes,$actorId,(int)$run['campaign_id']]);
            tl_limited_email_pilot_event($pdo,(int)$run['id'],'pilot_rejected','The limited live email pilot was rejected.','critical',['decision'=>'rejected','reason_code'=>'administrator_decision'],$actorId);
            $pdo->commit();
            return ['public_id'=>(string)$run['public_id'],'status'=>'rejected'];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}

if (!function_exists('tl_limited_email_pilot_dashboard')) {
    function tl_limited_email_pilot_dashboard(array $user, string $runRef = ''): array
    {
        tl_limited_email_pilot_admin($user);
        $readiness = [
            'configuration'=>tl_limited_email_pilot_config(),
            'schema_ready'=>tl_limited_email_pilot_tables_ready(),
            'provider'=>tl_notifications_provider_state(),
            'webhook'=>tl_resend_webhook_readiness(),
            'general_worker_disabled'=>empty(tl_notifications_config()['worker_enabled']),
        ];
        if (!$readiness['schema_ready']) return ['readiness'=>$readiness,'campaigns'=>[],'runs'=>[],'selected'=>null,'members'=>[],'events'=>[],'checks'=>[],'metrics'=>[],'breaches'=>[]];
        $pdo = tl_require_db();
        $campaigns = $pdo->query("SELECT id,public_id,slug,title,status FROM training_campaigns WHERE status IN ('draft','active','paused') ORDER BY updated_at DESC,id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $runs = $pdo->query("SELECT r.*,c.title campaign_title,c.slug campaign_slug FROM training_notification_pilot_runs r JOIN training_campaigns c ON c.id=r.campaign_id ORDER BY r.created_at DESC,r.id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $selected = null;
        if ($runRef !== '') {
            $selected = tl_limited_email_pilot_run($pdo,$user,$runRef);
        } elseif ($runs) {
            $selected = $runs[0];
        }
        $members = $events = $checks = $metrics = $breaches = [];
        if ($selected) {
            $selected['canary_effective_status'] = tl_limited_email_pilot_canary_state($pdo,$selected);
            $memberStmt = $pdo->prepare("SELECT public_id,participant_id,user_id,member_status,activated_at,completed_at,LEFT(recipient_hash,12) recipient_confirmation,created_at FROM training_notification_pilot_members WHERE pilot_run_id=? ORDER BY id");
            $memberStmt->execute([(int)$selected['id']]);
            $members = $memberStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $eventStmt = $pdo->prepare("SELECT public_id,event_type,severity,event_summary,metadata_json,created_at FROM training_notification_pilot_events WHERE pilot_run_id=? ORDER BY created_at DESC,id DESC LIMIT 100");
            $eventStmt->execute([(int)$selected['id']]);
            $events = $eventStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $checkStmt = $pdo->prepare("SELECT check_group_id,check_key,check_label,check_status,observed_value,required_value,detail,evaluated_at FROM training_notification_pilot_checks WHERE pilot_run_id=? ORDER BY evaluated_at DESC,id DESC LIMIT 100");
            $checkStmt->execute([(int)$selected['id']]);
            $checks = $checkStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $metrics = tl_limited_email_pilot_metrics($pdo,$selected);
            $breaches = tl_limited_email_pilot_threshold_breaches($selected,$metrics);
        }
        return compact('readiness','campaigns','runs','selected','members','events','checks','metrics','breaches');
    }
}
