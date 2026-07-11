#!/usr/bin/env php
<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require_once dirname(__DIR__) . '/includes/training-lab-product-acceptance.php';
$report=tl_product_acceptance_report();
echo "Training Lab product acceptance\n";
echo "Score: ".(int)$report['score']."%\n";
foreach($report['checks'] as $check){echo ($check['passed']?'[PASS] ':'[BLOCKED] ').$check['label'].' — '.$check['detail']."\n";}
echo $report['ready']?"Acceptance ready.\n":"Acceptance blocked by ".count($report['failed'])." check(s).\n";
exit($report['ready']?0:1);
