<?php
/**
 * Optional Training Lab → Microgifter reward bridge configuration.
 *
 * Copy this value into your live config.php or environment. Do not commit a real
 * key. Training Lab reads the key, fingerprints it for readiness checks, and
 * never writes or displays the raw secret.
 */
// define('TL_MICROGIFTER_DEVELOPER_API_KEY', 'replace-with-real-developer-key');

/**
 * Optional direct adapter examples. Implement in the main Microgifter app if you
 * want Training Lab reward claims to issue/link real Microgifter rewards.
 */
// function microgifter_training_reward_catalog(array $context): array { return []; }
// function microgifter_training_issue_reward(array $payload): array { return ['gift_id' => 123]; }
