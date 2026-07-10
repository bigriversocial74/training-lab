<?php
/**
 * Participant task detail, ordered status, and proof revision read model.
 */
require_once __DIR__ . '/training-lab-campaign-experience.php';
require_once __DIR__ . '/training-lab-participant-home.php';

if (!function_exists('tl_task_clean_ref')) {
    function tl_task_clean_ref(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($value)) ?: '';
    }
}

if (!function_exists('tl_task_json')) {
    function tl_task_json($value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('tl_task_campaign_participant')) {
    function tl_task_campaign_participant(PDO $pdo, int $userId, string $campaignRef = ''): ?array
    {
        $campaignRef = tl_campaign_clean_ref($campaignRef);
        $where = $campaignRef !== ''
            ? '(c.id=? OR c.public_id=? OR c.slug=?)'
            : "c.status IN ('scheduled','active','paused','completed')";
        $sql = "SELECT c.*,tp.id AS participant_id,tp.public_id AS participant_public_id,tp.status AS participant_status,tp.joined_at
                FROM training_participants tp
                INNER JOIN training_campaigns c ON c.id=tp.campaign_id
                WHERE tp.user_id=? AND tp.status<>'removed' AND {$where}
                ORDER BY CASE tp.status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 WHEN 'completed' THEN 2 WHEN 'invited' THEN 3 ELSE 4 END,c.updated_at DESC,c.id DESC
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $params = [$userId];
        if ($campaignRef !== '') {
            $params[] = ctype_digit($campaignRef) ? (int)$campaignRef : 0;
            $params[] = $campaignRef;
            $params[] = $campaignRef;
        }
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('tl_task_latest_proof')) {
    function tl_task_latest_proof(PDO $pdo, int $participantId, int $taskId): ?array
    {
        $stmt = $pdo->prepare("SELECT p.*,
            (SELECT r.decision FROM training_reviews r WHERE r.proof_submission_id=p.id ORDER BY r.created_at DESC,r.id DESC LIMIT 1) AS latest_decision,
            (SELECT r.review_notes FROM training_reviews r WHERE r.proof_submission_id=p.id ORDER BY r.created_at DESC,r.id DESC LIMIT 1) AS latest_review_notes,
            (SELECT r.reviewed_at FROM training_reviews r WHERE r.proof_submission_id=p.id ORDER BY r.created_at DESC,r.id DESC LIMIT 1) AS latest_reviewed_at
            FROM training_proof_submissions p
            WHERE p.participant_id=? AND p.task_id=?
            ORDER BY p.created_at DESC,p.id DESC LIMIT 1");
        $stmt->execute([$participantId, $taskId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('tl_task_has_receipt')) {
    function tl_task_has_receipt(PDO $pdo, int $participantId, int $taskId): bool
    {
        $stmt = $pdo->prepare("SELECT ar.id
            FROM training_action_receipts ar
            INNER JOIN training_proof_submissions p ON p.id=ar.proof_submission_id
            WHERE ar.participant_id=? AND p.task_id=? AND ar.receipt_status='active'
            ORDER BY ar.id ASC LIMIT 1");
        $stmt->execute([$participantId, $taskId]);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('tl_task_due_at')) {
    function tl_task_due_at(array $task, array $campaign): ?DateTimeImmutable
    {
        $settings = tl_task_json($task['settings_json'] ?? null);
        $timezone = trim((string)($campaign['timezone'] ?? 'America/Phoenix')) ?: 'America/Phoenix';
        $dueValue = $settings['due_at'] ?? $settings['deadline'] ?? $campaign['ends_at'] ?? null;
        return tl_campaign_datetime($dueValue, $timezone);
    }
}

if (!function_exists('tl_task_status_model')) {
    function tl_task_status_model(array $task, ?array $proof, bool $hasReceipt, bool $prerequisitesMet, array $campaign): array
    {
        $participantStatus = (string)($campaign['participant_status'] ?? '');
        $campaignStatus = (string)($campaign['status'] ?? 'draft');
        $timezone = trim((string)($campaign['timezone'] ?? 'America/Phoenix')) ?: 'America/Phoenix';
        try { $now = new DateTimeImmutable('now', new DateTimeZone($timezone)); }
        catch (Throwable $e) { $now = new DateTimeImmutable('now', new DateTimeZone('America/Phoenix')); }
        $startsAt = tl_campaign_datetime($campaign['starts_at'] ?? null, $timezone);
        $endsAt = tl_campaign_datetime($campaign['ends_at'] ?? null, $timezone);
        $dueAt = tl_task_due_at($task, $campaign);
        $settings = tl_task_json($task['settings_json'] ?? null);
        $closeAfterDue = !empty($settings['close_after_due']);
        $latestDecision = (string)($proof['latest_decision'] ?? '');
        $proofStatus = (string)($proof['status'] ?? '');
        $complete = $hasReceipt || $proofStatus === 'approved' || $latestDecision === 'approved';
        $overdue = !$complete && $dueAt instanceof DateTimeImmutable && $dueAt < $now;

        if ($complete) return ['key'=>'complete','label'=>'Complete','tone'=>'success','can_submit'=>false,'reason'=>'This task is verified.','overdue'=>false,'due_at'=>$dueAt];
        if ($latestDecision === 'needs_more_info' || $proofStatus === 'rejected') return ['key'=>'needs_revision','label'=>'Needs an update','tone'=>'warning','can_submit'=>true,'reason'=>(string)($proof['latest_review_notes'] ?? 'Update your proof and submit it again.'),'overdue'=>$overdue,'due_at'=>$dueAt];
        if (in_array($proofStatus, ['submitted','in_review'], true)) return ['key'=>'in_review','label'=>'In review','tone'=>'pending','can_submit'=>false,'reason'=>'Your proof is waiting for a reviewer decision.','overdue'=>$overdue,'due_at'=>$dueAt];
        if ($participantStatus === 'invited') return ['key'=>'locked','label'=>'Accept invitation','tone'=>'neutral','can_submit'=>false,'reason'=>'Accept the campaign invitation before starting tasks.','overdue'=>$overdue,'due_at'=>$dueAt];
        if ($participantStatus === 'paused') return ['key'=>'locked','label'=>'Paused','tone'=>'warning','can_submit'=>false,'reason'=>'Your campaign enrollment is paused.','overdue'=>$overdue,'due_at'=>$dueAt];
        if ($participantStatus === 'completed') return ['key'=>'locked','label'=>'Campaign complete','tone'=>'neutral','can_submit'=>false,'reason'=>'This campaign is already completed.','overdue'=>$overdue,'due_at'=>$dueAt];
        if ($startsAt instanceof DateTimeImmutable && $startsAt > $now) return ['key'=>'locked','label'=>'Starts soon','tone'=>'pending','can_submit'=>false,'reason'=>'This task unlocks when the campaign starts.','overdue'=>false,'due_at'=>$dueAt];
        if (($endsAt instanceof DateTimeImmutable && $endsAt < $now) || in_array($campaignStatus, ['completed','archived'], true)) return ['key'=>'locked','label'=>'Campaign ended','tone'=>'neutral','can_submit'=>false,'reason'=>'The campaign is no longer accepting task submissions.','overdue'=>$overdue,'due_at'=>$dueAt];
        if ($campaignStatus === 'paused') return ['key'=>'locked','label'=>'Campaign paused','tone'=>'warning','can_submit'=>false,'reason'=>'The campaign is temporarily paused.','overdue'=>$overdue,'due_at'=>$dueAt];
        if (!$prerequisitesMet) return ['key'=>'locked','label'=>'Locked','tone'=>'neutral','can_submit'=>false,'reason'=>'Complete the previous task before starting this one.','overdue'=>$overdue,'due_at'=>$dueAt];
        if ($overdue && $closeAfterDue) return ['key'=>'locked','label'=>'Past due','tone'=>'warning','can_submit'=>false,'reason'=>'The submission deadline has passed.','overdue'=>true,'due_at'=>$dueAt];
        if ($overdue) return ['key'=>'overdue','label'=>'Overdue','tone'=>'warning','can_submit'=>true,'reason'=>'This task is past due, but submissions are still accepted.','overdue'=>true,'due_at'=>$dueAt];
        return ['key'=>'ready','label'=>'Ready','tone'=>'info','can_submit'=>true,'reason'=>'This task is ready to complete.','overdue'=>false,'due_at'=>$dueAt];
    }
}

if (!function_exists('tl_task_history')) {
    function tl_task_history(PDO $pdo, int $participantId, int $taskId): array
    {
        $stmt = $pdo->prepare("SELECT p.* FROM training_proof_submissions p WHERE p.participant_id=? AND p.task_id=? ORDER BY p.created_at DESC,p.id DESC");
        $stmt->execute([$participantId, $taskId]);
        $proofs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $reviewStmt = $pdo->prepare("SELECT id,public_id,decision,review_notes,reviewed_at,created_at FROM training_reviews WHERE proof_submission_id=? ORDER BY created_at DESC,id DESC");
        foreach ($proofs as &$proof) {
            $reviewStmt->execute([(int)$proof['id']]);
            $proof['reviews'] = $reviewStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $proof['metadata'] = tl_task_json($proof['metadata_json'] ?? null);
        }
        unset($proof);
        return $proofs;
    }
}

if (!function_exists('tl_task_experience')) {
    function tl_task_experience(array $user, string $campaignRef = '', string $taskRef = ''): array
    {
        $pdo = tl_require_db();
        $userId = tl_campaign_user_id($user);
        $campaign = tl_task_campaign_participant($pdo, $userId, $campaignRef);
        if (!$campaign) return ['found'=>false,'reason'=>'You are not enrolled in this campaign.','tasks'=>[]];
        $participantId = (int)$campaign['participant_id'];

        $taskStmt = $pdo->prepare("SELECT * FROM training_campaign_tasks WHERE campaign_id=? AND status='active' ORDER BY position_no ASC,id ASC");
        $taskStmt->execute([(int)$campaign['id']]);
        $taskRows = $taskStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $tasks = [];
        $priorComplete = true;
        foreach ($taskRows as $row) {
            $proof = tl_task_latest_proof($pdo, $participantId, (int)$row['id']);
            $receipt = tl_task_has_receipt($pdo, $participantId, (int)$row['id']);
            $status = tl_task_status_model($row, $proof, $receipt, $priorComplete, $campaign);
            $tasks[] = $row + [
                'ref'=>(string)($row['public_id'] ?: $row['id']),
                'latest_proof'=>$proof,
                'has_receipt'=>$receipt,
                'status_model'=>$status,
            ];
            if ($status['key'] !== 'complete') $priorComplete = false;
        }

        $taskRef = tl_task_clean_ref($taskRef);
        $selected = null;
        if ($taskRef !== '') {
            foreach ($tasks as $task) {
                if (in_array($taskRef, [(string)$task['id'], (string)$task['public_id']], true)) { $selected = $task; break; }
            }
        }
        if (!$selected) {
            foreach ($tasks as $task) {
                if (in_array((string)$task['status_model']['key'], ['needs_revision','ready','overdue'], true)) { $selected = $task; break; }
            }
        }
        if (!$selected) $selected = $tasks[0] ?? null;

        $selectedIndex = 0;
        foreach ($tasks as $index => $task) if ($selected && (int)$task['id'] === (int)$selected['id']) $selectedIndex = $index;
        $completeCount = count(array_filter($tasks, static fn(array $task): bool => ($task['status_model']['key'] ?? '') === 'complete'));
        $campaignRefResolved = (string)($campaign['slug'] ?: $campaign['public_id']);

        return [
            'found'=>true,
            'user_id'=>$userId,
            'campaign'=>$campaign,
            'campaign_ref'=>$campaignRefResolved,
            'participant_id'=>$participantId,
            'tasks'=>$tasks,
            'selected'=>$selected,
            'selected_index'=>$selectedIndex,
            'previous'=>$selectedIndex > 0 ? $tasks[$selectedIndex - 1] : null,
            'next'=>$selectedIndex < count($tasks) - 1 ? $tasks[$selectedIndex + 1] : null,
            'history'=>$selected ? tl_task_history($pdo, $participantId, (int)$selected['id']) : [],
            'progress_percent'=>count($tasks) > 0 ? (int)round(($completeCount / count($tasks)) * 100) : 0,
            'complete_count'=>$completeCount,
        ];
    }
}

if (!function_exists('tl_task_flash_set')) {
    function tl_task_flash_set(string $type, string $message): void
    {
        tl_security_session_start();
        $_SESSION['_tl_task_flash'] = ['type'=>in_array($type, ['success','error','info'], true) ? $type : 'info','message'=>mb_substr(trim($message), 0, 500)];
    }
}

if (!function_exists('tl_task_flash_take')) {
    function tl_task_flash_take(): ?array
    {
        tl_security_session_start();
        $flash = $_SESSION['_tl_task_flash'] ?? null;
        unset($_SESSION['_tl_task_flash']);
        return is_array($flash) ? $flash : null;
    }
}
