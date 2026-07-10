<?php
require_once __DIR__ . '/../../../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../../../includes/training-lab-campaign-enrollment.php';

try {
    $raw = tl_security_request_data(false);
    $campaignRef = tl_campaign_clean_ref((string)($raw['campaign_id'] ?? $raw['campaign'] ?? $raw['slug'] ?? ''));
    if ($campaignRef === '') throw new TlHttpException('A campaign is required.', 422, 'campaign_required');
    $user = tl_security_guard_write('join_campaign', $raw);
    $result = tl_campaign_secure_enroll($user, $campaignRef);
    tl_security_json_response(['ok'=>true,'data'=>$result]);
} catch (Throwable $e) {
    tl_security_json_exception($e);
}
