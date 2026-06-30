<?php
/** Training Lab reusable UI component helpers. */
if (!function_exists('labs_e')) {
    function labs_e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('labs_stat_card')) {
    function labs_stat_card(string $label, string $value, string $note = ''): void {
        echo '<div class="labs-kpi"><span class="labs-muted">' . labs_e($label) . '</span><strong>' . labs_e($value) . '</strong>';
        if ($note !== '') echo '<small>' . labs_e($note) . '</small>';
        echo '</div>';
    }
}
if (!function_exists('labs_status_pill')) {
    function labs_status_pill(string $label): void { echo '<span class="labs-pill">' . labs_e($label) . '</span>'; }
}
