<?php
declare(strict_types=1);

/**
 * Stage 896 — Limited Reward Issuance Pilot v1.
 *
 * Runs exactly one operator-selected reward handoff through the established
 * Stage 890–894 production path, then immediately performs a signed read-back.
 * No scheduled or batch processing is introduced here.
 */
require_once __DIR__ . '/training-lab-stage894-reconciliation-bootstrap.php';
require_once __DIR__ . '/training-lab-stage893-legacy-action-guard.php';
require_once __DIR__ . '/training-lab-stage895-integration-acceptance.php';

if (!function_exists('tl_stage896_bool')) {
    function tl_stage896_bool($value, bool $default = false): bool
    {
        if (is_bool($value)) return $value;
        if ($value === null || $value === '') return $default;
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed === null ? $default : $parsed;
    }
}

if (!function_exists('tl_stage896_config')) {
    function tl_stage896_config(): array
    {
        $root = function_exists('tl_security_config') ? tl_security_config() : [];
        $enabled = getenv('TL_STAGE896_LIMITED_PILOT_ENABLED');
        $maxValue = getenv('TL_STAGE896_MAX_VALUE_CENTS');
        $acceptanceAge = getenv('TL_STAGE896_ACCEPTANCE_MAX_AGE_SECONDS');
        return [
            'enabled'=>tl_stage896_bool($enabled !== false ? $enabled : ($root['stage896_limited_pilot_enabled'] ?? false), false),
            'max_value_cents'=>max(0, min(100000, (int)($maxValue !== false && $maxValue !== '' ? $maxValue : ($root['stage896_max_value_cents'] ?? 2500)))),
            'acceptance_max_age_seconds'=>max(300, min(604800, (int)($acceptanceAge !== false && $acceptanceAge !== '' ? $acceptanceAge : ($root['stage896_acceptance_max_age_seconds'] ?? 86400)))),
            'allowed_currency'=>'USD',
            'confirmation_phrase'=>'ISSUE ONE PILOT',
            'advisory_lock_name'=>'training-lab-stage896-limited-pilot-v1',
        ];
    }
}

if (!function_exists('tl_stage896_json')) {
    function tl_stage896_json($value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('tl_stage896_fingerprint')) {
    function tl_stage896_fingerprint($value): string
    {
        $value = trim((string)$value);
        return $value === '' ? '' : substr(hash('sha256', $value), 0, 16);
    }
}

if (!function_exists('tl_stage896_latest_acceptance')) {
    function tl_stage896_latest_acceptance(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query("SELECT public_id,actor_user_id,metadata_json,created_at FROM training_events WHERE event_type='stage895_signed_integration_acceptance' ORDER BY id DESC LIMIT 1");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        } catch (Throwable $e) {
            $row = false;
        }
        if (!$row) {
            return ['found'=>false,'passed'=>false,'fresh'=>false,'age_seconds'=>null,'status'=>'missing','score'=>0,'suite_id'=>''];
        }
        $metadata = tl_stage896_json($row['metadata_json'] ?? null);
        $created = strtotime((string)($row['created_at'] ?? '')) ?: 0;
        $age = $created > 0 ? max(0, time() - $created) : null;
        $passed = (string)($metadata['status'] ?? '') === 'passed'
            && (int)($metadata['score'] ?? 0) === 100
            && !empty($metadata['ready_for_reconciliation']);
        $fresh = $age !== null && $age <= (int)tl_stage896_config()['acceptance_max_age_seconds'];
        return [
            'found'=>true,
            'passed'=>$passed,
            'fresh'=>$fresh,
            'age_seconds'=>$age,
            'status'=>(string)($metadata['status'] ?? 'unknown'),
            'score'=>(int)($metadata['score'] ?? 0),
            'suite_id'=>(string)($metadata['suite_id'] ?? $row['public_id'] ?? ''),
            'recorded_at'=>(string)($row['created_at'] ?? ''),
        ];
    }
}

if (!function_exists('tl_stage896_worker_disabled')) {
    function tl_stage896_worker_disabled(): bool
    {
        return empty(tl_stage892_config()['worker_enabled']);
    }
}

if (!function_exists('tl_stage896_readiness')) {
    function tl_stage896_readiness(): array
    {
        $config = tl_stage896_config();
        $pdo = function_exists('tl_db') ? tl_db() : null;
        $acceptance = $pdo ? tl_stage896_latest_acceptance($pdo) : ['found'=>false,'passed'=>false,'fresh'=>false];
        $lookup = function_exists('tl_stage894_summary') ? tl_stage894_summary() : ['ready'=>false];
        $adapter = function_exists('tl_stage890_adapter_state') ? tl_stage890_adapter_state() : ['can_process'=>false];
        $reconciliation = function_exists('tl_stage893_config') ? tl_stage893_config() : ['enabled'=>false];
        $workerDisabled = function_exists('tl_stage892_config') ? tl_stage896_worker_disabled() : false;
        $checks = [
            'pilot_enabled'=>!empty($config['enabled']),
            'database_ready'=>$pdo instanceof PDO && function_exists('tl_stage890_table_ready') && tl_stage890_table_ready(),
            'stage895_passed'=>!empty($acceptance['passed']),
            'stage895_fresh'=>!empty($acceptance['fresh']),
            'signed_readback_ready'=>!empty($lookup['ready']),
            'reconciliation_enabled'=>!empty($reconciliation['enabled']),
            'production_adapter_ready'=>!empty($adapter['can_process']),
            'scheduled_worker_disabled'=>$workerDisabled,
        ];
        return [
            'stage'=>'Stage 896 Limited Reward Issuance Pilot v1',
            'ready_to_issue'=>count(array_filter($checks)) === count($checks),
            'score'=>(int)round((count(array_filter($checks)) / max(1, count($checks))) * 100),
            'checks'=>$checks,
            'acceptance'=>$acceptance,
            'lookup'=>[
                'enabled'=>!empty($lookup['enabled']),
                'ready'=>!empty($lookup['ready']),
                'endpoint_host'=>(string)($lookup['endpoint']['host'] ?? ''),
            ],
            'adapter'=>[
                'processing_enabled'=>!empty($adapter['processing_enabled']),
                'production_issuing_enabled'=>!empty($adapter['production_issuing_enabled']),
                'developer_key_present'=>!empty($adapter['developer_key_present']),
                'direct_adapter_present'=>!empty($adapter['direct_adapter_functions']),
                'can_process'=>!empty($adapter['can_process']),
            ],
            'reconciliation_enabled'=>!empty($reconciliation['enabled']),
            'scheduled_worker_disabled'=>$workerDisabled,
            'max_value_cents'=>(int)$config['max_value_cents'],
            'allowed_currency'=>(string)$config['allowed_currency'],
        ];
    }
}

if (!function_exists('tl_stage896_acquire_lock')) {
    function tl_stage896_acquire_lock(PDO $pdo): bool
    {
        $stmt = $pdo->prepare('SELECT GET_LOCK(?, 0)');
        $stmt->execute([(string)tl_stage896_config()['advisory_lock_name']]);
        return (int)$stmt->fetchColumn() === 1;
    }
}

if (!function_exists('tl_stage896_release_lock')) {
    function tl_stage896_release_lock(PDO $pdo): void
    {
        try {
            $stmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([(string)tl_stage896_config()['advisory_lock_name']]);
        } catch (Throwable $e) {
            // Connection close also releases MySQL advisory locks.
        }
    }
}

if (!function_exists('tl_stage896_pilot_patch')) {
    function tl_stage896_pilot_patch($metadataJson, array $patch): string
    {
        $metadata = tl_stage896_json($metadataJson);
        $pilot = is_array($metadata['stage896_pilot'] ?? null) ? $metadata['stage896_pilot'] : [];
        $history = is_array($metadata['stage896_pilot_history'] ?? null) ? $metadata['stage896_pilot_history'] : [];
        if (isset($patch['history_event']) && is_array($patch['history_event'])) {
            $history[] = $patch['history_event'];
            $history = array_slice($history, -30);
            unset($patch['history_event']);
        }
        $metadata['stage896_pilot'] = array_merge($pilot, $patch);
        $metadata['stage896_pilot_history'] = $history;
        return tl_stage890_json($metadata);
    }
}

if (!function_exists('tl_stage896_terminal_statuses')) {
    function tl_stage896_terminal_statuses(): array
    {
        return ['verified','closed_absent','cancelled_before_processing'];
    }
}

if (!function_exists('tl_stage896_active_pilots')) {
    function tl_stage896_active_pilots(PDO $pdo, int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $pdo->query("SELECT h.*,re.public_id AS reward_public_id,re.status AS reward_status,re.value_cents,re.currency,re.user_id AS reward_user_id FROM training_reward_handoffs h JOIN training_reward_events re ON re.id=h.reward_event_id WHERE COALESCE(h.metadata_json,'') LIKE '%\"stage896_pilot\"%' ORDER BY h.updated_at DESC,h.id DESC LIMIT " . $limit);
        $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        $active = [];
        foreach ($rows as $row) {
            $metadata = tl_stage896_json($row['metadata_json'] ?? null);
            $pilot = is_array($metadata['stage896_pilot'] ?? null) ? $metadata['stage896_pilot'] : [];
            if (!$pilot) continue;
            $status = (string)($pilot['status'] ?? 'unknown');
            if (!in_array($status, tl_stage896_terminal_statuses(), true)) {
                $row['stage896_pilot'] = $pilot;
                $active[] = $row;
            }
        }
        return $active;
    }
}

if (!function_exists('tl_stage896_candidate_rows')) {
    function tl_stage896_candidate_rows(int $limit = 25): array
    {
        if (!function_exists('tl_stage890_table_ready') || !tl_stage890_table_ready()) return [];
        $pdo = tl_db();
        if (!$pdo) return [];
        $limit = max(1, min(100, $limit));
        $maxValue = (int)tl_stage896_config()['max_value_cents'];
        $sql = "SELECT h.id,h.public_id,h.handoff_status,h.failure_code,h.attempt_count,h.microgifter_user_id,h.updated_at,
                       re.id AS reward_event_id,re.public_id AS reward_public_id,re.status AS reward_status,re.value_cents,re.currency,
                       COALESCE(rr.reward_label,rr.rule_name,'Training Reward') AS reward_label,
                       COALESCE(tp.participant_label,CONCAT('User #',re.user_id)) AS participant_label
                FROM training_reward_handoffs h
                JOIN training_reward_events re ON re.id=h.reward_event_id
                LEFT JOIN training_reward_rules rr ON rr.id=re.reward_rule_id
                LEFT JOIN training_participants tp ON tp.id=re.participant_id
                WHERE h.handoff_status IN ('queued','failed','blocked')
                  AND COALESCE(h.failure_code,'') <> 'external_delivery_confirmation_required'
                  AND COALESCE(h.metadata_json,'') NOT LIKE '%\"reconciliation_required\":true%'
                  AND re.status NOT IN ('issued','linked','cancelled')
                  AND re.value_cents BETWEEN 0 AND ?
                  AND re.currency='USD'
                ORDER BY re.value_cents ASC,h.updated_at ASC,h.id ASC
                LIMIT " . $limit;
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$maxValue]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('tl_stage896_load_context')) {
    function tl_stage896_load_context(PDO $pdo, string $handoffRef, bool $forUpdate = false): array
    {
        $handoffRef = trim($handoffRef);
        if ($handoffRef === '') throw new TlHttpException('A reward handoff is required.', 422, 'stage896_handoff_required');
        $sql = "SELECT h.*,re.public_id AS reward_public_id,re.status AS reward_status,re.value_cents,re.currency,re.user_id AS reward_user_id
                FROM training_reward_handoffs h JOIN training_reward_events re ON re.id=h.reward_event_id
                WHERE h.public_id=? OR h.id=? LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$handoffRef, ctype_digit($handoffRef) ? (int)$handoffRef : 0]);
        $handoff = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$handoff) throw new TlHttpException('The selected reward handoff was not found.', 404, 'stage896_handoff_not_found');
        $reward = tl_stage890_load_reward($pdo, (string)$handoff['reward_event_id']);
        $link = tl_stage890_find_account_link($pdo, $reward);
        return ['handoff'=>$handoff,'reward'=>$reward,'link'=>$link];
    }
}

if (!function_exists('tl_stage896_sanitize_pilot')) {
    function tl_stage896_sanitize_pilot(array $row): array
    {
        $pilot = is_array($row['stage896_pilot'] ?? null)
            ? $row['stage896_pilot']
            : (array)(tl_stage896_json($row['metadata_json'] ?? null)['stage896_pilot'] ?? []);
        return [
            'handoff_id'=>(int)($row['id'] ?? 0),
            'handoff_public_id'=>(string)($row['public_id'] ?? ''),
            'reward_public_id'=>(string)($row['reward_public_id'] ?? ''),
            'handoff_status'=>(string)($row['handoff_status'] ?? ''),
            'reward_status'=>(string)($row['reward_status'] ?? ''),
            'pilot_id'=>(string)($pilot['pilot_id'] ?? ''),
            'pilot_status'=>(string)($pilot['status'] ?? ''),
            'started_at'=>(string)($pilot['started_at'] ?? ''),
            'updated_at'=>(string)($pilot['updated_at'] ?? ''),
            'value_cents'=>(int)($row['value_cents'] ?? 0),
            'currency'=>(string)($row['currency'] ?? ''),
            'microgifter_user_fingerprint'=>(string)($pilot['microgifter_user_fingerprint'] ?? ''),
            'external_reference_fingerprint'=>(string)($pilot['external_reference_fingerprint'] ?? ''),
            'last_verification_status'=>(string)($pilot['last_verification_status'] ?? ''),
        ];
    }
}

if (!function_exists('tl_stage896_update_pilot')) {
    function tl_stage896_update_pilot(PDO $pdo, int $handoffId, array $patch): array
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM training_reward_handoffs WHERE id=? LIMIT 1 FOR UPDATE');
            $stmt->execute([$handoffId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('The Stage 896 handoff no longer exists.');
            $patch['updated_at'] = gmdate('c');
            $metadata = tl_stage896_pilot_patch($row['metadata_json'] ?? null, $patch);
            $update = $pdo->prepare('UPDATE training_reward_handoffs SET metadata_json=? WHERE id=?');
            $update->execute([$metadata, $handoffId]);
            $pdo->commit();
            $row['metadata_json'] = $metadata;
            return $row;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('tl_stage896_lookup_context')) {
    function tl_stage896_lookup_context(PDO $pdo, int $handoffId): array
    {
        $context = tl_stage896_load_context($pdo, (string)$handoffId, false);
        $lookup = tl_stage893_lookup_external($context['handoff'], $context['reward']);
        return $context + ['lookup'=>$lookup];
    }
}

if (!function_exists('tl_stage896_finalize_verification')) {
    function tl_stage896_finalize_verification(PDO $pdo, int $handoffId, int $actorUserId, array $processing = []): array
    {
        $context = tl_stage896_lookup_context($pdo, $handoffId);
        $handoff = $context['handoff'];
        $reward = $context['reward'];
        $lookup = $context['lookup'];
        $processingOk = !empty($processing['adapter_result']['ok']) || (string)($handoff['handoff_status'] ?? '') === 'delivered';
        $terminal = false;
        $pilotStatus = 'verification_pending';
        $eventType = 'stage896_pilot_verification_pending';

        if (!empty($lookup['confirmed_delivered'])) {
            if ((string)$handoff['handoff_status'] !== 'delivered' || !in_array((string)$reward['status'], ['issued','linked'], true)) {
                $reconciled = tl_stage893_reconcile_handoff_guarded(['handoff_id'=>(string)$handoffId,'actor_user_id'=>$actorUserId]);
                $context = tl_stage896_load_context($pdo, (string)$handoffId, false);
                $handoff = $context['handoff'];
                $reward = $context['reward'];
                $processing['readback_reconciliation'] = $reconciled;
            }
            $pilotStatus = 'verified';
            $terminal = true;
            $eventType = 'stage896_pilot_delivery_verified';
        } elseif (!empty($lookup['confirmed_absent']) && !$processingOk && !in_array((string)$handoff['handoff_status'], ['delivered','processing'], true)) {
            $pilotStatus = 'closed_absent';
            $terminal = true;
            $eventType = 'stage896_pilot_absence_confirmed';
        } elseif ((string)($lookup['delivery_status'] ?? '') === 'error') {
            $pilotStatus = 'verification_failed';
            $eventType = 'stage896_pilot_verification_failed';
        }

        $external = (string)($lookup['external_reference'] ?? $handoff['external_reference'] ?? '');
        $row = tl_stage896_update_pilot($pdo, $handoffId, [
            'status'=>$pilotStatus,
            'terminal'=>$terminal,
            'last_verification_status'=>(string)($lookup['delivery_status'] ?? 'unknown'),
            'lookup_adapter'=>(string)($lookup['adapter'] ?? ''),
            'external_reference_fingerprint'=>tl_stage896_fingerprint($external),
            'verified_at'=>$pilotStatus === 'verified' ? gmdate('c') : null,
            'closed_absent_at'=>$pilotStatus === 'closed_absent' ? gmdate('c') : null,
            'history_event'=>[
                'event'=>$eventType,
                'at'=>gmdate('c'),
                'lookup_status'=>(string)($lookup['delivery_status'] ?? 'unknown'),
                'terminal'=>$terminal,
            ],
        ]);
        tl_log_event($pdo, $actorUserId, 'reward_event', (int)$handoff['reward_event_id'], $eventType, [
            'pilot_id'=>(string)(tl_stage896_json($row['metadata_json'] ?? null)['stage896_pilot']['pilot_id'] ?? ''),
            'handoff_id'=>$handoffId,
            'handoff_status'=>(string)$handoff['handoff_status'],
            'reward_status'=>(string)$reward['status'],
            'lookup_status'=>(string)($lookup['delivery_status'] ?? 'unknown'),
            'lookup_adapter'=>(string)($lookup['adapter'] ?? ''),
            'external_reference_fingerprint'=>tl_stage896_fingerprint($external),
            'terminal'=>$terminal,
            'raw_identity_reference_and_adapter_payload_excluded'=>true,
        ]);
        return [
            'pilot'=>tl_stage896_sanitize_pilot($row + ['reward_public_id'=>$reward['public_id'] ?? '', 'reward_status'=>$reward['status'] ?? '', 'value_cents'=>$reward['value_cents'] ?? 0, 'currency'=>$reward['currency'] ?? '']),
            'processing'=>$processing,
            'verification'=>[
                'lookup_available'=>!empty($lookup['lookup_available']),
                'delivery_status'=>(string)($lookup['delivery_status'] ?? 'unknown'),
                'confirmed_delivered'=>!empty($lookup['confirmed_delivered']),
                'confirmed_absent'=>!empty($lookup['confirmed_absent']),
                'terminal'=>$terminal,
            ],
        ];
    }
}

if (!function_exists('tl_stage896_run_pilot')) {
    function tl_stage896_run_pilot(array $input): array
    {
        $readiness = tl_stage896_readiness();
        if (empty($readiness['ready_to_issue'])) {
            throw new TlHttpException('Stage 896 is not ready. Complete Stage 895, enable only the required manual production gates, and keep the scheduled worker disabled.', 409, 'stage896_not_ready');
        }
        $config = tl_stage896_config();
        $phrase = trim((string)($input['confirmation_phrase'] ?? ''));
        if (!hash_equals((string)$config['confirmation_phrase'], $phrase)) {
            throw new TlHttpException('Enter the exact Stage 896 confirmation phrase.', 422, 'stage896_confirmation_phrase_invalid');
        }
        $confirmedUserId = trim((string)($input['confirm_microgifter_user_id'] ?? ''));
        if ($confirmedUserId === '' || !ctype_digit($confirmedUserId) || (int)$confirmedUserId < 1) {
            throw new TlHttpException('Re-enter the linked Microgifter user ID to authorize this pilot.', 422, 'stage896_recipient_confirmation_required');
        }
        $handoffRef = trim((string)($input['handoff_id'] ?? $input['public_id'] ?? ''));
        $actor = max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1));
        $pdo = tl_require_db();
        if (!tl_stage896_acquire_lock($pdo)) {
            throw new TlHttpException('Another Stage 896 pilot operation is already running.', 409, 'stage896_operation_locked');
        }

        $handoffId = 0;
        $pilotId = function_exists('tl_uuid') ? tl_uuid() : bin2hex(random_bytes(16));
        try {
            $active = tl_stage896_active_pilots($pdo);
            if ($active) {
                throw new TlHttpException('A prior Stage 896 pilot is still awaiting verified delivery or confirmed absence.', 409, 'stage896_active_pilot_exists');
            }
            $pdo->beginTransaction();
            $context = tl_stage896_load_context($pdo, $handoffRef, true);
            $handoff = $context['handoff'];
            $reward = $context['reward'];
            $link = $context['link'];
            $handoffId = (int)$handoff['id'];

            if (!in_array((string)$handoff['handoff_status'], ['queued','failed','blocked'], true)) {
                throw new TlHttpException('Only a queued, failed, or requirements-blocked handoff can enter the limited pilot.', 409, 'stage896_handoff_not_eligible');
            }
            if ((string)($handoff['failure_code'] ?? '') === 'external_delivery_confirmation_required') {
                throw new TlHttpException('A quarantined handoff cannot be used for a new pilot.', 409, 'stage896_handoff_quarantined');
            }
            $handoffMetadata = tl_stage896_json($handoff['metadata_json'] ?? null);
            if (!empty($handoffMetadata['stage893_reconciliation']['reconciliation_required'])) {
                throw new TlHttpException('A handoff awaiting external reconciliation cannot be used for a new pilot.', 409, 'stage896_handoff_quarantined');
            }
            if (in_array((string)$reward['status'], ['issued','linked','cancelled'], true)) {
                throw new TlHttpException('The selected reward is already complete or cancelled.', 409, 'stage896_reward_not_eligible');
            }
            if ((int)$reward['value_cents'] > (int)$config['max_value_cents']) {
                throw new TlHttpException('The selected reward exceeds the Stage 896 pilot value ceiling.', 409, 'stage896_value_limit_exceeded');
            }
            if ((string)$reward['currency'] !== (string)$config['allowed_currency']) {
                throw new TlHttpException('The selected reward currency is not allowed in the Stage 896 pilot.', 409, 'stage896_currency_not_allowed');
            }
            if (!$link || empty($link['microgifter_user_id'])) {
                throw new TlHttpException('An active linked Microgifter recipient is required.', 409, 'stage896_active_account_link_required');
            }
            $linkedUserId = (string)$link['microgifter_user_id'];
            $handoffUserId = trim((string)($handoff['microgifter_user_id'] ?? ''));
            if (!hash_equals($linkedUserId, $confirmedUserId) || ($handoffUserId !== '' && !hash_equals($handoffUserId, $confirmedUserId))) {
                throw new TlHttpException('The re-entered Microgifter user ID does not match the active account link and handoff recipient.', 409, 'stage896_recipient_mismatch');
            }

            $metadata = tl_stage896_pilot_patch($handoff['metadata_json'] ?? null, [
                'pilot_id'=>$pilotId,
                'status'=>'reserved',
                'terminal'=>false,
                'started_at'=>gmdate('c'),
                'updated_at'=>gmdate('c'),
                'actor_user_id'=>$actor,
                'microgifter_user_fingerprint'=>tl_stage896_fingerprint($confirmedUserId),
                'reward_reference_fingerprint'=>tl_stage896_fingerprint((string)$reward['public_id']),
                'value_cents'=>(int)$reward['value_cents'],
                'currency'=>(string)$reward['currency'],
                'stage895_suite_id'=>(string)($readiness['acceptance']['suite_id'] ?? ''),
                'history_event'=>['event'=>'pilot_reserved','at'=>gmdate('c'),'actor_user_id'=>$actor],
            ]);
            $update = $pdo->prepare('UPDATE training_reward_handoffs SET metadata_json=? WHERE id=?');
            $update->execute([$metadata, $handoffId]);
            tl_log_event($pdo, $actor, 'reward_event', (int)$reward['id'], 'stage896_pilot_reserved', [
                'pilot_id'=>$pilotId,
                'handoff_id'=>$handoffId,
                'value_cents'=>(int)$reward['value_cents'],
                'currency'=>(string)$reward['currency'],
                'microgifter_user_fingerprint'=>tl_stage896_fingerprint($confirmedUserId),
                'reward_reference_fingerprint'=>tl_stage896_fingerprint((string)$reward['public_id']),
                'stage895_suite_id'=>(string)($readiness['acceptance']['suite_id'] ?? ''),
                'raw_recipient_and_adapter_payload_excluded'=>true,
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            tl_stage896_release_lock($pdo);
            throw $e;
        }
        tl_stage896_release_lock($pdo);

        tl_stage896_update_pilot($pdo, $handoffId, [
            'status'=>'processing',
            'history_event'=>['event'=>'pilot_processing_started','at'=>gmdate('c')],
        ]);
        $processing = [];
        try {
            $processing = tl_stage893_process_handoff_production_guarded([
                'handoff_id'=>(string)$handoffId,
                'actor_user_id'=>$actor,
                'stage896_pilot_id'=>$pilotId,
            ]);
        } catch (Throwable $e) {
            $processing = ['handoff_id'=>$handoffId,'status'=>'error','error_code'=>'pilot_processing_exception','message'=>mb_substr($e->getMessage(), 0, 500)];
            tl_stage896_update_pilot($pdo, $handoffId, [
                'status'=>'verification_pending',
                'processing_error_code'=>'pilot_processing_exception',
                'history_event'=>['event'=>'pilot_processing_exception','at'=>gmdate('c')],
            ]);
        }
        return tl_stage896_finalize_verification($pdo, $handoffId, $actor, $processing);
    }
}

if (!function_exists('tl_stage896_verify_active_pilot')) {
    function tl_stage896_verify_active_pilot(array $input = []): array
    {
        $actor = max(1, (int)($input['actor_user_id'] ?? $input['user_id'] ?? 1));
        $pdo = tl_require_db();
        if (empty(tl_stage894_summary()['ready'])) {
            throw new TlHttpException('The signed Stage 894 read-back client must be ready before verifying a pilot.', 409, 'stage896_readback_not_ready');
        }
        if (!tl_stage896_acquire_lock($pdo)) {
            throw new TlHttpException('Another Stage 896 pilot operation is already running.', 409, 'stage896_operation_locked');
        }
        try {
            $active = tl_stage896_active_pilots($pdo);
            if (!$active) throw new TlHttpException('There is no active Stage 896 pilot to verify.', 404, 'stage896_active_pilot_missing');
            if (count($active) > 1) throw new TlHttpException('Multiple active pilot records require engineering review before any verification continues.', 409, 'stage896_multiple_active_pilots');
            $handoffId = (int)$active[0]['id'];
            tl_stage896_update_pilot($pdo, $handoffId, [
                'status'=>'verifying',
                'history_event'=>['event'=>'manual_verification_started','at'=>gmdate('c'),'actor_user_id'=>$actor],
            ]);
        } finally {
            tl_stage896_release_lock($pdo);
        }
        return tl_stage896_finalize_verification($pdo, $handoffId, $actor, []);
    }
}

if (!function_exists('tl_stage896_summary')) {
    function tl_stage896_summary(): array
    {
        $readiness = tl_stage896_readiness();
        $pdo = function_exists('tl_db') ? tl_db() : null;
        $active = $pdo ? tl_stage896_active_pilots($pdo) : [];
        return $readiness + [
            'active_pilot_count'=>count($active),
            'active_pilot'=>$active ? tl_stage896_sanitize_pilot($active[0]) : null,
            'multiple_active_pilots'=>count($active) > 1,
            'candidate_count'=>count(tl_stage896_candidate_rows(100)),
            'candidates'=>array_map(static function (array $row): array {
                return [
                    'handoff_id'=>(int)$row['id'],
                    'handoff_public_id'=>(string)$row['public_id'],
                    'reward_public_id'=>(string)$row['reward_public_id'],
                    'handoff_status'=>(string)$row['handoff_status'],
                    'reward_status'=>(string)$row['reward_status'],
                    'reward_label'=>(string)$row['reward_label'],
                    'participant_label'=>(string)$row['participant_label'],
                    'value_cents'=>(int)$row['value_cents'],
                    'currency'=>(string)$row['currency'],
                    'microgifter_user_id'=>(string)$row['microgifter_user_id'],
                    'attempt_count'=>(int)$row['attempt_count'],
                ];
            }, tl_stage896_candidate_rows(25)),
            'safe_boundaries'=>[
                'single_handoff_only'=>true,
                'scheduled_worker_must_remain_disabled'=>true,
                'stage895_acceptance_required'=>true,
                'recipient_reentry_required'=>true,
                'value_ceiling_enforced'=>true,
                'immediate_signed_readback'=>true,
                'next_pilot_blocked_until_terminal_evidence'=>true,
                'no_batch_processing'=>true,
            ],
        ];
    }
}

if (!function_exists('tl_stage896_render_admin_page')) {
    function tl_stage896_render_admin_page(?array $result = null, string $error = ''): void
    {
        if (!function_exists('labs_e') || !function_exists('labs_url')) return;
        $summary = tl_stage896_summary();
        $active = $summary['active_pilot'] ?? null;
        echo '<section class="labs-page-title"><div><span class="labs-eyebrow">Stage 896</span><h1>Limited Reward Issuance Pilot</h1><p class="labs-copy">Issue one selected reward through the durable handoff path, immediately verify it through the signed read adapter, and block every later pilot until delivery or absence is confirmed.</p></div><a class="labs-btn" href="' . labs_e(labs_url('/api/training/reward-pilot.php')) . '">Pilot JSON</a></section>';
        echo '<section class="labs-kpis">';
        echo '<div class="labs-kpi"><span>Readiness</span><strong>' . (int)$summary['score'] . '%</strong><small>manual production gates</small></div>';
        echo '<div class="labs-kpi"><span>Pilot</span><strong>' . ($active ? labs_e((string)$active['pilot_status']) : 'Open') . '</strong><small>one globally active</small></div>';
        echo '<div class="labs-kpi"><span>Value ceiling</span><strong>$' . number_format(((int)$summary['max_value_cents']) / 100, 2) . '</strong><small>USD only</small></div>';
        echo '<div class="labs-kpi"><span>Worker</span><strong>' . (!empty($summary['scheduled_worker_disabled']) ? 'Disabled' : 'STOP') . '</strong><small>must remain disabled</small></div>';
        echo '</section>';
        if ($error !== '') echo '<section class="labs-card labs-error-card"><h2>Pilot needs attention</h2><p class="labs-copy">' . labs_e($error) . '</p></section>';
        if ($result) {
            echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Pilot result</span><h2>' . labs_e((string)($result['pilot']['pilot_status'] ?? 'Complete')) . '</h2><p class="labs-copy">Delivery status: ' . labs_e((string)($result['verification']['delivery_status'] ?? 'unknown')) . '. Raw recipient data and adapter payloads are excluded.</p></div></div></section>';
        }
        if ($active) {
            echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Active pilot</span><h2>' . labs_e((string)$active['pilot_status']) . '</h2><p class="labs-copy">Handoff ' . labs_e((string)$active['handoff_public_id']) . ' remains the only allowed pilot until verification reaches a terminal outcome.</p></div></div>';
            echo '<div class="labs-kpis"><div class="labs-kpi"><span>Handoff</span><strong>' . labs_e((string)$active['handoff_status']) . '</strong></div><div class="labs-kpi"><span>Reward</span><strong>' . labs_e((string)$active['reward_status']) . '</strong></div><div class="labs-kpi"><span>Read-back</span><strong>' . labs_e((string)($active['last_verification_status'] ?: 'pending')) . '</strong></div></div>';
            echo '<form method="post" class="labs-stage30-form">' . (function_exists('tl_security_csrf_field') ? tl_security_csrf_field() : '') . '<input type="hidden" name="training_action" value="stage896_verify_active_pilot"><button class="labs-btn labs-btn-primary" type="submit">Run Read-Back Verification</button></form></section>';
        } else {
            echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">One-at-a-time issuance</span><h2>Select one eligible handoff</h2><p class="labs-copy">Re-enter the exact linked Microgifter user ID and the confirmation phrase. No batch or scheduled processing is used.</p></div></div>';
            $candidates = (array)$summary['candidates'];
            echo '<form method="post" class="labs-stage30-form">' . (function_exists('tl_security_csrf_field') ? tl_security_csrf_field() : '') . '<input type="hidden" name="training_action" value="stage896_run_pilot">';
            echo '<label>Eligible handoff<select name="handoff_id" required><option value="">Select one reward</option>';
            foreach ($candidates as $candidate) {
                $label = (string)$candidate['reward_label'] . ' · ' . (string)$candidate['participant_label'] . ' · $' . number_format(((int)$candidate['value_cents']) / 100, 2) . ' · MG user ' . (string)$candidate['microgifter_user_id'];
                echo '<option value="' . labs_e((string)$candidate['handoff_id']) . '">' . labs_e($label) . '</option>';
            }
            echo '</select></label>';
            echo '<label>Confirm linked Microgifter user ID<input type="number" min="1" name="confirm_microgifter_user_id" required></label>';
            echo '<label>Type ISSUE ONE PILOT<input type="text" name="confirmation_phrase" autocomplete="off" required></label>';
            echo '<button class="labs-btn labs-btn-primary" type="submit"' . (empty($summary['ready_to_issue']) || !$candidates ? ' disabled' : '') . '>Issue One Pilot Reward</button></form>';
            if (!$candidates) echo '<div class="labs-empty-state"><strong>No eligible pilot handoffs</strong><p>Create or enqueue one low-value reward and verify its active account link.</p></div>';
            echo '</section>';
        }
        echo '<section class="labs-safe-note">Required rollout state: Stage 895 passed and fresh; Stage 894 lookup ready; reconciliation and manual processing enabled; direct production adapter ready; scheduled worker disabled. Do not enable cron during Stage 896.</section>';
    }
}

if (!function_exists('tl_stage896_render_reward_bridge_panel')) {
    function tl_stage896_render_reward_bridge_panel(): void
    {
        if (!function_exists('labs_e') || !function_exists('labs_url')) return;
        $summary = tl_stage896_summary();
        $active = $summary['active_pilot'] ?? null;
        echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 896</span><h2>Limited Reward Issuance Pilot</h2><p class="labs-copy">One manually selected delivery, immediate signed read-back, and no second pilot until terminal evidence exists.</p></div><a class="labs-btn labs-btn-primary" href="' . labs_e(labs_url('/admin/reward-pilot.php')) . '">Open Pilot Control</a></div>';
        echo '<div class="labs-kpis"><div class="labs-kpi"><span>Readiness</span><strong>' . (int)$summary['score'] . '%</strong></div><div class="labs-kpi"><span>Active</span><strong>' . ($active ? 'Yes' : 'No') . '</strong></div><div class="labs-kpi"><span>Worker</span><strong>' . (!empty($summary['scheduled_worker_disabled']) ? 'Off' : 'STOP') . '</strong></div><div class="labs-kpi"><span>Candidates</span><strong>' . (int)$summary['candidate_count'] . '</strong></div></div></section>';
    }
}
