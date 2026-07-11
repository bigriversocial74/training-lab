<?php
require_once __DIR__ . '/../includes/training-lab-campaign-builder.php';

try {
    $input = tl_security_request_data(false);
    $action = strtolower(trim((string)($input['builder_action'] ?? '')));
    if ($action === '') throw new TlHttpException('Select a campaign-builder action.', 422, 'campaign_builder_action_required');
    $user = tl_security_guard_write('update_campaign_plan', $input);
    tl_campaign_builder_actor($user);

    $result = match ($action) {
        'create_campaign' => tl_campaign_builder_create($user, $input),
        'save_campaign' => tl_campaign_builder_save_campaign($user, $input),
        'publish_campaign' => tl_campaign_builder_save_campaign($user, $input + ['status'=>'active','visibility'=>'published']),
        'duplicate_campaign' => tl_campaign_builder_duplicate($user, $input),
        'archive_campaign' => tl_campaign_builder_archive($user, $input),
        'add_task' => tl_campaign_builder_add_task($user, $input),
        'update_task' => tl_campaign_builder_update_task($user, $input),
        'reorder_tasks' => tl_campaign_builder_reorder_tasks($user, $input),
        'delete_task' => tl_campaign_builder_delete_task($user, $input),
        'attach_reward' => tl_campaign_builder_attach_reward($user, $input),
        default => throw new TlHttpException('Unsupported campaign-builder action.', 422, 'campaign_builder_action_invalid'),
    };

    tl_security_session_start();
    $_SESSION['tl_campaign_builder_flash'] = [
        'tone'=>'success',
        'message'=>match ($action) {
            'create_campaign' => 'Campaign draft created.',
            'save_campaign' => 'Campaign settings saved.',
            'publish_campaign' => 'Campaign published.',
            'duplicate_campaign' => 'Campaign duplicated as a private draft.',
            'archive_campaign' => 'Campaign archived. Participant history was retained.',
            'add_task' => 'Task added to the campaign path.',
            'update_task' => 'Task updated.',
            'reorder_tasks' => 'Task order saved.',
            'delete_task' => 'Task removed safely.',
            'attach_reward' => 'Reward rule attached.',
            default => 'Campaign Builder action completed.',
        },
    ];
    $campaignRef = (string)($result['campaign_ref'] ?? $result['public_id'] ?? $input['campaign'] ?? '');
    tl_product_redirect('/admin/campaigns.php' . ($campaignRef !== '' ? '?campaign=' . rawurlencode($campaignRef) : ''), 303);
} catch (Throwable $error) {
    tl_security_session_start();
    $_SESSION['tl_campaign_builder_flash'] = [
        'tone'=>'danger',
        'message'=>$error instanceof TlHttpException ? $error->getMessage() : 'The Campaign Builder could not complete this action.',
    ];
    $campaignRef = trim((string)($_POST['campaign'] ?? ''));
    tl_product_redirect('/admin/campaigns.php' . ($campaignRef !== '' ? '?campaign=' . rawurlencode($campaignRef) : ''), 303);
}
