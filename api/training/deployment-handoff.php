<?php
require_once __DIR__ . '/../../includes/training-lab-stage34-service.php';
require_once __DIR__ . '/../../includes/training-lab-app-service.php';
require_once __DIR__ . '/../../includes/labs-layout.php';
require_once __DIR__ . '/../../includes/training-lab-design-assets.php';
tl_stage34_json(function_exists('tl_stage460_deployment_summary') ? tl_stage460_deployment_summary() : (function_exists('tl_stage440_release_summary') ? tl_stage440_release_summary() : []));
