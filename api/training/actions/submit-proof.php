<?php
require_once __DIR__ . '/_action-bootstrap.php';
require_once __DIR__ . '/../../../includes/training-lab-task-submission.php';

tl_action_wrap_user(fn(array $input, array $user): array => tl_task_secure_submit($user, $input));
