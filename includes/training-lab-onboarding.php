<?php
/** Role-aware onboarding and guided empty-state read model. */
require_once __DIR__ . '/training-lab-participant-home.php';
require_once __DIR__ . '/training-lab-reward-management.php';

if (!function_exists('tl_onboarding_step')) {
    function tl_onboarding_step(string $key,string $label,string $detail,bool $complete,string $href):array
    {
        return ['key'=>$key,'label'=>$label,'detail'=>$detail,'complete'=>$complete,'href'=>$href,'state'=>$complete?'complete':'next'];
    }
}

if (!function_exists('tl_onboarding_participant')) {
    function tl_onboarding_participant(array $user):array
    {
        $home=tl_product_participant_home($user,'');
        $hasCampaign=(int)($home['totals']['campaigns']??0)>0;
        $hasTask=(int)($home['totals']['completed_tasks']??0)>0;
        $hasProof=((int)($home['totals']['pending_reviews']??0)+(int)($home['totals']['completed_tasks']??0))>0;
        $hasReward=(int)($home['totals']['rewards']??0)>0;
        $steps=[
            tl_onboarding_step('account','Confirm your account','Use the connected Microgifter identity shown in Account.',true,'/account.php'),
            tl_onboarding_step('campaign','Join a campaign','Browse published campaigns and choose the training path that fits your goal.',$hasCampaign,'/app/campaigns.php'),
            tl_onboarding_step('task','Complete your first task','Follow the task instructions and complete the required action.',$hasTask,$hasCampaign?'/app/task-runner.php':'/app/campaigns.php'),
            tl_onboarding_step('proof','Track verification','Proof-required work stays visible while a reviewer makes a decision.',$hasProof,$hasCampaign?'/app/progress-map.php':'/app/campaigns.php'),
            tl_onboarding_step('reward','Follow reward progress','Eligible rewards and delivery status appear in your Rewards area.',$hasReward,'/app/rewards.php'),
        ];
        $complete=count(array_filter($steps,static fn(array $s):bool=>$s['complete']));
        $next=null;foreach($steps as $step){if(!$step['complete']){$next=$step;break;}}$next=$next??end($steps);
        return ['role'=>'participant','steps'=>$steps,'complete_count'=>$complete,'total_count'=>count($steps),'percent'=>(int)round($complete/count($steps)*100),'next'=>$next,'first_name'=>(string)($home['first_name']??'there')];
    }
}

if (!function_exists('tl_onboarding_manager')) {
    function tl_onboarding_manager(array $user):array
    {
        $pdo=tl_require_db();$scope=tl_reward_management_scope($user);$where=$scope['platform']?'1=1':'owner_user_id=?';$params=$scope['platform']?[]:[(int)$scope['owner_user_id']];
        $count=static function(PDO $pdo,string $sql,array $params):int{$stmt=$pdo->prepare($sql);$stmt->execute($params);return (int)$stmt->fetchColumn();};
        $campaigns=$count($pdo,"SELECT COUNT(*) FROM training_campaigns WHERE {$where}",$params);
        $tasks=$count($pdo,"SELECT COUNT(*) FROM training_campaign_tasks t INNER JOIN training_campaigns c ON c.id=t.campaign_id WHERE ".($scope['platform']?'1=1':'c.owner_user_id=?')." AND t.status<>'archived'",$params);
        $rules=$count($pdo,"SELECT COUNT(*) FROM training_reward_rules rr INNER JOIN training_campaigns c ON c.id=rr.campaign_id WHERE ".($scope['platform']?'1=1':'c.owner_user_id=?')." AND rr.status<>'archived'",$params);
        $published=$count($pdo,"SELECT COUNT(*) FROM training_campaigns WHERE {$where} AND visibility='published' AND status IN ('scheduled','active')",$params);
        $participants=$count($pdo,"SELECT COUNT(*) FROM training_participants tp INNER JOIN training_campaigns c ON c.id=tp.campaign_id WHERE ".($scope['platform']?'1=1':'c.owner_user_id=?')." AND tp.status<>'removed'",$params);
        $steps=[
            tl_onboarding_step('campaign','Create a campaign','Define the audience, schedule, enrollment rules, and participant promise.',$campaigns>0,'/admin/campaigns.php'),
            tl_onboarding_step('tasks','Build the task path','Add clear tasks, proof requirements, timing, and unlock rules.',$tasks>0,'/admin/campaigns.php'),
            tl_onboarding_step('reward','Configure a reward rule','Connect a verified milestone to Training Lab reward eligibility.',$rules>0,'/admin/reward-rules.php'),
            tl_onboarding_step('publish','Publish the campaign','Review the participant experience, then publish or schedule enrollment.',$published>0,'/admin/campaigns.php'),
            tl_onboarding_step('participants','Enroll participants','Invite connected accounts or share the published campaign.',$participants>0,'/admin/cohort-manager.php'),
        ];
        $complete=count(array_filter($steps,static fn(array $s):bool=>$s['complete']));$next=null;foreach($steps as $step){if(!$step['complete']){$next=$step;break;}}$next=$next??end($steps);
        return ['role'=>'manager','steps'=>$steps,'complete_count'=>$complete,'total_count'=>count($steps),'percent'=>(int)round($complete/count($steps)*100),'next'=>$next,'counts'=>compact('campaigns','tasks','rules','published','participants')];
    }
}

if (!function_exists('tl_onboarding_empty_state')) {
    function tl_onboarding_empty_state(string $kicker,string $title,string $detail,string $href,string $action):void
    {
        echo '<section class="labs-guided-empty"><span class="labs-product-kicker">'.labs_e($kicker).'</span><h2>'.labs_e($title).'</h2><p>'.labs_e($detail).'</p><a class="labs-btn labs-btn-primary" href="'.htmlspecialchars(labs_url($href),ENT_QUOTES,'UTF-8').'">'.labs_e($action).'</a></section>';
    }
}
