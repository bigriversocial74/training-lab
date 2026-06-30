<?php
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
tl_app_json(['ok' => true, 'data' => ['summary' => tl_app_flow_summary(), 'campaigns' => tl_app_campaign_options(), 'stage35' => tl_app_stage35_summary()]]);
