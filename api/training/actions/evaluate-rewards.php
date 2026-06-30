<?php
require_once __DIR__ . '/_action-bootstrap.php';
tl_action_wrap(function(array $input) {
    $pdo = tl_require_db();
    $participantId = (int)($input['participant_id'] ?? 0);
    if ($participantId <= 0) throw new RuntimeException('participant_id is required.');
    $stmt = $pdo->prepare('SELECT * FROM training_participants WHERE id = ? LIMIT 1');
    $stmt->execute([$participantId]);
    $participant = $stmt->fetch();
    if (!$participant) throw new RuntimeException('Participant not found.');
    return ['created' => tl_evaluate_rewards_for_participant($pdo, (int)$participant['campaign_id'], (int)$participant['id'], (int)$participant['user_id'], null)];
});
