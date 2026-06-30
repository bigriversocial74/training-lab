<?php
require_once __DIR__ . '/_action-bootstrap.php';
tl_action_wrap(fn(array $input) => tl_review_proof($input));
