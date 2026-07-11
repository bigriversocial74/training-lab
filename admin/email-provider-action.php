<?php
require_once __DIR__ . '/../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../includes/training-lab-resend-email-provider.php';
require_once __DIR__ . '/../includes/training-lab-pilot-communications.php';
$destination = '/admin/email-provider.php';
try {
    $raw = tl_security_request_data(false);
    $action = tl_action_enum($raw['provider_action'] ?? '', ['send_test'], '');
    if ($action === '') throw new TlHttpException('Select a valid provider action.', 422, 'email_provider_action_invalid');
    $user = tl_security_guard_write('send_notification_provider_test', $raw);
    if (tl_product_role($user) !== 'admin') throw new TlHttpException('Administrator provider test access is required.', 403, 'email_provider_test_forbidden');
    $result = tl_resend_send_test($user);
    $message = 'Provider test accepted. Recipient confirmation ' . (string)$result['recipient_confirmation'] . '; message confirmation ' . (string)$result['provider_message_confirmation'] . '.';
    tl_security_session_start();
    $_SESSION['tl_email_provider_flash'] = ['tone'=>'success','message'=>$message];
} catch (Throwable $error) {
    if ($error instanceof TlNotificationProviderFailure) {
        $message = tl_resend_safe_message($error->getMessage());
    } else {
        [$payload] = tl_security_error_payload($error);
        $message = (string)$payload['error'];
    }
    tl_security_session_start();
    $_SESSION['tl_email_provider_flash'] = ['tone'=>'error','message'=>$message];
}
tl_product_redirect($destination, 303);
