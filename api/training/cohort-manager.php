<?php
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
tl_app_json(['ok' => true, 'data' => ['stage' => 'Cohort manager', 'campaigns' => tl_app_campaign_options(), 'participants' => tl_stage40_participant_rows(100), 'safe_boundaries' => tl_app_stage40_summary()['safe_boundaries']]]);

