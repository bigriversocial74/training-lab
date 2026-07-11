<?php
/** Shared accessibility helpers for product forms and status feedback. */
if (!function_exists('tl_accessibility_error_summary')) {
    function tl_accessibility_error_summary(array $errors, string $title = 'Please correct the following'): void
    {
        $errors = array_values(array_filter(array_map('strval', $errors)));
        if (!$errors) return;
        echo '<section class="labs-error-summary" role="alert" aria-labelledby="labs-error-summary-title" tabindex="-1" data-labs-error-summary>';
        echo '<h2 id="labs-error-summary-title">' . labs_e($title) . '</h2><ul>';
        foreach ($errors as $error) echo '<li>' . labs_e($error) . '</li>';
        echo '</ul></section>';
    }
}
if (!function_exists('tl_accessibility_status')) {
    function tl_accessibility_status(string $message, string $tone = 'info'): void
    {
        $tone = in_array($tone, ['info','success','warning','error'], true) ? $tone : 'info';
        echo '<section class="labs-product-alert is-' . $tone . '" role="status" aria-live="polite"><div><p>' . labs_e($message) . '</p></div></section>';
    }
}
if (!function_exists('tl_accessibility_field_error')) {
    function tl_accessibility_field_error(string $fieldId, string $message): void
    {
        echo '<span class="labs-field-error" id="' . htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') . '-error">' . labs_e($message) . '</span>';
    }
}
