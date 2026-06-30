<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
require_once __DIR__ . '/../../includes/labs-layout.php';
$section = preg_replace('/[^a-z0-9_\-]/i', '', (string)($_GET['section'] ?? 'summary'));
$userId = max(0, (int)($_GET['user_id'] ?? 0));
if ($section === 'bridge' || $section === 'account') { tl_stage34_json(tl_stage840_customer_account_bridge($userId)); exit; }
if ($section === 'inbox' || $section === 'awards') { tl_stage34_json(tl_stage840_award_inbox($userId)); exit; }
if ($section === 'claim' || $section === 'readiness') { tl_stage34_json(tl_stage840_claim_readiness($userId)); exit; }
if ($section === 'history' || $section === 'trail') { tl_stage34_json(tl_stage840_award_history($userId)); exit; }
if ($section === 'audit') { tl_stage34_json(tl_stage840_user_awards_audit()); exit; }
$summary = tl_stage840_user_award_summary($userId);
$summary['stage880_adapter_sync_overlay'] = function_exists('tl_stage880_adapter_sync_summary') ? tl_stage880_adapter_sync_summary($userId, false) : [];
$summary['microgifter_campaign_import_overlay'] = function_exists('tl_stage800_microgifter_campaign_import_summary') ? tl_stage800_microgifter_campaign_import_summary(false) : [];
tl_stage34_json($summary);
exit;
