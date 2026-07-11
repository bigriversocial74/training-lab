#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "The PHP zip extension is required to verify a release package.\n");
    exit(1);
}

$options = getopt('', ['file:']);
$file = (string)($options['file'] ?? ($argv[1] ?? ''));
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php ./bin/verify-release-package.php --file=/path/to/package.zip\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($file) !== true) {
    fwrite(STDERR, "Could not open release package.\n");
    exit(1);
}

$failures = [];
$entries = [];
for ($index = 0; $index < $zip->numFiles; $index++) {
    $name = (string)$zip->getNameIndex($index);
    $entries[$name] = true;
    if ($name === '' || str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name)) {
        $failures[] = 'Unsafe archive path: ' . $name;
    }
    if (!str_starts_with($name, 'labs/')) $failures[] = 'Archive entry is outside the outer labs folder: ' . $name;
    $relative = preg_replace('#^labs/#', '', $name);
    if (preg_match('#(^|/)(?:\.env(?:\..*)?|config\.php)$#i', (string)$relative)) {
        $failures[] = 'Private configuration must not be packaged: ' . $name;
    }
}

$manifestPath = 'labs/release-manifest.json';
$manifestJson = $zip->getFromName($manifestPath);
if (!is_string($manifestJson) || $manifestJson === '') {
    $failures[] = 'Release manifest is missing.';
    $manifest = [];
} else {
    try {
        $manifest = json_decode($manifestJson, true, 128, JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        $manifest = [];
        $failures[] = 'Release manifest JSON is invalid.';
    }
}

if (($manifest['format'] ?? '') !== 'training-lab-release-manifest-v1') {
    $failures[] = 'Unexpected release manifest format.';
}
if (($manifest['package_root'] ?? '') !== 'labs') {
    $failures[] = 'Release package root must be labs.';
}
if (empty($manifest['deployment_contract']['preserve_active_labs_config_php'])) {
    $failures[] = 'Manifest must preserve active /labs/config.php.';
}
if (empty($manifest['deployment_contract']['does_not_enable_reward_delivery'])) {
    $failures[] = 'Manifest must preserve reward-delivery gates.';
}

$requiredEntries = [
    'labs/index.php',
    'labs/signin.php',
    'labs/app/index.php',
    'labs/admin/index.php',
    'labs/admin/live-acceptance.php',
    'labs/bin/live-acceptance.php',
    'labs/labs/config-example.php',
];
foreach ($requiredEntries as $required) {
    if (!isset($entries[$required])) $failures[] = 'Required archive entry is missing: ' . $required;
}

$manifestFiles = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
foreach ($manifestFiles as $entry) {
    $relative = (string)($entry['path'] ?? '');
    $expectedHash = (string)($entry['sha256'] ?? '');
    $archivePath = 'labs/' . ltrim($relative, '/');
    $contents = $zip->getFromName($archivePath);
    if (!is_string($contents)) {
        $failures[] = 'Manifest file is missing from archive: ' . $archivePath;
        continue;
    }
    if ($expectedHash === '' || !hash_equals($expectedHash, hash('sha256', $contents))) {
        $failures[] = 'Hash mismatch: ' . $archivePath;
    }
}
$zip->close();

if ($failures) {
    fwrite(STDERR, "Release package verification failed:\n- " . implode("\n- ", array_unique($failures)) . "\n");
    exit(1);
}

echo "Release package verified.\n";
echo 'Archive SHA-256: ' . hash_file('sha256', $file) . "\n";
echo 'Manifest files: ' . count($manifestFiles) . "\n";
echo "Private config excluded: yes\n";
