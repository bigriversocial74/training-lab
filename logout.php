<?php
require_once __DIR__ . '/includes/training-lab-auth-gate.php';

tl_auth_logout();

$next = tl_auth_clean_path($_GET['next'] ?? '/signin.php', '/signin.php');
header('Location: ' . $next);
exit;
