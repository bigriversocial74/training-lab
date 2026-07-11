<?php
require_once __DIR__ . '/../../includes/training-lab-route-bootstrap.php';
require_once __DIR__ . '/../../includes/training-lab-pilot-communications-reporting.php';
try {
    tl_security_require_method('GET');
    $user = tl_security_current_user();
    if (!$user) throw new TlHttpException('Authentication is required.', 401, 'authentication_required');
    $user['role'] = tl_security_trusted_role($user);
    if (!tl_product_role_allows(tl_product_role($user), 'manager')) throw new TlHttpException('Merchant communications access is required.', 403, 'communications_forbidden');
    $dashboard = tl_notifications_dashboard($user);
    $report = !empty($dashboard['schema_ready']) ? tl_notifications_pilot_report($user) : ['campaigns'=>[],'totals'=>[]];
    tl_security_json_response([
        'ok'=>true,
        'schema_ready'=>(bool)($dashboard['schema_ready'] ?? false),
        'totals'=>$dashboard['totals'] ?? [],
        'campaigns'=>$dashboard['campaigns'] ?? [],
        'engagement'=>$report,
        'outbox'=>!empty($dashboard['schema_ready']) ? tl_notifications_outbox_rows($user, 100) : [],
        'provider'=>$dashboard['provider'] ?? tl_notifications_provider_state(),
        'privacy'=>[
            'recipient_addresses_exposed'=>false,
            'raw_provider_responses_exposed'=>false,
            'credentials_exposed'=>false,
            'account_link_ids_exposed'=>false,
        ],
    ]);
} catch (Throwable $error) {
    tl_security_json_exception($error);
}
