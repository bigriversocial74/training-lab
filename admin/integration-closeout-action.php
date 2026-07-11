<?php
require_once __DIR__ . '/../includes/training-lab-production-integration-closeout.php';

try {
    $input = tl_security_request_data(false);
    $user = tl_security_guard_write('manage_integration_closeout', $input);
    tl_closeout_admin($user);
    $action = strtolower(trim((string)($input['closeout_action'] ?? '')));
    $runRef = trim((string)($input['run_id'] ?? $input['run'] ?? ''));
    $campaignRef = trim((string)($input['campaign_id'] ?? $input['campaign'] ?? ''));
    $result = match ($action) {
        'record' => tl_closeout_record($user, $campaignRef),
        'approve' => tl_closeout_approve($user, $runRef, (string)($input['notes'] ?? '')),
        'reject' => tl_closeout_reject($user, $runRef, (string)($input['notes'] ?? '')),
        default => throw new TlHttpException('Select a supported integration-closeout action.', 422, 'integration_closeout_action_invalid'),
    };
    tl_security_session_start();
    $_SESSION['tl_integration_closeout_flash'] = [
        'tone'=>(string)($result['status'] ?? '') === 'rejected' ? 'warning' : 'success',
        'message'=>'Production integration closeout action completed: ' . (string)($result['status'] ?? $action) . '.',
    ];
    $targetRun = (string)($result['public_id'] ?? $runRef);
    $query = $targetRun !== '' ? '?run=' . rawurlencode($targetRun) : ($campaignRef !== '' ? '?campaign=' . rawurlencode($campaignRef) : '');
    tl_product_redirect('/admin/integration-closeout.php' . $query, 303);
} catch (Throwable $error) {
    tl_security_session_start();
    $_SESSION['tl_integration_closeout_flash'] = [
        'tone'=>'danger',
        'message'=>$error instanceof TlHttpException ? $error->getMessage() : 'The production integration closeout action could not be completed.',
    ];
    $runRef = trim((string)($_POST['run_id'] ?? $_POST['run'] ?? ''));
    $campaignRef = trim((string)($_POST['campaign_id'] ?? $_POST['campaign'] ?? ''));
    $query = $runRef !== '' ? '?run=' . rawurlencode($runRef) : ($campaignRef !== '' ? '?campaign=' . rawurlencode($campaignRef) : '');
    tl_product_redirect('/admin/integration-closeout.php' . $query, 303);
}
