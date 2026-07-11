<?php
/**
 * Strictly scoped event synchronization.
 *
 * Every candidate query contains an explicit WHERE clause so merchant and
 * campaign filters can never attach to a JOIN condition.
 */
require_once __DIR__ . '/training-lab-pilot-communications.php';

if (!function_exists('tl_notifications_scoped_candidate_queries')) {
    function tl_notifications_scoped_candidate_queries(bool $includeReminders = false): array
    {
        $queries = [
            ['event'=>'participant_invited','source'=>'participant','sql'=>"SELECT tp.id source_id,tp.id participant_id,tp.user_id,c.id campaign_id,c.owner_user_id,c.title campaign_title,c.slug campaign_slug,tp.participant_label,'' task_title,'' reward_label,'' review_status FROM training_participants tp JOIN training_campaigns c ON c.id=tp.campaign_id WHERE tp.status IN ('invited','active')"],
            ['event'=>'proof_submitted','source'=>'proof','sql'=>"SELECT p.id source_id,p.participant_id,p.submitted_by_user_id user_id,c.id campaign_id,c.owner_user_id,c.title campaign_title,c.slug campaign_slug,COALESCE(tp.participant_label,'Training participant') participant_label,COALESCE(t.title,'Training task') task_title,'' reward_label,'' review_status FROM training_proof_submissions p JOIN training_campaigns c ON c.id=p.campaign_id LEFT JOIN training_participants tp ON tp.id=p.participant_id LEFT JOIN training_campaign_tasks t ON t.id=p.task_id WHERE p.status IN ('submitted','in_review','approved','rejected')"],
            ['event'=>'review_result','source'=>'review','sql'=>"SELECT r.id source_id,p.participant_id,p.submitted_by_user_id user_id,c.id campaign_id,c.owner_user_id,c.title campaign_title,c.slug campaign_slug,COALESCE(tp.participant_label,'Training participant') participant_label,COALESCE(t.title,'Training task') task_title,'' reward_label,r.decision review_status FROM training_reviews r JOIN training_proof_submissions p ON p.id=r.proof_submission_id JOIN training_campaigns c ON c.id=p.campaign_id LEFT JOIN training_participants tp ON tp.id=p.participant_id LEFT JOIN training_campaign_tasks t ON t.id=p.task_id WHERE 1=1"],
            ['event'=>'reward_earned','source'=>'reward_event','sql'=>"SELECT re.id source_id,re.participant_id,re.user_id,c.id campaign_id,c.owner_user_id,c.title campaign_title,c.slug campaign_slug,COALESCE(tp.participant_label,'Training participant') participant_label,'' task_title,COALESCE(rr.reward_label,'Training reward') reward_label,'' review_status FROM training_reward_events re JOIN training_campaigns c ON c.id=re.campaign_id LEFT JOIN training_participants tp ON tp.id=re.participant_id LEFT JOIN training_reward_rules rr ON rr.id=re.reward_rule_id WHERE re.status IN ('eligible','offered','claimable','issued','linked_to_microgifter')"],
            ['event'=>'reward_delivery','source'=>'reward_handoff','sql'=>"SELECT h.id source_id,re.participant_id,re.user_id,c.id campaign_id,c.owner_user_id,c.title campaign_title,c.slug campaign_slug,COALESCE(tp.participant_label,'Training participant') participant_label,'' task_title,COALESCE(rr.reward_label,'Training reward') reward_label,h.handoff_status review_status FROM training_reward_handoffs h JOIN training_reward_events re ON re.id=h.reward_event_id JOIN training_campaigns c ON c.id=re.campaign_id LEFT JOIN training_participants tp ON tp.id=re.participant_id LEFT JOIN training_reward_rules rr ON rr.id=re.reward_rule_id WHERE h.handoff_status IN ('delivered','failed')"],
        ];
        if ($includeReminders) {
            $queries[] = ['event'=>'task_reminder','source'=>'task_reminder','sql'=>"SELECT t.id source_id,tp.id participant_id,tp.user_id,c.id campaign_id,c.owner_user_id,c.title campaign_title,c.slug campaign_slug,COALESCE(tp.participant_label,'Training participant') participant_label,t.title task_title,'' reward_label,'' review_status FROM training_participants tp JOIN training_campaigns c ON c.id=tp.campaign_id JOIN training_campaign_tasks t ON t.campaign_id=c.id AND t.status='active' WHERE tp.status='active' AND NOT EXISTS (SELECT 1 FROM training_action_receipts ar WHERE ar.participant_id=tp.id AND ar.receipt_status='active' AND ar.receipt_type='task_completed' AND JSON_UNQUOTE(JSON_EXTRACT(ar.metadata_json,'$.task_id'))=CAST(t.id AS CHAR))"];
        }
        return $queries;
    }
}

if (!function_exists('tl_notifications_sync_events_scoped')) {
    function tl_notifications_sync_events_scoped(array $user, string $campaignRef = '', int $limit = 250, bool $includeReminders = false): array
    {
        $pdo = tl_require_db();
        if (!tl_notifications_tables_ready()) throw new TlHttpException('Import the Pilot Operations + Communications migration first.', 503, 'notification_schema_missing');
        $scope = tl_notifications_scope($user);
        $campaignId = 0;
        if ($campaignRef !== '') $campaignId = (int)tl_notifications_campaign($pdo, $user, $campaignRef)['id'];
        $counts = ['queued'=>0,'blocked'=>0,'suppressed'=>0,'duplicate'=>0];
        $remaining = max(1, min(1000, $limit));
        foreach (tl_notifications_scoped_candidate_queries($includeReminders) as $definition) {
            if ($remaining < 1) break;
            $where = $scope['platform'] ? '' : ' AND c.owner_user_id=' . (int)$scope['owner_user_id'];
            if ($campaignId > 0) $where .= ' AND c.id=' . $campaignId;
            $sql = $definition['sql'] . $where . ' ORDER BY source_id DESC LIMIT ' . $remaining;
            try {
                $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $error) {
                error_log('[TrainingLab][notifications-sync] ' . get_class($error) . ': ' . $error->getMessage());
                $rows = [];
            }
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
