<?php
/**
 * Renders <tr> rows for an ads table. Used for the server-side first paint
 * (so a page never flashes an empty table before JS runs, and it still
 * works with JavaScript disabled) — js/dashboard.js's renderAdsTable()
 * mirrors this same markup for interactive filtering/search/actions.
 *
 * Once Section 4 (API) and Section 5 (Database) land, this becomes the
 * only renderer: pages fetch real ads from a repository and pass them
 * straight into this function, and the client-side mock re-render in
 * dashboard.js is removed.
 *
 * @param array  $ads                  List of ad rows (see data/mock-data.php shape)
 * @param array  $apps                 List of connected apps, for name lookups
 * @param string $baseHref             Path prefix back to project root (e.g. '../')
 * @param bool   $showAdvertiser       Include the advertiser name in the meta line (admin views)
 * @param bool   $showApprovalActions  Show Approve/Reject buttons for pending ads (admin views)
 * @param int|null $limit              Cap the number of rows (e.g. "Recent Ads" widgets)
 */
function ads_table_rows(
    array $ads,
    array $apps,
    string $baseHref = '',
    bool $showAdvertiser = false,
    bool $showApprovalActions = false,
    ?int $limit = null
): string {
    if ($limit !== null) {
        $ads = array_slice($ads, 0, $limit);
    }
    if (!$ads) {
        return '';
    }

    $rows = '';
    foreach ($ads as $ad) {
        $appName = app_name_by_id($apps, $ad['app']);
        $ctr = $ad['impressions'] > 0
            ? number_format(($ad['clicks'] / $ad['impressions']) * 100, 1) . '%'
            : '—';

        $meta = ($showAdvertiser ? htmlspecialchars($ad['advertiser']) . ' &middot; ' : '')
            . htmlspecialchars($appName) . ' &middot; ' . htmlspecialchars($ad['placement']);

        $actions = '<div class="db-table__actions">';
        if ($showApprovalActions && $ad['status'] === 'pending') {
            $actions .= '<button type="button" class="btn-approve" data-action="approve">Approve</button>';
            $actions .= '<button type="button" class="btn-reject" data-action="reject">Reject</button>';
        } else {
            if ($ad['status'] === 'active') {
                $actions .= '<button type="button" class="db-action-btn" data-action="pause" title="Pause ad"><i class="bi bi-pause-fill"></i></button>';
            } elseif (in_array($ad['status'], ['paused', 'draft'], true)) {
                $actions .= '<button type="button" class="db-action-btn db-action-btn--success" data-action="activate" title="Activate ad"><i class="bi bi-play-fill"></i></button>';
            }
            $actions .= '<button type="button" class="db-action-btn" data-action="edit" title="Edit ad"><i class="bi bi-pencil"></i></button>';
            $actions .= '<button type="button" class="db-action-btn db-action-btn--danger" data-action="delete" title="Delete ad"><i class="bi bi-trash3"></i></button>';
        }
        $actions .= '</div>';

        $rows .= '<tr data-ad-id="' . htmlspecialchars($ad['id']) . '" data-status="' . htmlspecialchars($ad['status']) . '" data-app="' . htmlspecialchars($appName) . '">'
            . '<td><div class="d-flex align-items-center gap-3">'
            . '<img src="' . htmlspecialchars($baseHref . $ad['image']) . '" class="db-table__thumb" alt="" loading="lazy">'
            . '<div><p class="db-table__title">' . htmlspecialchars($ad['title']) . '</p>'
            . '<p class="db-table__meta">' . $meta . '</p></div></div></td>'
            . '<td>' . status_badge($ad['status']) . '</td>'
            . '<td>' . number_format($ad['impressions']) . '</td>'
            . '<td>' . number_format($ad['clicks']) . '</td>'
            . '<td>' . $ctr . '</td>'
            . '<td>' . htmlspecialchars($ad['startDate'] ?: '—') . ' &rarr; ' . htmlspecialchars($ad['endDate'] ?: '—') . '</td>'
            . '<td>' . $actions . '</td>'
            . '</tr>';
    }
    return $rows;
}

/**
 * Maps one row from AdRepository::findAllForUser()/findByStatus()
 * (10.g/10.h) into the shape ads_table_rows() above expects (see
 * data/mock-data.php for that shape). Relies on app_name_by_id()'s
 * existing fallback: when it finds no match in `$apps`, it returns
 * its `$id` argument unchanged (see app_by_id() in app-chip.php) —
 * so putting the already-JOIN-resolved app name straight into `app`
 * and calling ads_table_rows() with an empty `$apps` array renders
 * correctly with no changes to that shared helper.
 *
 * @param array<string, mixed> $row One row as returned by the repository methods above.
 */
function db_ad_row_to_display(array $row): array
{
    return [
        'id' => (string) $row['id'],
        'title' => $row['title'],
        'image' => $row['image_path'] ? 'uploads/ads/' . $row['image_path'] : 'assets/img/ad-1.svg',
        'status' => $row['status'],
        'impressions' => (int) $row['impressions'],
        'clicks' => (int) $row['clicks'],
        'startDate' => $row['start_date'] ?? '',
        'endDate' => $row['end_date'] ?? '',
        'app' => $row['app_name'],
        'placement' => $row['placement_label'],
        'advertiser' => $row['advertiser_name'] ?? '',
    ];
}
