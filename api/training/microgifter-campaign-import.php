<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
require_once __DIR__ . '/../../includes/labs-layout.php';
$section = preg_replace('/[^a-z0-9_\-]/i', '', (string)($_GET['section'] ?? 'summary'));
$campaign = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($_GET['campaign'] ?? ''));
if ($section === 'bridge' || $section === 'merchant') { tl_stage34_json(tl_stage800_merchant_account_bridge()); exit; }
if ($section === 'campaigns' || $section === 'import') { tl_stage34_json(tl_stage800_reward_campaign_import()); exit; }
if ($section === 'inventory' || $section === 'quantity') { tl_stage34_json(tl_stage800_reward_inventory_board()); exit; }
if ($section === 'assignment' || $section === 'task') { tl_stage34_json(tl_stage800_assignment_preview($campaign)); exit; }
if ($section === 'audit') { tl_stage34_json(tl_stage800_microgifter_import_audit()); exit; }
$summary = tl_stage800_microgifter_campaign_import_summary();
$summary['stage880_adapter_sync_overlay'] = function_exists('tl_stage880_adapter_sync_summary') ? tl_stage880_adapter_sync_summary(0, false) : [];
$summary['merchant_commerce_overlay'] = function_exists('tl_stage760_merchant_commerce_summary') ? tl_stage760_merchant_commerce_summary(false) : [];
tl_stage34_json($summary);
exit;
