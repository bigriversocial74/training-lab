#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "The PHP zip extension is required to build a release package.\n");
    exit(1);
}

$root = dirname(__DIR__);
$options = getopt('', ['output::', 'release::']);
$release = preg_replace('/[^a-zA-Z0-9._-]/', '-', (string)($options['release'] ?? getenv('TL_RELEASE_SHA') ?: getenv('GITHUB_SHA') ?: 'manual')) ?: 'manual';
$defaultOutput = $root . '/dist/training-lab-' . substr($release, 0, 16) . '.zip';
$output = (string)($options['output'] ?? $defaultOutput);
if (!str_starts_with($output, DIRECTORY_SEPARATOR)) $output = $root . '/' . ltrim($output, '/');
$outputDir = dirname($output);
if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Could not create output directory.\n");
    exit(1);
}

$excludedExact = [
    '.env', '.env.local', 'config.php', 'labs/config.php',
    'release-manifest.json',
];
$excludedPrefixes = ['.git/', '.github/', 'dist/', 'var/', 'storage/', 'node_modules/'];
$requiredFiles = [
    'index.php', 'signin.php', 'app/index.php', 'admin/index.php',
    'admin/live-acceptance.php', 'bin/live-acceptance.php',
    'bin/verify-release-package.php', 'labs/config-example.php',
];

$files = [];
$totalBytes = 0;
$maxFileBytes = 15 * 1024 * 1024;
$maxPackageBytes = 150 * 1024 * 1024;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile() || $file->isLink()) continue;
    $absolute = $file->getPathname();
    if (realpath($absolute) === realpath($output)) continue;
    $relative = str_replace('\\', '/', substr($absolute, strlen($root) + 1));
    if (in_array($relative, $excludedExact, true)) continue;
    $excluded = false;
    foreach ($excludedPrefixes as $prefix) {
        if (str_starts_with($relative, $prefix)) { $excluded = true; break; }
    }
    if ($excluded) continue;
    if (preg_match('#(^|/)(?:\.env(?:\..*)?|config\.php)$#i', $relative)) continue;
    if (preg_match('#\.(?:zip|tar|tgz|gz|bz2|7z)$#i', $relative)) continue;
    $bytes = (int)$file->getSize();
    if ($bytes > $maxFileBytes) {
        fwrite(STDERR, "Refusing oversized file: {$relative}\n");
        exit(1);
    }
    $totalBytes += $bytes;
    if ($totalBytes > $maxPackageBytes) {
        fwrite(STDERR, "Release package exceeds the configured size limit.\n");
        exit(1);
    }
    $files[$relative] = ['absolute' => $absolute, 'bytes' => $bytes, 'sha256' => hash_file('sha256', $absolute)];
}
ksort($files, SORT_STRING);
foreach ($requiredFiles as $required) {
    if (!isset($files[$required])) {
        fwrite(STDERR, "Required release file is missing: {$required}\n");
        exit(1);
    }
}

$manifestFiles = [];
foreach ($files as $relative => $metadata) {
    $manifestFiles[] = ['path' => $relative, 'bytes' => $metadata['bytes'], 'sha256' => $metadata['sha256']];
}
$manifest = [
    'format' => 'training-lab-release-manifest-v1',
    'release' => $release,
    'generated_at' => gmdate('c'),
    'package_root' => 'labs',
    'file_count' => count($manifestFiles),
    'total_bytes' => $totalBytes,
    'files' => $manifestFiles,
    'excluded_private_paths' => ['config.php', 'labs/config.php', '.env*'],
    'excluded_archive_artifacts' => ['*.zip', '*.tar', '*.tgz', '*.gz', '*.bz2', '*.7z'],
    'deployment_contract' => [
        'extract_outer_labs_then_move_contents_to_web_root' => true,
        'preserve_active_labs_config_php' => true,
        'does_not_enable_reward_delivery' => true,
    ],
];
$manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

$zip = new ZipArchive();
if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Could not open release archive for writing.\n");
    exit(1);
}
foreach ($files as $relative => $metadata) {
    if (!$zip->addFile($metadata['absolute'], 'labs/' . $relative)) {
        $zip->close();
        @unlink($output);
        fwrite(STDERR, "Could not add release file: {$relative}\n");
        exit(1);
    }
}
$zip->addFromString('labs/release-manifest.json', $manifestJson);
$zip->close();

if (!is_file($output)) {
    fwrite(STDERR, "Release archive was not created.\n");
    exit(1);
}
echo "Release package: {$output}\n";
echo 'SHA-256: ' . hash_file('sha256', $output) . "\n";
echo 'Files: ' . count($manifestFiles) . "\n";
echo "Private config excluded: yes\n";
echo "Nested archives excluded: yes\n";
