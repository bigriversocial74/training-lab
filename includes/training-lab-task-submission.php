<?php
/**
 * Transaction-safe task completion and proof revision writes.
 */
require_once __DIR__ . '/training-lab-task-experience.php';

if (!function_exists('tl_task_secure_submit')) {
    function tl_task_secure_submit(array $user, array $input): array
    {
        $pdo = tl_require_db();
        $userId = tl_campaign_user_id($user);
        $campaignRef = tl_campaign_clean_ref((string)($input['campaign_id'] ?? $input['campaign'] ?? ''));
        $taskRef = tl_task_clean_ref((string)($input['task_id'] ?? $input['task'] ?? ''));
        if ($campaignRef === '' || $taskRef === '') throw new TlHttpException('Campaign and task are required.', 422, 'task_reference_required');

        $proofText = tl_action_clean((string)($input['proof_text'] ?? $input['reflection'] ?? ''), 5000, false, 'Proof note');
        $externalUrl = tl_action_external_url($input['external_url'] ?? null);
        $pdo->beginTransaction();
        try {
            $campaignStmt = $pdo->prepare("SELECT c.*,tp.id AS participant_id,tp.status AS participant_status,tp.public_id AS participant_public_id
                FROM training_campaigns c
                INNER JOIN training_participants tp ON tp.campaign_id=c.id AND tp.user_id=?
                WHERE (c.id=? OR c.public_id=? OR c.slug=?) AND tp.status<>'removed'
                LIMIT 1 FOR UPDATE");
            $campaignStmt->execute([$userId, ctype_digit($campaignRef) ? (int)$campaignRef : 0, $campaignRef, $campaignRef]);
            $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
            if (!$campaign) throw new TlHttpException('You are not enrolled in this campaign.', 403, 'campaign_enrollment_required');

            $taskStmt = $pdo->prepare("SELECT * FROM training_campaign_tasks WHERE campaign_id=? AND (id=? OR public_id=?) AND status='active' LIMIT 1 FOR UPDATE");
            $taskStmt->execute([(int)$campaign['id'], ctype_digit($taskRef) ? (int)$taskRef : 0, $taskRef]);
            $task = $taskStmt->fetch(PDO::FETCH_ASSOC);
            if (!$task) throw new TlHttpException('This task is not available in the selected campaign.', 404, 'task_not_found');

            $priorStmt = $pdo->prepare("SELECT t.id
                FROM training_campaign_tasks t
                WHERE t.campaign_id=? AND t.status='active' AND t.position_no<?
                  AND NOT EXISTS (
                    SELECT 1 FROM training_action_receipts ar
                    INNER JOIN training_proof_submissions p ON p.id=ar.proof_submission_id
                    WHERE ar.participant_id=? AND ar.receipt_status='active' AND p.task_id=t.id
                  )
                ORDER BY t.position_no ASC,t.id ASC LIMIT 1 FOR UPDATE");
            $priorStmt->execute([(int)$campaign['id'], (int)$task['position_no'], (int)$campaign['participant_id']]);
            if ($priorStmt->fetchColumn()) throw new TlHttpException('Complete the previous task before starting this one.', 409, 'task_prerequisite_incomplete');

            $latestStmt = $pdo->prepare("SELECT p.*,
                (SELECT r.decision FROM training_reviews r WHERE r.proof_submission_id=p.id ORDER BY r.created_at DESC,r.id DESC LIMIT 1) AS latest_decision,
                (SELECT r.review_notes FROM training_reviews r WHERE r.proof_submission_id=p.id ORDER BY r.created_at DESC,r.id DESC LIMIT 1) AS latest_review_notes
                FROM training_proof_submissions p
                WHERE p.participant_id=? AND p.task_id=?
                ORDER BY p.created_at DESC,p.id DESC LIMIT 1 FOR UPDATE");
            $latestStmt->execute([(int)$campaign['participant_id'], (int)$task['id']]);
            $latest = $latestStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            $receiptStmt = $pdo->prepare("SELECT ar.id,ar.public_id FROM training_action_receipts ar
                INNER JOIN training_proof_submissions p ON p.id=ar.proof_submission_id
                WHERE ar.participant_id=? AND p.task_id=? AND ar.receipt_status='active'
                ORDER BY ar.id ASC LIMIT 1 FOR UPDATE");
            $receiptStmt->execute([(int)$campaign['participant_id'], (int)$task['id']]);
            $existingReceipt = $receiptStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($existingReceipt || ($latest && ((string)$latest['status'] === 'approved' || (string)($latest['latest_decision'] ?? '') === 'approved'))) {
                $pdo->commit();
                return ['status'=>'complete','already_complete'=>true,'task_id'=>(int)$task['id'],'receipt_public_id'=>(string)($existingReceipt['public_id'] ?? '')];
            }

            $prerequisitesMet = true;
            $status = tl_task_status_model($task, $latest, false, $prerequisitesMet, $campaign);
            if (empty($status['can_submit'])) throw new TlHttpException((string)$status['reason'], 409, 'task_not_submittable');

            $proofRequired = (int)($task['proof_required'] ?? 0) === 1;
            if ($proofRequired && mb_strlen($proofText) < 10 && $externalUrl === null) {
                throw new TlHttpException('Add a short proof note or a valid proof link.', 422, 'proof_required');
            }

            $latestDecision = (string)($latest['latest_decision'] ?? '');
            $isRevision = $latest && ($latestDecision === 'needs_more_info' || (string)$latest['status'] === 'rejected');
            if ($proofRequired && $latest && !$isRevision && in_array((string)$latest['status'], ['submitted','in_review'], true)) {
                throw new TlHttpException('Your current proof is already in review.', 409, 'proof_already_in_review');
            }

            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM training_proof_submissions WHERE participant_id=? AND task_id=?');
            $countStmt->execute([(int)$campaign['participant_id'], (int)$task['id']]);
            $revisionNumber = (int)$countStmt->fetchColumn() + 1;
            $proofPublicId = tl_uuid();
            $proofStatus = $proofRequired ? 'submitted' : 'approved';
            $proofType = $externalUrl !== null && $proofText === '' ? 'external_link' : 'text';
            $metadata = [
                'source'=>'task_product_submission',
                'revision_number'=>$revisionNumber,
                'revision_of_public_id'=>$isRevision ? (string)$latest['public_id'] : null,
                'auto_verified'=>!$proofRequired,
                'trusted_actor'=>true,
            ];
            $insertProof = $pdo->prepare('INSERT INTO training_proof_submissions (public_id,campaign_id,task_id,participant_id,submitted_by_user_id,proof_type,proof_text,external_url,status,reviewed_at,metadata_json) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $insertProof->execute([
                $proofPublicId,
                (int)$campaign['id'],
                (int)$task['id'],
                (int)$campaign['participant_id'],
                $userId,
                $proofType,
                $proofText !== '' ? $proofText : null,
                $externalUrl,
                $proofStatus,
                $proofRequired ? null : gmdate('Y-m-d H:i:s'),
                json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);
            $proofId = (int)$pdo->lastInsertId();

            if ($isRevision) {
                $oldMetadata = tl_task_json($latest['metadata_json'] ?? null);
                $oldMetadata['replaced_by_public_id'] = $proofPublicId;
                $oldMetadata['replaced_at'] = gmdate('c');
                $cancel = $pdo->prepare("UPDATE training_proof_submissions SET status='cancelled',metadata_json=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status IN ('submitted','in_review','rejected')");
                $cancel->execute([json_encode($oldMetadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), (int)$latest['id']]);
            }

            $receipt = null;
            if (!$proofRequired) {
                $verificationHash = hash('sha256', random_bytes(32) . '|' . $proofPublicId . '|' . $userId);
                $receiptPublicId = tl_uuid();
                $insertReceipt = $pdo->prepare('INSERT INTO training_action_receipts (public_id,campaign_id,participant_id,user_id,proof_submission_id,review_id,receipt_type,verification_hash,receipt_status,metadata_json) VALUES (?,?,?,?,?,NULL,?,?,?,?,?)');
                $insertReceipt->execute([
                    $receiptPublicId,
                    (int)$campaign['id'],
                    (int)$campaign['participant_id'],
                    $userId,
                    $proofId,
                    'task_completed',
                    $verificationHash,
                    'active',
                    json_encode(['source'=>'task_product_auto_verification','task_id'=>(int)$task['id']], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                ]);
                $receipt = ['id'=>(int)$pdo->lastInsertId(),'public_id'=>$receiptPublicId];

                $streak = $pdo->prepare("INSERT INTO training_streaks (campaign_id,participant_id,user_id,current_streak_days,longest_streak_days,completed_action_count,last_action_date)
                    VALUES (?,?,?,1,1,1,CURRENT_DATE)
                    ON DUPLICATE KEY UPDATE
                      completed_action_count=completed_action_count+1,
                      current_streak_days=CASE WHEN last_action_date=CURRENT_DATE THEN current_streak_days WHEN last_action_date=DATE_SUB(CURRENT_DATE,INTERVAL 1 DAY) THEN current_streak_days+1 ELSE 1 END,
                      longest_streak_days=GREATEST(longest_streak_days,CASE WHEN last_action_date=CURRENT_DATE THEN current_streak_days WHEN last_action_date=DATE_SUB(CURRENT_DATE,INTERVAL 1 DAY) THEN current_streak_days+1 ELSE 1 END),
                      last_action_date=CURRENT_DATE,
                      updated_at=CURRENT_TIMESTAMP");
                $streak->execute([(int)$campaign['id'], (int)$campaign['participant_id'], $userId]);
            }

            tl_log_event($pdo, $userId, 'proof', $proofId, $isRevision ? 'proof_revised' : ($proofRequired ? 'proof_submitted' : 'task_completed'), [
                'campaign_id'=>(int)$campaign['id'],
                'task_id'=>(int)$task['id'],
                'participant_id'=>(int)$campaign['participant_id'],
                'revision_number'=>$revisionNumber,
                'proof_required'=>$proofRequired,
            ]);
            $pdo->commit();

            $rewardEvaluation = null;
            if (!$proofRequired) {
                try { $rewardEvaluation = tl_evaluate_rewards(['campaign_id'=>(string)$campaign['id'],'user_id'=>$userId]); }
                catch (Throwable $e) { $rewardEvaluation = ['deferred'=>true]; }
            }

            return [
                'status'=>$proofRequired ? 'submitted' : 'complete',
                'task_id'=>(int)$task['id'],
                'proof_id'=>$proofId,
                'proof_public_id'=>$proofPublicId,
                'revision_number'=>$revisionNumber,
                'is_revision'=>$isRevision,
                'receipt'=>$receipt,
                'reward_evaluation'=>$rewardEvaluation,
                'already_complete'=>false,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
