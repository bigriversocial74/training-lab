<?php
/** Production Runtime Acceptance v1: read-only deployment and workflow diagnostics. */
require_once __DIR__ . '/training-lab-db.php';
require_once __DIR__ . '/training-lab-security.php';
require_once __DIR__ . '/training-lab-auth-gate.php';
require_once __DIR__ . '/training-lab-stage881-deployment-acceptance.php';
require_once __DIR__ . '/training-lab-stage882-live-smoke.php';
require_once __DIR__ . '/training-lab-stage885-proof-review-handoff.php';

if (!function_exists('tl_runtime_acceptance_e')) {
    function tl_runtime_acceptance_e($value): string { return function_exists('labs_e') ? labs_e((string)$value) : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('tl_runtime_acceptance_row')) {
    function tl_runtime_acceptance_row(string $label, bool $passed, string $detail, string $status = ''): array { return compact('label','passed','detail') + ['status'=>$status ?: ($passed ? 'pass' : 'check')]; }
}
if (!function_exists('tl_runtime_acceptance_score')) {
    function tl_runtime_acceptance_score(array $rows): int { return $rows ? (int)round(count(array_filter($rows, fn($r)=>!empty($r['passed']))) / count($rows) * 100) : 100; }
}
if (!function_exists('tl_runtime_acceptance_routes')) {
    function tl_runtime_acceptance_routes(): array {
        return [
            '/admin/deployment-acceptance.php'=>'Deployment QA','/admin/live-smoke.php'=>'Live smoke','/admin/db-health.php'=>'DB health','/admin/adapter-readiness.php'=>'Adapter readiness','/admin/review-workbench.php'=>'Review workbench','/admin/runtime-acceptance.php'=>'Runtime acceptance',
            '/api/training/deployment-acceptance.php'=>'Deployment API','/api/training/live-smoke.php'=>'Live smoke API','/api/training/db-status.php'=>'DB status API','/api/training/proof-review-workflow.php'=>'Proof workflow API','/api/training/runtime-acceptance.php'=>'Runtime acceptance API',
        ];
    }
}
if (!function_exists('tl_runtime_acceptance_local')) {
    function tl_runtime_acceptance_local(): bool { $h=strtolower(preg_replace('/:\d+$/','',(string)($_SERVER['HTTP_HOST']??''))); return $h===''||in_array($h,['localhost','127.0.0.1','::1'],true)||str_ends_with($h,'.test'); }
}
if (!function_exists('tl_runtime_acceptance_deployment')) {
    function tl_runtime_acceptance_deployment(): array {
        $db=tl_db_status_summary(); $smoke=tl_stage882_live_smoke_summary(); $path=str_replace('\\','/',tl_db_config_path()); $https=tl_security_is_https();
        $rows=[
            tl_runtime_acceptance_row('Private config path',str_ends_with($path,'/labs/config.php'),$path),
            tl_runtime_acceptance_row('Private DB config',!empty($db['config_ready']),!empty($db['config_ready'])?'Live non-placeholder config loaded':'Configure deployed /labs/config.php'),
            tl_runtime_acceptance_row('Database connection',!empty($db['connected']),!empty($db['connected'])?'PDO connection succeeded':'Connection unavailable'),
            tl_runtime_acceptance_row('Required tables',!empty($db['all_tables_present']),!empty($db['all_tables_present'])?'All required tables present':'Missing: '.implode(', ',(array)($db['missing_tables']??[]))),
            tl_runtime_acceptance_row('Stage 882 baseline',!empty($smoke['accepted']),!empty($smoke['accepted'])?'Live baseline accepted':'Resolve Stage 882 checks'),
            tl_runtime_acceptance_row('HTTPS transport',$https||tl_runtime_acceptance_local(),$https?'HTTPS detected':(tl_runtime_acceptance_local()?'Local/test exemption':'Production requires HTTPS'),$https?'pass':(tl_runtime_acceptance_local()?'local':'check')),
            tl_runtime_acceptance_row('Demo login disabled',!empty($db['config_ready'])&&!tl_security_demo_login_allowed(),'Production must require connected Microgifter sign-in'),
            tl_runtime_acceptance_row('Quality gate',is_file(dirname(__DIR__).'/run-quality-gate.sh')&&is_file(dirname(__DIR__).'/.github/workflows/quality-gate.yml'),'Runner and CI workflow present'),
        ];
        foreach(tl_runtime_acceptance_routes() as $route=>$label){$file=dirname(__DIR__).'/'.ltrim((string)parse_url($route,PHP_URL_PATH),'/');$rows[]=tl_runtime_acceptance_row($label.' route',is_file($file),$route);}
        return $rows;
    }
}
if (!function_exists('tl_runtime_acceptance_security')) {
    function tl_runtime_acceptance_security(): array {
        tl_security_session_start(); $token=tl_security_csrf_token(); $csrf=strlen($token)===64;
        try{tl_security_verify_csrf(['_csrf'=>$token]);}catch(Throwable $e){$csrf=false;}
        $src=@file_get_contents(__DIR__.'/training-lab-security.php')?:''; $cookie=session_get_cookie_params(); $https=tl_security_is_https();
        $pp=tl_security_role_permissions('participant'); $rp=tl_security_role_permissions('reviewer');
        return [
            tl_runtime_acceptance_row('CSRF round trip',$csrf,'Token generated and verified'),
            tl_runtime_acceptance_row('Anonymous write rejection',str_contains($src,'if (!$user) throw new TlHttpException')&&str_contains($src,'authentication_required'),'Central guard rejects anonymous writes'),
            tl_runtime_acceptance_row('Participant boundary',!in_array('training.proof.review',$pp,true)&&!in_array('training.ops.qa',$pp,true),'Participants cannot review proof or run ops'),
            tl_runtime_acceptance_row('Reviewer boundary',in_array('training.proof.review',$rp,true)&&!in_array('training.campaign.manage',$rp,true),'Reviewers cannot manage campaigns'),
            tl_runtime_acceptance_row('Untrusted role downgrade',tl_security_trusted_role(['role'=>'reviewer','source'=>'training_lab_demo_session'])==='participant','Demo role claims reduce to participant'),
            tl_runtime_acceptance_row('Trusted role retention',tl_security_trusted_role(['role'=>'reviewer','source'=>'existing_microgifter_session','microgifter_user_id'=>'42'])==='reviewer','Connected reviewer role retained'),
            tl_runtime_acceptance_row('HttpOnly cookie',!empty($cookie['httponly']),'Session cookie is HttpOnly'),
            tl_runtime_acceptance_row('SameSite cookie',strtolower((string)($cookie['samesite']??''))==='lax','Session cookie uses SameSite=Lax'),
            tl_runtime_acceptance_row('Secure cookie on HTTPS',!$https||!empty($cookie['secure']),'HTTPS requests require Secure cookie'),
            tl_runtime_acceptance_row('Production debug disabled',!tl_security_debug_enabled(),'TL_DEBUG must be disabled'),
            tl_runtime_acceptance_row('Safe error contract',str_contains($src,"'internal_error'")&&str_contains($src,'The Training Lab could not complete this request.'),'Internal details are redacted'),
            tl_runtime_acceptance_row('Security headers',str_contains($src,'Content-Security-Policy')&&str_contains($src,'Strict-Transport-Security')&&str_contains($src,'X-Request-ID'),'CSP, HSTS, and request IDs configured'),
        ];
    }
}
if (!function_exists('tl_runtime_acceptance_schema')) {
    function tl_runtime_acceptance_schema(): array {
        $db=tl_db_status_summary(); $pdo=tl_db(); $driver=$pdo instanceof PDO?(string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME):''; $probe=false;
        if($pdo)try{$probe=(int)$pdo->query('SELECT 1')->fetchColumn()===1;}catch(Throwable $e){}
        $rows=[
            tl_runtime_acceptance_row('DB configured',!empty($db['config_ready']),'Private configuration ready'),
            tl_runtime_acceptance_row('DB connected',!empty($db['connected']),'PDO connection active'),
            tl_runtime_acceptance_row('Required tables',!empty($db['all_tables_present']),'Required schema present'),
            tl_runtime_acceptance_row('MySQL driver',$driver==='mysql',$driver?:'No PDO driver'),
            tl_runtime_acceptance_row('Read query probe',$probe,$probe?'SELECT 1 succeeded':'Read probe failed'),
        ];
        foreach(tl_training_expected_table_columns() as $table=>$expected){$actual=tl_table_columns($table);$missing=array_values(array_diff($expected,$actual));$rows[]=tl_runtime_acceptance_row($table.' columns',$actual!==[]&&$missing===[],$actual===[]?'Metadata unavailable':($missing?'Missing: '.implode(', ',$missing):'Expected columns present'));}
        return $rows;
    }
}
if (!function_exists('tl_runtime_acceptance_scalar')) {
    function tl_runtime_acceptance_scalar(string $sql): ?int { $pdo=tl_db(); if(!$pdo)return null; try{$v=$pdo->query($sql)->fetchColumn();return $v===false?null:(int)$v;}catch(Throwable $e){return null;} }
}
if (!function_exists('tl_runtime_acceptance_workflow')) {
    function tl_runtime_acceptance_workflow(): array {
        $s=tl_stage885_summary(); $src=@file_get_contents(__DIR__.'/training-lab-actions.php')?:''; $safe=(array)($s['safe_boundaries']??[]);
        $dupR=tl_runtime_acceptance_scalar("SELECT COUNT(*) FROM (SELECT proof_submission_id FROM training_action_receipts WHERE proof_submission_id IS NOT NULL AND receipt_status='active' GROUP BY proof_submission_id HAVING COUNT(*)>1) x");
        $dupE=tl_runtime_acceptance_scalar("SELECT COUNT(*) FROM (SELECT action_receipt_id,reward_rule_id FROM training_reward_events WHERE action_receipt_id IS NOT NULL AND reward_rule_id IS NOT NULL AND status<>'cancelled' GROUP BY action_receipt_id,reward_rule_id HAVING COUNT(*)>1) x");
        $orphP=tl_runtime_acceptance_scalar("SELECT COUNT(*) FROM training_proof_submissions p LEFT JOIN training_campaigns c ON c.id=p.campaign_id LEFT JOIN training_campaign_tasks t ON t.id=p.task_id LEFT JOIN training_participants tp ON tp.id=p.participant_id WHERE c.id IS NULL OR t.id IS NULL OR tp.id IS NULL");
        $orphR=tl_runtime_acceptance_scalar("SELECT COUNT(*) FROM training_reviews r LEFT JOIN training_proof_submissions p ON p.id=r.proof_submission_id WHERE p.id IS NULL");
        return [
            tl_runtime_acceptance_row('Stage 885 service',isset($s['queue_count']),'Queue and handoff summary loaded'),
            tl_runtime_acceptance_row('Database workflow mode',($s['mode']??'')==='database','Workflow reads live rows'),
            tl_runtime_acceptance_row('Duplicate receipts',$dupR===0,$dupR===null?'Audit unavailable':$dupR.' duplicate active group(s)'),
            tl_runtime_acceptance_row('Duplicate reward events',$dupE===0,$dupE===null?'Audit unavailable':$dupE.' duplicate receipt/rule group(s)'),
            tl_runtime_acceptance_row('Orphan proofs',$orphP===0,$orphP===null?'Audit unavailable':$orphP.' orphan row(s)'),
            tl_runtime_acceptance_row('Orphan reviews',$orphR===0,$orphR===null?'Audit unavailable':$orphR.' orphan row(s)'),
            tl_runtime_acceptance_row('Review row locking',str_contains($src,'FOR UPDATE'),'Review writes use row locks'),
            tl_runtime_acceptance_row('Final review idempotency',str_contains($src,"'idempotent'=>true")&&str_contains($src,'action_receipt_reused'),'Repeated finalized reviews reuse results'),
            tl_runtime_acceptance_row('Task campaign scoping',str_contains($src,'WHERE campaign_id = ? AND (id = ? OR public_id = ?)'),'Task lookup is campaign-scoped'),
            tl_runtime_acceptance_row('Preview-only handoff',!empty($safe['award_handoff_preview_only'])&&!empty($safe['no_microgifter_reward_issuing']),'No reward, claim, wallet, or payment mutation'),
        ];
    }
}
if (!function_exists('tl_runtime_acceptance_probe')) {
    function tl_runtime_acceptance_probe(string $route): array {
        if(PHP_SAPI==='cli')return tl_runtime_acceptance_row($route,false,'Web request required','not-run');
        if(!function_exists('curl_init'))return tl_runtime_acceptance_row($route,false,'cURL unavailable','blocked');
        $host=preg_replace('/[^a-zA-Z0-9.\-:\[\]]/','',(string)($_SERVER['HTTP_HOST']??'')); if($host==='')return tl_runtime_acceptance_row($route,false,'Host unavailable','blocked');
        $url=(tl_security_is_https()?'https':'http').'://'.$host.(function_exists('labs_url')?labs_url($route):$route); $headers=[]; $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'TrainingLab-Runtime-Acceptance/1.0',CURLOPT_HTTPHEADER=>['Accept: text/html, application/json;q=0.9'],CURLOPT_HEADERFUNCTION=>static function($c,string $line)use(&$headers):int{if(str_contains($line,':')){[$n,$v]=explode(':',$line,2);$headers[strtolower(trim($n))]=trim($v);}return strlen($line);}]);
        if(session_status()===PHP_SESSION_ACTIVE&&session_id()!=='')curl_setopt($ch,CURLOPT_COOKIE,session_name().'='.session_id());
        $body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);$ok=$body!==false&&$code>=200&&$code<400;
        return tl_runtime_acceptance_row($route,$ok,$ok?'HTTP '.$code.'; '.($headers['content-type']??'unknown content type'):($code?'HTTP '.$code:($err?:'No response')),$ok?'pass':'failed');
    }
}
if (!function_exists('tl_runtime_acceptance_summary')) {
    function tl_runtime_acceptance_summary(bool $runProbes=false): array {
        $groups=['deployment'=>tl_runtime_acceptance_deployment(),'security'=>tl_runtime_acceptance_security(),'database_schema'=>tl_runtime_acceptance_schema(),'workflow_consistency'=>tl_runtime_acceptance_workflow()];
        $probes=[];foreach(tl_runtime_acceptance_routes() as $route=>$label){$row=$runProbes?tl_runtime_acceptance_probe($route):tl_runtime_acceptance_row($label,false,'Run explicit same-origin probe','not-run');$row['label']=$label;$probes[]=$row;}$groups['live_route_probes']=$probes;
        $scores=[];foreach($groups as $k=>$rows)$scores[$k]=($k==='live_route_probes'&&!$runProbes)?0:tl_runtime_acceptance_score($rows);
        $ready=$scores['deployment']===100&&$scores['security']===100&&$scores['database_schema']===100&&$scores['workflow_consistency']===100;$accepted=$ready&&$runProbes&&$scores['live_route_probes']===100;$used=$runProbes?$scores:array_slice($scores,0,4,true);
        return ['module'=>'Production Runtime Acceptance v1','generated_at'=>gmdate('c'),'request_id'=>tl_security_request_id(),'probe_requested'=>$runProbes,'ready_for_live_probe'=>$ready,'accepted'=>$accepted,'score'=>(int)round(array_sum($used)/count($used)),'scores'=>$scores]+$groups+['environment'=>['php_version'=>PHP_VERSION,'sapi'=>PHP_SAPI,'https'=>tl_security_is_https(),'database_driver'=>tl_db() instanceof PDO?(string)tl_db()->getAttribute(PDO::ATTR_DRIVER_NAME):null],'safe_boundaries'=>['read_only_acceptance_checks'=>true,'same_origin_get_probes_only'=>true,'no_new_sql'=>true,'no_config_overwrite'=>true,'no_real_upload_processing'=>true,'no_payment_processing'=>true,'no_wallet_mutation'=>true,'no_claim_redeem_mutation'=>true,'no_microgifter_reward_issuing'=>true,'no_destructive_microgifter_sync'=>true],'next_recommended_step'=>$accepted?'Proceed to shared Microgifter account integration.':($ready?'Run live probes and resolve failed routes.':'Resolve failed runtime groups before production acceptance.')];
    }
}
if (!function_exists('tl_runtime_acceptance_render')) {
    function tl_runtime_acceptance_render(bool $runProbes=false): void {
        $s=tl_runtime_acceptance_summary($runProbes);$probe=function_exists('labs_url')?labs_url('/admin/runtime-acceptance.php?probe=1'):'/admin/runtime-acceptance.php?probe=1';$json=function_exists('labs_url')?labs_url('/api/training/runtime-acceptance.php'.($runProbes?'?probe=1':'')):'/api/training/runtime-acceptance.php';
        echo '<section class="labs-page-title"><div><span class="labs-eyebrow">Production QA</span><h1>Production Runtime Acceptance v1</h1><p class="labs-copy">Read-only deployment, security, schema, workflow consistency, and live-route verification.</p></div><div class="labs-actions"><a class="labs-btn" href="'.tl_runtime_acceptance_e($json).'">View JSON</a><a class="labs-btn labs-btn-primary" href="'.tl_runtime_acceptance_e($probe).'">Run Live Probes</a></div></section>';
        echo '<section class="labs-kpis"><div class="labs-kpi"><span class="labs-muted">Accepted</span><strong>'.($s['accepted']?'Yes':'Not Yet').'</strong><small>all probes required</small></div><div class="labs-kpi"><span class="labs-muted">Ready</span><strong>'.($s['ready_for_live_probe']?'Yes':'Check').'</strong><small>base gate</small></div><div class="labs-kpi"><span class="labs-muted">Score</span><strong>'.(int)$s['score'].'/100</strong><small>runtime groups</small></div><div class="labs-kpi"><span class="labs-muted">Mutation</span><strong>None</strong><small>read-only</small></div></section>';
        foreach(['deployment'=>'Deployment and configuration','security'=>'Security regression checks','database_schema'=>'Database and schema readiness','workflow_consistency'=>'Workflow consistency audit','live_route_probes'=>'Same-origin live route probes'] as $key=>$title){echo '<section class="labs-card"><h2>'.tl_runtime_acceptance_e($title).'</h2><p class="labs-copy">Score: '.(int)$s['scores'][$key].'/100</p><div class="labs-table-wrap"><table class="labs-table"><thead><tr><th>Check</th><th>Status</th><th>Detail</th></tr></thead><tbody>';foreach($s[$key] as $r){$status=(string)$r['status'];$class=in_array($status,['pass','local'],true)?'good':(in_array($status,['not-run','blocked'],true)?'warn':'bad');echo '<tr><td>'.tl_runtime_acceptance_e($r['label']).'</td><td><span class="labs-pill is-'.$class.'">'.tl_runtime_acceptance_e($status).'</span></td><td>'.tl_runtime_acceptance_e($r['detail']).'</td></tr>';}echo '</tbody></table></div></section>';}
    }
}
