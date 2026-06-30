<?php
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
tl_app_json(['ok' => true, 'data' => ['summary' => tl_app_flow_summary(), 'pending_proofs' => tl_app_pending_proofs(50), 'recent_reviews' => tl_app_recent_reviews(20), 'recent_receipts' => tl_app_recent_receipts(20), 'recent_rewards' => tl_app_recent_rewards(20)]]);
