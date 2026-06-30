<?php
require_once __DIR__ . '/../../../includes/training-lab-auth-gate.php';

tl_auth_logout_session();

tl_auth_json([
    'ok' => true,
    'authenticated' => false,
    'message' => 'Training Lab session cleared.',
]);
