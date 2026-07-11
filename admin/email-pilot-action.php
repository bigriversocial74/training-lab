<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/training-lab-limited-email-pilot.php';

try {
    $input = $_POST;
    $user = tl_security_guard_write('manage_limited_email_pilot', $input);
    tl_limited_email_pilot_admin($user);
    $action = strtolower(trim((string)($input['pilot_action'] ?? '')));
    $runRef = trim((string)($input['run_id'] ?? $input['run'] ?? ''));
    $result = match ($action) {
        'create' => tl_limited_email_pilot_create($user, $input),
        'send_canary' => tl_limited_email_pilot_send_canary($user, $runRef),
        'approve' => tl_limited_email_pilot_approve($user, $runRef),
        'start' => tl_limited_email_pilot_start($user, $runRef),
        'process' => tl_limited_email_pilot_process($user, $runRef, max(1, min(3, (int)($input['limit'] ?? 1)))),
        'evaluate' => tl_limited_email_pilot_evaluate($user, $runRef),
        'pause' => tl_limited_email_pilot_pause($user, $runRef, (string)($input['reason'] ?? '')),
        'graduate' => tl_limited_email_pilot_graduate($user, $runRef, (string)($input['notes'] ?? '')),
        'reject' => tl_limited_email_pilot_reject($user, $runRef, (string)($input['notes'] ?? '')),
        default => throw new TlHttpException('Select a supported limited pilot action.', 422, 'limited_email_pilot_action_invalid'),
    };
    tl_security_session_start();
    $_SESSION['tl_limited_email_pilot_flash'] = [
        'tone'=>!empty($result['ok']) || !in_array((string)($result['status'] ?? ''), ['failed','paused'], true) ? 'success' : 'warning',
        'message'=>'Limited email pilot action completed: ' . (string)($result['status'] ?? $action) . '.',
    ];
    $targetRun = (string)($result['public_id'] ?? $result['run_public_id'] ?? $runRef);
    tl_product_redirect('/admin/email-pilot.php' . ($targetRun !== '' ? '?run=' . rawurlencode($targetRun) : ''), 303);
} catch (Throwable $error) {
    tl_security_session_start();
    $_SESSION['tl_limited_email_pilot_flash'] = ['tone'=>'danger','message'=>$error instanceof TlHttpException ? $error->getMessage() : 'The limited email pilot action could not be completed.'];
    $targetRun = trim((string)($_POST['run_id'] ?? $_POST['run'] ?? ''));
    tl_product_redirect('/admin/email-pilot.php' . ($targetRun !== '' ? '?run=' . rawurlencode($targetRun) : ''), 303);
}
