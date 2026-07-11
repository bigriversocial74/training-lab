<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/training-lab-production-integration-closeout.php';

$campaign='';$json=false;
foreach(array_slice($argv,1) as $argument){
    if($argument==='--json')$json=true;
    elseif(str_starts_with($argument,'--campaign='))$campaign=substr($argument,11);
    elseif($argument==='--help'){echo "Usage: php ./bin/integration-closeout.php [--campaign=PUBLIC_ID_OR_SLUG] [--json]\nRead-only. Recording and approval are available only through the protected administrator workflow.\n";exit(0);}
    else{fwrite(STDERR,"Unsupported argument: {$argument}\n");exit(64);}
}

try{
    $report=tl_closeout_report($campaign);
    $raw=getenv('TL_ALLOW_DEMO_LOGIN');
    $cfg=function_exists('tl_security_config')?tl_security_config():[];
    $demoEnabled=$raw!==false&&$raw!==''?tl_security_bool($raw,false):tl_security_bool($cfg['allow_demo_session_login']??false,false);
    foreach($report['checks'] as &$check){if((string)$check['key']==='demo_login_disabled'){$check['passed']=!$demoEnabled;$check['status']=!$demoEnabled?'passed':'failed';$check['observed']=!$demoEnabled?'disabled':'enabled';}}unset($check);
    $report['passed']=count(array_filter($report['checks'],static fn(array $check):bool=>!empty($check['passed'])));$report['total']=count($report['checks']);$report['failed']=$report['total']-$report['passed'];$report['ready']=$report['failed']===0;$report['score']=$report['total']?(int)round($report['passed']/$report['total']*100):0;
    $report['categories']=[];foreach($report['checks'] as $check){$category=(string)$check['category'];$report['categories'][$category]??=['passed'=>0,'total'=>0,'percent'=>0];$report['categories'][$category]['total']++;if(!empty($check['passed']))$report['categories'][$category]['passed']++;}foreach($report['categories'] as &$summary)$summary['percent']=(int)round($summary['passed']/max(1,$summary['total'])*100);unset($summary);
    $fingerprint=['campaign'=>(string)($report['campaign']['public_id']??''),'account'=>(string)($report['account']['public_id']??''),'pilot'=>(string)($report['email_pilot']['public_id']??''),'handoff'=>(string)($report['reward_handoff']['public_id']??''),'checks'=>array_map(static fn(array $check):array=>['category'=>$check['category'],'key'=>$check['key'],'passed'=>$check['passed'],'observed'=>$check['observed'],'required'=>$check['required'],'evidence_hash'=>$check['evidence_hash']],$report['checks'])];
    $report['report_hash']=hash('sha256',json_encode($fingerprint,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    if($json)echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
    else{echo "Production Integration Closeout\nScore: ".(int)$report['score']."%\nStatus: ".(!empty($report['ready'])?'READY':'BLOCKED')."\nPassed: ".(int)$report['passed']."/".(int)$report['total']."\n";foreach((array)$report['checks'] as $check)echo sprintf("[%s] %-28s %s\n",!empty($check['passed'])?'PASS':'FAIL',(string)$check['category'],(string)$check['label']);}
    exit(!empty($report['ready'])?0:2);
}catch(Throwable $error){fwrite(STDERR,"Production integration closeout failed: ".$error->getMessage()."\n");exit(1);}
