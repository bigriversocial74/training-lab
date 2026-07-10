<?php
declare(strict_types=1);

/**
 * Stage 895 — Signed Integration Acceptance & Controlled Rollout v1.
 *
 * Runs read-only, HMAC-signed acceptance probes against the Stage 894
 * Microgifter lookup endpoint. Production reconciliation, handoff processing,
 * and the scheduled worker must remain disabled for every live suite.
 */
require_once __DIR__ . '/training-lab-stage894-reconciliation-bootstrap.php';
require_once __DIR__ . '/training-lab-stage892-scheduled-worker.php';

if (!function_exists('tl_stage895_bool')) {
    function tl_stage895_bool($value, bool $default = false): bool
    {
        if (is_bool($value)) return $value;
        if ($value === null || $value === '') return $default;
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed === null ? $default : $parsed;
    }
}

if (!function_exists('tl_stage895_config')) {
    function tl_stage895_config(): array
    {
        $root = function_exists('tl_security_config') ? tl_security_config() : [];
        $enabled = getenv('TL_STAGE895_LIVE_ACCEPTANCE_ENABLED');
        return [
            'live_acceptance_enabled'=>tl_stage895_bool(
                $enabled !== false ? $enabled : ($root['stage895_live_acceptance_enabled'] ?? false),
                false
            ),
        ];
    }
}

if (!function_exists('tl_stage895_reference')) {
    function tl_stage895_reference($value): string
    {
        $value = trim((string)$value);
        if (strlen($value) > 190) {
            throw new TlHttpException('The acceptance lookup reference is too long.', 422, 'stage895_reference_too_long');
        }
        if ($value !== '' && preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new TlHttpException('The acceptance lookup reference contains invalid characters.', 422, 'stage895_reference_invalid');
        }
        return $value;
    }
}

if (!function_exists('tl_stage895_processing_gates')) {
    function tl_stage895_processing_gates(): array
    {
        $handoff = function_exists('tl_stage890_config') ? tl_stage890_config() : ['processing_enabled'=>false];
        $reconciliation = function_exists('tl_stage893_config') ? tl_stage893_config() : ['enabled'=>false];
        $worker = function_exists('tl_stage892_config') ? tl_stage892_config() : ['worker_enabled'=>false];
        return [
            'reconciliation_disabled'=>empty($reconciliation['enabled']),
            'handoff_processing_disabled'=>empty($handoff['processing_enabled']),
            'scheduled_worker_disabled'=>empty($worker['worker_enabled']),
            'all_closed'=>empty($reconciliation['enabled']) && empty($handoff['processing_enabled']) && empty($worker['worker_enabled']),
        ];
    }
}

if (!function_exists('tl_stage895_readiness')) {
    function tl_stage895_readiness(): array
    {
        $acceptance = tl_stage895_config();
        $client = function_exists('tl_stage894_summary') ? tl_stage894_summary() : [];
        $gates = tl_stage895_processing_gates();
        $checks = [
            'stage894_client_enabled'=>!empty($client['enabled']),
            'stage894_client_ready'=>!empty($client['ready']),
            'https_endpoint'=>!empty($client['endpoint']['https']),
            'endpoint_host_allowed'=>!empty($client['endpoint']['host_allowed']),
            'shared_secret_present'=>!empty($client['secret_present']),
            'curl_available'=>!empty($client['curl_available']),
            'live_acceptance_enabled'=>!empty($acceptance['live_acceptance_enabled']),
            'reconciliation_disabled'=>!empty($gates['reconciliation_disabled']),
            'handoff_processing_disabled'=>!empty($gates['handoff_processing_disabled']),
            'scheduled_worker_disabled'=>!empty($gates['scheduled_worker_disabled']),
        ];
        $passed = count(array_filter($checks));
        $score = (int)round(($passed / max(1, count($checks))) * 100);
        return [
            'stage'=>'Stage 895 Signed Integration Acceptance & Controlled Rollout v1',
            'checks'=>$checks,
            'score'=>$score,
            'ready_to_run'=>!empty($client['ready']) && !empty($acceptance['live_acceptance_enabled']) && !empty($gates['all_closed']),
            'client'=>$client,
            'processing_gates'=>$gates,
            'safe_boundaries'=>[
                'read_only_microgifter_lookup_only'=>true,
                'production_processing_must_remain_disabled'=>true,
                'shared_secret_not_returned'=>true,
                'raw_signatures_nonces_and_payloads_not_logged'=>true,
                'no_reward_issue_claim_redeem_or_wallet_mutation'=>true,
            ],
        ];
    }
}

if (!function_exists('tl_stage895_payload')) {
    function tl_stage895_payload(int $microgifterUserId, string $idempotencyKey = '', string $externalReference = ''): array
    {
        return [
            'contract'=>'training_lab_reward_reconciliation_v1',
            'source'=>'training_lab',
            'read_only'=>true,
            'microgifter_user_id'=>(string)$microgifterUserId,
            'idempotency_key'=>$idempotencyKey,
            'external_reference'=>$externalReference,
            'training_handoff_id'=>0,
            'training_handoff_public_id'=>'stage895-acceptance',
            'training_reward_event_id'=>0,
            'training_reward_public_id'=>'stage895-acceptance',
            'training_user_id'=>0,
        ];
    }
}

if (!function_exists('tl_stage895_transport')) {
    function tl_stage895_transport(array $payload, array $options = []): array
    {
        $config = tl_stage894_config();
        if (empty($config['ready'])) {
            throw new RuntimeException('The Stage 894 signed lookup client is not ready.');
        }
        $payload = tl_stage894_validate_payload($payload);
        try {
            $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('The Stage 895 probe request could not be encoded.');
        }

        $timestamp = (string)($options['timestamp'] ?? time());
        $nonce = (string)($options['nonce'] ?? bin2hex(random_bytes(24)));
        $signature = tl_stage894_signature((string)$config['secret'], $timestamp, $nonce, $rawBody);
        if (!empty($options['tamper_signature'])) {
            $signature = ($signature[0] ?? '0') === '0' ? ('1' . substr($signature, 1)) : ('0' . substr($signature, 1));
        }

        $responseHeaders = [];
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Microgifter-Training-Lab-Timestamp: ' . $timestamp,
            'X-Microgifter-Training-Lab-Nonce: ' . $nonce,
            'X-Microgifter-Training-Lab-Signature: ' . $signature,
        ];
        $handle = curl_init();
        if ($handle === false) throw new RuntimeException('The Stage 895 acceptance transport is unavailable.');
        $started = microtime(true);
        $optionsCurl = [
            CURLOPT_URL=>(string)$config['url'],
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>$rawBody,
            CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_HEADER=>false,
            CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_MAXREDIRS=>0,
            CURLOPT_CONNECTTIMEOUT=>(int)$config['connect_timeout_seconds'],
            CURLOPT_TIMEOUT=>(int)$config['timeout_seconds'],
            CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_USERAGENT=>'Microgifter-Training-Lab-Stage895-Acceptance/1.0',
            CURLOPT_HEADERFUNCTION=>static function ($curl, string $header) use (&$responseHeaders): int {
                $length = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $name = strtolower(trim($parts[0]));
                    if (in_array($name, ['x-request-id','content-type'], true)) {
                        $responseHeaders[$name] = trim($parts[1]);
                    }
                }
                return $length;
            },
        ];
        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) $optionsCurl[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTPS')) $optionsCurl[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
        curl_setopt_array($handle, $optionsCurl);
        $response = curl_exec($handle);
        $httpStatus = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_errno($handle);
        curl_close($handle);
        $durationMs = (int)round((microtime(true) - $started) * 1000);

        $decoded = null;
        $responseValid = false;
        if (is_string($response) && strlen($response) <= (int)$config['max_response_bytes']) {
            try {
                $candidate = json_decode($response, true, 32, JSON_THROW_ON_ERROR);
                if (is_array($candidate)) {
                    $decoded = $candidate;
                    $responseValid = true;
                }
            } catch (JsonException $e) {
                $decoded = null;
            }
        }
        $data = is_array($decoded['data'] ?? null) ? (array)$decoded['data'] : [];
        $errorCode = preg_replace('/[^a-z0-9_\-]/i', '', (string)($decoded['error_code'] ?? '')) ?: '';
        return [
            'transport_ok'=>is_string($response) && $curlError === 0,
            'response_valid'=>$responseValid,
            'http_status'=>$httpStatus,
            'remote_ok'=>!empty($decoded['ok']),
            'error_code'=>$errorCode,
            'request_id'=>mb_substr((string)($responseHeaders['x-request-id'] ?? ''), 0, 80),
            'duration_ms'=>$durationMs,
            'data'=>[
                'found'=>!empty($data['found']),
                'delivery_status'=>preg_replace('/[^a-z0-9_\-]/i', '', strtolower((string)($data['delivery_status'] ?? $data['status'] ?? ''))) ?: '',
                'read_only'=>!empty($data['read_only']),
            ],
        ];
    }
}

if (!function_exists('tl_stage895_probe')) {
    function tl_stage895_probe(string $id, string $label, string $status, array $transport = [], string $detail = ''): array
    {
        return [
            'id'=>$id,
            'label'=>$label,
            'status'=>in_array($status, ['passed','failed','skipped'], true) ? $status : 'failed',
            'http_status'=>(int)($transport['http_status'] ?? 0),
            'error_code'=>mb_substr((string)($transport['error_code'] ?? ''), 0, 80),
            'request_id'=>mb_substr((string)($transport['request_id'] ?? ''), 0, 80),
            'duration_ms'=>(int)($transport['duration_ms'] ?? 0),
            'found'=>!empty($transport['data']['found']),
            'delivery_status'=>mb_substr((string)($transport['data']['delivery_status'] ?? ''), 0, 50),
            'detail'=>mb_substr($detail, 0, 240),
        ];
    }
}

if (!function_exists('tl_stage895_log_suite')) {
    function tl_stage895_log_suite(array $result, int $actorUserId): void
    {
        if (!function_exists('tl_stage892_event')) return;
        $probes = [];
        foreach ((array)($result['probes'] ?? []) as $probe) {
            $probes[] = [
                'id'=>(string)($probe['id'] ?? ''),
                'status'=>(string)($probe['status'] ?? ''),
                'http_status'=>(int)($probe['http_status'] ?? 0),
                'error_code'=>(string)($probe['error_code'] ?? ''),
                'request_id'=>(string)($probe['request_id'] ?? ''),
                'duration_ms'=>(int)($probe['duration_ms'] ?? 0),
            ];
        }
        tl_stage892_event('stage895_signed_integration_acceptance', [
            'suite_id'=>(string)($result['suite_id'] ?? ''),
            'status'=>(string)($result['status'] ?? ''),
            'score'=>(int)($result['score'] ?? 0),
            'ready_for_reconciliation'=>!empty($result['ready_for_reconciliation']),
            'microgifter_user_fingerprint'=>(string)($result['evidence']['microgifter_user_fingerprint'] ?? ''),
            'reward_reference_fingerprint'=>(string)($result['evidence']['reward_reference_fingerprint'] ?? ''),
            'probes'=>$probes,
            'secrets_signatures_nonces_and_raw_payloads_excluded'=>true,
        ], $actorUserId);
    }
}

if (!function_exists('tl_stage895_run_suite')) {
    function tl_stage895_run_suite(array $input): array
    {
        $readiness = tl_stage895_readiness();
        if (empty($readiness['ready_to_run'])) {
            throw new TlHttpException('Stage 895 is not ready. Enable only the acceptance flag and keep all production processing gates closed.', 409, 'stage895_not_ready');
        }
        $microgifterUserId = max(0, (int)($input['microgifter_user_id'] ?? 0));
        if ($microgifterUserId < 1) {
            throw new TlHttpException('A valid Microgifter user ID is required for signed acceptance.', 422, 'stage895_microgifter_user_required');
        }
        $knownKey = tl_stage895_reference($input['known_idempotency_key'] ?? '');
        $knownExternal = tl_stage895_reference($input['known_external_reference'] ?? '');
        $wrongUserId = max(0, (int)($input['wrong_microgifter_user_id'] ?? 0));
        if ($wrongUserId === $microgifterUserId) $wrongUserId = 0;
        $actor = max(1, (int)($input['actor_user_id'] ?? 1));
        $suiteId = function_exists('tl_uuid') ? tl_uuid() : bin2hex(random_bytes(16));
        $syntheticKey = 'stage895-not-found-' . bin2hex(random_bytes(16));
        $probes = [];

        $probes[] = tl_stage895_probe('configuration', 'HTTPS, allowlist, secret, cURL and closed production gates', 'passed', [], 'All local readiness checks passed.');

        $validMissing = tl_stage895_transport(tl_stage895_payload($microgifterUserId, $syntheticKey));
        $validMissingPassed = !empty($validMissing['transport_ok']) && !empty($validMissing['response_valid'])
            && $validMissing['http_status'] === 200 && !empty($validMissing['remote_ok'])
            && empty($validMissing['data']['found']) && in_array((string)$validMissing['data']['delivery_status'], ['not_found','unknown'], true);
        $probes[] = tl_stage895_probe('valid_not_found', 'Valid signed read-only request and missing reward', $validMissingPassed ? 'passed' : 'failed', $validMissing, $validMissingPassed ? 'Signed request accepted and synthetic reward remained absent.' : 'Expected a successful read-only not-found response.');

        $tampered = tl_stage895_transport(tl_stage895_payload($microgifterUserId, 'stage895-tampered-' . bin2hex(random_bytes(12))), ['tamper_signature'=>true]);
        $tamperedPassed = $tampered['http_status'] === 401 && (string)$tampered['error_code'] === 'signature_invalid';
        $probes[] = tl_stage895_probe('tampered_signature', 'Tampered signature rejection', $tamperedPassed ? 'passed' : 'failed', $tampered, $tamperedPassed ? 'Microgifter rejected the modified signature.' : 'Expected signature_invalid with HTTP 401.');

        $expired = tl_stage895_transport(tl_stage895_payload($microgifterUserId, 'stage895-expired-' . bin2hex(random_bytes(12))), ['timestamp'=>time() - 1200]);
        $expiredPassed = $expired['http_status'] === 401 && (string)$expired['error_code'] === 'timestamp_expired';
        $probes[] = tl_stage895_probe('expired_timestamp', 'Expired timestamp rejection', $expiredPassed ? 'passed' : 'failed', $expired, $expiredPassed ? 'Microgifter rejected the expired request window.' : 'Expected timestamp_expired with HTTP 401.');

        $replayPayload = tl_stage895_payload($microgifterUserId, 'stage895-replay-' . bin2hex(random_bytes(12)));
        $replayTimestamp = time();
        $replayNonce = bin2hex(random_bytes(24));
        $replayFirst = tl_stage895_transport($replayPayload, ['timestamp'=>$replayTimestamp,'nonce'=>$replayNonce]);
        $replaySecond = tl_stage895_transport($replayPayload, ['timestamp'=>$replayTimestamp,'nonce'=>$replayNonce]);
        $replayPassed = $replayFirst['http_status'] === 200 && !empty($replayFirst['remote_ok'])
            && $replaySecond['http_status'] === 409 && (string)$replaySecond['error_code'] === 'request_replayed';
        $replayEvidence = $replaySecond;
        $replayEvidence['duration_ms'] = (int)$replayFirst['duration_ms'] + (int)$replaySecond['duration_ms'];
        $probes[] = tl_stage895_probe('replay_nonce', 'Nonce replay rejection', $replayPassed ? 'passed' : 'failed', $replayEvidence, $replayPassed ? 'First request succeeded and the identical replay was rejected.' : 'Expected the second identical request to return request_replayed with HTTP 409.');

        if ($knownKey !== '' || $knownExternal !== '') {
            $found = tl_stage895_transport(tl_stage895_payload($microgifterUserId, $knownKey, $knownExternal));
            $foundPassed = $found['http_status'] === 200 && !empty($found['remote_ok']) && !empty($found['data']['found']) && (string)$found['data']['delivery_status'] === 'delivered';
            $probes[] = tl_stage895_probe('known_reward_found', 'Known reward delivery confirmation', $foundPassed ? 'passed' : 'failed', $found, $foundPassed ? 'The identity-bound canonical reward was confirmed as previously delivered.' : 'Expected the supplied identity and reward reference to confirm delivery.');
        } else {
            $probes[] = tl_stage895_probe('known_reward_found', 'Known reward delivery confirmation', 'skipped', [], 'Provide a known idempotency key or external reference to complete production readiness.');
        }

        if (($knownKey !== '' || $knownExternal !== '') && $wrongUserId > 0) {
            $wrong = tl_stage895_transport(tl_stage895_payload($wrongUserId, $knownKey, $knownExternal));
            $wrongPassed = $wrong['http_status'] === 200 && !empty($wrong['remote_ok']) && empty($wrong['data']['found']);
            $probes[] = tl_stage895_probe('wrong_user', 'Wrong-user isolation', $wrongPassed ? 'passed' : 'failed', $wrong, $wrongPassed ? 'The unrelated Microgifter identity could not see the reward.' : 'Expected the unrelated identity to receive a read-only not-found result.');
        } else {
            $probes[] = tl_stage895_probe('wrong_user', 'Wrong-user isolation', 'skipped', [], 'Provide an unrelated Microgifter user ID and a known reward reference to complete production readiness.');
        }

        $requiredIds = ['configuration','valid_not_found','tampered_signature','expired_timestamp','replay_nonce','known_reward_found','wrong_user'];
        $passedCount = 0;
        $failedCount = 0;
        $skippedCount = 0;
        $requiredPassed = true;
        foreach ($probes as $probe) {
            if ($probe['status'] === 'passed') $passedCount++;
            elseif ($probe['status'] === 'failed') $failedCount++;
            else $skippedCount++;
            if (in_array($probe['id'], $requiredIds, true) && $probe['status'] !== 'passed') $requiredPassed = false;
        }
        $score = (int)round(($passedCount / max(1, count($probes))) * 100);
        $status = $failedCount > 0 ? 'failed' : ($requiredPassed ? 'passed' : 'incomplete');
        $result = [
            'stage'=>'Stage 895 Signed Integration Acceptance & Controlled Rollout v1',
            'suite_id'=>$suiteId,
            'status'=>$status,
            'score'=>$score,
            'counts'=>['passed'=>$passedCount,'failed'=>$failedCount,'skipped'=>$skippedCount,'total'=>count($probes)],
            'ready_for_reconciliation'=>$requiredPassed && $failedCount === 0 && !empty(tl_stage895_processing_gates()['all_closed']),
            'production_processing_locked'=>true,
            'probes'=>$probes,
            'evidence'=>[
                'microgifter_user_fingerprint'=>substr(hash('sha256', (string)$microgifterUserId), 0, 16),
                'reward_reference_fingerprint'=>($knownKey !== '' || $knownExternal !== '') ? substr(hash('sha256', $knownKey . '|' . $knownExternal), 0, 16) : '',
                'shared_secret_excluded'=>true,
                'raw_signatures_nonces_payloads_and_responses_excluded'=>true,
            ],
            'completed_at'=>gmdate('c'),
        ];
        tl_stage895_log_suite($result, $actor);
        return $result;
    }
}

if (!function_exists('tl_stage895_render_probe_table')) {
    function tl_stage895_render_probe_table(array $probes): void
    {
        if (!function_exists('labs_e')) return;
        echo '<div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>Probe</th><th>Status</th><th>HTTP</th><th>Evidence</th></tr></thead><tbody>';
        foreach ($probes as $probe) {
            $status = (string)($probe['status'] ?? 'failed');
            $class = $status === 'passed' ? 'good' : ($status === 'skipped' ? 'warn' : 'bad');
            $evidence = trim(implode(' · ', array_filter([
                (string)($probe['error_code'] ?? ''),
                (string)($probe['request_id'] ?? ''),
                (string)($probe['detail'] ?? ''),
            ])));
            echo '<tr><td><strong>' . labs_e((string)($probe['label'] ?? 'Probe')) . '</strong></td><td><span class="labs-pill is-' . labs_e($class) . '">' . labs_e($status) . '</span></td><td>' . (int)($probe['http_status'] ?? 0) . '</td><td>' . labs_e($evidence) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

if (!function_exists('tl_stage895_render_admin_page')) {
    function tl_stage895_render_admin_page(?array $result = null, string $error = ''): void
    {
        if (!function_exists('labs_e') || !function_exists('labs_url')) return;
        $readiness = tl_stage895_readiness();
        echo '<section class="labs-page-title"><div><span class="labs-eyebrow">Stage 895</span><h1>Signed Integration Acceptance</h1><p class="labs-copy">Run read-only cross-server security probes before enabling reconciliation or any reward-processing path.</p></div><a class="labs-btn" href="' . labs_e(labs_url('/api/training/integration-acceptance.php')) . '">View JSON Readiness</a></section>';
        echo '<section class="labs-kpis">';
        echo '<div class="labs-kpi"><span class="labs-muted">Readiness</span><strong>' . (int)$readiness['score'] . '%</strong><small>configuration and safety gates</small></div>';
        echo '<div class="labs-kpi"><span class="labs-muted">Live suite</span><strong>' . (!empty($readiness['ready_to_run']) ? 'Ready' : 'Locked') . '</strong><small>dedicated acceptance flag</small></div>';
        echo '<div class="labs-kpi"><span class="labs-muted">Production</span><strong>Locked</strong><small>reconciliation, processing, worker</small></div>';
        echo '<div class="labs-kpi"><span class="labs-muted">Mutation</span><strong>None</strong><small>read-only endpoint</small></div>';
        echo '</section>';

        if ($error !== '') echo '<section class="labs-card labs-error-card"><h2>Acceptance needs attention</h2><p class="labs-copy">' . labs_e($error) . '</p></section>';
        if ($result) {
            echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Suite ' . labs_e((string)($result['suite_id'] ?? '')) . '</span><h2>' . labs_e(ucfirst((string)($result['status'] ?? 'complete'))) . ' · ' . (int)($result['score'] ?? 0) . '%</h2><p class="labs-copy">Sanitized evidence was recorded without secrets, signatures, nonces, raw payloads, or raw responses.</p></div></div>';
            tl_stage895_render_probe_table((array)($result['probes'] ?? []));
            echo '</section>';
        }

        echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Controlled live suite</span><h2>Run signed acceptance probes</h2><p class="labs-copy">Use a real linked Microgifter user and one known reward reference. The wrong-user ID must belong to an unrelated account.</p></div></div>';
        echo '<form method="post" class="labs-stage30-form">' . (function_exists('tl_security_csrf_field') ? tl_security_csrf_field() : '');
        echo '<input type="hidden" name="training_action" value="stage895_run_signed_acceptance">';
        echo '<label>Microgifter user ID<input type="number" min="1" name="microgifter_user_id" required></label>';
        echo '<label>Known reward idempotency key<input type="text" maxlength="190" name="known_idempotency_key"></label>';
        echo '<label>Known external reference<input type="text" maxlength="190" name="known_external_reference"></label>';
        echo '<label>Unrelated Microgifter user ID<input type="number" min="1" name="wrong_microgifter_user_id"></label>';
        echo '<button class="labs-btn labs-btn-primary" type="submit">Run Read-Only Acceptance</button></form>';
        echo '<p class="labs-safe-note">Required server state: TL_STAGE895_LIVE_ACCEPTANCE_ENABLED=true while reconciliation, handoff processing, and the scheduled worker remain false.</p></section>';
    }
}

if (!function_exists('tl_stage895_render_reward_bridge_panel')) {
    function tl_stage895_render_reward_bridge_panel(): void
    {
        if (!function_exists('labs_e') || !function_exists('labs_url')) return;
        $readiness = tl_stage895_readiness();
        echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 895</span><h2>Signed Integration Acceptance</h2><p class="labs-copy">Complete read-only endpoint, signature, timestamp, replay, identity-isolation, found, and not-found acceptance before reconciliation is enabled.</p></div><a class="labs-btn labs-btn-primary" href="' . labs_e(labs_url('/admin/integration-acceptance.php')) . '">Open Acceptance</a></div><p class="labs-safe-note">Readiness ' . (int)$readiness['score'] . '% · Production processing remains locked.</p></section>';
    }
}
