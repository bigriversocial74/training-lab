<?php
require_once __DIR__ . '/../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../includes/training-lab-task-submission.php';

$campaignRef = '';
$taskRef = '';
try {
    $raw = tl_security_request_data(false);
    $campaignRef = tl_campaign_clean_ref((string)($raw['campaign_id'] ?? $raw['campaign'] ?? ''));
    $taskRef = tl_task_clean_ref((string)($raw['task_id'] ?? $raw['task'] ?? ''));
    $user = tl_security_guard_write('complete_task', $raw);
    $result = tl_task_secure_submit($user, $raw);
    if (!empty($result['already_complete'])) {
        tl_task_flash_set('info', 'This task is already complete.');
    } elseif (!empty($result['is_revision'])) {
        tl_task_flash_set('success', 'Your updated proof was submitted for review.');
    } elseif ((string)($result['status'] ?? '') === 'submitted') {
        tl_task_flash_set('success', 'Your proof was submitted for review.');
    } else {
        tl_task_flash_set('success', 'Task completed. Your progress has been updated.');
    }
} catch (Throwable $e) {
    [$payload] = tl_security_error_payload($e);
    tl_task_flash_set('error', (string)$payload['error']);
}

$query = [];
if ($campaignRef !== '') $query['campaign'] = $campaignRef;
if ($taskRef !== '') $query['task'] = $taskRef;
$destination = '/app/task-runner.php' . ($query ? '?' . http_build_query($query) : '');
tl_product_redirect($destination, 303);
