<?php
/** Owner-scoped engagement and communications reporting for controlled pilots. */
require_once __DIR__ . '/training-lab-pilot-communications-actions.php';

if (!function_exists('tl_notifications_pilot_report')) {
    function tl_notifications_pilot_report(array $user): array
    {
        $pdo = tl_require_db();
        $scope = tl_notifications_scope($user);
        if (!tl_notifications_tables_ready()) return ['campaigns'=>[],'totals'=>[]];
        $where = $scope['platform'] ? '1=1' : 'c.owner_user_id=?';
        $params = $scope['platform'] ? [] : [$scope['owner_user_id']];
        $sql = "SELECT c.id,c.public_id,c.slug,c.title,c.status,
                    (SELECT COUNT(*) FROM training_participants tp WHERE tp.campaign_id=c.id AND tp.status<>'removed') participants,
                    (SELECT COUNT(*) FROM training_participants tp WHERE tp.campaign_id=c.id AND tp.status='active') active_participants,
                    (SELECT COUNT(*) FROM training_participants tp WHERE tp.campaign_id=c.id AND tp.status='completed') completed_participants,
                    (SELECT COUNT(DISTINCT ar.participant_id) FROM training_action_receipts ar WHERE ar.campaign_id=c.id AND ar.receipt_status='active' AND ar.receipt_type='task_completed') engaged_participants,
                    (SELECT COUNT(*) FROM training_proof_submissions p WHERE p.campaign_id=c.id) proofs,
                    (SELECT COUNT(*) FROM training_reviews r JOIN training_proof_submissions p ON p.id=r.proof_submission_id WHERE p.campaign_id=c.id) reviews,
                    (SELECT COUNT(*) FROM training_reward_events re WHERE re.campaign_id=c.id AND re.status<>'cancelled') rewards,
                    (SELECT COUNT(*) FROM training_notification_outbox o WHERE o.campaign_id=c.id) notifications,
                    (SELECT COUNT(*) FROM training_notification_outbox o WHERE o.campaign_id=c.id AND o.outbox_status='delivered') delivered,
                    (SELECT COUNT(*) FROM training_notification_outbox o WHERE o.campaign_id=c.id AND o.outbox_status IN ('failed','blocked')) incidents
                FROM training_campaigns c WHERE {$where} ORDER BY c.updated_at DESC,c.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $totals = ['participants'=>0,'active_participants'=>0,'completed_participants'=>0,'engaged_participants'=>0,'proofs'=>0,'reviews'=>0,'rewards'=>0,'notifications'=>0,'delivered'=>0,'incidents'=>0];
        foreach ($rows as &$row) {
            foreach (array_keys($totals) as $key) {
                $row[$key] = (int)$row[$key];
                $totals[$key] += $row[$key];
            }
            $row['engagement_rate'] = $row['participants'] > 0 ? (int)round($row['engaged_participants'] / $row['participants'] * 100) : 0;
            $row['completion_rate'] = $row['participants'] > 0 ? (int)round($row['completed_participants'] / $row['participants'] * 100) : 0;
            $row['delivery_rate'] = $row['notifications'] > 0 ? (int)round($row['delivered'] / $row['notifications'] * 100) : 0;
        }
        unset($row);
        $totals['engagement_rate'] = $totals['participants'] > 0 ? (int)round($totals['engaged_participants'] / $totals['participants'] * 100) : 0;
        $totals['completion_rate'] = $totals['participants'] > 0 ? (int)round($totals['completed_participants'] / $totals['participants'] * 100) : 0;
        $totals['delivery_rate'] = $totals['notifications'] > 0 ? (int)round($totals['delivered'] / $totals['notifications'] * 100) : 0;
        return ['campaigns'=>$rows,'totals'=>$totals];
    }
}
