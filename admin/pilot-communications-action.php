<?php
require_once __DIR__ . '/../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../includes/training-lab-pilot-communications-actions.php';
$destination = '/admin/pilot-communications.php';
try {
    $raw = tl_security_request_data(false);
    $action = tl_action_enum($raw['communications_action'] ?? '', [
        'sync_events','save_pilot_control','retry_notification','cancel_notification',
        'save_notification_template','notification_template_action',
        'process_notification_batch','add_notification_suppression','release_notification_suppression',
    ], '');
    if ($action === '') throw new TlHttpException('Select a valid communications action.', 422, 'communications_action_invalid');
    $user = tl_security_guard_write($action, $raw);
    $message = 'Communications action completed.';
    if ($action === 'sync_events') {
        $result = tl_notifications_sync_events($user, (string)($raw['campaign_id'] ?? ''), max(1,(int)($raw['limit'] ?? 250)), !empty($raw['include_reminders']));
        $message = sprintf('Event synchronization completed: %d queued, %d blocked, %d suppressed, %d already present.', (int)$result['queued'], (int)$result['blocked'], (int)$result['suppressed'], (int)$result['duplicate']);
    } elseif ($action === 'save_pilot_control') {
        $result = tl_notifications_save_pilot_control($user, $raw);
        $message = 'Pilot communications control saved.';
    } elseif ($action === 'retry_notification') {
        $result = tl_notifications_retry($user, (string)($raw['notification_id'] ?? ''));
        $message = 'Notification returned to the queue.';
    } elseif ($action === 'cancel_notification') {
        $result = tl_notifications_cancel($user, (string)($raw['notification_id'] ?? ''));
        $message = 'Notification cancelled.';
    } elseif ($action === 'save_notification_template') {
        $result = tl_notifications_save_template($user, $raw);
        $message = 'Merchant notification template saved.';
        $destination = '/admin/notification-templates.php?template=' . rawurlencode((string)$result['template_key']);
    } elseif ($action === 'notification_template_action') {
        $result = tl_notifications_template_action($user, $raw);
        $message = 'Merchant notification template ' . (string)$result['status'] . '.';
        $destination = '/admin/notification-templates.php';
    } elseif ($action === 'process_notification_batch') {
        if (tl_product_role($user) !== 'admin') throw new TlHttpException('Administrator notification worker access is required.', 403, 'notification_worker_forbidden');
        $result = tl_notifications_process_batch(max(1,min(100,(int)($raw['limit'] ?? 10))));
        $message = sprintf('Notification worker processed %d item(s): %d delivered, %d failed, %d suppressed.', (int)$result['processed'], (int)$result['delivered'], (int)$result['failed'], (int)$result['suppressed']);
        $destination = '/admin/notification-incidents.php';
    } elseif ($action === 'add_notification_suppression') {
        $result = tl_notifications_add_suppression($user, $raw);
        $message = 'Recipient suppression added for confirmation ' . (string)$result['email_confirmation'] . '.';
        $destination = '/admin/notification-incidents.php';
    } else {
        $result = tl_notifications_release_suppression($user, (string)($raw['suppression_id'] ?? ''));
        $message = 'Recipient suppression released.';
        $destination = '/admin/notification-incidents.php';
    }
    tl_security_session_start();
    $_SESSION['tl_pilot_communications_flash'] = ['tone'=>'success','message'=>$message];
} catch (Throwable $error) {
    [$payload] = tl_security_error_payload($error);
    tl_security_session_start();
    $_SESSION['tl_pilot_communications_flash'] = ['tone'=>'error','message'=>(string)$payload['error']];
}
tl_product_redirect($destination, 303);
