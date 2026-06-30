<?php
require_once __DIR__ . '/../../includes/training-lab-account-bridge.php';
tl_stage34_json(['ok' => true, 'roles' => tl_account_bridge_roles(), 'context' => tl_account_bridge_current_context(), 'catalog_count' => function_exists('tl_app_count') ? tl_app_count('training_permission_catalog') : 0]);
