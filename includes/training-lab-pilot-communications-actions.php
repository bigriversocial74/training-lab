<?php
/** Protected operational actions for Pilot Operations + Communications. */
require_once __DIR__ . '/training-lab-pilot-communications.php';

if (!function_exists('tl_notifications_owned_outbox')) {
    function tl_notifications_owned_outbox(PDO $pdo, array $user, string $publicId, bool $lock = false): array
    {
        $scope = tl_notifications_scope($user);
        $whereOwner = $scope['platform'] ? '' : ' AND c.owner_user_id=?';
        $sql = 'SELECT o.*,c.owner_user_id,c.slug campaign_slug FROM training_notification_outbox o JOIN training_campaigns c ON c.id=o.campaign_id WHERE o.public_id=?' . $whereOwner . ' LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
        $params = [$publicId];
        if (!$scope['platform']) $params[] = $scope['owner_user_id'];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new TlHttpException('Notification was not found in your merchant account.', 404, 'notification_not_found');
        return $row;
    }
}

if (!function_exists('tl_notifications_retry')) {
    function tl_notifications_retry(array $user, string $publicId): array
    {
        $pdo = tl_require_db();
        $publicId = tl_action_clean($publicId, 64, true, 'Notification');
        $pdo->beginTransaction();
        try {
            $row = tl_notifications_owned_outbox($pdo, $user, $publicId, true);
            if (!in_array((string)$row['outbox_status'], ['failed','blocked','suppressed'], true)) {
                throw new TlHttpException('Only failed, blocked, or suppressed notifications can be reconsidered.', 409, 'notification_retry_invalid');
            }
            if ((int)$row['attempt_count'] >= (int)$row['max_attempts']) {
                throw new TlHttpException('This notification reached its retry limit.', 409, 'notification_retry_exhausted');
            }
            $account = tl_notifications_account_link($pdo, (int)$row['user_id']);
            if (!$account) throw new TlHttpException('An active account link with a valid email is required.', 409, 'account_link_email_missing');
            $emailHash = tl_notifications_email_hash((string)$account['email']);
            if (tl_notifications_is_suppressed($pdo, (int)$account['id'], $emailHash)) {
                throw new TlHttpException('The recipient remains suppressed.', 409, 'recipient_suppressed');
            }
            $pref = tl_notifications_preference($pdo, (int)$account['id']);
            if ((string)$row['message_class'] === 'reminder' && empty($pref['reminder_enabled'])) {
                throw new TlHttpException('The recipient has disabled reminders.', 409, 'reminders_disabled');
            }
            $control = $pdo->prepare("SELECT pilot_status,email_enabled FROM training_pilot_controls WHERE campaign_id=? LIMIT 1");
            $control->execute([(int)$row['campaign_id']]);
            $pilot = $control->fetch(PDO::FETCH_ASSOC);
            if (!$pilot || (string)$pilot['pilot_status'] !== 'active' || empty($pilot['email_enabled'])) {
                throw new TlHttpException('Campaign communications are not active.', 409, 'pilot_not_active');
            }
            $stmt = $pdo->prepare("UPDATE training_notification_outbox SET account_link_id=?,recipient_hash=?,outbox_status='queued',next_attempt_at=CURRENT_TIMESTAMP,last_error_code=NULL,last_error_detail=NULL,leased_at=NULL,lease_token_hash=NULL WHERE id=?");
            $stmt->execute([(int)$account['id'],$emailHash,(int)$row['id']]);
            tl_log_event($pdo, tl_campaign_user_id($user), 'notification', (int)$row['id'], 'notification_retry_requested', ['public_id'=>$publicId]);
            $pdo->commit();
            return ['public_id'=>$publicId,'status'=>'queued'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_notifications_cancel')) {
    function tl_notifications_cancel(array $user, string $publicId): array
    {
        $pdo = tl_require_db();
        $publicId = tl_action_clean($publicId, 64, true, 'Notification');
        $pdo->beginTransaction();
        try {
            $row = tl_notifications_owned_outbox($pdo, $user, $publicId, true);
            if (in_array((string)$row['outbox_status'], ['delivered','cancelled'], true)) {
                throw new TlHttpException('Delivered or cancelled notifications cannot be cancelled again.', 409, 'notification_cancel_invalid');
            }
            $stmt = $pdo->prepare("UPDATE training_notification_outbox SET outbox_status='cancelled',next_attempt_at=NULL,leased_at=NULL,lease_token_hash=NULL,last_error_code='operator_cancelled',last_error_detail='Cancelled by an authorized Training Lab operator.' WHERE id=?");
            $stmt->execute([(int)$row['id']]);
            tl_log_event($pdo, tl_campaign_user_id($user), 'notification', (int)$row['id'], 'notification_cancelled', ['public_id'=>$publicId]);
            $pdo->commit();
            return ['public_id'=>$publicId,'status'=>'cancelled'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_notifications_template_action')) {
    function tl_notifications_template_action(array $user, array $input): array
    {
        $pdo = tl_require_db();
        $scope = tl_notifications_scope($user);
        $key = tl_action_clean($input['template_key'] ?? '', 96, true, 'Template');
        $action = tl_action_enum($input['template_action'] ?? '', ['pause','resume','archive'], '');
        if ($action === '') throw new TlHttpException('Select a valid template action.', 422, 'notification_template_action_invalid');
        $status = ['pause'=>'paused','resume'=>'active','archive'=>'archived'][$action];
        $stmt = $pdo->prepare('UPDATE training_notification_templates SET status=?,updated_by_user_id=?,updated_at=CURRENT_TIMESTAMP WHERE owner_user_id=? AND template_key=? AND is_system=0');
        $stmt->execute([$status,tl_campaign_user_id($user),$scope['owner_user_id'],$key]);
        if ($stmt->rowCount() < 1) throw new TlHttpException('Merchant template override was not found.', 404, 'notification_template_override_not_found');
        return ['template_key'=>$key,'status'=>$status];
    }
}

if (!function_exists('tl_notifications_add_suppression')) {
    function tl_notifications_add_suppression(array $user, array $input): array
    {
        if (tl_product_role($user) !== 'admin') throw new TlHttpException('Administrator suppression access is required.', 403, 'notification_suppression_forbidden');
        $pdo = tl_require_db();
        $email = tl_notifications_email((string)($input['email'] ?? ''));
        $accountLinkId = max(0, (int)($input['account_link_id'] ?? 0));
        if ($email === '' && $accountLinkId > 0) {
            $stmt = $pdo->prepare("SELECT email FROM training_account_links WHERE id=? AND link_status='active' LIMIT 1");
            $stmt->execute([$accountLinkId]);
            $email = tl_notifications_email((string)$stmt->fetchColumn());
        }
        if ($email === '') throw new TlHttpException('A valid recipient email or active account link is required.', 422, 'notification_suppression_email_invalid');
        $type = tl_action_enum($input['suppression_type'] ?? 'manual', ['manual','hard_bounce','complaint','invalid_recipient','policy'], 'manual');
        $reason = tl_action_clean($input['reason'] ?? '', 255);
        $hash = tl_notifications_email_hash($email);
        $actorId = tl_campaign_user_id($user);
        $stmt = $pdo->prepare("INSERT INTO training_notification_suppressions (public_id,account_link_id,email_hash,suppression_type,reason,status,created_by_user_id) VALUES (?,?,?,?,?,'active',?) ON DUPLICATE KEY UPDATE account_link_id=VALUES(account_link_id),suppression_type=VALUES(suppression_type),reason=VALUES(reason),status='active',released_by_user_id=NULL,released_at=NULL,updated_at=CURRENT_TIMESTAMP");
        $stmt->execute([tl_uuid(),$accountLinkId ?: null,$hash,$type,$reason,$actorId]);
        return ['email_confirmation'=>substr($hash,0,12),'status'=>'active'];
    }
}

if (!function_exists('tl_notifications_release_suppression')) {
    function tl_notifications_release_suppression(array $user, string $publicId): array
    {
        if (tl_product_role($user) !== 'admin') throw new TlHttpException('Administrator suppression access is required.', 403, 'notification_suppression_forbidden');
        $pdo = tl_require_db();
        $publicId = tl_action_clean($publicId, 64, true, 'Suppression');
        $stmt = $pdo->prepare("UPDATE training_notification_suppressions SET status='released',released_by_user_id=?,released_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE public_id=? AND status='active'");
        $stmt->execute([tl_campaign_user_id($user),$publicId]);
        if ($stmt->rowCount() < 1) throw new TlHttpException('Active suppression was not found.', 404, 'notification_suppression_not_found');
        return ['public_id'=>$publicId,'status'=>'released'];
    }
}
