<?php
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
$userId = max(1, (int)($_GET['user_id'] ?? 1));
$campaignRef = isset($_GET['campaign']) ? preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)$_GET['campaign']) : tl_app_default_campaign_ref();
tl_app_json(['ok' => true, 'data' => tl_stage40_launchpad_state($campaignRef, $userId)]);

