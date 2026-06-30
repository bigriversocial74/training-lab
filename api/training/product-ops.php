<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
$campaign = (string)($_GET['campaign'] ?? $_GET['campaign_id'] ?? '');
tl_stage34_json(tl_stage240_campaign_ops_state($campaign));
