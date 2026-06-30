<?php
require_once __DIR__ . '/_action-bootstrap.php';
tl_action_wrap(fn(array $input) => tl_submit_proof($input));
