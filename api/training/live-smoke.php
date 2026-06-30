<?php
require_once __DIR__ . '/../../includes/labs-layout.php';
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
require_once __DIR__ . '/../../includes/training-lab-stage881-deployment-acceptance.php';
require_once __DIR__ . '/../../includes/training-lab-stage882-live-smoke.php';

tl_stage34_json(tl_stage882_live_smoke_summary());
exit;
