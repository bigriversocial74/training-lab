<?php
declare(strict_types=1);

/**
 * Stage 898 — Scheduled Worker Canary & Monitoring v1.
 *
 * Runs at most one automatically selected reward through the proven Stage 896
 * single-item pilot path. The normal Stage 892 worker remains disabled. Any
 * uncertain result latches a pause that must be reviewed and acknowledged.
 */
require_once __DIR__ . '/training-lab-stage897-controlled-batch-rollout.php';

if (!function_exists('tl_stage898_bool')) {
    function tl_stage898_bool($value, bool $default = false): bool
    {
        if (is_bool($value)) return $value;
        if ($value === null || $value === '') return $default;
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed === null ? $default : $parsed;
    }
}

if (!function_exists('tl_stage898_config')) {
    function tl_stage898_config(): array
    {
        $root = function_exists('tl_security_config') ? tl_security_config() : [];
        $enabled = getenv('TL_STAGE898_WORKER_CANARY_ENABLED');
        $maxValue = getenv('TL_STAGE898_MAX_VALUE_CENTS');
        $minInterval = getenv('TL_STAGE898_MIN_INTERVAL_SECONDS');
        $evidenceAge = getenv('TL_STAGE898_STAGE897_EVIDENCE_MAX_AGE_SECONDS');
        $staleAfter = getenv('TL_STAGE898_STALE_AFTER_SECONDS');
        $healthWindow = getenv('TL_STAGE898_HEALTH_WINDOW');
        $actor = getenv('TL_STAGE898_ACTOR_USER_ID');
        $lockFile = getenv('TL_STAGE898_LOCK_FILE');
        $defaultLock = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'training-lab-stage898-worker-canary.lock';
        $resolvedLock = trim((string)($lockFile !== false && $lockFile !== ''
            ? $lockFile
            : ($root['stage898_lock_file'] ?? $defaultLock)));
        if ($resolvedLock === '') $resolvedLock = $defaultLock;
        return [
            'enabled'=>tl_stage898_bool($enabled !== false ? $enabled : ($root['stage898_worker_canary_enabled'] ?? false), false),
            'max_value_cents'=>max(0, min(2500, (int)($maxValue !== false && $maxValue !== '' ? $maxValue : ($root['stage898_max_value_cents'] ?? 1000)))),
            'min_interval_seconds'=>max(60, min(86400, (int)($minInterval !== false && $minInterval !== '' ? $minInterval : ($root['stage898_min_interval_seconds'] ?? 900)))),
            'stage897_evidence_max_age_seconds'=>max(300, min(1209600, (int)($evidenceAge !== false && $evidenceAge !== '' ? $evidenceAge : ($root['stage898_stage897_evidence_max_age_seconds'] ?? 604800)))),
            'stale_after_seconds'=>max(300, min(604800, (int)($staleAfter !== false && $staleAfter !== '' ? $staleAfter : ($root['stage898_stale_after_seconds'] ?? 7200)))),
            'health_window'=>max(5, min(100, (int)($healthWindow !== false && $healthWindow !== '' ? $healthWindow : ($root['stage898_health_window'] ?? 20)))),
            'actor_user_id'=>max(1, (int)($actor !== false && $actor !== '' ? $actor : ($root['stage898_actor_user_id'] ?? 1))),
            'lock_file'=>$resolvedLock,
            'pause_ack_phrase'=>'ACKNOWLEDGE CANARY PAUSE',
        ];
    }
}

if (!function_exists('tl_stage898_json')) {
    function tl_stage898_json($value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('tl_stage898_fingerprint')) {
    function tl_stage898_fingerprint($value): string
    {
        $value = trim((string)$value);
        return $value === '' ? '' : substr(hash('sha256', $value), 0, 16);
    }
}

if (!function_exists('tl_stage898_lock_status')) {
    function tl_stage898_lock_status(string $path): array
    {
        $path = trim($path);
        $directory = dirname($path);
        $root = realpath(dirname(__DIR__));
        $realDirectory = realpath($directory);
        $insideRepository = false;
        if ($root !== false && $realDirectory !== false) {
            $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $insideRepository = $realDirectory === $root
                || str_starts_with($realDirectory . DIRECTORY_SEPARATOR, $prefix);
        }
        return [
            'path_present'=>$path !== '',
            'directory_exists'=>is_dir($directory),
            'directory_writable'=>is_dir($directory) && is_writable($directory),
            'outside_repository_tree'=>!$insideRepository,
            'display_name'=>basename($path),
            'ready'=>$path !== '' && is_dir($directory) && is_writable($directory) && !$insideRepository,
        ];
    }
}

if (!function_exists('tl_stage898_acquire_lock')) {
    function tl_stage898_acquire_lock(string $path, string $runId): array
    {
        $status = tl_stage898_lock_status($path);
        if (empty($status['ready'])) {
            throw new RuntimeException('Stage 898 requires a writable lock directory outside the deployed repository tree.');
        }
        $handle = @fopen($path, 'c+');
        if (!is_resource($handle)) throw new RuntimeException('Stage 898 could not open the canary lock file.');
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return ['acquired'=>false,'handle'=>null,'status'=>$status];
        }
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode([
            'run_id'=>$runId,
            'mode'=>'canary',
            'pid'=>function_exists('getmypid') ? getmypid() : null,
            'started_at'=>gmdate('c'),
        ], JSON_UNESCAPED_SLASHES));
        fflush($handle);
        return ['acquired'=>true,'handle'=>$handle,'status'=>$status];
    }
}

if (!function_exists('tl_stage898_release_lock')) {
    function tl_stage898_release_lock($handle): void
    {
        if (!is_resource($handle)) return;
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}

if (!function_exists('tl_stage898_log')) {
    function tl_stage898_log(PDO $pdo, int $actor, string $eventType, array $metadata, ?int $subjectId = null): void
    {
        $safe = [
            'run_id'=>(string)($metadata['run_id'] ?? ''),
            'status'=>(string)($metadata['status'] ?? ''),
            'reason'=>(string)($metadata['reason'] ?? ''),
            'pause_reason'=>(string)($metadata['pause_reason'] ?? ''),
            'exit_code'=>(int)($metadata['exit_code'] ?? 0),
            'handoff_id'=>(int)($metadata['handoff_id'] ?? 0),
            'reward_event_id'=>(int)($metadata['reward_event_id'] ?? 0),
            'value_cents'=>(int)($metadata['value_cents'] ?? 0),
            'delivery_status'=>(string)($metadata['delivery_status'] ?? ''),
            'pilot_status'=>(string)($metadata['pilot_status'] ?? ''),
            'duration_ms'=>(int)($metadata['duration_ms'] ?? 0),
            'stage897_batch_id'=>(string)($metadata['stage897_batch_id'] ?? ''),
            'handoff_reference_fingerprint'=>(string)($metadata['handoff_reference_fingerprint'] ?? ''),
            'microgifter_user_fingerprint'=>(string)($metadata['microgifter_user_fingerprint'] ?? ''),
            'queue_due'=>(int)($metadata['queue_due'] ?? 0),
            'queue_failed'=>(int)($metadata['queue_failed'] ?? 0),
            'queue_quarantined'=>(int)($metadata['queue_quarantined'] ?? 0),
            'raw_recipient_secret_signature_nonce_payload_and_response_excluded'=>true,
        ];
        tl_log_event($pdo, $actor, $subjectId ? 'reward_event' : 'system', $subjectId, $eventType, $safe);
    }
}

if (!function_exists('tl_stage898_stage897_evidence')) {
    function tl_stage898_stage897_evidence(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query("SELECT public_id,event_type,metadata_json,created_at FROM training_events WHERE event_type IN ('stage897_controlled_batch_completed','stage897_controlled_batch_paused','stage897_controlled_batch_pause_acknowledged') ORDER BY id DESC LIMIT 1");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        } catch (Throwable $e) {
            $row = false;
        }
        if (!$row) {
            return ['found'=>false,'clean'=>false,'fresh'=>false,'age_seconds'=>null,'batch_id'=>'','recorded_at'=>''];
        }
        $metadata = tl_stage898_json($row['metadata_json'] ?? null);
        $created = strtotime((string)($row['created_at'] ?? '')) ?: 0;
        $age = $created > 0 ? max(0, time() - $created) : null;
        $eventType = (string)($row['event_type'] ?? '');
        $selected = (int)($metadata['selected_count'] ?? 0);
        $processed = (int)($metadata['processed_count'] ?? 0);
        $verified = (int)($metadata['verified_count'] ?? 0);
        $clean = $eventType === 'stage897_controlled_batch_completed'
            && (string)($metadata['status'] ?? '') === 'completed'
            && $selected >= 2
            && $processed === $selected
            && $verified === $selected;
        $fresh = $age !== null && $age <= (int)tl_stage898_config()['stage897_evidence_max_age_seconds'];
        return [
            'found'=>true,
            'clean'=>$clean,
            'fresh'=>$fresh,
            'age_seconds'=>$age,
            'event_type'=>$eventType,
            'batch_id'=>(string)($metadata['batch_id'] ?? ''),
            'selected_count'=>$selected,
            'processed_count'=>$processed,
            'verified_count'=>$verified,
            'recorded_at'=>(string)($row['created_at'] ?? ''),
        ];
    }
}

if (!function_exists('tl_stage898_pause_state')) {
    function tl_stage898_pause_state(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query("SELECT public_id,event_type,metadata_json,created_at FROM training_events WHERE event_type IN ('stage898_worker_canary_paused','stage898_worker_canary_pause_acknowledged') ORDER BY id DESC LIMIT 1");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        } catch (Throwable $e) {
            $row = false;
        }
        if (!$row) return ['found'=>false,'paused'=>false,'run_id'=>'','reason'=>'','recorded_at'=>''];
        $metadata = tl_stage898_json($row['metadata_json'] ?? null);
        $paused = (string)($row['event_type'] ?? '') === 'stage898_worker_canary_paused';
        return [
            'found'=>true,
            'paused'=>$paused,
            'run_id'=>(string)($metadata['run_id'] ?? ''),
            'reason'=>(string)($metadata['pause_reason'] ?? $metadata['reason'] ?? ''),
            'recorded_at'=>(string)($row['created_at'] ?? ''),
        ];
    }
}

if (!function_exists('tl_stage898_recent_runs')) {
    function tl_stage898_recent_runs(PDO $pdo, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        try {
            $stmt = $pdo->query("SELECT public_id,event_type,metadata_json,created_at FROM training_events WHERE event_type IN ('stage898_worker_canary_completed','stage898_worker_canary_idle','stage898_worker_canary_paused','stage898_worker_canary_failed','stage898_worker_canary_skipped') ORDER BY id DESC LIMIT " . $limit);
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable $e) {
            return [];
        }
        return array_map(static function (array $row): array {
            return [
                'event_id'=>(string)($row['public_id'] ?? ''),
                'event_type'=>(string)($row['event_type'] ?? ''),
                'created_at'=>(string)($row['created_at'] ?? ''),
                'run'=>tl_stage898_json($row['metadata_json'] ?? null),
            ];
        }, $rows);
    }
}

if (!function_exists('tl_stage898_last_attempt')) {
    function tl_stage898_last_attempt(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query("SELECT event_type,metadata_json,created_at FROM training_events WHERE event_type IN ('stage898_worker_canary_completed','stage898_worker_canary_idle','stage898_worker_canary_paused','stage898_worker_canary_failed') ORDER BY id DESC LIMIT 1");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        } catch (Throwable $e) {
            $row = false;
        }
        if (!$row) return ['found'=>false,'age_seconds'=>null,'created_at'=>'','event_type'=>''];
        $created = strtotime((string)($row['created_at'] ?? '')) ?: 0;
        return [
            'found'=>true,
            'age_seconds'=>$created > 0 ? max(0, time() - $created) : null,
            'created_at'=>(string)($row['created_at'] ?? ''),
            'event_type'=>(string)($row['event_type'] ?? ''),
            'run'=>tl_stage898_json($row['metadata_json'] ?? null),
        ];
    }
}

if (!function_exists('tl_stage898_queue_metrics')) {
    function tl_stage898_queue_metrics(PDO $pdo): array
    {
        $counts = ['queued'=>0,'failed'=>0,'blocked'=>0,'processing'=>0,'delivered'=>0,'due'=>0,'quarantined'=>0];
        try {
            $stmt = $pdo->query('SELECT handoff_status,COUNT(*) AS total FROM training_reward_handoffs GROUP BY handoff_status');
            foreach ($stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $row) {
                $status = (string)($row['handoff_status'] ?? '');
                if (array_key_exists($status, $counts)) $counts[$status] = (int)($row['total'] ?? 0);
            }
            $maxAttempts = (int)tl_stage890_config()['max_attempts'];
            $due = $pdo->prepare("SELECT COUNT(*) FROM training_reward_handoffs WHERE handoff_status IN ('queued','failed') AND (next_attempt_at IS NULL OR next_attempt_at<=UTC_TIMESTAMP()) AND attempt_count<?");
            $due->execute([$maxAttempts]);
            $counts['due'] = (int)$due->fetchColumn();
            $quarantined = $pdo->query("SELECT COUNT(*) FROM training_reward_handoffs WHERE failure_code='external_delivery_confirmation_required' OR COALESCE(metadata_json,'') LIKE '%\"reconciliation_required\":true%'");
            $counts['quarantined'] = $quarantined ? (int)$quarantined->fetchColumn() : 0;
        } catch (Throwable $e) {
            $counts['query_error'] = 1;
        }
        return $counts;
    }
}
