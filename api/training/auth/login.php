<?php
require_once __DIR__ . '/../../../includes/training-lab-auth-gate.php';

$input = [];
$raw = file_get_contents('php://input') ?: '';
$json = json_decode($raw, true);

if (is_array($json)) {
    $input = $json;
} elseif (!empty($_POST)) {
    $input = $_POST;
} else {
    $input = $_GET;
}

$user = tl_auth_login_session($input);

tl_auth_json([
    'ok' => true,
    'authenticated' => true,
    'user' => $user,
    'message' => 'Training Lab session created. Existing Microgifter production auth is not replaced.',
]);
