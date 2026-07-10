<?php
require_once __DIR__ . '/training-lab-reward-management.php';

if (!function_exists('tl_reward_management_analytics_v2')) {
    function tl_reward_management_analytics_v2(array $user): array
    {
        $pdo=tl_require_db();
        $scope=tl_reward_management_scope($user);
        $where=$scope['platform']?'1=1':'c.owner_user_id=?';
        $params=$scope['platform']?[]:[(int)$scope['owner_user_id']];
        $sql="SELECT c.id,c.public_id,c.slug,c.title,c.status,
            (SELECT COUNT(*) FROM training_participants tp WHERE tp.campaign_id=c.id AND tp.status<>'removed') participants,
            (SELECT COUNT(*) FROM training_participants tp WHERE tp.campaign_id=c.id AND tp.status='completed') completed_participants,
            (SELECT COUNT(*) FROM training_proof_submissions p WHERE p.campaign_id=c.id) proofs,
            (SELECT COUNT(*) FROM training_reviews r INNER JOIN training_proof_submissions p ON p.id=r.proof_submission_id WHERE p.campaign_id=c.id) reviews,
            (SELECT COUNT(*) FROM training_reviews r INNER JOIN training_proof_submissions p ON p.id=r.proof_submission_id WHERE p.campaign_id=c.id AND r.decision='approved') approvals,
            (SELECT COUNT(*) FROM training_reward_events re WHERE re.campaign_id=c.id AND re.status<>'cancelled') rewards,
            (SELECT COUNT(*) FROM training_reward_events re WHERE re.campaign_id=c.id AND re.status IN ('issued','linked')) delivered_rewards,
            (SELECT COALESCE(SUM(re.value_cents),0) FROM training_reward_events re WHERE re.campaign_id=c.id AND re.status<>'cancelled') reward_value_cents,
            (SELECT AVG(TIMESTAMPDIFF(MINUTE,p.submitted_at,COALESCE(r.reviewed_at,r.created_at))) FROM training_reviews r INNER JOIN training_proof_submissions p ON p.id=r.proof_submission_id WHERE p.campaign_id=c.id) avg_review_minutes
            FROM training_campaigns c WHERE {$where} ORDER BY c.updated_at DESC,c.id DESC";
        $stmt=$pdo->prepare($sql);$stmt->execute($params);$campaigns=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
        $totals=['campaigns'=>count($campaigns),'participants'=>0,'completed'=>0,'proofs'=>0,'reviews'=>0,'approvals'=>0,'rewards'=>0,'delivered'=>0,'reward_value_cents'=>0];
        foreach($campaigns as &$row){foreach(['participants','completed_participants','proofs','reviews','approvals','rewards','delivered_rewards','reward_value_cents'] as $key)$row[$key]=(int)$row[$key];$row['completion_rate']=$row['participants']?min(100,(int)round($row['completed_participants']/$row['participants']*100)):0;$row['approval_rate']=$row['reviews']?min(100,(int)round($row['approvals']/$row['reviews']*100)):0;$row['delivery_rate']=$row['rewards']?min(100,(int)round($row['delivered_rewards']/$row['rewards']*100)):0;$row['avg_review_minutes']=$row['avg_review_minutes']===null?null:max(0,(int)round((float)$row['avg_review_minutes']));$totals['participants']+=$row['participants'];$totals['completed']+=$row['completed_participants'];$totals['proofs']+=$row['proofs'];$totals['reviews']+=$row['reviews'];$totals['approvals']+=$row['approvals'];$totals['rewards']+=$row['rewards'];$totals['delivered']+=$row['delivered_rewards'];$totals['reward_value_cents']+=$row['reward_value_cents'];}unset($row);
        $totals['completion_rate']=$totals['participants']?(int)round($totals['completed']/$totals['participants']*100):0;$totals['approval_rate']=$totals['reviews']?(int)round($totals['approvals']/$totals['reviews']*100):0;$totals['delivery_rate']=$totals['rewards']?(int)round($totals['delivered']/$totals['rewards']*100):0;
        return ['scope'=>$scope['platform']?'platform':'merchant','campaigns'=>$campaigns,'totals'=>$totals];
    }
}

if (!function_exists('tl_reward_management_fulfillment_v2')) {
    function tl_reward_management_fulfillment_v2(array $user,int $limit=100):array
    {
        $pdo=tl_require_db();$scope=tl_reward_management_scope($user);$where=$scope['platform']?'1=1':'c.owner_user_id=?';$params=$scope['platform']?[]:[(int)$scope['owner_user_id']];
        $handoff=tl_table_exists('training_reward_handoffs')?",(SELECT h.handoff_status FROM training_reward_handoffs h WHERE h.reward_event_id=re.id ORDER BY h.id DESC LIMIT 1) handoff_status,(SELECT h.attempt_count FROM training_reward_handoffs h WHERE h.reward_event_id=re.id ORDER BY h.id DESC LIMIT 1) attempt_count,(SELECT h.failure_message FROM training_reward_handoffs h WHERE h.reward_event_id=re.id ORDER BY h.id DESC LIMIT 1) failure_message":",'not_configured' handoff_status,0 attempt_count,NULL failure_message";
        $sql="SELECT re.id,re.public_id,re.status,re.value_cents,re.currency,re.created_at,re.updated_at,rr.reward_label,c.title campaign_title,tp.participant_label{$handoff} FROM training_reward_events re INNER JOIN training_campaigns c ON c.id=re.campaign_id LEFT JOIN training_reward_rules rr ON rr.id=re.reward_rule_id LEFT JOIN training_participants tp ON tp.id=re.participant_id WHERE {$where} AND re.status<>'cancelled' ORDER BY CASE re.status WHEN 'failed' THEN 0 WHEN 'claimed' THEN 1 WHEN 'eligible' THEN 2 ELSE 3 END,re.updated_at DESC,re.id DESC LIMIT ".max(1,min(250,$limit));
        $stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];$counts=['total'=>count($rows),'ready'=>0,'processing'=>0,'delivered'=>0,'failed'=>0,'blocked'=>0];
        foreach($rows as &$row){$h=strtolower((string)($row['handoff_status']??''));$bucket=match(true){$h==='delivered'||in_array((string)$row['status'],['issued','linked'],true)=>'delivered',$h==='processing'=>'processing',$h==='failed'||(string)$row['status']==='failed'=>'failed',in_array($h,['blocked','quarantined','cancelled'],true)=>'blocked',default=>'ready'};$counts[$bucket]++;$row['health_bucket']=$bucket;$row['display_value']=strtoupper((string)$row['currency']).' '.number_format(((int)$row['value_cents'])/100,2);$row['confirmation']=substr(hash('sha256',(string)$row['public_id']),0,12);$row['last_error']=$row['failure_message']?mb_substr(strip_tags((string)$row['failure_message']),0,220):null;unset($row['id'],$row['failure_message']);}unset($row);
        return ['scope'=>$scope['platform']?'platform':'merchant','counts'=>$counts,'rows'=>$rows,'advanced_available'=>$scope['role']==='admin'];
    }
}
