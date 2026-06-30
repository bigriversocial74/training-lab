<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';

$campaignRef = isset($_GET['campaign']) ? preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)$_GET['campaign']) : null;

$payload = tl_training_campaign_inspector_summary($campaignRef);
$payload['available_campaigns'] = tl_training_recent_campaign_snapshots(20);

tl_stage34_json($payload);
