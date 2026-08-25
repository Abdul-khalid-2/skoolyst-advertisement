<?php
/**
 * Renders the small "ⓘ" tooltip icon used next to form labels and stats
 * that need a short explanation. Copy lives in config/help-text.php so
 * wording is edited in one place, not hunted down inside templates.
 *
 * Usage:  <?= help_icon('ctr', $helpText) ?>
 *
 * Rendering is CSS-only (.db-help-icon in css/dashboard.css); activation
 * is one shared line in js/dashboard.js that turns every
 * [data-bs-toggle="tooltip"] on the page into a Bootstrap tooltip — no
 * per-instance JS needed here.
 */
function help_icon(string $key, array $helpText): string
{
    if (empty($helpText[$key])) {
        return '';
    }
    $text = htmlspecialchars($helpText[$key], ENT_QUOTES);

    return '<i class="bi bi-info-circle db-help-icon" '
        . 'data-bs-toggle="tooltip" data-bs-placement="top" '
        . 'title="' . $text . '" tabindex="0" role="img" '
        . 'aria-label="' . $text . '"></i>';
}
