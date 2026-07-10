<?php
require_once __DIR__ . '/../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../includes/training-lab-completion.php';

$campaignRef = '';
try {
    $raw = tl_security_request_data(false);
    $campaignRef = tl_campaign_clean_ref((string)($raw['campaign_id'] ?? $raw['campaign'] ?? ''));
    $user = tl_security_guard_write('complete_task', $raw);
    $result = tl_progress_secure_complete($user, $campaignRef);
    if (!empty($result['already_completed'])) {
        tl_progress_flash_set('info', 'This campaign is already complete.');
    } else {
        tl_progress_flash_set('success', 'Campaign completed. Your completion record and reward eligibility are ready.');
    }
} catch (Throwable $e) {
    [$payload] = tl_security_error_payload($e);
    tl_progress_flash_set('error', (string)$payload['error']);
}

$destination = '/app/progress-map.php' . ($campaignRef !== '' ? '?campaign=' . rawurlencode($campaignRef) : '');
tl_product_redirect($destination, 303);
