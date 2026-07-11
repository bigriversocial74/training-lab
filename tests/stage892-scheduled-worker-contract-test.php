<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/training-lab-stage892-scheduled-worker.php';

$failures = [];
$check = static function (bool $condition, string $label) use (&$failures): void {
    if (!$condition) $failures[] = $label;
};
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';

$service = $read('includes/training-lab-stage892-scheduled-worker.php');
$cli = $read('bin/reward-handoff-worker.php');
$deny = $read('bin/.htaccess');
$statusApi = $read('api/training/reward-handoff-worker-status.php');
$advancedOperations = $read('admin/reward-operations.php');
$config = $read('config-example.php');
$labsConfig = $read('labs/config-example.php');

$defaultArgs = tl_stage892_parse_cli_arguments(['worker.php']);
$check($defaultArgs['mode'] === 'observe', 'CLI defaults to observe mode');
$check(empty($defaultArgs['explicit_process']), 'default CLI does not imply process permission');
$processArgs = tl_stage892_parse_cli_arguments(['worker.php', '--process', '--limit=7']);
$check($processArgs['mode'] === 'process', 'CLI parses process mode');
$check(!empty($processArgs['explicit_process']), 'process mode records explicit flag');
$check((int)$processArgs['limit'] === 7, 'CLI parses bounded limit');
$conflictArgs = tl_stage892_parse_cli_arguments(['worker.php', '--observe', '--process']);
$check(!empty($conflictArgs['errors']), 'CLI rejects conflicting modes');
$badLimit = tl_stage892_parse_cli_arguments(['worker.php', '--limit=0']);
$check(!empty($badLimit['errors']), 'CLI rejects invalid limit');

$tempLock = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'stage892-contract-' . bin2hex(random_bytes(6)) . '.lock';
$lockStatus = tl_stage892_lock_path_status($tempLock);
$check(!empty($lockStatus['ready']), 'temporary lock path is ready');
$firstLock = tl_stage892_acquire_lock($tempLock, 'contract-run-1', 'observe');
$check(!empty($firstLock['acquired']), 'first worker acquires lock');
$secondLock = tl_stage892_acquire_lock($tempLock, 'contract-run-2', 'observe');
$check(empty($secondLock['acquired']), 'overlapping worker is rejected');
tl_stage892_release_lock($firstLock['handle'] ?? null);
@unlink($tempLock);

$repoLock = $root . '/stage892-worker.lock';
$repoLockStatus = tl_stage892_lock_path_status($repoLock);
$check(empty($repoLockStatus['ready']), 'lock path inside repository is rejected');
$check(empty($repoLockStatus['outside_repository_tree']), 'repository lock path is identified');

$check(str_contains($cli, "PHP_SAPI !== 'cli'"), 'worker entrypoint is CLI-only');
$check(str_contains($cli, "'mode'=>(string)\$parsed['mode']"), 'CLI passes parsed mode to service');
$check(str_contains($cli, "'explicit_process'=>!empty(\$parsed['explicit_process'])"), 'CLI passes explicit process proof');
$check(str_contains($cli, 'exit(max(0, min(255'), 'CLI returns bounded process exit code');
$check(str_contains($deny, 'Require all denied') || str_contains($deny, 'Deny from all'), 'worker directory denies web access');

$check(str_contains($service, 'LOCK_EX | LOCK_NB'), 'worker uses non-blocking exclusive lock');
$check(str_contains($service, 'outside_repository_tree'), 'worker validates lock location');
$check(str_contains($service, "if (\$mode !== 'observe' && empty(\$config['worker_enabled']))"), 'recover and process require worker enablement');
$check(str_contains($service, "if (\$mode === 'process' && empty(\$input['explicit_process']))"), 'process requires explicit CLI flag');
$check(str_contains($service, "if (\$mode === 'process' && empty(\$adapter['can_process']))"), 'process requires all adapter gates');
$check(str_contains($service, "if (\$mode === 'recover' || \$mode === 'process')"), 'observe mode excludes recovery writes');
$check(str_contains($service, "if (\$mode === 'process')"), 'adapter processing is isolated to process mode');
$check(str_contains($service, 'microtime(true) >= $deadline'), 'worker checks runtime deadline between handoffs');
$check(str_contains($service, 'tl_stage892_due_handoff_ids'), 'worker selects bounded due handoffs');
$check(str_contains($service, 'tl_stage891_process_handoff_owned'), 'worker uses lease-owned processor');
$check(str_contains($service, 'stage892_worker_started'), 'worker records start audit event');
$check(str_contains($service, 'stage892_worker_completed'), 'worker records completion audit event');
$check(str_contains($service, 'stage892_worker_failed'), 'worker records failure audit event');
$check(str_contains($service, 'stage892_worker_skipped'), 'worker records gated skip event');
$check(!str_contains($service, 'TL_MICROGIFTER_DEVELOPER_API_KEY'), 'worker does not read or log developer key');
$check(!str_contains($service, 'identity_shared_secret'), 'worker does not read or log identity secret');
$check(str_contains($service, "'credentials_are_not_logged'=>true"), 'worker declares credential exclusion boundary');

$check(str_contains($statusApi, "if (\$method !== 'GET')"), 'worker status API is GET-only');
$check(str_contains($statusApi, 'tl_auth_role_allowed'), 'worker status API requires manager role');
$check(str_contains($statusApi, 'tl_security_json_exception'), 'worker status API uses safe JSON errors');
$check(str_contains($advancedOperations, 'tl_stage892_render_admin_panel'), 'Advanced Reward Operations renders the worker panel');

foreach ([$config, $labsConfig] as $index => $example) {
    $label = $index === 0 ? 'root config example' : 'labs config example';
    $check(str_contains($example, "'reward_handoff_worker_enabled' => false"), $label . ' keeps worker disabled');
    $check(str_contains($example, "'reward_handoff_worker_batch_size' => 10"), $label . ' documents batch size');
    $check(str_contains($example, "'reward_handoff_worker_max_runtime_seconds' => 45"), $label . ' documents runtime budget');
    $check(str_contains($example, "'reward_handoff_worker_lock_file'"), $label . ' documents external lock file');
}

$check(!is_file($root . '/database/stage892_scheduled_reward_handoff_worker.sql'), 'Stage 892 requires no SQL migration');

if ($failures) {
    fwrite(STDERR, "Stage 892 scheduled worker contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Stage 892 scheduled worker contract passed.\n";
