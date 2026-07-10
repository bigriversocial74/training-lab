<?php
require_once __DIR__ . '/../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../includes/training-lab-campaign-enrollment.php';

$campaignRef = '';
try {
    $raw = tl_security_request_data(false);
    $campaignRef = tl_campaign_clean_ref((string)($raw['campaign_id'] ?? $raw['campaign'] ?? ''));
    if ($campaignRef === '') throw new TlHttpException('A campaign is required.', 422, 'campaign_required');

    $user = tl_security_guard_write('join_campaign', $raw);
    $result = tl_campaign_secure_enroll($user, $campaignRef);
    if (!empty($result['invitation_accepted'])) {
        tl_campaign_flash_set('success', 'Invitation accepted. Your first task is ready.');
    } elseif (!empty($result['already_joined'])) {
        tl_campaign_flash_set('info', 'You are already enrolled in this campaign.');
    } else {
        tl_campaign_flash_set('success', 'You joined the campaign. Your first task is ready.');
    }
} catch (Throwable $e) {
    [$payload] = tl_security_error_payload($e);
    tl_campaign_flash_set('error', (string)$payload['error']);
}

$destination = '/app/campaign-detail.php' . ($campaignRef !== '' ? '?id=' . rawurlencode($campaignRef) : '');
tl_product_redirect($destination, 303);
