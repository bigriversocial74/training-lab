<?php
declare(strict_types=1);

/**
 * Stage 899 — Canary Graduation & Limited Scheduled Processing v1.
 *
 * Graduates from Stage 898 one-item canaries to a separate CLI-only scheduler
 * that processes a maximum of two rewards per run. The Stage 892 worker,
 * Stage 897 manual batch, and Stage 898 canary must remain disabled while this
 * scheduler is active.
 */
require_once __DIR__ . '/training-lab-stage898-worker-canary-monitoring.php';

if (!function_exists('tl_stage899_bool')) {
    function tl_stage899_bool($value, bool $default = false): bool
    {
        if (is_bool($value)) return $value;
        if ($value === null || $value === '') return $default;
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed === null ? $default : $parsed;
    }
}

if (!function_exists('tl_stage899_json')) {
    function tl_stage899_json($value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('tl_stage899_fingerprint')) {
    function tl_stage899_fingerprint($value): string
    {
        $value = trim((string)$value);
        return $value === '' ? '' : substr(hash('sha256', $value), 0, 16);
    }
}

if (!function_exists('tl_stage899_config')) {
    function tl_stage899_config(): array
    {
        $root = function_exists('tl_security_config') ? tl_security_config() : [];
        $enabled = getenv('TL_STAGE899_LIMITED_SCHEDULER_ENABLED');
        $batch = getenv('TL_STAGE899_MAX_BATCH_SIZE');
        $itemValue = getenv('TL_STAGE899_MAX_ITEM_VALUE_CENTS');
        $totalValue = getenv('TL_STAGE899_MAX_TOTAL_VALUE_CENTS');
        $interval = getenv('TL_STAGE899_MIN_INTERVAL_SECONDS');
        $canaries = getenv('TL_STAGE899_MIN_VERIFIED_CANARIES');
        $canaryWindow = getenv('TL_STAGE899_CANARY_GRADUATION_WINDOW');
        $canaryAge = getenv('TL_STAGE899_CANARY_EVIDENCE_MAX_AGE_SECONDS');
        $healthWindow = getenv('TL_STAGE899_HEALTH_WINDOW');
        $successRate = getenv('TL_STAGE899_MIN_SUCCESS_RATE_PERCENT');
        $runtime = getenv('TL_STAGE899_MAX_RUNTIME_SECONDS');
        $staleAfter = getenv('TL_STAGE899_STALE_AFTER_SECONDS');
        $actor = getenv('TL_STAGE899_ACTOR_USER_ID');
        $lockFile = getenv('TL_STAGE899_LOCK_FILE');
        $defaultLock = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'training-lab-stage899-limited-scheduler.lock';
        $resolvedLock = trim((string)($lockFile !== false && $lockFile !== ''
            ? $lockFile
            : ($root['stage899_lock_file'] ?? $defaultLock)));
        if ($resolvedLock === '') $resolvedLock = $defaultLock;

        return [
            'enabled'=>tl_stage899_bool($enabled !== false ? $enabled : ($root['stage899_limited_scheduler_enabled'] ?? false), false),
            'max_batch_size'=>max(1, min(2, (int)($batch !== false && $batch !== '' ? $batch : ($root['stage899_max_batch_size'] ?? 2)))),
            'max_item_value_cents'=>max(0, min(2500, (int)($itemValue !== false && $itemValue !== '' ? $itemValue : ($root['stage899_max_item_value_cents'] ?? 1000)))),
            'max_total_value_cents'=>max(0, min(5000, (int)($totalValue !== false && $totalValue !== '' ? $totalValue : ($root['stage899_max_total_value_cents'] ?? 2000)))),
            'min_interval_seconds'=>max(300, min(86400, (int)($interval !== false && $interval !== '' ? $interval : ($root['stage899_min_interval_seconds'] ?? 1800)))),
            'min_verified_canaries'=>max(3, min(20, (int)($canaries !== false && $canaries !== '' ? $canaries : ($root['stage899_min_verified_canaries'] ?? 3)))),
            'canary_graduation_window'=>max(3, min(50, (int)($canaryWindow !== false && $canaryWindow !== '' ? $canaryWindow : ($root['stage899_canary_graduation_window'] ?? 5)))),
            'canary_evidence_max_age_seconds'=>max(300, min(1209600, (int)($canaryAge !== false && $canaryAge !== '' ? $canaryAge : ($root['stage899_canary_evidence_max_age_seconds'] ?? 86400)))),
            'health_window'=>max(3, min(100, (int)($healthWindow !== false && $healthWindow !== '' ? $healthWindow : ($root['stage899_health_window'] ?? 10)))),
            'min_success_rate_percent'=>max(90, min(100, (int)($successRate !== false && $successRate !== '' ? $successRate : ($root['stage899_min_success_rate_percent'] ?? 100)))),
            'max_runtime_seconds'=>max(20, min(180, (int)($runtime !== false && $runtime !== '' ? $runtime : ($root['stage899_max_runtime_seconds'] ?? 90)))),
            'stale_after_seconds'=>max(900, min(604800, (int)($staleAfter !== false && $staleAfter !== '' ? $staleAfter : ($root['stage899_stale_after_seconds'] ?? 10800)))),
            'actor_user_id'=>max(1, (int)($actor !== false && $actor !== '' ? $actor : ($root['stage899_actor_user_id'] ?? 1))),
            'lock_file'=>$resolvedLock,
            'suspension_ack_phrase'=>'ACKNOWLEDGE LIMITED PROCESSING SUSPENSION',
        ];
    }
}

if (!function_exists('tl_stage899_lock_status')) {
    function tl_stage899_lock_status(string $path): array
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

if (!function_exists('tl_stage899_acquire_lock')) {
    function tl_stage899_acquire_lock(string $path, string $runId): array
    {
        $status = tl_stage899_lock_status($path);
        if (empty($status['ready'])) {
            throw new RuntimeException('Stage 899 requires a writable lock directory outside the deployed repository tree.');
        }
        $handle = @fopen($path, 'c+');
        if (!is_resource($handle)) throw new RuntimeException('Stage 899 could not open the scheduler lock file.');
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return ['acquired'=>false,'handle'=>null,'status'=>$status];
        }
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode([
            'run_id'=>$runId,
            'mode'=>'limited_scheduled_processing',
            'pid'=>function_exists('getmypid') ? getmypid() : null,
            'started_at'=>gmdate('c'),
        ], JSON_UNESCAPED_SLASHES));
        fflush($handle);
        return ['acquired'=>true,'handle'=>$handle,'status'=>$status];
    }
}

if (!function_exists('tl_stage899_release_lock')) {
    function tl_stage899_release_lock($handle): void
    {
        if (!is_resource($handle)) return;
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}

if (!function_exists('tl_stage899_log')) {
    function tl_stage899_log(PDO $pdo, int $actor, string $eventType, array $metadata, ?int $subjectId = null): void
    {
        $safe = [
            'run_id'=>(string)($metadata['run_id'] ?? ''),
            'status'=>(string)($metadata['status'] ?? ''),
            'reason'=>(string)($metadata['reason'] ?? ''),
            'suspension_reason'=>(string)($metadata['suspension_reason'] ?? ''),
            'severity'=>(string)($metadata['severity'] ?? ''),
            'exit_code'=>(int)($metadata['exit_code'] ?? 0),
            'selected_count'=>(int)($metadata['selected_count'] ?? 0),
            'processed_count'=>(int)($metadata['processed_count'] ?? 0),
            'verified_count'=>(int)($metadata['verified_count'] ?? 0),
            'sequence'=>(int)($metadata['sequence'] ?? 0),
            'handoff_id'=>(int)($metadata['handoff_id'] ?? 0),
            'reward_event_id'=>(int)($metadata['reward_event_id'] ?? 0),
            'value_cents'=>(int)($metadata['value_cents'] ?? 0),
            'total_value_cents'=>(int)($metadata['total_value_cents'] ?? 0),
            'delivery_status'=>(string)($metadata['delivery_status'] ?? ''),
            'pilot_status'=>(string)($metadata['pilot_status'] ?? ''),
            'duration_ms'=>(int)($metadata['duration_ms'] ?? 0),
            'rolling_success_rate'=>(int)($metadata['rolling_success_rate'] ?? 0),
            'graduated_canary_count'=>(int)($metadata['graduated_canary_count'] ?? 0),
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

if (!function_exists('tl_stage899_canary_graduation')) {
    function tl_stage899_canary_graduation(PDO $pdo): array
    {
        $config = tl_stage899_config();
        $limit = max((int)$config['min_verified_canaries'], (int)$config['canary_graduation_window']);
        try {
            $stmt = $pdo->query("SELECT event_type,metadata_json,created_at FROM training_events WHERE event_type IN ('stage898_worker_canary_completed','stage898_worker_canary_paused','stage898_worker_canary_failed') ORDER BY id DESC LIMIT " . $limit);
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable $e) {
            $rows = [];
        }
        $verified = 0;
        $nonSuccess = 0;
        foreach ($rows as $row) {
            if ((string)($row['event_type'] ?? '') === 'stage898_worker_canary_completed') $verified++;
            else $nonSuccess++;
        }
        $latest = $rows[0] ?? null;
        $latestAt = $latest ? (strtotime((string)($latest['created_at'] ?? '')) ?: 0) : 0;
        $latestAge = $latestAt > 0 ? max(0, time() - $latestAt) : null;
        $latestVerified = $latest && (string)($latest['event_type'] ?? '') === 'stage898_worker_canary_completed';
        $fresh = $latestAge !== null && $latestAge <= (int)$config['canary_evidence_max_age_seconds'];
        $window = count($rows);
        $successRate = $window > 0 ? (int)round(($verified / $window) * 100) : 0;
        $graduated = $verified >= (int)$config['min_verified_canaries']
            && $nonSuccess === 0
            && $latestVerified
            && $fresh
            && $successRate >= (int)$config['min_success_rate_percent'];
        return [
            'graduated'=>$graduated,
            'verified_count'=>$verified,
            'non_success_count'=>$nonSuccess,
            'window_count'=>$window,
            'success_rate'=>$successRate,
            'minimum_verified_required'=>(int)$config['min_verified_canaries'],
            'minimum_success_rate'=>(int)$config['min_success_rate_percent'],
            'latest_verified'=>$latestVerified,
            'fresh'=>$fresh,
            'latest_age_seconds'=>$latestAge,
            'latest_at'=>(string)($latest['created_at'] ?? ''),
        ];
    }
}

if (!function_exists('tl_stage899_suspension_state')) {
    function tl_stage899_suspension_state(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query("SELECT public_id,event_type,metadata_json,created_at FROM training_events WHERE event_type IN ('stage899_limited_processing_suspended','stage899_limited_processing_suspension_acknowledged') ORDER BY id DESC LIMIT 1");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        } catch (Throwable $e) {
            $row = false;
        }
        if (!$row) return ['found'=>false,'suspended'=>false,'run_id'=>'','reason'=>'','severity'=>'','recorded_at'=>''];
        $metadata = tl_stage899_json($row['metadata_json'] ?? null);
        $suspended = (string)($row['event_type'] ?? '') === 'stage899_limited_processing_suspended';
        return [
            'found'=>true,
            'suspended'=>$suspended,
            'run_id'=>(string)($metadata['run_id'] ?? ''),
            'reason'=>(string)($metadata['suspension_reason'] ?? $metadata['reason'] ?? ''),
            'severity'=>(string)($metadata['severity'] ?? ''),
            'recorded_at'=>(string)($row['created_at'] ?? ''),
        ];
    }
}

if (!function_exists('tl_stage899_recent_runs')) {
    function tl_stage899_recent_runs(PDO $pdo, int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));
        try {
            $stmt = $pdo->query("SELECT public_id,event_type,metadata_json,created_at FROM training_events WHERE event_type IN ('stage899_limited_processing_completed','stage899_limited_processing_idle','stage899_limited_processing_suspended','stage899_limited_processing_skipped') ORDER BY id DESC LIMIT " . $limit);
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable $e) {
            return [];
        }
        return array_map(static function (array $row): array {
            return [
                'event_id'=>(string)($row['public_id'] ?? ''),
                'event_type'=>(string)($row['event_type'] ?? ''),
                'created_at'=>(string)($row['created_at'] ?? ''),
                'run'=>tl_stage899_json($row['metadata_json'] ?? null),
            ];
        }, $rows);
    }
}

if (!function_exists('tl_stage899_last_attempt')) {
    function tl_stage899_last_attempt(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query("SELECT event_type,metadata_json,created_at FROM training_events WHERE event_type IN ('stage899_limited_processing_completed','stage899_limited_processing_idle','stage899_limited_processing_suspended') ORDER BY id DESC LIMIT 1");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        } catch (Throwable $e) {
            $row = false;
        }
        if (!$row) return ['found'=>false,'age_seconds'=>null,'created_at'=>'','event_type'=>'','run'=>[]];
        $created = strtotime((string)($row['created_at'] ?? '')) ?: 0;
        return [
            'found'=>true,
            'age_seconds'=>$created > 0 ? max(0, time() - $created) : null,
            'created_at'=>(string)($row['created_at'] ?? ''),
            'event_type'=>(string)($row['event_type'] ?? ''),
            'run'=>tl_stage899_json($row['metadata_json'] ?? null),
        ];
    }
}
