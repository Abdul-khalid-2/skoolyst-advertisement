<?php
/**
 * Renders one KPI card (icon + label + value + optional trend line).
 * Used on the advertiser dashboard and the admin overview — previously
 * this exact block of markup was hand-copied four times per page.
 *
 * @param string $icon        Bootstrap Icons class, without the "bi-" prefix's leading "bi " part (e.g. 'bi-eye-fill')
 * @param string $label       Small label above the value
 * @param string $value       The headline number
 * @param string $delta       Optional trend text, already formatted (e.g. "12.4% vs last month")
 * @param string $deltaClass  'up' | 'down' | 'flat' — controls the arrow/color
 * @param string $iconVariant '' | 'secondary' | 'success' | 'warning' | 'admin'
 * @param string $deltaIcon   Bootstrap icon class for the small arrow, e.g. 'bi-arrow-up-short'
 * @param string $labelExtra  Optional extra HTML appended right after the label (e.g. a help_icon())
 */
function stat_card(
    string $icon,
    string $label,
    string $value,
    string $delta = '',
    string $deltaClass = 'flat',
    string $iconVariant = '',
    string $deltaIcon = '',
    string $labelExtra = ''
): string {
    $iconClass = 'db-stat__icon' . ($iconVariant ? ' db-stat__icon--' . htmlspecialchars($iconVariant) : '');
    $deltaHtml = '';
    if ($delta !== '') {
        $arrow = $deltaIcon ? '<i class="bi ' . htmlspecialchars($deltaIcon) . '"></i> ' : '';
        $deltaHtml = '<span class="db-stat__delta db-stat__delta--' . htmlspecialchars($deltaClass) . '">'
            . $arrow . htmlspecialchars($delta) . '</span>';
    }

    return '<div class="db-stat">'
        . '<div class="' . $iconClass . '"><i class="bi ' . htmlspecialchars($icon) . '"></i></div>'
        . '<div>'
        . '<p class="db-stat__label mb-0">' . htmlspecialchars($label) . $labelExtra . '</p>'
        . '<p class="db-stat__value mb-0">' . htmlspecialchars($value) . '</p>'
        . $deltaHtml
        . '</div></div>';
}
