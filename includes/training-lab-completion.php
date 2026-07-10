<?php
/**
 * Transaction-safe participant campaign completion and certificate receipt.
 */
require_once __DIR__ . '/training-lab-progress-experience.php';

if (!function_exists('tl_progress_secure_complete')) {
    function tl_progress_secure_complete(array $user, string $campaignRef): array
    {
        $pdo = tl_require_db();
        $userId = tl_campaign_user_id($user);
        $campaignRef = tl_campaign_clean_ref($campaignRef);
        if ($campaignRef === '') throw new TlHttpException('A campaign is required.', 422, 'campaign_required');

        $pdo->beginTransaction();
        try {
            $campaignStmt = $pdo->prepare("SELECT c.*,tp.id AS participant_id,tp.public_id AS participant_public_id,tp.status AS participant_status,tp.completed_at
                FROM training_campaigns c
                INNER JOIN training_participants tp ON tp.campaign_id=c.id AND tp.user_id=?
                WHERE (c.id=? OR c.public_id=? OR c.slug=?) AND tp.status<>'removed'
                LIMIT 1 FOR UPDATE");
            $campaignStmt->execute([$userId, ctype_digit($campaignRef) ? (int)$campaignRef : 0, $campaignRef, $campaignRef]);
            $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
            if (!$campaign) throw new TlHttpException('You are not enrolled in this campaign.', 403, 'campaign_enrollment_required');

            $existingStmt = $pdo->prepare("SELECT id,public_id,issued_at FROM training_action_receipts
                WHERE campaign_id=? AND participant_id=? AND receipt_type='sequence_completed' AND receipt_status='active'
                ORDER BY id ASC LIMIT 1 FOR UPDATE");
            $existingStmt->execute([(int)$campaign['id'], (int)$campaign['participant_id']]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($existing) {
                if ((string)$campaign['participant_status'] !== 'completed') {
                    $repair = $pdo->prepare("UPDATE training_participants SET status='completed',completed_at=COALESCE(completed_at,?),updated_at=CURRENT_TIMESTAMP WHERE id=?");
                    $repair->execute([(string)$existing['issued_at'], (int)$campaign['participant_id']]);
                }
                $pdo->commit();
                return [
                    'status'=>'completed',
                    'already_completed'=>true,
                    'certificate_public_id'=>(string)$existing['public_id'],
                    'completed_at'=>(string)$existing['issued_at'],
                ];
            }

            if ((string)$campaign['participant_status'] !== 'active') {
                throw new TlHttpException('Your campaign enrollment must be active before completion.', 409, 'campaign_not_active');
            }

            $taskCountStmt = $pdo->prepare("SELECT COUNT(*) FROM training_campaign_tasks WHERE campaign_id=? AND status='active'");
            $taskCountStmt->execute([(int)$campaign['id']]);
            $taskCount = (int)$taskCountStmt->fetchColumn();
            if ($taskCount < 1) throw new TlHttpException('This campaign has no active tasks to complete.', 409, 'campaign_has_no_tasks');

            $completeStmt = $pdo->prepare("SELECT COUNT(DISTINCT p.task_id)
                FROM training_action_receipts ar
                INNER JOIN training_proof_submissions p ON p.id=ar.proof_submission_id
                INNER JOIN training_campaign_tasks t ON t.id=p.task_id AND t.campaign_id=ar.campaign_id AND t.status='active'
                WHERE ar.campaign_id=? AND ar.participant_id=? AND ar.receipt_type='task_completed' AND ar.receipt_status='active'");
            $completeStmt->execute([(int)$campaign['id'], (int)$campaign['participant_id']]);
            $completeCount = (int)$completeStmt->fetchColumn();
            if ($completeCount < $taskCount) {
                throw new TlHttpException('Complete every active task before finishing this campaign.', 409, 'campaign_tasks_incomplete');
            }

            $completedAt = gmdate('Y-m-d H:i:s');
            $certificatePublicId = tl_uuid();
            $verificationHash = hash('sha256', random_bytes(32) . '|' . $certificatePublicId . '|' . $userId . '|' . (int)$campaign['id']);
            $metadata = [
                'source'=>'participant_campaign_completion',
                'task_count'=>$taskCount,
                'completed_task_count'=>$completeCount,
                'trusted_actor'=>true,
            ];
            $receiptStmt = $pdo->prepare("INSERT INTO training_action_receipts
                (public_id,campaign_id,participant_id,user_id,proof_submission_id,review_id,receipt_type,verification_hash,receipt_status,issued_at,metadata_json)
                VALUES (?,?,?,?,NULL,NULL,'sequence_completed',?,'active',?,?)");
            $receiptStmt->execute([
                $certificatePublicId,
                (int)$campaign['id'],
                (int)$campaign['participant_id'],
                $userId,
                $verificationHash,
                $completedAt,
                json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);
            $receiptId = (int)$pdo->lastInsertId();

            $participantStmt = $pdo->prepare("UPDATE training_participants SET status='completed',completed_at=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='active'");
            $participantStmt->execute([$completedAt, (int)$campaign['participant_id']]);
            if ($participantStmt->rowCount() !== 1) throw new TlHttpException('Campaign completion could not be finalized.', 409, 'campaign_completion_conflict');

            tl_log_event($pdo, $userId, 'participant', (int)$campaign['participant_id'], 'campaign_completed', [
                'campaign_id'=>(int)$campaign['id'],
                'receipt_id'=>$receiptId,
                'task_count'=>$taskCount,
                'source'=>'participant_campaign_completion',
            ]);
            $pdo->commit();

            $rewardEvaluation = null;
            try { $rewardEvaluation = tl_evaluate_rewards(['campaign_id'=>(string)$campaign['id'],'user_id'=>$userId]); }
            catch (Throwable $e) { $rewardEvaluation = ['deferred'=>true]; }

            return [
                'status'=>'completed',
                'already_completed'=>false,
                'certificate_public_id'=>$certificatePublicId,
                'completed_at'=>$completedAt,
                'reward_evaluation'=>$rewardEvaluation,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
