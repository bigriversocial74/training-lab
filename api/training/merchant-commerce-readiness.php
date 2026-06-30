<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
require_once __DIR__ . '/../../includes/labs-layout.php';
$section = preg_replace('/[^a-z0-9_\-]/i', '', (string)($_GET['section'] ?? 'summary'));
$campaign = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($_GET['campaign'] ?? ''));
$userId = max(0, (int)($_GET['user_id'] ?? 0));
if ($section === 'packages' || $section === 'reward') { tl_stage34_json(tl_stage760_reward_package_builder($campaign)); exit; }
if ($section === 'sponsor' || $section === 'merchant') { tl_stage34_json(tl_stage760_merchant_sponsor_context($campaign)); exit; }
if ($section === 'offers' || $section === 'catalog') { tl_stage34_json(tl_stage760_offer_preview_experience($campaign, $userId)); exit; }
if ($section === 'ops' || $section === 'operations') { tl_stage34_json(tl_stage760_merchant_operations_console()); exit; }
if ($section === 'audit') { tl_stage34_json(tl_stage760_merchant_commerce_audit()); exit; }
$summary = tl_stage760_merchant_commerce_summary();
$summary['stage880_adapter_sync_overlay'] = function_exists('tl_stage880_adapter_sync_summary') ? tl_stage880_adapter_sync_summary(0, false) : [];
$summary['content_experience_overlay'] = function_exists('tl_stage720_content_experience_summary') ? tl_stage720_content_experience_summary(false) : [];
$summary['user_awards_overlay'] = function_exists('tl_stage840_user_award_summary') ? tl_stage840_user_award_summary(0, false) : [];
tl_stage34_json($summary);
exit;
