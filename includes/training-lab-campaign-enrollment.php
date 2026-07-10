<?php
/**
 * Transaction-safe participant enrollment for published Training Lab campaigns.
 */
require_once __DIR__ . '/training-lab-campaign-experience.php';

if (!function_exists('tl_campaign_secure_enroll')) {
    function tl_campaign_secure_enroll(array $user, string $campaignRef): array
    {
        $pdo = tl_require_db();
        $campaignRef = tl_campaign_clean_ref($campaignRef);
        if ($campaignRef === '') throw new TlHttpException('A campaign is required.', 422, 'campaign_required');
        $userId = tl_campaign_user_id($user);
        $participantLabel = tl_action_clean((string)($user['name'] ?? 'Training Participant'), 180, true, 'Participant label');

        $pdo->beginTransaction();
        try {
            $campaignStmt = $pdo->prepare('SELECT * FROM training_campaigns WHERE (id = ? OR public_id = ? OR slug = ?) LIMIT 1 FOR UPDATE');
            $campaignStmt->execute([ctype_digit($campaignRef) ? (int)$campaignRef : 0, $campaignRef, $campaignRef]);
            $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
            if (!$campaign) throw new TlHttpException('Campaign not found.', 404, 'campaign_not_found');

            $timezone = trim((string)($campaign['timezone'] ?? 'America/Phoenix')) ?: 'America/Phoenix';
            try { $now = new DateTimeImmutable('now', new DateTimeZone($timezone)); }
            catch (Throwable $e) { $now = new DateTimeImmutable('now', new DateTimeZone('America/Phoenix')); }
            $endsAt = tl_campaign_datetime($campaign['ends_at'] ?? null, $timezone);
            $ended = $endsAt instanceof DateTimeImmutable && $endsAt < $now;
            $campaignStatus = (string)($campaign['status'] ?? 'draft');
            $campaignOpen = in_array($campaignStatus, ['scheduled','active'], true) && !$ended;

            $existingStmt = $pdo->prepare('SELECT * FROM training_participants WHERE campaign_id = ? AND user_id = ? LIMIT 1 FOR UPDATE');
            $existingStmt->execute([(int)$campaign['id'], $userId]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $status = (string)($existing['status'] ?? 'active');
                if ($status === 'removed') throw new TlHttpException('Your access to this campaign has been removed.', 403, 'campaign_access_removed');
                if ($status === 'invited') {
                    if (!$campaignOpen) throw new TlHttpException('This campaign invitation is no longer active.', 409, 'campaign_invitation_closed');
                    $activate = $pdo->prepare("UPDATE training_participants SET status='active', participant_label=?, joined_at=COALESCE(joined_at,CURRENT_TIMESTAMP), updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='invited'");
                    $activate->execute([$participantLabel, (int)$existing['id']]);
                    tl_log_event($pdo, $userId, 'participant', (int)$existing['id'], 'campaign_invitation_accepted', ['campaign_id'=>(int)$campaign['id']]);
                    $status = 'active';
                }
                $pdo->commit();
                return [
                    'participant_id'=>(int)$existing['id'],
                    'public_id'=>(string)$existing['public_id'],
                    'status'=>$status,
                    'already_joined'=>true,
                    'invitation_accepted'=>(string)($existing['status'] ?? '') === 'invited',
                ];
            }

            $visibility = (string)($campaign['visibility'] ?? 'draft');
            if ($visibility !== 'published' || !$campaignOpen) {
                throw new TlHttpException($ended ? 'Enrollment has closed for this campaign.' : 'This campaign is not accepting enrollment.', 409, $ended ? 'campaign_ended' : 'campaign_not_joinable');
            }

            $settings = tl_campaign_settings($campaign['settings_json'] ?? null);
            $capacity = max(0, (int)($settings['participant_limit'] ?? $settings['capacity'] ?? 0));
            if ($capacity > 0) {
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM training_participants WHERE campaign_id=? AND status<>'removed'");
                $countStmt->execute([(int)$campaign['id']]);
                if ((int)$countStmt->fetchColumn() >= $capacity) {
                    throw new TlHttpException('This campaign has reached its participant limit.', 409, 'campaign_full');
                }
            }

            $publicId = tl_uuid();
            $insert = $pdo->prepare('INSERT INTO training_participants (public_id, campaign_id, user_id, participant_label, status, metadata_json) VALUES (?, ?, ?, ?, ?, ?)');
            $insert->execute([
                $publicId,
                (int)$campaign['id'],
                $userId,
                $participantLabel,
                'active',
                json_encode(['source'=>'campaign_product_enrollment','trusted_actor'=>true], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);
            $participantId = (int)$pdo->lastInsertId();

            $streak = $pdo->prepare('INSERT IGNORE INTO training_streaks (campaign_id, participant_id, user_id, current_streak_days, longest_streak_days, completed_action_count) VALUES (?, ?, ?, 0, 0, 0)');
            $streak->execute([(int)$campaign['id'], $participantId, $userId]);
            tl_log_event($pdo, $userId, 'participant', $participantId, 'campaign_joined', ['campaign_id'=>(int)$campaign['id'],'source'=>'campaign_product_enrollment']);
            $pdo->commit();

            return [
                'participant_id'=>$participantId,
                'public_id'=>$publicId,
                'status'=>'active',
                'already_joined'=>false,
                'invitation_accepted'=>false,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
