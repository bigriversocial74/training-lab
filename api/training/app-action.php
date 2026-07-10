<?php
require_once __DIR__ . '/../../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
require_once __DIR__ . '/../../includes/training-lab-campaign-enrollment.php';
require_once __DIR__ . '/../../includes/training-lab-stage894-reconciliation-bootstrap.php';
require_once __DIR__ . '/../../includes/training-lab-stage893-legacy-action-guard.php';

try {
    $raw = tl_security_request_data(false);
    $action = preg_replace('/[^a-z0-9_\-]/i', '', (string)($raw['training_action'] ?? $raw['action'] ?? ''));
    if ($action === '') throw new TlHttpException('Training action is required.', 422, 'action_required');
    $user = tl_security_guard_write($action, $raw);
    $data = tl_security_apply_actor($raw, $user);
    if ($action === 'join_campaign') {
        $campaignRef = tl_campaign_clean_ref((string)($data['campaign_id'] ?? $data['campaign'] ?? $data['slug'] ?? ''));
        $result = [
            'action'=>'join_campaign',
            'label'=>'Join Training Lab campaign',
            'result'=>tl_campaign_secure_enroll($user, $campaignRef),
        ];
    } elseif (in_array($action, ['claim_training_reward','retry_microgifter_reward_issue'], true)) {
        $result = [
            'action'=>$action,
            'label'=>$action === 'claim_training_reward' ? 'Claim Training Lab reward' : 'Retry Microgifter reward issue',
            'result'=>tl_stage893_claim_or_retry_reward_guarded($data),
        ];
    } else {
        $result = tl_training_handle_app_action($data);
    }
    tl_security_json_response(['ok'=>true,'data'=>$result,'flow'=>tl_app_flow_summary(),'reward_lookup_client'=>tl_stage894_summary()]);
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
