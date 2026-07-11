<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/training-lab-production-integration-closeout.php';

$campaign = '';
$json = false;
foreach (array_slice($argv,1) as $argument) {
    if ($argument === '--json') $json = true;
    elseif (str_starts_with($argument,'--campaign=')) $campaign = substr($argument,11);
    elseif ($argument === '--help') {
        echo "Usage: php ./bin/integration-closeout.php [--campaign=PUBLIC_ID_OR_SLUG] [--json]\n";
        echo "Read-only. Recording and approval are available only through the protected administrator workflow.\n";
        exit(0);
    } else {
        fwrite(STDERR,"Unsupported argument: {$argument}\n");
        exit(64);
    }
}

try {
    $report = tl_closeout_report($campaign);
    if ($json) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    } else {
        echo "Production Integration Closeout\n";
        echo "Score: " . (int)$report['score'] . "%\n";
        echo "Status: " . (!empty($report['ready']) ? 'READY' : 'BLOCKED') . "\n";
        echo "Passed: " . (int)$report['passed'] . "/" . (int)$report['total'] . "\n";
        foreach ((array)$report['checks'] as $check) {
            echo sprintf("[%s] %-28s %s\n", !empty($check['passed']) ? 'PASS' : 'FAIL', (string)$check['category'], (string)$check['label']);
        }
    }
    exit(!empty($report['ready']) ? 0 : 2);
} catch (Throwable $error) {
    fwrite(STDERR, "Production integration closeout failed: " . $error->getMessage() . "\n");
    exit(1);
}
