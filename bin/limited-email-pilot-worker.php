<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/training-lab-limited-email-pilot.php';
$options = getopt('', ['run:','limit::','json']);
$run = trim((string)($options['run'] ?? ''));
$limit = max(1,min(3,(int)($options['limit'] ?? 1)));
if ($run === '') { fwrite(STDERR,"--run is required.\n"); exit(2); }
$user = ['id'=>'section18-worker','numeric_user_id'=>max(1,(int)tl_limited_email_pilot_value('TL_LIMITED_EMAIL_PILOT_ACTOR_USER_ID','limited_email_pilot_actor_user_id','1')),'role'=>'admin','source'=>'developer_key','name'=>'Section 18 Worker'];
try {
    $result = tl_limited_email_pilot_process($user,$run,$limit);
    $payload = ['ok'=>true,'bounded_worker'=>true,'run'=>$run,'limit'=>$limit,'result'=>$result,'generated_at'=>gmdate('c'),'safe_boundaries'=>['general_worker_required_disabled'=>true,'maximum_batch'=>3,'cohort_only'=>true,'automatic_pause'=>true,'no_microgifter_authority'=>true]];
    echo isset($options['json']) ? json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . "\n" : 'Limited pilot worker: ' . (string)($result['status'] ?? 'complete') . '; processed ' . (int)($result['processed'] ?? 0) . "\n";
    exit((string)($result['status'] ?? '') === 'paused' ? 1 : 0);
} catch (Throwable $error) {
    $payload = ['ok'=>false,'bounded_worker'=>true,'error'=>$error instanceof TlHttpException ? $error->getMessage() : 'The bounded pilot worker failed.','error_code'=>$error instanceof TlHttpException ? $error->errorCode() : 'limited_email_pilot_worker_failed'];
    fwrite(STDERR,json_encode($payload,JSON_UNESCAPED_SLASHES) . "\n");
    exit(1);
}
