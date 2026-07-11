<?php
/**
 * Production Integration Closeout v1.
 *
 * Reads durable evidence from the established Training Lab, Resend, and signed
 * Microgifter integration layers. Recording and administrator decisions write
 * only to the Section 20 closeout evidence tables. No operation sends email,
 * runs a worker, issues a reward, changes a wallet, processes a payment, or
 * enables a production gate.
 */
require_once __DIR__ . '/training-lab-product-acceptance.php';
require_once __DIR__ . '/training-lab-campaign-builder-runtime.php';
require_once __DIR__ . '/training-lab-limited-email-pilot.php';
require_once __DIR__ . '/training-lab-stage895-integration-acceptance.php';

if (!function_exists('tl_closeout_value')) {
    function tl_closeout_value(string $environmentName, string $configKey, string $default = ''): string
    {
        $value = getenv($environmentName);
        if ($value !== false && $value !== '') return trim((string)$value);
        $config = function_exists('tl_security_config') ? tl_security_config() : [];
        return trim((string)($config[$configKey] ?? $default));
    }
}

if (!function_exists('tl_closeout_bool')) {
    function tl_closeout_bool(string $environmentName, string $configKey, bool $default = false): bool
    {
        $value = tl_closeout_value($environmentName, $configKey, $default ? 'true' : 'false');
        return function_exists('tl_security_bool') ? tl_security_bool($value, $default) : (bool)filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('tl_closeout_config')) {
    function tl_closeout_config(): array
    {
        return [
            'enabled'=>tl_closeout_bool('TL_PRODUCTION_INTEGRATION_CLOSEOUT_ENABLED', 'production_integration_closeout_enabled', false),
            'approval_enabled'=>tl_closeout_bool('TL_PRODUCTION_INTEGRATION_CLOSEOUT_APPROVAL_ENABLED', 'production_integration_closeout_approval_enabled', false),
        ];
    }
}

if (!function_exists('tl_closeout_required_tables')) {
    function tl_closeout_required_tables(): array
    {
        return [
            'training_campaigns','training_campaign_tasks','training_participants','training_proof_submissions','training_reviews',
            'training_action_receipts','training_reward_rules','training_reward_events','training_events',
            'training_account_links','training_auth_nonces','training_reward_handoffs',
            'training_notification_outbox','training_notification_provider_states','training_notification_provider_events',
            'training_notification_pilot_runs','training_notification_pilot_members','training_notification_pilot_checks','training_notification_pilot_events',
            'training_integration_closeout_runs','training_integration_closeout_checks','training_integration_closeout_events',
        ];
    }
}

if (!function_exists('tl_closeout_tables_ready')) {
    function tl_closeout_tables_ready(): bool
    {
        foreach (tl_closeout_required_tables() as $table) if (!tl_table_exists($table)) return false;
        return true;
    }
}

if (!function_exists('tl_closeout_admin')) {
    function tl_closeout_admin(array $user): int
    {
        $role = function_exists('tl_product_role') ? tl_product_role($user) : strtolower((string)($user['role'] ?? ''));
        if ($role !== 'admin') throw new TlHttpException('Administrator integration-closeout access is required.', 403, 'integration_closeout_admin_required');
        return function_exists('tl_security_numeric_user_id') ? tl_security_numeric_user_id($user) : max(1, (int)($user['numeric_user_id'] ?? $user['id'] ?? 1));
    }
}

if (!function_exists('tl_closeout_clean')) {
    function tl_closeout_clean($value, int $maximum = 255): string
    {
        $value = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string)$value) ?? '');
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        return mb_substr($value, 0, $maximum);
    }
}

if (!function_exists('tl_closeout_scalar')) {
    function tl_closeout_scalar(PDO $pdo, string $sql, array $params = [], $fallback = 0)
    {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $value = $stmt->fetchColumn();
            return $value === false ? $fallback : $value;
        } catch (Throwable $e) {
            return $fallback;
        }
    }
}

if (!function_exists('tl_closeout_row')) {
    function tl_closeout_row(PDO $pdo, string $sql, array $params = []): ?array
    {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('tl_closeout_json')) {
    function tl_closeout_json($value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('tl_closeout_check')) {
    function tl_closeout_check(string $category, string $key, string $label, bool $passed, string $observed, string $required, string $detail = '', string $evidence = ''): array
    {
        return [
            'category'=>preg_replace('/[^a-z0-9_-]/i', '', $category) ?: 'system',
            'key'=>preg_replace('/[^a-z0-9_-]/i', '', $key) ?: 'check',
            'label'=>tl_closeout_clean($label, 191),
            'passed'=>$passed,
            'status'=>$passed ? 'passed' : 'failed',
            'observed'=>tl_closeout_clean($observed, 191),
            'required'=>tl_closeout_clean($required, 191),
            'detail'=>tl_closeout_clean($detail, 700),
            'evidence_hash'=>$evidence !== '' ? hash('sha256', $evidence) : null,
        ];
    }
}

if (!function_exists('tl_closeout_campaigns')) {
    function tl_closeout_campaigns(): array
    {
        if (!tl_table_exists('training_campaigns')) return [];
        $pdo = tl_require_db();
        try {
            return $pdo->query("SELECT id,public_id,slug,title,status,visibility,updated_at FROM training_campaigns ORDER BY CASE status WHEN 'completed' THEN 0 WHEN 'active' THEN 1 WHEN 'paused' THEN 2 ELSE 3 END,updated_at DESC,id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('tl_closeout_campaign')) {
    function tl_closeout_campaign(PDO $pdo, string $campaignRef = ''): ?array
    {
        $campaignRef = preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($campaignRef)) ?: '';
        if ($campaignRef !== '') {
            $row = tl_closeout_row($pdo, 'SELECT * FROM training_campaigns WHERE id=? OR public_id=? OR slug=? LIMIT 1', [ctype_digit($campaignRef) ? (int)$campaignRef : 0,$campaignRef,$campaignRef]);
            if ($row) return $row;
        }
        $row = tl_closeout_row($pdo, "SELECT c.* FROM training_campaigns c WHERE EXISTS (SELECT 1 FROM training_action_receipts ar WHERE ar.campaign_id=c.id AND ar.receipt_type='sequence_completed' AND ar.receipt_status='active') ORDER BY c.updated_at DESC,c.id DESC LIMIT 1");
        return $row ?: tl_closeout_row($pdo, "SELECT * FROM training_campaigns ORDER BY CASE status WHEN 'completed' THEN 0 WHEN 'active' THEN 1 ELSE 2 END,updated_at DESC,id DESC LIMIT 1");
    }
}

if (!function_exists('tl_closeout_account')) {
    function tl_closeout_account(PDO $pdo, ?array $campaign): ?array
    {
        if ($campaign) {
            $row = tl_closeout_row($pdo, "SELECT al.* FROM training_account_links al JOIN training_participants tp ON tp.user_id=al.training_user_id WHERE tp.campaign_id=? AND al.link_status='active' ORDER BY CASE tp.status WHEN 'completed' THEN 0 WHEN 'active' THEN 1 ELSE 2 END,al.last_authenticated_at DESC,al.id DESC LIMIT 1", [(int)$campaign['id']]);
            if ($row) return $row;
        }
        return tl_closeout_row($pdo, "SELECT * FROM training_account_links WHERE link_status='active' ORDER BY last_authenticated_at DESC,id DESC LIMIT 1");
    }
}

if (!function_exists('tl_closeout_email_pilot')) {
    function tl_closeout_email_pilot(PDO $pdo, ?array $campaign): ?array
    {
        if ($campaign) {
            $row = tl_closeout_row($pdo, "SELECT * FROM training_notification_pilot_runs WHERE campaign_id=? ORDER BY CASE run_status WHEN 'graduated' THEN 0 WHEN 'running' THEN 1 ELSE 2 END,created_at DESC,id DESC LIMIT 1", [(int)$campaign['id']]);
            if ($row) return $row;
        }
        return tl_closeout_row($pdo, "SELECT * FROM training_notification_pilot_runs ORDER BY CASE run_status WHEN 'graduated' THEN 0 WHEN 'running' THEN 1 ELSE 2 END,created_at DESC,id DESC LIMIT 1");
    }
}

if (!function_exists('tl_closeout_reward_handoff')) {
    function tl_closeout_reward_handoff(PDO $pdo, ?array $campaign): ?array
    {
        if ($campaign) {
            $row = tl_closeout_row($pdo, "SELECT h.* FROM training_reward_handoffs h JOIN training_reward_events re ON re.id=h.reward_event_id WHERE re.campaign_id=? ORDER BY CASE h.handoff_status WHEN 'delivered' THEN 0 ELSE 1 END,h.updated_at DESC,h.id DESC LIMIT 1", [(int)$campaign['id']]);
            if ($row) return $row;
        }
        return tl_closeout_row($pdo, "SELECT * FROM training_reward_handoffs ORDER BY CASE handoff_status WHEN 'delivered' THEN 0 ELSE 1 END,updated_at DESC,id DESC LIMIT 1");
    }
}

if (!function_exists('tl_closeout_stage895_evidence')) {
    function tl_closeout_stage895_evidence(PDO $pdo): ?array
    {
        $rows = [];
        try {
            $rows = $pdo->query("SELECT id,metadata_json,created_at FROM training_events WHERE event_type='stage895_signed_integration_acceptance' ORDER BY created_at DESC,id DESC LIMIT 25")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return null;
        }
        foreach ($rows as $row) {
            $metadata = tl_closeout_json($row['metadata_json'] ?? null);
            if ((string)($metadata['status'] ?? '') === 'passed' && (int)($metadata['score'] ?? 0) === 100 && !empty($metadata['ready_for_reconciliation'])) {
                $row['metadata'] = $metadata;
                return $row;
            }
        }
        return $rows[0] ?? null;
    }
}

if (!function_exists('tl_closeout_report')) {
    function tl_closeout_report(string $campaignRef = ''): array
    {
        $config = tl_closeout_config();
        $checks = [];
        $db = function_exists('tl_db') ? tl_db() : null;
        $dbReady = $db instanceof PDO;
        $checks[] = tl_closeout_check('deployment','database_connected','Training Lab database connected',$dbReady,$dbReady?'connected':'unavailable','connected','The closeout report reads the deployed Training Lab database.');
        $schemaReady = $dbReady && tl_closeout_tables_ready();
        $checks[] = tl_closeout_check('deployment','closeout_schema','Section 20 evidence schema installed',$schemaReady,$schemaReady?'present':'missing','present','Import database/production_integration_closeout_v1.sql.');
        $productAcceptance = function_exists('tl_product_acceptance_report') ? tl_product_acceptance_report() : ['ready'=>false,'score'=>0];
        $checks[] = tl_closeout_check('deployment','product_acceptance','Canonical product acceptance passes',!empty($productAcceptance['ready']),(string)($productAcceptance['score'] ?? 0) . '%','100%','All application, schema, acceptance, and authority-boundary assets must be present.');
        $securityConfig = function_exists('tl_security_config') ? tl_security_config() : [];
        $debugDisabled = empty($securityConfig['debug']) && !tl_security_debug_enabled();
        $checks[] = tl_closeout_check('deployment','debug_disabled','Production debug output disabled',$debugDisabled,$debugDisabled?'disabled':'enabled','disabled');
        $demoDisabled = !tl_security_demo_login_allowed();
        $checks[] = tl_closeout_check('deployment','demo_login_disabled','Standalone demo login disabled',$demoDisabled,$demoDisabled?'disabled':'enabled','disabled');

        $campaign = $account = $pilot = $handoff = $stage895 = null;
        $participantDelivered = $badDeliveries = 0;
        if ($dbReady) {
            $campaign = tl_closeout_campaign($db, $campaignRef);
            $account = tl_closeout_account($db, $campaign);
            $pilot = tl_closeout_email_pilot($db, $campaign);
            $handoff = tl_closeout_reward_handoff($db, $campaign);
            $stage895 = tl_closeout_stage895_evidence($db);
        }

        $accountActive = is_array($account) && (string)($account['link_status'] ?? '') === 'active';
        $checks[] = tl_closeout_check('account','account_link_active','Active signed Microgifter account link exists',$accountActive,$accountActive?'active':'missing','active','The participant identity must originate from the signed Microgifter handoff.',(string)($account['public_id'] ?? ''));
        $recentAuth = $accountActive && trim((string)($account['last_authenticated_at'] ?? '')) !== '';
        $checks[] = tl_closeout_check('account','account_authenticated','Linked account has authenticated successfully',$recentAuth,$recentAuth?(string)$account['last_authenticated_at']:'missing','timestamp','A successful signed launch must update the persistent account link.',(string)($account['microgifter_user_id'] ?? ''));
        $nonceConsumed = false;
        if ($dbReady && $accountActive) {
            $nonceConsumed = (int)tl_closeout_scalar($db, "SELECT COUNT(*) FROM training_auth_nonces WHERE nonce_status='consumed' AND microgifter_user_id=?", [(string)$account['microgifter_user_id']], 0) > 0;
        }
        $checks[] = tl_closeout_check('account','nonce_consumed','Single-use identity nonce was consumed',$nonceConsumed,$nonceConsumed?'consumed':'missing','consumed','Replay-protected signed identity evidence is required.');

        $campaignExists = is_array($campaign);
        $checks[] = tl_closeout_check('participant_flow','campaign_selected','Closeout campaign selected',$campaignExists,$campaignExists?(string)$campaign['title']:'missing','campaign', '', (string)($campaign['public_id'] ?? ''));
        $published = $campaignExists && in_array((string)($campaign['status'] ?? ''), ['active','completed'], true) && (string)($campaign['visibility'] ?? '') === 'published';
        $checks[] = tl_closeout_check('participant_flow','campaign_published','Campaign is published and active or completed',$published,$campaignExists?((string)$campaign['status'] . '/' . (string)$campaign['visibility']):'missing','active|completed / published');
        $builderReady = false;
        if ($dbReady && $campaignExists) {
            try {
                $builderReady = !empty(tl_campaign_builder_readiness($campaign, tl_campaign_builder_tasks($db,(int)$campaign['id']), tl_campaign_builder_reward_rules($db,(int)$campaign['id']))['ready']);
            } catch (Throwable $e) {
                $builderReady = false;
            }
        }
        $checks[] = tl_closeout_check('participant_flow','campaign_builder_ready','Campaign builder readiness passes',$builderReady,$builderReady?'ready':'blocked','ready');

        $campaignId = $campaignExists ? (int)$campaign['id'] : 0;
        $participantCount = $campaignId ? (int)tl_closeout_scalar($db, "SELECT COUNT(*) FROM training_participants WHERE campaign_id=? AND status<>'removed'", [$campaignId], 0) : 0;
        $proofCount = $campaignId ? (int)tl_closeout_scalar($db, 'SELECT COUNT(*) FROM training_proof_submissions WHERE campaign_id=?', [$campaignId], 0) : 0;
        $approvedCount = $campaignId ? (int)tl_closeout_scalar($db, "SELECT COUNT(*) FROM training_reviews r JOIN training_proof_submissions p ON p.id=r.proof_submission_id WHERE p.campaign_id=? AND r.decision='approved'", [$campaignId], 0) : 0;
        $taskReceiptCount = $campaignId ? (int)tl_closeout_scalar($db, "SELECT COUNT(*) FROM training_action_receipts WHERE campaign_id=? AND receipt_type='task_completed' AND receipt_status='active'", [$campaignId], 0) : 0;
        $sequenceCount = $campaignId ? (int)tl_closeout_scalar($db, "SELECT COUNT(*) FROM training_action_receipts WHERE campaign_id=? AND receipt_type='sequence_completed' AND receipt_status='active'", [$campaignId], 0) : 0;
        $checks[] = tl_closeout_check('participant_flow','participant_enrolled','Participant enrollment exists',$participantCount>0,(string)$participantCount,'>=1');
        $checks[] = tl_closeout_check('participant_flow','proof_submitted','Participant proof exists',$proofCount>0,(string)$proofCount,'>=1');
        $checks[] = tl_closeout_check('participant_flow','proof_approved','Approved proof review exists',$approvedCount>0,(string)$approvedCount,'>=1');
        $checks[] = tl_closeout_check('participant_flow','task_receipt','Verified task-completion receipt exists',$taskReceiptCount>0,(string)$taskReceiptCount,'>=1');
        $checks[] = tl_closeout_check('participant_flow','sequence_receipt','Verified campaign-completion receipt exists',$sequenceCount>0,(string)$sequenceCount,'>=1');

        $pilotGraduated = is_array($pilot) && (string)($pilot['run_status'] ?? '') === 'graduated';
        $checks[] = tl_closeout_check('email','pilot_graduated','Limited participant email pilot graduated',$pilotGraduated,$pilot?(string)$pilot['run_status']:'missing','graduated','Section 18 must be explicitly graduated.',(string)($pilot['public_id'] ?? ''));
        $canaryDelivered = $pilotGraduated && (string)($pilot['canary_status'] ?? '') === 'delivered';
        $checks[] = tl_closeout_check('email','canary_delivered','Administrator canary was webhook-confirmed',$canaryDelivered,$pilot?(string)$pilot['canary_status']:'missing','delivered');
        if ($dbReady && $campaignId && $pilot) {
            $started = (string)($pilot['started_at'] ?? $pilot['created_at'] ?? '1970-01-01 00:00:00');
            $participantDelivered = (int)tl_closeout_scalar($db, "SELECT COUNT(DISTINCT ps.id) FROM training_notification_provider_states ps JOIN training_notification_outbox o ON o.id=ps.outbox_id WHERE o.campaign_id=? AND o.source_type<>'pilot_canary' AND o.created_at>=? AND ps.delivery_status='delivered'", [$campaignId,$started], 0);
            $badDeliveries = (int)tl_closeout_scalar($db, "SELECT COUNT(DISTINCT ps.id) FROM training_notification_provider_states ps JOIN training_notification_outbox o ON o.id=ps.outbox_id WHERE o.campaign_id=? AND o.created_at>=? AND ps.delivery_status IN ('failed','suppressed','bounced','complained')", [$campaignId,$started], 0);
        }
        $checks[] = tl_closeout_check('email','participant_delivered','Participant email delivery was webhook-confirmed',$participantDelivered>0,(string)$participantDelivered,'>=1');
        $checks[] = tl_closeout_check('email','no_terminal_delivery','No terminal participant email outcome exists',$badDeliveries===0,(string)$badDeliveries,'0');

        $handoffDelivered = is_array($handoff) && (string)($handoff['handoff_status'] ?? '') === 'delivered';
        $checks[] = tl_closeout_check('reward','handoff_delivered','Training reward handoff is delivered',$handoffDelivered,$handoff?(string)$handoff['handoff_status']:'missing','delivered','The existing idempotent reward outbox remains authoritative.',(string)($handoff['public_id'] ?? ''));
        $externalConfirmed = $handoffDelivered && trim((string)($handoff['external_reference'] ?? '')) !== '';
        $checks[] = tl_closeout_check('reward','external_reference','Delivered reward has a one-way external confirmation',$externalConfirmed,$externalConfirmed?'present':'missing','present','The full external reference is not displayed.',(string)($handoff['external_reference'] ?? ''));
        $stage894 = function_exists('tl_stage894_summary') ? tl_stage894_summary() : [];
        $checks[] = tl_closeout_check('reward','signed_lookup_ready','Signed Microgifter reward lookup client is ready',!empty($stage894['ready']),!empty($stage894['ready'])?'ready':'blocked','ready');
        $stage895Passed = is_array($stage895) && (string)($stage895['metadata']['status'] ?? '') === 'passed' && (int)($stage895['metadata']['score'] ?? 0) === 100 && !empty($stage895['metadata']['ready_for_reconciliation']);
        $checks[] = tl_closeout_check('reward','stage895_passed','Signed Microgifter integration acceptance passed',$stage895Passed,$stage895?(string)($stage895['metadata']['status'] ?? 'incomplete'):'missing','passed / 100%','The suite must include known reward and wrong-user isolation evidence.',(string)($stage895['metadata']['suite_id'] ?? ''));

        $notificationWorkerDisabled = empty(tl_notifications_config()['worker_enabled']);
        $checks[] = tl_closeout_check('safety','notification_worker_disabled','Unrestricted notification worker remains disabled',$notificationWorkerDisabled,$notificationWorkerDisabled?'disabled':'enabled','disabled');
        $rewardWorker = function_exists('tl_stage892_config') ? tl_stage892_config() : ['worker_enabled'=>false];
        $checks[] = tl_closeout_check('safety','reward_worker_disabled','Unrestricted reward handoff worker remains disabled',empty($rewardWorker['worker_enabled']),empty($rewardWorker['worker_enabled'])?'disabled':'enabled','disabled');
        $processingRows = $dbReady ? (int)tl_closeout_scalar($db, "SELECT COUNT(*) FROM training_reward_handoffs WHERE handoff_status='processing'", [], 0) : 0;
        $checks[] = tl_closeout_check('safety','no_processing_handoffs','No reward handoff is left processing',$processingRows===0,(string)$processingRows,'0');
        $safeCommerce = empty($securityConfig['payments_enabled']) && empty($securityConfig['claim_redeem_enabled']) && !empty($securityConfig['reward_events_only_no_wallet_balance_changes']);
        $checks[] = tl_closeout_check('safety','commerce_authority_separated','Payments, wallets, and claim/redeem authority remain outside Training Lab',$safeCommerce,$safeCommerce?'separated':'unsafe','separated');
        $checks[] = tl_closeout_check('safety','closeout_gate_enabled','Production closeout recording gate enabled',!empty($config['enabled']),!empty($config['enabled'])?'enabled':'disabled','enabled');
        $checks[] = tl_closeout_check('safety','approval_gate_enabled','Final administrator approval gate enabled',!empty($config['approval_enabled']),!empty($config['approval_enabled'])?'enabled':'disabled','enabled');

        $passed = count(array_filter($checks, static fn(array $check): bool => !empty($check['passed'])));
        $total = count($checks);
        $failed = $total - $passed;
        $categories = [];
        foreach ($checks as $check) {
            $category = (string)$check['category'];
            $categories[$category] ??= ['passed'=>0,'total'=>0,'percent'=>0];
            $categories[$category]['total']++;
            if (!empty($check['passed'])) $categories[$category]['passed']++;
        }
        foreach ($categories as &$category) $category['percent'] = (int)round($category['passed'] / max(1,$category['total']) * 100);
        unset($category);
        $fingerprintData = [
            'campaign'=>(string)($campaign['public_id'] ?? ''),
            'account'=>(string)($account['public_id'] ?? ''),
            'pilot'=>(string)($pilot['public_id'] ?? ''),
            'handoff'=>(string)($handoff['public_id'] ?? ''),
            'checks'=>array_map(static fn(array $check): array => [
                'category'=>$check['category'],'key'=>$check['key'],'passed'=>$check['passed'],'observed'=>$check['observed'],'required'=>$check['required'],'evidence_hash'=>$check['evidence_hash'],
            ], $checks),
        ];
        $reportHash = hash('sha256', json_encode($fingerprintData, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return [
            'ready'=>$failed === 0,
            'score'=>$total ? (int)round($passed / $total * 100) : 0,
            'passed'=>$passed,'failed'=>$failed,'total'=>$total,
            'checks'=>$checks,'categories'=>$categories,'report_hash'=>$reportHash,
            'campaign'=>$campaign ? ['id'=>(int)$campaign['id'],'public_id'=>(string)$campaign['public_id'],'slug'=>(string)$campaign['slug'],'title'=>(string)$campaign['title'],'status'=>(string)$campaign['status'],'visibility'=>(string)$campaign['visibility']] : null,
            'account'=>$account ? ['id'=>(int)$account['id'],'public_id'=>(string)$account['public_id'],'status'=>(string)$account['link_status'],'role'=>(string)$account['role'],'last_authenticated_at'=>(string)$account['last_authenticated_at'],'identity_fingerprint'=>substr(hash('sha256',(string)$account['microgifter_user_id']),0,16)] : null,
            'email_pilot'=>$pilot ? ['id'=>(int)$pilot['id'],'public_id'=>(string)$pilot['public_id'],'status'=>(string)$pilot['run_status'],'canary_status'=>(string)$pilot['canary_status'],'graduated_at'=>(string)$pilot['graduated_at'],'participant_delivered'=>$participantDelivered,'terminal_outcomes'=>$badDeliveries] : null,
            'reward_handoff'=>$handoff ? ['id'=>(int)$handoff['id'],'public_id'=>(string)$handoff['public_id'],'status'=>(string)$handoff['handoff_status'],'delivered_at'=>(string)$handoff['delivered_at'],'external_reference_fingerprint'=>trim((string)$handoff['external_reference'])!==''?substr(hash('sha256',(string)$handoff['external_reference']),0,16):''] : null,
            'stage895'=>$stage895 ? ['event_id'=>(int)$stage895['id'],'status'=>(string)($stage895['metadata']['status'] ?? ''),'score'=>(int)($stage895['metadata']['score'] ?? 0),'suite_fingerprint'=>substr(hash('sha256',(string)($stage895['metadata']['suite_id'] ?? '')),0,16),'completed_at'=>(string)$stage895['created_at']] : null,
            'configuration'=>['enabled'=>!empty($config['enabled']),'approval_enabled'=>!empty($config['approval_enabled'])],
            'generated_at'=>gmdate('c'),
            'safe_boundaries'=>[
                'read_only_report'=>true,'recording_writes_closeout_tables_only'=>true,'no_gate_activation'=>true,'no_worker_execution'=>true,
                'no_email_send'=>true,'no_reward_issue'=>true,'no_wallet_payment_claim_or_redemption_mutation'=>true,'no_raw_email_secret_provider_or_external_reference_output'=>true,
            ],
        ];
    }
}

if (!function_exists('tl_closeout_event')) {
    function tl_closeout_event(PDO $pdo, int $runId, string $type, string $summary, string $severity, array $metadata, ?int $actorId): void
    {
        $allowed = [];
        foreach (['status','score','passed','failed','total','decision'] as $key) if (array_key_exists($key,$metadata)) $allowed[$key] = is_scalar($metadata[$key]) ? $metadata[$key] : null;
        $severity = in_array($severity,['info','warning','critical','success'],true) ? $severity : 'info';
        $stmt = $pdo->prepare('INSERT INTO training_integration_closeout_events (public_id,closeout_run_id,event_type,severity,event_summary,metadata_json,actor_user_id) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([tl_uuid(),$runId,substr(preg_replace('/[^a-z0-9_-]/i','',$type) ?: 'closeout_event',0,96),$severity,tl_closeout_clean($summary,255),$allowed?json_encode($allowed,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR):null,$actorId]);
    }
}

if (!function_exists('tl_closeout_persist_checks')) {
    function tl_closeout_persist_checks(PDO $pdo, int $runId, array $checks, int $actorId): string
    {
        $group = tl_uuid();
        $stmt = $pdo->prepare('INSERT INTO training_integration_closeout_checks (public_id,check_group_id,closeout_run_id,category_key,check_key,check_label,check_status,observed_value,required_value,detail,evidence_hash,evaluated_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($checks as $check) {
            $stmt->execute([tl_uuid(),$group,$runId,(string)$check['category'],(string)$check['key'],(string)$check['label'],!empty($check['passed'])?'passed':'failed',(string)$check['observed'],(string)$check['required'],(string)$check['detail'],$check['evidence_hash'],$actorId]);
        }
        return $group;
    }
}

if (!function_exists('tl_closeout_record')) {
    function tl_closeout_record(array $user, string $campaignRef = ''): array
    {
        $actorId = tl_closeout_admin($user);
        if (!tl_closeout_config()['enabled']) throw new TlHttpException('Enable the production integration closeout recording gate first.',503,'integration_closeout_disabled');
        if (!tl_closeout_tables_ready()) throw new TlHttpException('Import the Section 20 closeout migration first.',503,'integration_closeout_schema_missing');
        $report = tl_closeout_report($campaignRef);
        $pdo = tl_require_db();
        $pdo->beginTransaction();
        try {
            $existing = tl_closeout_row($pdo,'SELECT * FROM training_integration_closeout_runs WHERE report_hash=? LIMIT 1 FOR UPDATE',[(string)$report['report_hash']]);
            if ($existing) {
                $pdo->commit();
                return ['public_id'=>(string)$existing['public_id'],'status'=>(string)$existing['run_status'],'idempotent'=>true,'report'=>$report];
            }
            $publicId = tl_uuid();
            $status = !empty($report['ready']) ? 'recorded' : 'blocked';
            $stmt = $pdo->prepare('INSERT INTO training_integration_closeout_runs (public_id,campaign_id,account_link_id,email_pilot_run_id,reward_handoff_id,run_status,report_hash,score_percent,passed_count,required_count,failed_count,recorded_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$publicId,$report['campaign']['id']??null,$report['account']['id']??null,$report['email_pilot']['id']??null,$report['reward_handoff']['id']??null,$status,(string)$report['report_hash'],(int)$report['score'],(int)$report['passed'],(int)$report['total'],(int)$report['failed'],$actorId]);
            $runId = (int)$pdo->lastInsertId();
            $group = tl_closeout_persist_checks($pdo,$runId,(array)$report['checks'],$actorId);
            tl_closeout_event($pdo,$runId,'closeout_recorded','A production integration closeout evidence snapshot was recorded.',!empty($report['ready'])?'success':'warning',['status'=>$status,'score'=>(int)$report['score'],'passed'=>(int)$report['passed'],'failed'=>(int)$report['failed'],'total'=>(int)$report['total']],$actorId);
            $pdo->commit();
            return ['public_id'=>$publicId,'check_group_id'=>$group,'status'=>$status,'idempotent'=>false,'report'=>$report];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_closeout_run')) {
    function tl_closeout_run(PDO $pdo, string $runRef, bool $lock = false): array
    {
        $runRef = preg_replace('/[^a-zA-Z0-9\-_]/','',trim($runRef)) ?: '';
        if ($runRef === '') throw new TlHttpException('Select a closeout report.',422,'integration_closeout_run_required');
        $where = ctype_digit($runRef) ? 'id=?' : 'public_id=?';
        $run = tl_closeout_row($pdo,'SELECT * FROM training_integration_closeout_runs WHERE ' . $where . ' LIMIT 1' . ($lock?' FOR UPDATE':''),[ctype_digit($runRef)?(int)$runRef:$runRef]);
        if (!$run) throw new TlHttpException('The closeout report was not found.',404,'integration_closeout_run_not_found');
        return $run;
    }
}

if (!function_exists('tl_closeout_approve')) {
    function tl_closeout_approve(array $user, string $runRef, string $notes = ''): array
    {
        $actorId = tl_closeout_admin($user);
        $config = tl_closeout_config();
        if (!$config['enabled'] || !$config['approval_enabled']) throw new TlHttpException('Both production closeout gates must be explicitly enabled.',503,'integration_closeout_approval_disabled');
        $pdo = tl_require_db();
        $pdo->beginTransaction();
        try {
            $run = tl_closeout_run($pdo,$runRef,true);
            if ((string)$run['run_status'] === 'approved') { $pdo->commit(); return ['public_id'=>(string)$run['public_id'],'status'=>'approved','idempotent'=>true]; }
            if ((string)$run['run_status'] !== 'recorded' || (int)$run['failed_count'] !== 0 || (int)$run['score_percent'] !== 100) throw new TlHttpException('Only a fully passing recorded closeout report can be approved.',409,'integration_closeout_not_ready');
            $campaignRef = '';
            if (!empty($run['campaign_id'])) $campaignRef = (string)$run['campaign_id'];
            $fresh = tl_closeout_report($campaignRef);
            if (empty($fresh['ready']) || !hash_equals((string)$run['report_hash'],(string)$fresh['report_hash'])) throw new TlHttpException('Production evidence changed after recording. Record a new closeout report.',409,'integration_closeout_evidence_changed');
            $notes = tl_closeout_clean($notes !== ''?$notes:'The complete Microgifter Training Lab v1 integration path passed every production closeout requirement.',1000);
            $pdo->prepare("UPDATE training_integration_closeout_runs SET run_status='approved',decision_notes=?,approved_by_user_id=?,approved_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$notes,$actorId,(int)$run['id']]);
            tl_closeout_event($pdo,(int)$run['id'],'closeout_approved','The administrator approved the complete production integration closeout.','success',['status'=>'approved','score'=>100,'decision'=>'approved'],$actorId);
            $pdo->commit();
            return ['public_id'=>(string)$run['public_id'],'status'=>'approved','idempotent'=>false];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_closeout_reject')) {
    function tl_closeout_reject(array $user, string $runRef, string $notes): array
    {
        $actorId = tl_closeout_admin($user);
        $notes = tl_closeout_clean($notes,1000);
        if ($notes === '') throw new TlHttpException('A closeout rejection reason is required.',422,'integration_closeout_rejection_reason_required');
        $pdo = tl_require_db();
        $pdo->beginTransaction();
        try {
            $run = tl_closeout_run($pdo,$runRef,true);
            if ((string)$run['run_status'] === 'approved') throw new TlHttpException('An approved closeout report is immutable.',409,'integration_closeout_approved_immutable');
            $pdo->prepare("UPDATE training_integration_closeout_runs SET run_status='rejected',decision_notes=?,rejected_by_user_id=?,rejected_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$notes,$actorId,(int)$run['id']]);
            tl_closeout_event($pdo,(int)$run['id'],'closeout_rejected','The administrator rejected the production integration closeout.','critical',['status'=>'rejected','decision'=>'rejected'],$actorId);
            $pdo->commit();
            return ['public_id'=>(string)$run['public_id'],'status'=>'rejected'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_closeout_dashboard')) {
    function tl_closeout_dashboard(array $user, string $campaignRef = '', string $runRef = ''): array
    {
        tl_closeout_admin($user);
        $report = tl_closeout_report($campaignRef);
        $runs = $checks = $events = [];
        $selected = null;
        if (tl_closeout_tables_ready()) {
            $pdo = tl_require_db();
            $runs = $pdo->query('SELECT r.*,c.title campaign_title FROM training_integration_closeout_runs r LEFT JOIN training_campaigns c ON c.id=r.campaign_id ORDER BY r.recorded_at DESC,r.id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($runRef !== '') $selected = tl_closeout_run($pdo,$runRef);
            elseif ($runs) $selected = $runs[0];
            if ($selected) {
                $stmt = $pdo->prepare('SELECT check_group_id,category_key,check_key,check_label,check_status,observed_value,required_value,detail,LEFT(evidence_hash,12) evidence_fingerprint,evaluated_at FROM training_integration_closeout_checks WHERE closeout_run_id=? ORDER BY evaluated_at DESC,id ASC LIMIT 250');
                $stmt->execute([(int)$selected['id']]);
                $checks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $stmt = $pdo->prepare('SELECT event_type,severity,event_summary,metadata_json,created_at FROM training_integration_closeout_events WHERE closeout_run_id=? ORDER BY created_at DESC,id DESC LIMIT 100');
                $stmt->execute([(int)$selected['id']]);
                $events = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }
        return ['report'=>$report,'campaigns'=>tl_closeout_campaigns(),'runs'=>$runs,'selected'=>$selected,'checks'=>$checks,'events'=>$events,'configuration'=>tl_closeout_config(),'schema_ready'=>tl_closeout_tables_ready()];
    }
}
