<?php
/**
 * Renders one status pill. Every page that shows an ad's status (dashboard,
 * my-ads, admin moderation, admin overview) calls this instead of writing
 * its own <span class="badge-status--...">.
 */
function status_badge(string $status): string
{
    $labels = [
        'active'   => 'Active',
        'pending'  => 'Pending Review',
        'paused'   => 'Paused',
        'rejected' => 'Rejected',
        'draft'    => 'Draft',
        'ended'    => 'Ended',
    ];
    $label = $labels[$status] ?? ucfirst($status);

    return '<span class="badge-status badge-status--' . htmlspecialchars($status) . '">'
        . htmlspecialchars($label) . '</span>';
}
