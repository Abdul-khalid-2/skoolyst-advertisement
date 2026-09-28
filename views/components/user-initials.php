<?php
/**
 * Turns a display name into 1-2 uppercase initials for the sidebar
 * avatar (e.g. "Khalid Khan" -> "KK", "Skoolyst" -> "S") — replaces the
 * hardcoded "KK" both sidebars used to show for every user.
 */
function user_initials(string $name): string
{
    $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    if ($words === [] || $words === false) {
        return '?';
    }

    $initials = mb_substr($words[0], 0, 1);
    if (count($words) > 1) {
        $initials .= mb_substr($words[count($words) - 1], 0, 1);
    }

    return mb_strtoupper($initials);
}
