#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once dirname(__DIR__) . '/includes/training-lab-live-acceptance.php';

$options = getopt('', ['base-url::', 'require-role-sessions', 'json', 'output::']);
$baseUrl = (string)($options['base-url'] ?? getenv('TL_PUBLIC_BASE_URL') ?: '');
$requireRoleSessions = array_key_exists('require-role-sessions', $options);
$report = tl_live_acceptance_report($baseUrl, $requireRoleSessions);

if (array_key_exists('json', $options)) {
    $output = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} else {
    $output = "Training Lab live acceptance\n";
    if (!empty($report['error'])) {
        $output .= 'BLOCKED: ' . $report['error'] . "\n";
    } else {
        $output .= 'Base URL: ' . $report['base_url'] . "\n";
        $output .= 'Score: ' . (int)$report['score'] . "%\n";
        foreach ((array)$report['checks'] as $check) {
            $state = !empty($check['skipped']) ? 'SKIP' : (!empty($check['passed']) ? 'PASS' : 'BLOCKED');
            $output .= '[' . $state . '] ' . $check['label'] . ' — ' . $check['detail'] . "\n";
        }
        $output .= !empty($report['ready'])
            ? "Live acceptance ready.\n"
            : 'Live acceptance blocked by ' . count((array)$report['failed']) . " check(s).\n";
    }
}

$outputFile = (string)($options['output'] ?? '');
if ($outputFile !== '') {
    if (!str_starts_with($outputFile, DIRECTORY_SEPARATOR)) {
        $outputFile = dirname(__DIR__) . '/' . ltrim($outputFile, '/');
    }
    $directory = dirname($outputFile);
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        fwrite(STDERR, "Could not create acceptance output directory.\n");
        exit(1);
    }
    if (file_put_contents($outputFile, $output, LOCK_EX) === false) {
        fwrite(STDERR, "Could not write acceptance output.\n");
        exit(1);
    }
    @chmod($outputFile, 0660);
    echo "Acceptance report written to {$outputFile}\n";
} else {
    echo $output;
}

exit(!empty($report['ready']) ? 0 : 1);
