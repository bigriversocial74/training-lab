<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$jsonMode = in_array('--json', $argv, true);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$exists = static fn(string $path): bool => is_file($root . '/' . $path);
$contains = static fn(string $path, string $needle): bool => str_contains($read($path), $needle);

$sections = [
    'security_auth' => [
        'label'=>'Security & authentication',
        'checks'=>[
            'central_security_layer'=>$exists('includes/training-lab-security.php'),
            'secure_session_cookie'=>$contains('includes/training-lab-security.php', "'httponly'=>true") && $contains('includes/training-lab-security.php', "'samesite'=>'Lax'"),
            'csrf_guard'=>$contains('includes/training-lab-security.php', 'tl_security_verify_csrf'),
            'origin_guard'=>$contains('includes/training-lab-security.php', 'tl_security_validate_origin'),
            'trusted_roles'=>$contains('includes/training-lab-security.php', 'tl_security_trusted_role'),
            'safe_demo_login'=>$contains('includes/training-lab-security.php', 'tl_security_demo_login_allowed'),
            'security_headers'=>$contains('includes/training-lab-security.php', 'Content-Security-Policy'),
            'safe_logout'=>$contains('logout.php', 'tl_security_guard_auth_action') && $contains('logout.php', 'REQUEST_METHOD'),
            'worker_cli_only'=>$contains('bin/reward-handoff-worker.php', "PHP_SAPI !== 'cli'") && ($contains('bin/.htaccess', 'Require all denied') || $contains('bin/.htaccess', 'Deny from all')),
            'signed_lookup_tls'=>$contains('includes/training-lab-stage894-signed-reward-lookup-client.php', 'CURLOPT_SSL_VERIFYPEER=>true') && $contains('includes/training-lab-stage894-signed-reward-lookup-client.php', 'CURLOPT_SSL_VERIFYHOST=>2'),
            'signed_lookup_redirect_block'=>$contains('includes/training-lab-stage894-signed-reward-lookup-client.php', 'CURLOPT_FOLLOWLOCATION=>false') && $contains('includes/training-lab-stage894-signed-reward-lookup-client.php', 'CURLOPT_MAXREDIRS=>0'),
            'signed_lookup_secret_exclusion'=>$contains('includes/training-lab-stage894-signed-reward-lookup-client.php', "'shared_secret_not_returned'=>true") && !$contains('includes/training-lab-stage894-signed-reward-lookup-client.php', "'secret'=>(string)"),
            'stage895_acceptance_tls'=>$contains('includes/training-lab-stage895-integration-acceptance.php', 'CURLOPT_SSL_VERIFYPEER=>true') && $contains('includes/training-lab-stage895-integration-acceptance.php', 'CURLOPT_SSL_VERIFYHOST=>2'),
            'stage895_acceptance_csrf'=>$contains('includes/training-lab-stage895-integration-acceptance.php', 'tl_security_csrf_field') && $contains('admin/integration-acceptance.php', 'tl_security_guard_write'),
            'advanced_operations_admin_only'=>$contains('admin/reward-operations.php', "'required_role'=>'admin'"),
        ],
    ],
    'api_runtime' => [
        'label'=>'API & runtime behavior',
        'checks'=>[
            'route_bootstrap'=>$exists('includes/training-lab-route-bootstrap.php'),
            'post_only_app_actions'=>$contains('api/training/app-action.php', 'tl_security_request_data(false)'),
            'protected_action_bootstrap'=>$contains('api/training/actions/_action-bootstrap.php', 'tl_route_write_input'),
            'protected_review_api'=>$contains('api/training/proof-review-workflow.php', "tl_security_guard_write('stage885_review_proof'"),
            'protected_outbox_api'=>$contains('api/training/reward-handoff-outbox.php', 'tl_security_guard_write') && $contains('api/training/reward-handoff-outbox.php', 'tl_auth_role_allowed'),
            'protected_recovery_api'=>$contains('api/training/reward-handoff-operations.php', 'tl_security_guard_write') && $contains('api/training/reward-handoff-operations.php', 'tl_auth_role_allowed'),
            'production_guarded_processor_routes'=>$contains('api/training/reward-handoff-outbox.php', 'tl_stage893_process_handoff_production_guarded') && $contains('api/training/reward-handoff-operations.php', 'tl_stage893_process_batch_production_guarded'),
            'protected_reconciliation_api'=>$exists('api/training/reward-delivery-reconciliation.php') && $contains('api/training/reward-delivery-reconciliation.php', 'tl_security_guard_write') && $contains('api/training/reward-delivery-reconciliation.php', 'tl_auth_role_allowed'),
            'protected_worker_status_api'=>$exists('api/training/reward-handoff-worker-status.php') && $contains('api/training/reward-handoff-worker-status.php', "if (\$method !== 'GET')") && $contains('api/training/reward-handoff-worker-status.php', 'tl_auth_role_allowed'),
            'legacy_claim_retry_intercept'=>$contains('api/training/app-action.php', 'tl_stage893_claim_or_retry_reward_guarded') && !$contains('api/training/app-action.php', 'tl_mg_claim_training_reward($data)'),
            'proof_review_guarded_sync'=>$contains('api/training/proof-review-workflow.php', 'tl_stage893_sync_outbox_guarded'),
            'stage894_shared_bootstrap'=>$exists('includes/training-lab-stage894-reconciliation-bootstrap.php') && $contains('includes/training-lab-stage894-reconciliation-bootstrap.php', 'training-lab-stage894-signed-reward-lookup-client.php') && $contains('includes/training-lab-stage894-reconciliation-bootstrap.php', 'training-lab-stage893-processing-wrapper.php'),
            'stage894_active_routes'=>$contains('api/training/reward-delivery-reconciliation.php', 'training-lab-stage894-reconciliation-bootstrap.php') && $contains('api/training/reward-handoff-operations.php', 'training-lab-stage894-reconciliation-bootstrap.php') && $contains('api/training/reward-handoff-outbox.php', 'training-lab-stage894-reconciliation-bootstrap.php') && $contains('api/training/app-action.php', 'training-lab-stage894-reconciliation-bootstrap.php'),
            'stage894_sanitized_status'=>$contains('api/training/reward-delivery-reconciliation.php', 'tl_stage894_summary') && $contains('api/training/reward-handoff-operations.php', 'tl_stage894_summary') && $contains('api/training/reward-handoff-outbox.php', 'tl_stage894_summary'),
            'protected_stage895_api'=>$exists('api/training/integration-acceptance.php') && $contains('api/training/integration-acceptance.php', 'tl_auth_role_allowed') && $contains('api/training/integration-acceptance.php', 'tl_security_guard_write'),
            'stage895_acceptance_gate'=>$contains('includes/training-lab-stage895-integration-acceptance.php', 'stage895_not_ready') && $contains('includes/training-lab-stage895-integration-acceptance.php', "'all_closed'=>"),
            'safe_json_errors'=>$contains('includes/training-lab-security.php', 'tl_security_json_exception'),
            'request_ids'=>$contains('includes/training-lab-security.php', 'X-Request-ID'),
            'payload_limit'=>$contains('includes/training-lab-security.php', 'payload_too_large'),
            'rate_limit'=>$contains('includes/training-lab-security.php', 'tl_security_rate_limit'),
        ],
    ],
    'data_integrity' => [
        'label'=>'Database & data integrity',
        'checks'=>[
            'prepared_statements'=>$contains('includes/training-lab-actions.php', '$pdo->prepare'),
            'transactional_writes'=>$contains('includes/training-lab-actions.php', 'beginTransaction'),
            'row_locks'=>$contains('includes/training-lab-actions.php', 'FOR UPDATE'),
            'campaign_scoped_tasks'=>$contains('includes/training-lab-actions.php', 'campaign_id = ? AND (id = ? OR public_id = ?)'),
            'idempotent_receipts'=>$contains('includes/training-lab-actions.php', "receipt_status = 'active'") && $contains('includes/training-lab-actions.php', 'reused'),
            'idempotent_rewards'=>$contains('includes/training-lab-actions.php', "status <> 'cancelled'"),
            'crypto_receipt_hash'=>$contains('includes/training-lab-actions.php', 'random_bytes(32)'),
            'bounded_validated_input'=>$contains('includes/training-lab-actions.php', 'tl_action_clean') && $contains('includes/training-lab-actions.php', 'tl_action_enum'),
            'durable_reward_outbox'=>$exists('database/stage890_reward_handoff_outbox_v1.sql') && $exists('includes/training-lab-stage890-reward-handoff-outbox.php'),
            'outbox_idempotency_and_locking'=>$contains('database/stage890_reward_handoff_outbox_v1.sql', 'uq_training_reward_handoffs_idempotency') && $contains('includes/training-lab-stage890-reward-handoff-outbox.php', 'FOR UPDATE'),
            'stale_worker_recovery'=>$contains('includes/training-lab-stage891-reward-handoff-recovery.php', 'worker_lease_expired_recovered') && $contains('includes/training-lab-stage891-reward-handoff-recovery.php', 'FOR UPDATE'),
            'operator_requeue_audit'=>$contains('includes/training-lab-stage891-reward-handoff-recovery.php', 'stage891_handoff_requeued') && $contains('includes/training-lab-stage891-reward-handoff-recovery.php', 'stage891_recovery_history'),
            'owned_worker_finalization'=>$contains('includes/training-lab-stage891-owned-processor.php', "handoff_status='processing' AND locked_by=?") && $contains('includes/training-lab-stage891-owned-processor.php', 'adapter_result_unapplied'),
            'scheduled_worker_overlap_lock'=>$contains('includes/training-lab-stage892-scheduled-worker.php', 'LOCK_EX | LOCK_NB') && $contains('includes/training-lab-stage892-scheduled-worker.php', 'worker_overlap_detected'),
            'scheduled_worker_bounded_processing'=>$contains('includes/training-lab-stage893-worker-wrapper.php', 'microtime(true) >= $deadline') && $contains('includes/training-lab-stage893-worker-wrapper.php', 'tl_stage893_due_handoff_ids'),
            'external_delivery_quarantine'=>$contains('includes/training-lab-stage893-external-delivery-reconciliation.php', 'external_delivery_confirmation_required') && $contains('includes/training-lab-stage893-external-delivery-reconciliation.php', "handoff_status='blocked'"),
            'reconciliation_two_phase_locking'=>substr_count($read('includes/training-lab-stage893-external-delivery-reconciliation.php'), 'FOR UPDATE') >= 4 && $contains('includes/training-lab-stage893-external-delivery-reconciliation.php', 'state_changed_retry_later'),
            'active_lease_reconciliation_guard'=>$contains('includes/training-lab-stage893-processing-wrapper.php', 'active_worker_lease') && $contains('includes/training-lab-stage893-processing-wrapper.php', "h.handoff_status<>'processing' OR h.locked_at IS NULL OR h.locked_at<=?"),
            'completed_delivery_candidate_guard'=>$contains('includes/training-lab-stage893-processing-wrapper.php', "NOT (h.handoff_status='delivered' AND re.status IN ('issued','linked'))"),
            'quarantine_excluded_from_retry'=>$contains('includes/training-lab-stage893-processing-wrapper.php', "failure_code<>'external_delivery_confirmation_required'") && $contains('includes/training-lab-stage893-processing-wrapper.php', "COALESCE(metadata_json,'') NOT LIKE"),
            'quarantine_excluded_from_sync'=>$contains('includes/training-lab-stage893-processing-wrapper.php', "COALESCE(h.failure_code,'') <> 'external_delivery_confirmation_required'") && $contains('includes/training-lab-stage893-processing-wrapper.php', 'tl_stage893_enqueue_reward_event_guarded'),
            'quarantine_requeue_block'=>$contains('includes/training-lab-stage893-processing-wrapper.php', 'external_delivery_reconciliation_required') && $contains('api/training/reward-handoff-operations.php', 'tl_stage893_requeue_handoff_guarded'),
            'read_only_lookup_contract'=>$contains('includes/training-lab-stage893-external-delivery-reconciliation.php', "'read_only' => true") && !$contains('includes/training-lab-stage893-external-delivery-reconciliation.php', 'function tl_stage890_call_adapter'),
            'legacy_direct_adapter_bypass'=>$contains('includes/training-lab-stage893-legacy-action-guard.php', 'legacy_direct_adapter_bypassed') && !$contains('api/training/app-action.php', 'tl_mg_stage160_retry_microgifter_issue($data)'),
            'signed_lookup_identity_contract'=>$contains('includes/training-lab-stage894-signed-reward-lookup-client.php', 'microgifter_user_required') && $contains('includes/training-lab-stage894-signed-reward-lookup-client.php', 'read_only'),
            'signed_lookup_nonce_and_hmac'=>$contains('includes/training-lab-stage894-signed-reward-lookup-client.php', 'random_bytes(24)') && $contains('includes/training-lab-stage894-signed-reward-lookup-client.php', "hash_hmac('sha256'"),
            'stage895_replay_and_tamper'=>$contains('includes/training-lab-stage895-integration-acceptance.php', "'request_replayed'") && $contains('includes/training-lab-stage895-integration-acceptance.php', "['tamper_signature'=>true]"),
            'stage895_evidence_sanitization'=>$contains('includes/training-lab-stage895-integration-acceptance.php', 'secrets_signatures_nonces_and_raw_payloads_excluded') && $contains('includes/training-lab-stage895-integration-acceptance.php', 'reward_reference_fingerprint'),
            'stage895_no_mutation_adapter'=>!$contains('includes/training-lab-stage895-integration-acceptance.php', 'tl_stage890_call_adapter(') && !$contains('includes/training-lab-stage895-integration-acceptance.php', 'tl_mg_stage160_retry_microgifter_issue('),
        ],
    ],
    'architecture_maintainability' => [
        'label'=>'Architecture & maintainability',
        'checks'=>[
            'shared_security'=>$exists('includes/training-lab-security.php'),
            'shared_route_layer'=>$exists('includes/training-lab-route-bootstrap.php'),
            'shared_layout'=>$exists('includes/labs-layout.php'),
            'shared_public_template'=>$exists('includes/training-lab-public-template.php'),
            'single_action_service'=>$exists('includes/training-lab-actions.php'),
            'isolated_handoff_service'=>$exists('includes/training-lab-stage890-reward-handoff-outbox.php'),
            'isolated_recovery_service'=>$exists('includes/training-lab-stage891-reward-handoff-recovery.php'),
            'isolated_owned_processor'=>$exists('includes/training-lab-stage891-owned-processor.php'),
            'isolated_scheduled_worker'=>$exists('includes/training-lab-stage892-scheduled-worker.php') && $exists('bin/reward-handoff-worker.php'),
            'isolated_reconciliation_service'=>$exists('includes/training-lab-stage893-external-delivery-reconciliation.php') && $exists('includes/training-lab-stage893-processing-wrapper.php') && $exists('includes/training-lab-stage893-worker-wrapper.php') && $exists('includes/training-lab-stage893-legacy-action-guard.php'),
            'isolated_signed_lookup_client'=>$exists('includes/training-lab-stage894-signed-reward-lookup-client.php') && $exists('includes/training-lab-stage894-reconciliation-bootstrap.php'),
            'isolated_stage895_acceptance'=>$exists('includes/training-lab-stage895-integration-acceptance.php') && $exists('admin/integration-acceptance.php') && $exists('api/training/integration-acceptance.php'),
            'merchant_operations_separation'=>$exists('admin/reward-operations.php') && !$contains('admin/reward-bridge.php', 'tl_stage890_render_admin_panel'),
            'quality_script'=>$exists('scripts/quality-audit.php'),
            'audit_documentation'=>$exists('docs/CODE-AUDIT-2026-07-09.md'),
            'no_new_runtime_dependency'=>!$exists('composer.lock') || $exists('composer.json'),
        ],
    ],
    'frontend_accessibility' => [
        'label'=>'Frontend & accessibility',
        'checks'=>[
            'skip_links'=>$contains('includes/labs-layout.php', 'labs-skip-link') && $contains('includes/training-lab-public-template.php', 'tl-skip-link'),
            'main_landmark'=>$contains('includes/labs-layout.php', 'id="main-content"') && $contains('includes/training-lab-public-template.php', 'id="main-content"'),
            'aria_current'=>$contains('includes/labs-layout.php', 'aria-current="page"') && $contains('includes/training-lab-public-template.php', 'aria-current="page"'),
            'csrf_meta'=>$contains('includes/labs-layout.php', 'csrf-token') && $contains('includes/training-lab-public-template.php', 'csrf-token'),
            'form_csrf_injection'=>$contains('assets/js/labs.js', 'securePostForms') && $contains('assets/js/public-template.js', "input.name = '_csrf'"),
            'keyboard_escape'=>$contains('assets/js/labs.js', "event.key === 'Escape'") && $contains('assets/js/public-template.js', "event.key === 'Escape'"),
            'focus_styles'=>$contains('assets/css/security-accessibility.css', ':focus-visible'),
            'reduced_motion'=>$contains('assets/css/security-accessibility.css', 'prefers-reduced-motion'),
        ],
    ],
    'testing_ci' => [
        'label'=>'Testing & CI',
        'checks'=>[
            'recursive_syntax'=>$exists('run-full-syntax-check.sh'),
            'security_runtime_test'=>$exists('tests/security-runtime-test.php'),
            'data_contract_test'=>$exists('tests/data-integrity-contract-test.php'),
            'route_contract_test'=>$exists('tests/http-route-contract-test.php'),
            'stage889_session_contract'=>$exists('tests/stage889-shared-session-hardening-contract-test.php') && $contains('run-quality-gate.sh', 'stage889-shared-session-hardening-contract-test.php'),
            'stage890_outbox_contract'=>$exists('tests/stage890-reward-handoff-outbox-contract-test.php') && $contains('run-quality-gate.sh', 'stage890-reward-handoff-outbox-contract-test.php'),
            'stage891_recovery_contract'=>$exists('tests/stage891-reward-handoff-recovery-contract-test.php') && $contains('run-quality-gate.sh', 'stage891-reward-handoff-recovery-contract-test.php'),
            'stage892_worker_contract'=>$exists('tests/stage892-scheduled-worker-contract-test.php') && $contains('run-quality-gate.sh', 'stage892-scheduled-worker-contract-test.php'),
            'stage893_reconciliation_contract'=>$exists('tests/stage893-external-delivery-reconciliation-contract-test.php') && $contains('run-quality-gate.sh', 'stage893-external-delivery-reconciliation-contract-test.php'),
            'stage894_signed_lookup_contract'=>$exists('tests/stage894-signed-reward-lookup-client-contract-test.php') && $contains('run-quality-gate.sh', 'stage894-signed-reward-lookup-client-contract-test.php'),
            'stage895_acceptance_contract'=>$exists('tests/stage895-signed-integration-acceptance-contract-test.php') && $contains('run-quality-gate.sh', 'stage895-signed-integration-acceptance-contract-test.php'),
            'quality_gate_script'=>$exists('run-quality-gate.sh'),
            'quality_workflow'=>$exists('.github/workflows/quality-gate.yml'),
            'php_82_matrix'=>$contains('.github/workflows/quality-gate.yml', "'8.2'"),
            'php_83_matrix'=>$contains('.github/workflows/quality-gate.yml', "'8.3'"),
        ],
    ],
    'deployment_operations' => [
        'label'=>'Deployment & operations',
        'checks'=>[
            'config_examples'=>$exists('config-example.php') && $exists('labs/config-example.php'),
            'config_export_protection'=>$contains('.gitattributes', '/config.php export-ignore') && $contains('.gitattributes', '/labs/config.php export-ignore'),
            'archive_ignored'=>$contains('.gitignore', '*.zip'),
            'db_health_route'=>$exists('admin/db-health.php') && $exists('api/training/db-status.php'),
            'deployment_acceptance'=>$exists('admin/deployment-acceptance.php') && $exists('api/training/deployment-acceptance.php'),
            'live_smoke'=>$exists('admin/live-smoke.php') && $exists('api/training/live-smoke.php'),
            'outbox_migration_and_config'=>$exists('database/stage890_reward_handoff_outbox_v1.sql') && $contains('labs/config-example.php', 'reward_handoff_processing_enabled'),
            'lease_recovery_config'=>$contains('config-example.php', 'reward_handoff_lease_seconds') && $contains('labs/config-example.php', 'reward_handoff_recovery_batch_size'),
            'operations_acceptance_route'=>$exists('api/training/reward-handoff-operations.php') && $contains('admin/reward-operations.php', 'tl_stage891_render_admin_panel'),
            'terminal_failure_queue'=>$exists('includes/training-lab-stage891-terminal-failure-panel.php') && $contains('admin/reward-operations.php', 'tl_stage891_render_terminal_failure_panel'),
            'worker_config_and_status'=>$contains('config-example.php', 'reward_handoff_worker_enabled') && $contains('labs/config-example.php', 'reward_handoff_worker_lock_file') && $exists('api/training/reward-handoff-worker-status.php'),
            'worker_cron_entrypoint'=>$exists('bin/reward-handoff-worker.php') && $contains('admin/reward-operations.php', 'tl_stage892_render_admin_panel'),
            'reconciliation_config_and_status'=>$contains('config-example.php', 'reward_delivery_reconciliation_enabled') && $contains('labs/config-example.php', 'reward_delivery_reconciliation_min_age_seconds') && $exists('api/training/reward-delivery-reconciliation.php'),
            'reconciliation_operator_panel'=>$contains('admin/reward-operations.php', 'tl_stage893_render_admin_panel_guarded') && $contains('admin/action-result.php', 'stage893_reconcile_delivery_batch'),
            'production_processing_reconciliation_gate'=>$contains('includes/training-lab-stage893-legacy-action-guard.php', 'tl_stage893_require_reconciliation_processing_gate') && $contains('bin/reward-handoff-worker.php', 'tl_stage893_run_scheduled_worker'),
            'signed_lookup_config'=>$contains('config-example.php', 'microgifter_reward_lookup_enabled') && $contains('labs/config-example.php', 'microgifter_reward_lookup_allowed_hosts'),
            'signed_lookup_operator_panel'=>$contains('admin/reward-operations.php', 'tl_stage894_render_admin_panel') && $contains('api/training/reward-delivery-reconciliation.php', 'tl_stage894_summary'),
            'signed_lookup_worker_order'=>$contains('bin/reward-handoff-worker.php', 'training-lab-stage894-signed-reward-lookup-client.php') && strpos($read('bin/reward-handoff-worker.php'), 'training-lab-stage894-signed-reward-lookup-client.php') < strpos($read('bin/reward-handoff-worker.php'), 'training-lab-stage893-worker-wrapper.php'),
            'signed_lookup_documentation'=>$exists('docs/STAGE-894-SIGNED-REWARD-LOOKUP-CLIENT-V1.md'),
            'stage895_disabled_config'=>$contains('config-example.php', "'stage895_live_acceptance_enabled' => false") && $contains('labs/config-example.php', "'stage895_live_acceptance_enabled' => false"),
            'stage895_operator_routes'=>$exists('admin/integration-acceptance.php') && $exists('api/training/integration-acceptance.php') && $contains('admin/reward-operations.php', 'tl_stage895_render_reward_bridge_panel'),
            'merchant_fulfillment_read_only'=>$contains('admin/reward-bridge.php', 'This merchant page is read-only.') && !$contains('admin/reward-bridge.php', 'tl_stage893_render_admin_panel_guarded'),
            'stage895_documentation'=>$exists('docs/STAGE-895-SIGNED-INTEGRATION-ACCEPTANCE-V1.md'),
            'safe_error_logging'=>$contains('includes/training-lab-security.php', 'error_log'),
            'audit_report'=>$exists('docs/CODE-AUDIT-2026-07-09.md'),
        ],
    ],
];

$allPerfect = true;
foreach ($sections as &$section) {
    $passed = count(array_filter($section['checks']));
    $total = count($section['checks']);
    $section['passed'] = $passed;
    $section['total'] = $total;
    $section['score'] = round(($passed / max(1, $total)) * 10, 1);
    $section['status'] = $passed === $total ? 'pass' : 'needs_work';
    if ($passed !== $total) $allPerfect = false;
}
unset($section);

$result = [
    'audit'=>'Training Lab production-readiness quality gate',
    'rubric_version'=>'2026-07-11.10',
    'all_sections_10_of_10'=>$allPerfect,
    'sections'=>$sections,
];

if ($jsonMode) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    echo "Training Lab quality audit\n";
    echo str_repeat('=', 72) . "\n";
    foreach ($sections as $section) {
        printf("%-36s %4.1f/10  (%d/%d)\n", $section['label'], $section['score'], $section['passed'], $section['total']);
        foreach ($section['checks'] as $check => $passed) {
            echo '  ' . ($passed ? '[PASS] ' : '[FAIL] ') . str_replace('_', ' ', $check) . "\n";
        }
    }
    echo str_repeat('-', 72) . "\n";
    echo $allPerfect ? "All audited sections score 10/10.\n" : "Quality gate failed: one or more sections are below 10/10.\n";
}

exit($allPerfect ? 0 : 1);
