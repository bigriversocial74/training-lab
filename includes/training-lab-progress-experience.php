<?php
/**
 * Participant progress, completion, history, and merchant summary read models.
 */
require_once __DIR__ . '/training-lab-task-experience.php';

if (!function_exists('tl_progress_campaign_rows')) {
    function tl_progress_campaign_rows(array $user): array
    {
        $pdo = tl_require_db();
        $userId = tl_campaign_user_id($user);
        $sql = "SELECT
                    c.id AS campaign_id,c.public_id AS campaign_public_id,c.slug AS campaign_slug,c.title AS campaign_title,
                    c.summary AS campaign_summary,c.status AS campaign_status,c.visibility,c.starts_at,c.ends_at,c.timezone,
                    tp.id AS participant_id,tp.public_id AS participant_public_id,tp.status AS participant_status,tp.joined_at,tp.completed_at,tp.updated_at,
                    COALESCE(s.current_streak_days,0) AS current_streak_days,
                    COALESCE(s.longest_streak_days,0) AS longest_streak_days,
                    COALESCE(s.completed_action_count,0) AS completed_action_count,
                    (SELECT COUNT(*) FROM training_campaign_tasks t WHERE t.campaign_id=c.id AND t.status='active') AS task_count,
                    (SELECT COUNT(DISTINCT p.task_id)
                       FROM training_action_receipts ar
                       INNER JOIN training_proof_submissions p ON p.id=ar.proof_submission_id
                       WHERE ar.campaign_id=c.id AND ar.participant_id=tp.id AND ar.receipt_status='active' AND ar.receipt_type='task_completed') AS completed_task_count,
                    (SELECT COUNT(*) FROM training_proof_submissions p WHERE p.participant_id=tp.id AND p.status IN ('submitted','in_review')) AS pending_review_count,
                    (SELECT COUNT(*) FROM training_reward_events re WHERE re.participant_id=tp.id AND re.status<>'cancelled') AS reward_count,
                    (SELECT COUNT(*) FROM training_reward_events re WHERE re.participant_id=tp.id AND re.status='eligible') AS claimable_reward_count,
                    (SELECT ar.public_id FROM training_action_receipts ar WHERE ar.participant_id=tp.id AND ar.receipt_type='sequence_completed' AND ar.receipt_status='active' ORDER BY ar.id ASC LIMIT 1) AS certificate_public_id,
                    (SELECT ar.issued_at FROM training_action_receipts ar WHERE ar.participant_id=tp.id AND ar.receipt_type='sequence_completed' AND ar.receipt_status='active' ORDER BY ar.id ASC LIMIT 1) AS certificate_issued_at
                FROM training_participants tp
                INNER JOIN training_campaigns c ON c.id=tp.campaign_id
                LEFT JOIN training_streaks s ON s.participant_id=tp.id
                WHERE tp.user_id=? AND tp.status<>'removed'
                ORDER BY CASE tp.status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 WHEN 'invited' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END,tp.updated_at DESC,tp.id DESC";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }

        return array_map(static function (array $row): array {
            $total = max(0, (int)$row['task_count']);
            $complete = min($total, max(0, (int)$row['completed_task_count']));
            $row['progress_percent'] = $total > 0 ? min(100, (int)round(($complete / $total) * 100)) : 0;
            $row['is_completed'] = (string)$row['participant_status'] === 'completed' || !empty($row['certificate_public_id']);
            $row['ref'] = (string)($row['campaign_slug'] ?: $row['campaign_public_id']);
            return $row;
        }, $rows);
    }
}

if (!function_exists('tl_progress_activity')) {
    function tl_progress_activity(PDO $pdo, int $participantId, int $campaignId, int $limit = 30): array
    {
        $items = [];
        try {
            $proofStmt = $pdo->prepare("SELECT p.id,p.public_id,p.status,p.created_at,p.updated_at,t.title AS task_title
                FROM training_proof_submissions p
                INNER JOIN training_campaign_tasks t ON t.id=p.task_id
                WHERE p.participant_id=? AND p.campaign_id=?
                ORDER BY p.created_at DESC,p.id DESC LIMIT 50");
            $proofStmt->execute([$participantId, $campaignId]);
            foreach ($proofStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $proof) {
                $status = (string)$proof['status'];
                $items[] = [
                    'type'=>'proof',
                    'label'=>(string)$proof['task_title'],
                    'detail'=>match ($status) {
                        'approved'=>'Proof approved',
                        'rejected'=>'Proof needs attention',
                        'cancelled'=>'Proof replaced',
                        'in_review'=>'Proof in review',
                        default=>'Proof submitted',
                    },
                    'tone'=>$status === 'approved' ? 'success' : (in_array($status, ['rejected','cancelled'], true) ? 'warning' : 'pending'),
                    'at'=>(string)($proof['updated_at'] ?: $proof['created_at']),
                ];
            }

            $reviewStmt = $pdo->prepare("SELECT r.decision,r.review_notes,r.reviewed_at,r.created_at,t.title AS task_title
                FROM training_reviews r
                INNER JOIN training_proof_submissions p ON p.id=r.proof_submission_id
                INNER JOIN training_campaign_tasks t ON t.id=p.task_id
                WHERE p.participant_id=? AND p.campaign_id=?
                ORDER BY r.created_at DESC,r.id DESC LIMIT 50");
            $reviewStmt->execute([$participantId, $campaignId]);
            foreach ($reviewStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $review) {
                $decision = (string)$review['decision'];
                $items[] = [
                    'type'=>'review',
                    'label'=>(string)$review['task_title'],
                    'detail'=>ucwords(str_replace('_', ' ', $decision)),
                    'tone'=>$decision === 'approved' ? 'success' : ($decision === 'needs_more_info' ? 'warning' : 'info'),
                    'at'=>(string)($review['reviewed_at'] ?: $review['created_at']),
                ];
            }

            $receiptStmt = $pdo->prepare("SELECT ar.receipt_type,ar.issued_at,ar.created_at,t.title AS task_title
                FROM training_action_receipts ar
                LEFT JOIN training_proof_submissions p ON p.id=ar.proof_submission_id
                LEFT JOIN training_campaign_tasks t ON t.id=p.task_id
                WHERE ar.participant_id=? AND ar.campaign_id=? AND ar.receipt_status='active'
                ORDER BY ar.issued_at DESC,ar.id DESC LIMIT 50");
            $receiptStmt->execute([$participantId, $campaignId]);
            foreach ($receiptStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $receipt) {
                $sequence = (string)$receipt['receipt_type'] === 'sequence_completed';
                $items[] = [
                    'type'=>'receipt',
                    'label'=>$sequence ? 'Campaign completed' : ((string)($receipt['task_title'] ?: 'Task') . ' verified'),
                    'detail'=>$sequence ? 'Completion certificate created' : 'Completion receipt created',
                    'tone'=>'success',
                    'at'=>(string)($receipt['issued_at'] ?: $receipt['created_at']),
                ];
            }

            $rewardStmt = $pdo->prepare("SELECT re.status,re.value_cents,re.currency,re.created_at,re.updated_at,rr.reward_label
                FROM training_reward_events re
                LEFT JOIN training_reward_rules rr ON rr.id=re.reward_rule_id
                WHERE re.participant_id=? AND re.campaign_id=? AND re.status<>'cancelled'
                ORDER BY re.created_at DESC,re.id DESC LIMIT 50");
            $rewardStmt->execute([$participantId, $campaignId]);
            foreach ($rewardStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $reward) {
                $status = (string)$reward['status'];
                $items[] = [
                    'type'=>'reward',
                    'label'=>(string)($reward['reward_label'] ?: 'Training reward'),
                    'detail'=>ucwords(str_replace('_', ' ', $status)),
                    'tone'=>in_array($status, ['issued','linked'], true) ? 'success' : 'info',
                    'at'=>(string)($reward['updated_at'] ?: $reward['created_at']),
                ];
            }
        } catch (Throwable $e) {
            return [];
        }

        usort($items, static fn(array $a, array $b): int => (strtotime((string)$b['at']) ?: 0) <=> (strtotime((string)$a['at']) ?: 0));
        return array_slice($items, 0, max(1, min(100, $limit)));
    }
}

if (!function_exists('tl_progress_detail')) {
    function tl_progress_detail(array $user, string $campaignRef = ''): array
    {
        $pdo = tl_require_db();
        $campaigns = tl_progress_campaign_rows($user);
        $requested = tl_campaign_clean_ref($campaignRef);
        $selected = null;
        foreach ($campaigns as $row) {
            if ($requested !== '' && in_array($requested, [(string)$row['campaign_id'], (string)$row['campaign_public_id'], (string)$row['campaign_slug']], true)) {
                $selected = $row;
                break;
            }
        }
        if (!$selected) $selected = $campaigns[0] ?? null;
        if (!$selected) return ['found'=>false,'campaigns'=>[],'selected'=>null,'tasks'=>[],'activity'=>[],'rewards'=>[]];

        $taskExperience = tl_task_experience($user, (string)$selected['ref'], '');
        $tasks = $taskExperience['tasks'] ?? [];
        $taskTotal = count($tasks);
        $taskComplete = count(array_filter($tasks, static fn(array $task): bool => (string)($task['status_model']['key'] ?? '') === 'complete'));
        $pending = count(array_filter($tasks, static fn(array $task): bool => (string)($task['status_model']['key'] ?? '') === 'in_review'));
        $revisions = count(array_filter($tasks, static fn(array $task): bool => (string)($task['status_model']['key'] ?? '') === 'needs_revision'));
        $eligible = $taskTotal > 0 && $taskComplete === $taskTotal && (string)$selected['participant_status'] === 'active';

        $rewards = [];
        try {
            $rewardStmt = $pdo->prepare("SELECT re.*,rr.reward_label,rr.reward_type
                FROM training_reward_events re
                LEFT JOIN training_reward_rules rr ON rr.id=re.reward_rule_id
                WHERE re.participant_id=? AND re.campaign_id=? AND re.status<>'cancelled'
                ORDER BY re.created_at DESC,re.id DESC");
            $rewardStmt->execute([(int)$selected['participant_id'], (int)$selected['campaign_id']]);
            $rewards = $rewardStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $rewards = [];
        }

        $nextTask = null;
        foreach ($tasks as $task) {
            if (in_array((string)($task['status_model']['key'] ?? ''), ['ready','overdue','needs_revision'], true)) { $nextTask = $task; break; }
        }
        $nextAction = $nextTask
            ? ['label'=>(string)$nextTask['title'],'href'=>'/app/task-runner.php?campaign=' . rawurlencode((string)$selected['ref']) . '&task=' . rawurlencode((string)($nextTask['public_id'] ?: $nextTask['id']))]
            : ($eligible
                ? ['label'=>'Complete campaign','href'=>'#campaign-completion']
                : (!empty($selected['certificate_public_id'])
                    ? ['label'=>'View completion','href'=>'#campaign-completion']
                    : ['label'=>'Review training history','href'=>'/app/history.php']));

        return [
            'found'=>true,
            'campaigns'=>$campaigns,
            'selected'=>$selected,
            'tasks'=>$tasks,
            'task_total'=>$taskTotal,
            'task_complete'=>$taskComplete,
            'pending_review_count'=>$pending,
            'revision_count'=>$revisions,
            'progress_percent'=>$taskTotal > 0 ? min(100, (int)round(($taskComplete / $taskTotal) * 100)) : 0,
            'completion_eligible'=>$eligible,
            'completion_recorded'=>!empty($selected['certificate_public_id']),
            'activity'=>tl_progress_activity($pdo, (int)$selected['participant_id'], (int)$selected['campaign_id'], 30),
            'rewards'=>$rewards,
            'next_action'=>$nextAction,
            'current_streak_days'=>(int)$selected['current_streak_days'],
            'longest_streak_days'=>(int)$selected['longest_streak_days'],
        ];
    }
}

if (!function_exists('tl_progress_history')) {
    function tl_progress_history(array $user): array
    {
        $campaigns = tl_progress_campaign_rows($user);
        $completed = array_values(array_filter($campaigns, static fn(array $row): bool => !empty($row['is_completed'])));
        $active = array_values(array_filter($campaigns, static fn(array $row): bool => empty($row['is_completed']) && in_array((string)$row['participant_status'], ['active','paused'], true)));
        $invited = array_values(array_filter($campaigns, static fn(array $row): bool => (string)$row['participant_status'] === 'invited'));
        return [
            'campaigns'=>$campaigns,
            'completed'=>$completed,
            'active'=>$active,
            'invited'=>$invited,
            'totals'=>[
                'campaigns'=>count($campaigns),
                'completed'=>count($completed),
                'tasks'=>array_sum(array_map(static fn(array $row): int => (int)$row['completed_task_count'], $campaigns)),
                'rewards'=>array_sum(array_map(static fn(array $row): int => (int)$row['reward_count'], $campaigns)),
                'longest_streak'=>max(array_merge([0], array_map(static fn(array $row): int => (int)$row['longest_streak_days'], $campaigns))),
            ],
        ];
    }
}

if (!function_exists('tl_progress_admin_summary')) {
    function tl_progress_admin_summary(array $user): array
    {
        $pdo = tl_require_db();
        $role = tl_product_role($user);
        $ownerUserId = tl_campaign_user_id($user);
        $where = $role === 'admin' ? '1=1' : 'c.owner_user_id=?';
        $params = $role === 'admin' ? [] : [$ownerUserId];
        $sql = "SELECT c.id,c.public_id,c.slug,c.title,c.status,c.visibility,c.updated_at,
                    (SELECT COUNT(*) FROM training_campaign_tasks t WHERE t.campaign_id=c.id AND t.status='active') AS task_count,
                    (SELECT COUNT(*) FROM training_participants tp WHERE tp.campaign_id=c.id AND tp.status<>'removed') AS participant_count,
                    (SELECT COUNT(*) FROM training_participants tp WHERE tp.campaign_id=c.id AND tp.status='completed') AS completed_participant_count,
                    (SELECT COUNT(*) FROM training_proof_submissions p WHERE p.campaign_id=c.id AND p.status IN ('submitted','in_review')) AS pending_review_count,
                    (SELECT COUNT(*) FROM training_action_receipts ar WHERE ar.campaign_id=c.id AND ar.receipt_type='task_completed' AND ar.receipt_status='active') AS verified_action_count,
                    (SELECT COUNT(*) FROM training_reward_events re WHERE re.campaign_id=c.id AND re.status<>'cancelled') AS reward_count
                FROM training_campaigns c
                WHERE {$where}
                ORDER BY c.updated_at DESC,c.id DESC LIMIT 100";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $campaigns = [];
        }

        foreach ($campaigns as &$campaign) {
            $tasks = max(0, (int)$campaign['task_count']);
            $participants = max(0, (int)$campaign['participant_count']);
            $possible = $tasks * $participants;
            $campaign['average_progress_percent'] = $possible > 0 ? min(100, (int)round(((int)$campaign['verified_action_count'] / $possible) * 100)) : 0;
            $campaign['completion_rate_percent'] = $participants > 0 ? min(100, (int)round(((int)$campaign['completed_participant_count'] / $participants) * 100)) : 0;
            $campaign['ref'] = (string)($campaign['slug'] ?: $campaign['public_id']);
        }
        unset($campaign);

        return [
            'scope'=>$role === 'admin' ? 'platform' : 'owned_campaigns',
            'campaigns'=>$campaigns,
            'totals'=>[
                'campaigns'=>count($campaigns),
                'participants'=>array_sum(array_map(static fn(array $row): int => (int)$row['participant_count'], $campaigns)),
                'completed'=>array_sum(array_map(static fn(array $row): int => (int)$row['completed_participant_count'], $campaigns)),
                'pending_reviews'=>array_sum(array_map(static fn(array $row): int => (int)$row['pending_review_count'], $campaigns)),
                'verified_actions'=>array_sum(array_map(static fn(array $row): int => (int)$row['verified_action_count'], $campaigns)),
                'rewards'=>array_sum(array_map(static fn(array $row): int => (int)$row['reward_count'], $campaigns)),
            ],
        ];
    }
}

if (!function_exists('tl_progress_flash_set')) {
    function tl_progress_flash_set(string $type, string $message): void
    {
        tl_security_session_start();
        $_SESSION['_tl_progress_flash'] = ['type'=>in_array($type, ['success','error','info'], true) ? $type : 'info','message'=>mb_substr(trim($message), 0, 500)];
    }
}

if (!function_exists('tl_progress_flash_take')) {
    function tl_progress_flash_take(): ?array
    {
        tl_security_session_start();
        $flash = $_SESSION['_tl_progress_flash'] ?? null;
        unset($_SESSION['_tl_progress_flash']);
        return is_array($flash) ? $flash : null;
    }
}
