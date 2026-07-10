<?php
require_once __DIR__ . '/../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../includes/training-lab-app-service.php';
require_once __DIR__ . '/../includes/training-lab-campaign-experience.php';

$campaignRef = '';
try {
    $raw = tl_security_request_data(false);
    $campaignRef = tl_campaign_clean_ref((string)($raw['campaign_id'] ?? $raw['campaign'] ?? ''));
    if ($campaignRef === '') throw new TlHttpException('A campaign is required.', 422, 'campaign_required');

    $user = tl_security_guard_write('join_campaign', $raw);
    $detail = tl_campaign_detail($user, $campaignRef);
    if (!$detail) throw new TlHttpException('This campaign is not available.', 404, 'campaign_not_found');

    if (!empty($detail['state']['enrolled'])) {
        tl_campaign_flash_set('info', 'You are already enrolled in this campaign.');
    } elseif (empty($detail['state']['can_join'])) {
        throw new TlHttpException((string)$detail['state']['reason'], 409, 'campaign_not_joinable');
    } else {
        $input = tl_security_apply_actor([
            'training_action' => 'join_campaign',
            'campaign_id' => (string)$detail['ref'],
        ], $user);
        tl_training_handle_app_action($input);
        tl_campaign_flash_set('success', 'You joined the campaign. Your first task is ready.');
    }
} catch (Throwable $e) {
    [$payload] = tl_security_error_payload($e);
    tl_campaign_flash_set('error', (string)$payload['error']);
}

$destination = '/app/campaign-detail.php' . ($campaignRef !== '' ? '?id=' . rawurlencode($campaignRef) : '');
tl_product_redirect($destination, 303);
