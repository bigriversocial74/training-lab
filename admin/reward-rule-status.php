<?php
require_once __DIR__ . '/../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../includes/training-lab-reward-management.php';
$campaignRef='';
try {
    $raw=tl_security_request_data(false);
    $campaignRef=tl_campaign_clean_ref((string)($raw['campaign_id'] ?? ''));
    $user=tl_security_guard_write('evaluate_rewards',$raw);
    $result=tl_reward_management_transition($user,$raw);
    tl_security_session_start();
    $_SESSION['tl_reward_management_flash']=['tone'=>'success','message'=>'Reward rule '.str_replace('_',' ',(string)$result['status']).'.'];
} catch(Throwable $e) {
    [$payload]=tl_security_error_payload($e);
    tl_security_session_start();
    $_SESSION['tl_reward_management_flash']=['tone'=>'error','message'=>(string)$payload['error']];
}
$destination='/admin/reward-rules.php'.($campaignRef!==''?'?campaign='.rawurlencode($campaignRef):'');
tl_product_redirect($destination,303);
