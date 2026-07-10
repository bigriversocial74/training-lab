<?php
require_once __DIR__ . '/training-lab-stage891-reward-handoff-recovery.php';

if (!function_exists('tl_stage891_terminal_failure_rows')) {
    function tl_stage891_terminal_failure_rows(int $limit = 50): array
    {
        $maxAttempts = (int)tl_stage890_config()['max_attempts'];
        $rows = tl_stage890_rows(max(50, $limit * 3));
        $terminal = [];
        foreach ($rows as $row) {
            if (tl_stage891_is_terminal_failure($row, $maxAttempts)) $terminal[] = $row;
            if (count($terminal) >= $limit) break;
        }
        return $terminal;
    }
}

if (!function_exists('tl_stage891_render_terminal_failure_panel')) {
    function tl_stage891_render_terminal_failure_panel(): void
    {
        $rows = tl_stage891_terminal_failure_rows(50);
        echo '<section class="labs-card"><div class="labs-card-headline"><div><span class="labs-eyebrow">Stage 891 recovery queue</span><h2>Terminal Handoff Failures</h2><p class="labs-copy">These handoffs exhausted automatic attempts or were recovered after an abandoned worker reached the attempt limit. Requeue only after reviewing the failure and production gates.</p></div><strong>' . count($rows) . '</strong></div>';
        echo '<div class="labs-stage160-claim-table">';
        foreach ($rows as $row) {
            $value = (int)($row['value_cents'] ?? 0) > 0 ? '$' . number_format(((int)$row['value_cents']) / 100, 2) . ' ' . labs_e((string)($row['currency'] ?? 'USD')) : 'Recognition';
            echo '<div class="labs-stage160-claim-row"><div><span class="labs-pill">terminal failure</span><strong>' . labs_e((string)($row['reward_label'] ?? 'Training Reward')) . '</strong><p>' . labs_e((string)($row['campaign_title'] ?? 'Campaign')) . ' · ' . labs_e((string)($row['participant_label'] ?? 'Participant')) . ' · ' . $value . '</p><small>Attempts: ' . (int)($row['attempt_count'] ?? 0) . ' · ' . labs_e((string)($row['failure_code'] ?? 'delivery_failed')) . ' · ' . labs_e((string)($row['failure_message'] ?? 'Operator review required.')) . '</small></div><div class="labs-stage160-claim-actions"><form action="' . labs_e(labs_url('/admin/action-result.php')) . '" method="post"><input type="hidden" name="confirm_training_action" value="1"><input type="hidden" name="training_action" value="stage891_requeue_handoff"><input type="hidden" name="handoff_id" value="' . (int)$row['id'] . '"><input type="hidden" name="requeue_reason" value="Operator reviewed terminal failure and approved a fresh retry cycle."><button class="labs-btn labs-btn-primary" type="submit">Requeue</button></form></div></div>';
        }
        if (!$rows) echo '<div class="labs-empty-state"><strong>No terminal handoff failures</strong><p>Automatic retry limits have not produced any operator-review items.</p></div>';
        echo '</div><div class="labs-safe-note">Requeue resets the Training Lab attempt counter and does not call Microgifter. Delivery still occurs only through the gated processing action.</div></section>';
    }
}
