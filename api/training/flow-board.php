<?php
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
tl_app_json(['ok' => true, 'data' => ['summary' => tl_app_flow_summary(), 'stage35' => tl_app_stage35_summary(), 'campaigns' => tl_app_campaign_options(), 'participants' => tl_app_participant_progress(25), 'proofs' => tl_app_recent_proofs(25), 'reviews' => tl_app_recent_reviews(25), 'receipts' => tl_app_recent_receipts(25), 'rewards' => tl_app_recent_rewards(25), 'events' => tl_app_recent_events(25)]]);
