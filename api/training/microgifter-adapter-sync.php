<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
require_once __DIR__ . '/../../includes/labs-layout.php';
require_once __DIR__ . '/../../includes/training-lab-stage884-real-read-adapter.php';
require_once __DIR__ . '/../../includes/training-lab-stage883-readonly-adapter.php';
$section = preg_replace('/[^a-z0-9_\-]/i', '', (string)($_GET['section'] ?? 'summary'));
$userId = max(0, (int)($_GET['user_id'] ?? 0));
if ($section === 'config' || $section === 'adapter') { tl_stage34_json(tl_stage880_adapter_configuration_center()); exit; }
if ($section === 'identity' || $section === 'matching') { tl_stage34_json(tl_stage880_identity_matching($userId)); exit; }
if ($section === 'sync' || $section === 'inventory') { tl_stage34_json(tl_stage880_campaign_sync_health()); exit; }
if ($section === 'handoff' || $section === 'queue') { tl_stage34_json(tl_stage880_award_handoff_queue($userId)); exit; }
if ($section === 'real-read' || $section === 'stage884' || $section === 'db-read') { tl_stage34_json(tl_stage884_real_read_adapter_summary($userId)); exit; }
if ($section === 'readonly' || $section === 'stage883' || $section === 'readiness') { tl_stage34_json(tl_stage883_readonly_summary($userId)); exit; }
if ($section === 'audit') { tl_stage34_json(tl_stage880_adapter_sync_audit()); exit; }
$summary = tl_stage880_adapter_sync_summary($userId);
$summary['stage840_user_awards_overlay'] = function_exists('tl_stage840_user_award_summary') ? tl_stage840_user_award_summary($userId, false) : [];
$summary['stage800_campaign_import_overlay'] = function_exists('tl_stage800_microgifter_campaign_import_summary') ? tl_stage800_microgifter_campaign_import_summary(false) : [];
$summary['stage884_real_read_adapter_overlay'] = tl_stage884_real_read_adapter_summary($userId);
$summary['stage883_readonly_adapter_overlay'] = tl_stage883_readonly_summary($userId);
tl_stage34_json($summary);
exit;
