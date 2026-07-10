<?php
require_once __DIR__ . '/_action-bootstrap.php';
require_once __DIR__ . '/../../../includes/training-lab-campaign-enrollment.php';

tl_action_wrap_user(function (array $input, array $user): array {
    $campaignRef = tl_campaign_clean_ref((string)($input['campaign_id'] ?? $input['campaign'] ?? $input['slug'] ?? ''));
    if ($campaignRef === '') throw new TlHttpException('A campaign is required.', 422, 'campaign_required');
    return tl_campaign_secure_enroll($user, $campaignRef);
});
