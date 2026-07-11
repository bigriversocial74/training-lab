<?php
/**
 * Pilot operations and communications.
 *
 * This service owns only Training Lab notification templates, preferences,
 * suppressions, pilot controls, and an adapter-gated email outbox. Recipient
 * identity is resolved from the existing training_account_links authority.
 * It never creates users, changes wallets, issues gifts, or calls Microgifter.
 */
require_once __DIR__ . '/training-lab-reward-management.php';

if (!function_exists('tl_notifications_required_tables')) {
    function tl_notifications_required_tables(): array
    {
        return [
            'training_notification_templates',
            'training_notification_preferences',
            'training_notification_suppressions',
            'training_pilot_controls',
            'training_notification_outbox',
            'training_notification_attempts',
            'training_account_links',
        ];
    }
}

if (!function_exists('tl_notifications_tables_ready')) {
    function tl_notifications_tables_ready(): bool
    {
        foreach (tl_notifications_required_tables() as $table) {
            if (!tl_table_exists($table)) return false;
        }
        return true;
    }
}

if (!function_exists('tl_notifications_config')) {
    function tl_notifications_config(): array
    {
        $cfg = function_exists('tl_security_config') ? tl_security_config() : [];
        $bool = static function (string $env, string $key, bool $default = false) use ($cfg): bool {
            $value = getenv($env);
            if ($value !== false && $value !== '') return tl_security_bool($value, $default);
            return tl_security_bool($cfg[$key] ?? $default, $default);
        };
        $int = static function (string $env, string $key, int $default, int $min, int $max) use ($cfg): int {
            $value = getenv($env);
            $raw = $value !== false && $value !== '' ? $value : ($cfg[$key] ?? $default);
            return max($min, min($max, (int)$raw));
        };
        $string = static function (string $env, string $key, string $default = '') use ($cfg): string {
            $value = getenv($env);
            return trim((string)($value !== false && $value !== '' ? $value : ($cfg[$key] ?? $default)));
        };
        return [
            'delivery_enabled'=>$bool('TL_NOTIFICATION_DELIVERY_ENABLED', 'notification_delivery_enabled', false),
            'worker_enabled'=>$bool('TL_NOTIFICATION_WORKER_ENABLED', 'notification_worker_enabled', false),
            'batch_size'=>$int('TL_NOTIFICATION_BATCH_SIZE', 'notification_batch_size', 10, 1, 100),
            'max_attempts'=>$int('TL_NOTIFICATION_MAX_ATTEMPTS', 'notification_max_attempts', 5, 1, 20),
            'retry_base_seconds'=>$int('TL_NOTIFICATION_RETRY_BASE_SECONDS', 'notification_retry_base_seconds', 300, 30, 86400),
            'lease_seconds'=>$int('TL_NOTIFICATION_LEASE_SECONDS', 'notification_lease_seconds', 300, 60, 3600),
            'provider_name'=>preg_replace('/[^a-z0-9_.-]/i', '', $string('TL_NOTIFICATION_PROVIDER', 'notification_provider', 'adapter')) ?: 'adapter',
            'unsubscribe_secret'=>$string('TL_NOTIFICATION_UNSUBSCRIBE_SECRET', 'notification_unsubscribe_secret'),
            'public_base_url'=>rtrim($string('TL_PUBLIC_BASE_URL', 'public_base_url'), '/'),
        ];
    }
}

if (!function_exists('tl_notifications_scope')) {
    function tl_notifications_scope(array $user): array
    {
        $scope = tl_reward_management_scope($user);
        return [
            'role'=>$scope['role'],
            'owner_user_id'=>(int)$scope['owner_user_id'],
            'platform'=>(bool)$scope['platform'],
        ];
    }
}

if (!function_exists('tl_notifications_campaign')) {
    function tl_notifications_campaign(PDO $pdo, array $user, string $campaignRef, bool $lock = false): array
    {
        return tl_reward_management_campaign($pdo, $user, $campaignRef, $lock);
    }
}

if (!function_exists('tl_notifications_email')) {
    function tl_notifications_email(string $email): string
    {
        $email = strtolower(trim($email));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? substr($email, 0, 254) : '';
    }
}

if (!function_exists('tl_notifications_email_hash')) {
    function tl_notifications_email_hash(string $email): string
    {
        $email = tl_notifications_email($email);
        return $email === '' ? '' : hash('sha256', $email);
    }
}

if (!function_exists('tl_notifications_safe_error')) {
    function tl_notifications_safe_error(Throwable|string $error): array
    {
        $message = $error instanceof Throwable ? $error->getMessage() : (string)$error;
        $code = $error instanceof TlHttpException ? $error->errorCode() : 'notification_delivery_failed';
        $code = preg_replace('/[^a-z0-9_-]/i', '', $code) ?: 'notification_delivery_failed';
        $message = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $message) ?? '');
        $message = preg_replace('/(?:token|secret|password|authorization|cookie)\s*[:=]\s*\S+/i', '$1=[redacted]', $message) ?? $message;
        return [substr($code, 0, 96), substr($message !== '' ? $message : 'Delivery failed.', 0, 255)];
    }
}

if (!function_exists('tl_notifications_retry_delay')) {
    function tl_notifications_retry_delay(int $attempt): int
    {
        $base = tl_notifications_config()['retry_base_seconds'];
        return min(86400, $base * (2 ** max(0, min(10, $attempt - 1))));
    }
}

if (!function_exists('tl_notifications_account_link')) {
    function tl_notifications_account_link(PDO $pdo, int $userId): ?array
    {
        if ($userId < 1 || !tl_table_exists('training_account_links')) return null;
        $stmt = $pdo->prepare("SELECT id,public_id,training_user_id,email,display_name,link_status FROM training_account_links WHERE training_user_id=? AND link_status='active' AND email IS NOT NULL AND email<>'' ORDER BY updated_at DESC,id DESC LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || tl_notifications_email((string)$row['email']) === '') return null;
        return $row;
    }
}

if (!function_exists('tl_notifications_preference')) {
    function tl_notifications_preference(PDO $pdo, int $accountLinkId): array
    {
        $default = ['transactional_enabled'=>1,'reminder_enabled'=>1];
        if ($accountLinkId < 1 || !tl_table_exists('training_notification_preferences')) return $default;
        $stmt = $pdo->prepare('SELECT * FROM training_notification_preferences WHERE account_link_id=? LIMIT 1');
        $stmt->execute([$accountLinkId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: $default;
    }
}

if (!function_exists('tl_notifications_is_suppressed')) {
    function tl_notifications_is_suppressed(PDO $pdo, int $accountLinkId, string $emailHash): bool
    {
        if (!tl_table_exists('training_notification_suppressions')) return false;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM training_notification_suppressions WHERE status='active' AND (account_link_id=? OR email_hash=?)");
        $stmt->execute([$accountLinkId, $emailHash]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('tl_notifications_template')) {
    function tl_notifications_template(PDO $pdo, int $ownerUserId, string $key): ?array
    {
        if (!tl_table_exists('training_notification_templates')) return null;
        $stmt = $pdo->prepare("SELECT * FROM training_notification_templates WHERE template_key=? AND channel='email' AND status='active' AND owner_user_id IN (0,?) ORDER BY owner_user_id DESC LIMIT 1");
        $stmt->execute([$key, $ownerUserId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (!function_exists('tl_notifications_allowed_placeholders')) {
    function tl_notifications_allowed_placeholders(): array
    {
        return ['participant_name','campaign_title','task_title','reward_label','review_status','action_url','unsubscribe_url'];
    }
}

if (!function_exists('tl_notifications_render')) {
    function tl_notifications_render(array $template, array $context): array
    {
        $replace = [];
        foreach (tl_notifications_allowed_placeholders() as $key) {
            $value = trim((string)($context[$key] ?? ''));
            $replace['{{' . $key . '}}'] = substr($value, 0, $key === 'action_url' || $key === 'unsubscribe_url' ? 1200 : 500);
        }
        $subject = strtr((string)($template['subject_template'] ?? ''), $replace);
        $body = strtr((string)($template['body_template'] ?? ''), $replace);
        $subject = trim(str_replace(["\r", "\n"], ' ', $subject));
        return ['subject'=>substr($subject, 0, 255),'text'=>substr(trim($body), 0, 20000)];
    }
}

if (!function_exists('tl_notifications_base_url')) {
    function tl_notifications_base_url(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $base = tl_notifications_config()['public_base_url'];
        if ($base === '') return $path;
        $parts = parse_url($base);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) return $path;
        return $base . $path;
    }
}

if (!function_exists('tl_notifications_b64url')) {
    function tl_notifications_b64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

if (!function_exists('tl_notifications_unsubscribe_token')) {
    function tl_notifications_unsubscribe_token(int $accountLinkId, int $expiresAt): string
    {
        $secret = tl_notifications_config()['unsubscribe_secret'];
        if ($accountLinkId < 1 || strlen($secret) < 32) return '';
        $payload = $accountLinkId . '.' . $expiresAt;
        return tl_notifications_b64url($payload) . '.' . tl_notifications_b64url(hash_hmac('sha256', $payload, $secret, true));
    }
}

if (!function_exists('tl_notifications_verify_unsubscribe_token')) {
    function tl_notifications_verify_unsubscribe_token(string $token): int
    {
        $secret = tl_notifications_config()['unsubscribe_secret'];
        if (strlen($secret) < 32) throw new TlHttpException('Notification preference links are unavailable.', 503, 'unsubscribe_unavailable');
        $parts = explode('.', trim($token), 2);
        if (count($parts) !== 2) throw new TlHttpException('The notification preference link is invalid.', 400, 'unsubscribe_token_invalid');
        $decode = static function (string $value): string|false {
            $padding = strlen($value) % 4;
            if ($padding) $value .= str_repeat('=', 4 - $padding);
            return base64_decode(strtr($value, '-_', '+/'), true);
        };
        $payload = $decode($parts[0]);
        $signature = $decode($parts[1]);
        if (!is_string($payload) || !is_string($signature)) throw new TlHttpException('The notification preference link is invalid.', 400, 'unsubscribe_token_invalid');
        $expected = hash_hmac('sha256', $payload, $secret, true);
        if (!hash_equals($expected, $signature)) throw new TlHttpException('The notification preference link is invalid.', 400, 'unsubscribe_signature_invalid');
        [$accountLinkId, $expiresAt] = array_pad(explode('.', $payload, 2), 2, '0');
        if (!ctype_digit($accountLinkId) || (int)$accountLinkId < 1 || !ctype_digit($expiresAt) || (int)$expiresAt < time()) {
            throw new TlHttpException('The notification preference link has expired.', 410, 'unsubscribe_token_expired');
        }
        return (int)$accountLinkId;
    }
}

if (!function_exists('tl_notifications_unsubscribe_url')) {
    function tl_notifications_unsubscribe_url(int $accountLinkId): string
    {
        $token = tl_notifications_unsubscribe_token($accountLinkId, time() + 2592000);
        return $token === '' ? '' : tl_notifications_base_url('/notification-preferences.php?token=' . rawurlencode($token));
    }
}

if (!function_exists('tl_notifications_update_preference')) {
    function tl_notifications_update_preference(int $accountLinkId, bool $remindersEnabled, ?array $actor = null): array
    {
        $pdo = tl_require_db();
        if (!tl_notifications_tables_ready()) throw new TlHttpException('Import the Pilot Operations + Communications migration first.', 503, 'notification_schema_missing');
        $actorId = $actor ? tl_campaign_user_id($actor) : null;
        $publicId = tl_uuid();
        $stmt = $pdo->prepare("INSERT INTO training_notification_preferences (public_id,account_link_id,transactional_enabled,reminder_enabled,changed_by_user_id,unsubscribed_at) VALUES (?,?,1,?,?,?) ON DUPLICATE KEY UPDATE reminder_enabled=VALUES(reminder_enabled),changed_by_user_id=VALUES(changed_by_user_id),unsubscribed_at=VALUES(unsubscribed_at),updated_at=CURRENT_TIMESTAMP");
        $stmt->execute([$publicId,$accountLinkId,$remindersEnabled ? 1 : 0,$actorId,$remindersEnabled ? null : gmdate('Y-m-d H:i:s')]);
        return ['account_link_id'=>$accountLinkId,'reminder_enabled'=>$remindersEnabled];
    }
}

if (!function_exists('tl_notifications_templates')) {
    function tl_notifications_templates(array $user): array
    {
        $pdo = tl_require_db();
        $scope = tl_notifications_scope($user);
        if (!tl_table_exists('training_notification_templates')) return [];
        $stmt = $pdo->prepare("SELECT * FROM training_notification_templates WHERE owner_user_id IN (0,?) ORDER BY template_key,owner_user_id DESC");
        $stmt->execute([$scope['owner_user_id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('tl_notifications_save_template')) {
    function tl_notifications_save_template(array $user, array $input): array
    {
        $pdo = tl_require_db();
        $scope = tl_notifications_scope($user);
        $key = tl_action_enum($input['template_key'] ?? '', ['participant_invited','task_reminder','proof_submitted','review_approved','review_revision_required','review_rejected','reward_earned','reward_delivery_succeeded','reward_delivery_failed'], '');
        if ($key === '') throw new TlHttpException('Select a supported notification template.', 422, 'notification_template_invalid');
        $name = tl_action_clean($input['template_name'] ?? '', 191, true, 'Template name');
        $subject = tl_action_clean($input['subject_template'] ?? '', 255, true, 'Subject');
        $body = trim((string)($input['body_template'] ?? ''));
        if ($body === '' || mb_strlen($body) > 20000) throw new TlHttpException('Template body is required and must be under 20,000 characters.', 422, 'notification_body_invalid');
        preg_match_all('/{{\s*([a-z0-9_]+)\s*}}/i', $subject . "\n" . $body, $matches);
        $unknown = array_diff(array_unique($matches[1] ?? []), tl_notifications_allowed_placeholders());
        if ($unknown) throw new TlHttpException('Unsupported template placeholder: ' . implode(', ', $unknown), 422, 'notification_placeholder_invalid');
        $system = tl_notifications_template($pdo, 0, $key);
        $class = (string)($system['message_class'] ?? ($key === 'task_reminder' ? 'reminder' : 'transactional'));
        $publicId = tl_uuid();
        $actorId = tl_campaign_user_id($user);
        $stmt = $pdo->prepare("INSERT INTO training_notification_templates (public_id,owner_user_id,template_key,channel,message_class,template_name,subject_template,body_template,status,is_system,created_by_user_id,updated_by_user_id) VALUES (?,?,?,'email',?,?,?,?, 'active',0,?,?) ON DUPLICATE KEY UPDATE template_name=VALUES(template_name),subject_template=VALUES(subject_template),body_template=VALUES(body_template),status='active',updated_by_user_id=VALUES(updated_by_user_id),updated_at=CURRENT_TIMESTAMP");
        $stmt->execute([$publicId,$scope['owner_user_id'],$key,$class,$name,$subject,$body,$actorId,$actorId]);
        return ['template_key'=>$key,'owner_user_id'=>$scope['owner_user_id']];
    }
}

if (!function_exists('tl_notifications_pilot_control')) {
    function tl_notifications_pilot_control(PDO $pdo, array $user, string $campaignRef): array
    {
        $campaign = tl_notifications_campaign($pdo, $user, $campaignRef);
        $stmt = $pdo->prepare('SELECT * FROM training_pilot_controls WHERE campaign_id=? LIMIT 1');
        $stmt->execute([(int)$campaign['id']]);
        return ['campaign'=>$campaign,'control'=>$stmt->fetch(PDO::FETCH_ASSOC) ?: null];
    }
}

if (!function_exists('tl_notifications_save_pilot_control')) {
    function tl_notifications_save_pilot_control(array $user, array $input): array
    {
        $pdo = tl_require_db();
        $campaignRef = (string)($input['campaign_id'] ?? $input['campaign'] ?? '');
        $status = tl_action_enum($input['pilot_status'] ?? 'draft', ['draft','active','paused','completed'], 'draft');
        $emailEnabled = !empty($input['email_enabled']) ? 1 : 0;
        $maxParticipants = max(1, min(10000, (int)($input['max_participants'] ?? 25)));
        $dailyLimit = max(1, min(100000, (int)($input['daily_notification_limit'] ?? 100)));
        $pausedReason = tl_action_clean($input['paused_reason'] ?? '', 255);
        $actorId = tl_campaign_user_id($user);
        $pdo->beginTransaction();
        try {
            $campaign = tl_notifications_campaign($pdo, $user, $campaignRef, true);
            $ownerId = (int)$campaign['owner_user_id'];
            $publicId = tl_uuid();
            $startedAt = $status === 'active' ? gmdate('Y-m-d H:i:s') : null;
            $completedAt = $status === 'completed' ? gmdate('Y-m-d H:i:s') : null;
            $stmt = $pdo->prepare("INSERT INTO training_pilot_controls (public_id,campaign_id,owner_user_id,pilot_status,email_enabled,max_participants,daily_notification_limit,paused_reason,started_at,completed_at,created_by_user_id,updated_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE pilot_status=VALUES(pilot_status),email_enabled=VALUES(email_enabled),max_participants=VALUES(max_participants),daily_notification_limit=VALUES(daily_notification_limit),paused_reason=VALUES(paused_reason),started_at=COALESCE(started_at,VALUES(started_at)),completed_at=VALUES(completed_at),updated_by_user_id=VALUES(updated_by_user_id),updated_at=CURRENT_TIMESTAMP");
            $stmt->execute([$publicId,(int)$campaign['id'],$ownerId,$status,$emailEnabled,$maxParticipants,$dailyLimit,$pausedReason,$startedAt,$completedAt,$actorId,$actorId]);
            tl_log_event($pdo, $actorId, 'campaign', (int)$campaign['id'], 'pilot_communications_control_updated', ['pilot_status'=>$status,'email_enabled'=>(bool)$emailEnabled,'max_participants'=>$maxParticipants,'daily_notification_limit'=>$dailyLimit]);
            $pdo->commit();
            return ['campaign_ref'=>(string)($campaign['slug'] ?: $campaign['public_id']),'pilot_status'=>$status,'email_enabled'=>(bool)$emailEnabled];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_notifications_candidate_queries')) {
    function tl_notifications_candidate_queries(bool $includeReminders = false): array
    {
        $queries = [
            ['event'=>'participant_invited','source'=>'participant','sql'=>"SELECT tp.id source_id,tp.id participant_id,tp.user_id,c.id campaign_id,c.owner_user_id,c.title campaign_title,c.slug campaign_slug,tp.participant_label,'' task_title,'' reward_label,'' review_status FROM training_participants tp JOIN training_campaigns c ON c.id=tp.campaign_id WHERE tp.status IN ('invited','active')"],
            ['event'=>'proof_submitted','source'=>'proof','sql'=>"SELECT p.id source_id,p.participant_id,p.submitted_by_user_id user_id,c.id campaign_id,c.owner_user_id,c.title campaign_title,c.slug campaign_slug,COALESCE(tp.participant_label,'Training participant') participant_label,COALESCE(t.title,'Training task') task_title,'' reward_label,'' review_status FROM training_proof_submissions p JOIN training_campaigns c ON c.id=p.campaign_id LEFT JOIN training_participants tp ON tp.id=p.participant_id LEFT JOIN training_campaign_tasks t ON t.id=p.task_id WHERE p.status IN ('submitted','in_review','approved','rejected')"],
            ['event'=>'review_result','source'=>'review','sql'=>"SELECT r.id source_id,p.participant_id,p.submitted_by_user_id user_id,c.id campaign_id,c.owner_user_id,c.title campaign_title,c.slug campaign_slug,COALESCE(tp.participant_label,'Training participant') participant_label,COALESCE(t.title,'Training task') task_title,'' reward_label,r.decision review_status FROM training_reviews r JOIN training_proof_submissions p ON p.id=r.proof_submission_id JOIN training_campaigns c ON c.id=p.campaign_id LEFT JOIN training_participants tp ON tp.id=p.participant_id LEFT JOIN training_campaign_tasks t ON t.id=p.task_id"],
            ['event'=>'reward_earned','source'=>'reward_event','sql'=>"SELECT re.id source_id,re.participant_id,re.user_id,c.id campaign_id,c.owner_user_id,c.title campaign_title,c.slug campaign_slug,COALESCE(tp.participant_label,'Training participant') participant_label,'' task_title,COALESCE(rr.reward_label,'Training reward') reward_label,'' review_status FROM training_reward_events re JOIN training_campaigns c ON c.id=re.campaign_id LEFT JOIN training_participants tp ON tp.id=re.participant_id LEFT JOIN training_reward_rules rr ON rr.id=re.reward_rule_id WHERE re.status IN ('eligible','offered','claimable','issued','linked_to_microgifter')"],
            ['event'=>'reward_delivery','source'=>'reward_handoff','sql'=>"SELECT h.id source_id,re.participant_id,re.user_id,c.id campaign_id,c.owner_user_id,c.title campaign_title,c.slug campaign_slug,COALESCE(tp.participant_label,'Training participant') participant_label,'' task_title,COALESCE(rr.reward_label,'Training reward') reward_label,h.handoff_status review_status FROM training_reward_handoffs h JOIN training_reward_events re ON re.id=h.reward_event_id JOIN training_campaigns c ON c.id=re.campaign_id LEFT JOIN training_participants tp ON tp.id=re.participant_id LEFT JOIN training_reward_rules rr ON rr.id=re.reward_rule_id WHERE h.handoff_status IN ('delivered','failed')"],
        ];
        if ($includeReminders) {
            $queries[] = ['event'=>'task_reminder','source'=>'task_reminder','sql'=>"SELECT t.id source_id,tp.id participant_id,tp.user_id,c.id campaign_id,c.owner_user_id,c.title campaign_title,c.slug campaign_slug,COALESCE(tp.participant_label,'Training participant') participant_label,t.title task_title,'' reward_label,'' review_status FROM training_participants tp JOIN training_campaigns c ON c.id=tp.campaign_id JOIN training_campaign_tasks t ON t.campaign_id=c.id AND t.status='active' WHERE tp.status='active' AND NOT EXISTS (SELECT 1 FROM training_action_receipts ar WHERE ar.participant_id=tp.id AND ar.receipt_status='active' AND ar.receipt_type='task_completed' AND JSON_UNQUOTE(JSON_EXTRACT(ar.metadata_json,'$.task_id'))=CAST(t.id AS CHAR))"];
        }
        return $queries;
    }
}

if (!function_exists('tl_notifications_event_type')) {
    function tl_notifications_event_type(string $base, string $state): string
    {
        if ($base === 'review_result') {
            return match (strtolower($state)) {
                'approved'=>'review_approved',
                'needs_more_info','revision_requested','revision_required'=>'review_revision_required',
                default=>'review_rejected',
            };
        }
        if ($base === 'reward_delivery') return strtolower($state) === 'delivered' ? 'reward_delivery_succeeded' : 'reward_delivery_failed';
        return $base;
    }
}

if (!function_exists('tl_notifications_action_path')) {
    function tl_notifications_action_path(string $event, string $campaignSlug): string
    {
        return match ($event) {
            'participant_invited'=>'/app/campaigns.php',
            'task_reminder','proof_submitted','review_revision_required','review_rejected'=>'/app/task-runner.php?campaign=' . rawurlencode($campaignSlug),
            'review_approved'=>'/app/progress-map.php?campaign=' . rawurlencode($campaignSlug),
            'reward_earned','reward_delivery_succeeded','reward_delivery_failed'=>'/app/rewards.php',
            default=>'/app/index.php',
        };
    }
}

if (!function_exists('tl_notifications_enqueue_candidate')) {
    function tl_notifications_enqueue_candidate(PDO $pdo, array $candidate): string
    {
        $event = tl_notifications_event_type((string)$candidate['base_event'], (string)($candidate['review_status'] ?? ''));
        $sourceId = (int)$candidate['source_id'];
        $userId = (int)$candidate['user_id'];
        $campaignId = (int)$candidate['campaign_id'];
        $ownerId = (int)$candidate['owner_user_id'];
        $participantId = max(0, (int)($candidate['participant_id'] ?? 0));
        $dateBucket = $event === 'task_reminder' ? gmdate('Y-m-d') : 'event';
        $idempotency = hash('sha256', implode('|', ['training-notification-v1',$event,$candidate['source_type'],$sourceId,$campaignId,$userId,$dateBucket]));
        $template = tl_notifications_template($pdo, $ownerId, $event);
        $account = tl_notifications_account_link($pdo, $userId);
        $email = $account ? tl_notifications_email((string)$account['email']) : '';
        $emailHash = tl_notifications_email_hash($email);
        $accountLinkId = $account ? (int)$account['id'] : null;
        $messageClass = (string)($template['message_class'] ?? ($event === 'task_reminder' ? 'reminder' : 'transactional'));
        $status = 'queued';
        $errorCode = null;
        $errorDetail = null;

        $controlStmt = $pdo->prepare('SELECT * FROM training_pilot_controls WHERE campaign_id=? LIMIT 1');
        $controlStmt->execute([$campaignId]);
        $control = $controlStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$template) { $status='blocked'; $errorCode='template_missing'; $errorDetail='No active notification template is available.'; }
        elseif (!$account || $email === '') { $status='blocked'; $errorCode='account_link_email_missing'; $errorDetail='An active account link with a valid email is required.'; }
        elseif (!$control || (string)$control['pilot_status'] !== 'active' || empty($control['email_enabled'])) { $status='blocked'; $errorCode='pilot_not_active'; $errorDetail='Campaign communications are not active.'; }
        else {
            $participantCount = (int)$pdo->query('SELECT COUNT(*) FROM training_participants WHERE campaign_id=' . $campaignId . " AND status<>'removed'")->fetchColumn();
            $dayStmt = $pdo->prepare("SELECT COUNT(*) FROM training_notification_outbox WHERE campaign_id=? AND created_at>=UTC_DATE()");
            $dayStmt->execute([$campaignId]);
            $dailyCount = (int)$dayStmt->fetchColumn();
            if ($participantCount > (int)$control['max_participants']) { $status='blocked'; $errorCode='pilot_participant_limit'; $errorDetail='Campaign exceeds the pilot participant limit.'; }
            elseif ($dailyCount >= (int)$control['daily_notification_limit']) { $status='blocked'; $errorCode='pilot_daily_limit'; $errorDetail='Campaign reached its daily notification limit.'; }
            elseif ($accountLinkId && tl_notifications_is_suppressed($pdo, $accountLinkId, $emailHash)) { $status='suppressed'; $errorCode='recipient_suppressed'; $errorDetail='Recipient is on the suppression list.'; }
            elseif ($accountLinkId) {
                $pref = tl_notifications_preference($pdo, $accountLinkId);
                if ($messageClass === 'reminder' && empty($pref['reminder_enabled'])) { $status='suppressed'; $errorCode='reminders_disabled'; $errorDetail='Recipient disabled reminders.'; }
                elseif ($messageClass === 'transactional' && empty($pref['transactional_enabled'])) { $status='suppressed'; $errorCode='transactional_disabled'; $errorDetail='Recipient disabled transactional notices.'; }
            }
        }
        $context = [
            'participant_name'=>(string)($candidate['participant_label'] ?: ($account['display_name'] ?? 'Training participant')),
            'campaign_title'=>(string)$candidate['campaign_title'],
            'task_title'=>(string)($candidate['task_title'] ?? ''),
            'reward_label'=>(string)($candidate['reward_label'] ?? ''),
            'review_status'=>(string)($candidate['review_status'] ?? ''),
            'action_url'=>tl_notifications_base_url(tl_notifications_action_path($event, (string)$candidate['campaign_slug'])),
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO training_notification_outbox (public_id,campaign_id,participant_id,user_id,account_link_id,template_id,event_type,source_type,source_id,message_class,channel,recipient_hash,idempotency_key,outbox_status,max_attempts,next_attempt_at,last_error_code,last_error_detail,context_json) VALUES (?,?,?,?,?,?,?,?,?,?,'email',?,?,?,?,CURRENT_TIMESTAMP,?,?,?)");
        $stmt->execute([tl_uuid(),$campaignId,$participantId ?: null,$userId,$accountLinkId,$template['id'] ?? null,$event,(string)$candidate['source_type'],$sourceId,$messageClass,$emailHash ?: null,$idempotency,$status,tl_notifications_config()['max_attempts'],$errorCode,$errorDetail,json_encode($context, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
        return $stmt->rowCount() > 0 ? $status : 'duplicate';
    }
}

if (!function_exists('tl_notifications_sync_events')) {
    function tl_notifications_sync_events(array $user, string $campaignRef = '', int $limit = 250, bool $includeReminders = false): array
    {
        $pdo = tl_require_db();
        if (!tl_notifications_tables_ready()) throw new TlHttpException('Import the Pilot Operations + Communications migration first.', 503, 'notification_schema_missing');
        $scope = tl_notifications_scope($user);
        $campaignId = 0;
        if ($campaignRef !== '') $campaignId = (int)tl_notifications_campaign($pdo, $user, $campaignRef)['id'];
        $counts = ['queued'=>0,'blocked'=>0,'suppressed'=>0,'duplicate'=>0];
        $remaining = max(1, min(1000, $limit));
        foreach (tl_notifications_candidate_queries($includeReminders) as $definition) {
            if ($remaining < 1) break;
            $where = $scope['platform'] ? '' : ' AND c.owner_user_id=' . (int)$scope['owner_user_id'];
            if ($campaignId > 0) $where .= ' AND c.id=' . $campaignId;
            $sql = $definition['sql'] . $where . ' ORDER BY source_id DESC LIMIT ' . $remaining;
            try { $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: []; }
            catch (Throwable $e) { $rows = []; }
            foreach ($rows as $row) {
                $row['base_event'] = $definition['event'];
                $row['source_type'] = $definition['source'];
                $result = tl_notifications_enqueue_candidate($pdo, $row);
                $counts[$result] = ($counts[$result] ?? 0) + 1;
                $remaining--;
                if ($remaining < 1) break 2;
            }
        }
        return $counts;
    }
}

if (!function_exists('tl_notifications_provider_state')) {
    function tl_notifications_provider_state(): array
    {
        $cfg = tl_notifications_config();
        $adapter = function_exists('training_lab_send_notification_email');
        return [
            'delivery_enabled'=>(bool)$cfg['delivery_enabled'],
            'worker_enabled'=>(bool)$cfg['worker_enabled'],
            'adapter_available'=>$adapter,
            'provider_name'=>$cfg['provider_name'],
            'can_process'=>(bool)$cfg['delivery_enabled'] && (bool)$cfg['worker_enabled'] && $adapter,
            'safe_boundaries'=>[
                'no_php_mail_fallback'=>true,
                'no_raw_provider_response_storage'=>true,
                'no_password_or_cookie_payloads'=>true,
                'no_microgifter_api_calls'=>true,
            ],
        ];
    }
}

if (!function_exists('tl_notifications_record_attempt')) {
    function tl_notifications_record_attempt(PDO $pdo, int $outboxId, int $attemptNo, string $status, array $data = []): void
    {
        $stmt = $pdo->prepare('INSERT INTO training_notification_attempts (public_id,outbox_id,attempt_no,attempt_status,provider_name,provider_message_hash,response_code,error_code,error_detail,completed_at,metadata_json) VALUES (?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,?)');
        $stmt->execute([tl_uuid(),$outboxId,$attemptNo,$status,substr((string)($data['provider_name'] ?? ''),0,96) ?: null,($data['provider_message_hash'] ?? null),substr((string)($data['response_code'] ?? ''),0,64) ?: null,substr((string)($data['error_code'] ?? ''),0,96) ?: null,substr((string)($data['error_detail'] ?? ''),0,255) ?: null,json_encode(['raw_response_stored'=>false,'credential_data_stored'=>false], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
    }
}

if (!function_exists('tl_notifications_process_one')) {
    function tl_notifications_process_one(PDO $pdo, int $outboxId): array
    {
        $provider = tl_notifications_provider_state();
        if (!$provider['can_process']) throw new TlHttpException('Notification delivery is disabled or the provider adapter is unavailable.', 503, 'notification_provider_blocked');
        $lease = bin2hex(random_bytes(24));
        $leaseHash = hash('sha256', $lease);
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT o.*,c.owner_user_id,c.title campaign_title,c.slug campaign_slug,pc.pilot_status,pc.email_enabled,pc.daily_notification_limit FROM training_notification_outbox o JOIN training_campaigns c ON c.id=o.campaign_id LEFT JOIN training_pilot_controls pc ON pc.campaign_id=o.campaign_id WHERE o.id=? LIMIT 1 FOR UPDATE");
            $stmt->execute([$outboxId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new TlHttpException('Notification outbox item was not found.', 404, 'notification_not_found');
            if (!in_array((string)$row['outbox_status'], ['queued','failed'], true)) throw new TlHttpException('Notification is not eligible for processing.', 409, 'notification_not_processable');
            if ((int)$row['attempt_count'] >= (int)$row['max_attempts']) throw new TlHttpException('Notification reached its retry limit.', 409, 'notification_retry_exhausted');
            if ((string)($row['pilot_status'] ?? '') !== 'active' || empty($row['email_enabled'])) throw new TlHttpException('Campaign communications are paused.', 409, 'pilot_not_active');
            $attemptNo = (int)$row['attempt_count'] + 1;
            $upd = $pdo->prepare("UPDATE training_notification_outbox SET outbox_status='processing',attempt_count=?,leased_at=CURRENT_TIMESTAMP,lease_token_hash=?,last_error_code=NULL,last_error_detail=NULL WHERE id=?");
            $upd->execute([$attemptNo,$leaseHash,$outboxId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        try {
            $account = tl_notifications_account_link($pdo, (int)$row['user_id']);
            if (!$account || (int)$account['id'] !== (int)$row['account_link_id']) throw new TlHttpException('The recipient account link is unavailable.', 409, 'account_link_unavailable');
            $email = tl_notifications_email((string)$account['email']);
            $emailHash = tl_notifications_email_hash($email);
            if ($email === '' || !hash_equals((string)$row['recipient_hash'], $emailHash)) throw new TlHttpException('The recipient address changed and must be resynchronized.', 409, 'recipient_changed');
            if (tl_notifications_is_suppressed($pdo, (int)$account['id'], $emailHash)) throw new TlHttpException('The recipient is suppressed.', 409, 'recipient_suppressed');
            $pref = tl_notifications_preference($pdo, (int)$account['id']);
            if ((string)$row['message_class'] === 'reminder' && empty($pref['reminder_enabled'])) throw new TlHttpException('The recipient disabled reminders.', 409, 'reminders_disabled');
            if ((string)$row['message_class'] === 'transactional' && empty($pref['transactional_enabled'])) throw new TlHttpException('The recipient disabled transactional notifications.', 409, 'transactional_disabled');
            $template = tl_notifications_template($pdo, (int)$row['owner_user_id'], (string)$row['event_type']);
            if (!$template) throw new TlHttpException('The notification template is unavailable.', 409, 'template_missing');
            $context = json_decode((string)($row['context_json'] ?? '{}'), true);
            if (!is_array($context)) $context = [];
            $context['unsubscribe_url'] = (string)$row['message_class'] === 'reminder' ? tl_notifications_unsubscribe_url((int)$account['id']) : '';
            $rendered = tl_notifications_render($template, $context);
            $payload = [
                'to'=>$email,
                'subject'=>$rendered['subject'],
                'text'=>$rendered['text'],
                'idempotency_key'=>(string)$row['idempotency_key'],
                'metadata'=>[
                    'notification_public_id'=>(string)$row['public_id'],
                    'campaign_id'=>(int)$row['campaign_id'],
                    'event_type'=>(string)$row['event_type'],
                    'message_class'=>(string)$row['message_class'],
                    'no_passwords'=>true,
                    'no_cookies'=>true,
                    'no_wallet_or_reward_mutation'=>true,
                ],
            ];
            $result = training_lab_send_notification_email($payload);
            if (!is_array($result) || empty($result['ok'])) throw new RuntimeException((string)($result['error'] ?? 'Notification provider rejected the message.'));
            $providerId = trim((string)($result['message_id'] ?? $result['id'] ?? ''));
            $providerHash = $providerId === '' ? null : hash('sha256', $providerId);
            $pdo->beginTransaction();
            $lock = $pdo->prepare('SELECT id,lease_token_hash FROM training_notification_outbox WHERE id=? LIMIT 1 FOR UPDATE');
            $lock->execute([$outboxId]);
            $current = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$current || !hash_equals((string)$current['lease_token_hash'], $leaseHash)) throw new RuntimeException('Notification lease was lost.');
            tl_notifications_record_attempt($pdo,$outboxId,$attemptNo,'delivered',['provider_name'=>$provider['provider_name'],'provider_message_hash'=>$providerHash,'response_code'=>(string)($result['code'] ?? 'accepted')]);
            $done = $pdo->prepare("UPDATE training_notification_outbox SET outbox_status='delivered',delivered_at=CURRENT_TIMESTAMP,provider_message_hash=?,leased_at=NULL,lease_token_hash=NULL,next_attempt_at=NULL WHERE id=?");
            $done->execute([$providerHash,$outboxId]);
            $pdo->commit();
            return ['id'=>$outboxId,'status'=>'delivered'];
        } catch (Throwable $e) {
            [$errorCode,$errorDetail] = tl_notifications_safe_error($e);
            $suppressed = in_array($errorCode, ['recipient_suppressed','reminders_disabled','transactional_disabled'], true);
            $pdo->beginTransaction();
            try {
                tl_notifications_record_attempt($pdo,$outboxId,$attemptNo,$suppressed ? 'suppressed' : 'failed',['provider_name'=>$provider['provider_name'],'error_code'=>$errorCode,'error_detail'=>$errorDetail]);
                $next = gmdate('Y-m-d H:i:s', time() + tl_notifications_retry_delay($attemptNo));
                $status = $suppressed ? 'suppressed' : 'failed';
                $upd = $pdo->prepare('UPDATE training_notification_outbox SET outbox_status=?,last_error_code=?,last_error_detail=?,next_attempt_at=?,leased_at=NULL,lease_token_hash=NULL WHERE id=?');
                $upd->execute([$status,$errorCode,$errorDetail,$suppressed ? null : $next,$outboxId]);
                $pdo->commit();
            } catch (Throwable $inner) {
                if ($pdo->inTransaction()) $pdo->rollBack();
            }
            return ['id'=>$outboxId,'status'=>$suppressed ? 'suppressed' : 'failed','error_code'=>$errorCode];
        }
    }
}

if (!function_exists('tl_notifications_process_batch')) {
    function tl_notifications_process_batch(int $limit = 0): array
    {
        $pdo = tl_require_db();
        if (!tl_notifications_tables_ready()) throw new TlHttpException('Import the Pilot Operations + Communications migration first.', 503, 'notification_schema_missing');
        $provider = tl_notifications_provider_state();
        if (!$provider['can_process']) throw new TlHttpException('Notification delivery remains disabled until the worker and provider adapter are explicitly enabled.', 503, 'notification_provider_blocked');
        $limit = $limit > 0 ? max(1, min(100, $limit)) : (int)tl_notifications_config()['batch_size'];
        $stmt = $pdo->query("SELECT id FROM training_notification_outbox WHERE outbox_status IN ('queued','failed') AND attempt_count<max_attempts AND (next_attempt_at IS NULL OR next_attempt_at<=CURRENT_TIMESTAMP) ORDER BY scheduled_at,id LIMIT " . $limit);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $results = ['delivered'=>0,'failed'=>0,'suppressed'=>0];
        foreach ($ids as $id) {
            $result = tl_notifications_process_one($pdo, $id);
            $status = (string)($result['status'] ?? 'failed');
            $results[$status] = ($results[$status] ?? 0) + 1;
        }
        return $results + ['processed'=>count($ids)];
    }
}

if (!function_exists('tl_notifications_dashboard')) {
    function tl_notifications_dashboard(array $user): array
    {
        $pdo = tl_require_db();
        $scope = tl_notifications_scope($user);
        if (!tl_notifications_tables_ready()) return ['schema_ready'=>false,'campaigns'=>[],'totals'=>[],'provider'=>tl_notifications_provider_state()];
        $where = $scope['platform'] ? '1=1' : 'c.owner_user_id=?';
        $params = $scope['platform'] ? [] : [$scope['owner_user_id']];
        $sql = "SELECT c.id,c.public_id,c.slug,c.title,c.status,pc.pilot_status,pc.email_enabled,pc.max_participants,pc.daily_notification_limit,pc.paused_reason,
                    (SELECT COUNT(*) FROM training_participants tp WHERE tp.campaign_id=c.id AND tp.status<>'removed') participants,
                    (SELECT COUNT(*) FROM training_notification_outbox o WHERE o.campaign_id=c.id) notifications,
                    (SELECT COUNT(*) FROM training_notification_outbox o WHERE o.campaign_id=c.id AND o.outbox_status='queued') queued,
                    (SELECT COUNT(*) FROM training_notification_outbox o WHERE o.campaign_id=c.id AND o.outbox_status='delivered') delivered,
                    (SELECT COUNT(*) FROM training_notification_outbox o WHERE o.campaign_id=c.id AND o.outbox_status='failed') failed,
                    (SELECT COUNT(*) FROM training_notification_outbox o WHERE o.campaign_id=c.id AND o.outbox_status='blocked') blocked,
                    (SELECT COUNT(*) FROM training_notification_outbox o WHERE o.campaign_id=c.id AND o.outbox_status='suppressed') suppressed
                FROM training_campaigns c LEFT JOIN training_pilot_controls pc ON pc.campaign_id=c.id WHERE {$where} ORDER BY c.updated_at DESC,c.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $totals = ['participants'=>0,'notifications'=>0,'queued'=>0,'delivered'=>0,'failed'=>0,'blocked'=>0,'suppressed'=>0];
        foreach ($campaigns as $row) foreach (array_keys($totals) as $key) $totals[$key] += (int)$row[$key];
        return ['schema_ready'=>true,'campaigns'=>$campaigns,'totals'=>$totals,'provider'=>tl_notifications_provider_state()];
    }
}

if (!function_exists('tl_notifications_outbox_rows')) {
    function tl_notifications_outbox_rows(array $user, int $limit = 100): array
    {
        $pdo = tl_require_db();
        $scope = tl_notifications_scope($user);
        if (!tl_table_exists('training_notification_outbox')) return [];
        $where = $scope['platform'] ? '1=1' : 'c.owner_user_id=?';
        $params = $scope['platform'] ? [] : [$scope['owner_user_id']];
        $sql = "SELECT o.public_id,o.event_type,o.message_class,o.outbox_status,o.attempt_count,o.max_attempts,o.scheduled_at,o.next_attempt_at,o.delivered_at,o.last_error_code,o.last_error_detail,o.recipient_hash,c.title campaign_title FROM training_notification_outbox o JOIN training_campaigns c ON c.id=o.campaign_id WHERE {$where} ORDER BY o.created_at DESC,o.id DESC LIMIT " . max(1,min(500,$limit));
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) $row['recipient_confirmation'] = !empty($row['recipient_hash']) ? substr((string)$row['recipient_hash'],0,12) : 'unresolved';
        unset($row);
        return $rows;
    }
}

if (!function_exists('tl_notifications_incidents')) {
    function tl_notifications_incidents(array $user, int $limit = 200): array
    {
        if (tl_product_role($user) !== 'admin') throw new TlHttpException('Administrator notification operations access is required.', 403, 'notification_incidents_forbidden');
        $pdo = tl_require_db();
        if (!tl_notifications_tables_ready()) return ['incidents'=>[],'suppressions'=>[],'attempts'=>[]];
        $incidents = $pdo->query("SELECT o.public_id,o.event_type,o.outbox_status,o.attempt_count,o.max_attempts,o.last_error_code,o.last_error_detail,o.created_at,c.title campaign_title FROM training_notification_outbox o JOIN training_campaigns c ON c.id=o.campaign_id WHERE o.outbox_status IN ('failed','blocked') ORDER BY o.updated_at DESC LIMIT " . max(1,min(500,$limit)))->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $suppressions = $pdo->query("SELECT public_id,suppression_type,reason,status,created_at,released_at,LEFT(email_hash,12) email_confirmation FROM training_notification_suppressions ORDER BY updated_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $attempts = $pdo->query("SELECT a.public_id,a.attempt_no,a.attempt_status,a.provider_name,a.response_code,a.error_code,a.error_detail,a.completed_at,o.public_id outbox_public_id FROM training_notification_attempts a JOIN training_notification_outbox o ON o.id=a.outbox_id ORDER BY a.created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return ['incidents'=>$incidents,'suppressions'=>$suppressions,'attempts'=>$attempts];
    }
}
