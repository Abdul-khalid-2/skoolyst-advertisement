<?php
require __DIR__ . '/../../core/Autoload.php';
require __DIR__ . '/../../core/Env.php';
require __DIR__ . '/../../views/bootstrap.php';

use App\Ads\AdRepository;
use App\Ads\AdStatsRepository;
use App\Apps\AppRepository;
use App\Auth\UserRepository;
use Core\Auth\Middleware;

Core\Env::load(__DIR__ . '/../../.env');

// Same session check as my-ads.php — this page shows the logged-in
// advertiser's own stats, so a visitor with no session (or a stale
// session pointing at a deleted user) is sent to the marketing page
// rather than shown a fatal error or someone else's data.
$userId = Middleware::checkSession();
$currentUser = $userId !== null ? (new UserRepository())->findById($userId) : null;
if ($currentUser === null) {
    header('Location: ../index.html');
    exit;
}

$adRepo = new AdRepository();
$statsRepo = new AdStatsRepository();
$appRepo = new AppRepository();

// --- Stat cards: real counts/totals for this advertiser only -------

$statusCounts = $adRepo->countsByStatusForUser($userId);
$performance = $statsRepo->performanceSummaryForUser($userId);

/**
 * Percent change from $previous to $current, or null when there's no
 * prior-period baseline to compare against (a brand-new advertiser
 * with no data before this month shouldn't be shown a fabricated
 * "+100%"/"0%" trend).
 */
$percentChange = static function (int $current, int $previous): ?float {
    if ($previous === 0) {
        return null;
    }
    return (($current - $previous) / $previous) * 100;
};

$trendDelta = static function (?float $percent, string $suffix = ' vs last month'): array {
    if ($percent === null) {
        return ['text' => 'No data for last month', 'class' => 'flat', 'icon' => ''];
    }
    if (abs($percent) < 0.05) {
        return ['text' => 'Flat vs last month', 'class' => 'flat', 'icon' => ''];
    }
    return $percent > 0
        ? ['text' => number_format(abs($percent), 1) . '%' . $suffix, 'class' => 'up', 'icon' => 'bi-arrow-up-short']
        : ['text' => number_format(abs($percent), 1) . '%' . $suffix, 'class' => 'down', 'icon' => 'bi-arrow-down-short'];
};

$ctrCurrent = $performance['impressions_current'] > 0
    ? ($performance['clicks_current'] / $performance['impressions_current']) * 100
    : 0.0;
$ctrPrevious = $performance['impressions_previous'] > 0
    ? ($performance['clicks_previous'] / $performance['impressions_previous']) * 100
    : 0.0;

$impressionsTrend = $trendDelta($percentChange($performance['impressions_current'], $performance['impressions_previous']));
$clicksTrend = $trendDelta($percentChange($performance['clicks_current'], $performance['clicks_previous']));

// CTR's own trend is expressed in percentage points, not a % change of
// a %, and only makes sense once there's a prior CTR to compare to.
if ($performance['impressions_previous'] === 0) {
    $ctrTrend = ['text' => 'No data for last month', 'class' => 'flat', 'icon' => ''];
} else {
    $ctrPointDiff = $ctrCurrent - $ctrPrevious;
    $ctrTrend = abs($ctrPointDiff) < 0.05
        ? ['text' => 'Flat vs last month', 'class' => 'flat', 'icon' => '']
        : ($ctrPointDiff > 0
            ? ['text' => number_format(abs($ctrPointDiff), 1) . 'pt vs last month', 'class' => 'up', 'icon' => 'bi-arrow-up-short']
            : ['text' => number_format(abs($ctrPointDiff), 1) . 'pt vs last month', 'class' => 'down', 'icon' => 'bi-arrow-down-short']);
}

$activeAdsDelta = $statusCounts['pending'] > 0
    ? $statusCounts['pending'] . ' pending review'
    : 'No pending ads';

// --- Impressions chart: last 7 days, zero-filled so a quiet day ----
// still shows a bar at 0 rather than just vanishing from the x-axis.

$days = 7;
$byDate = [];
foreach ($statsRepo->dailyImpressionsForUser($userId, $days) as $row) {
    $byDate[$row['date']] = $row['impressions'];
}

$impressionsChartData = [];
$rangeStart = null;
$rangeEnd = null;
for ($i = $days - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $rangeStart ??= $date;
    $rangeEnd = $date;
    $impressionsChartData[] = [
        'label' => date('D', strtotime($date)),
        'value' => $byDate[$date] ?? 0,
    ];
}
$chartRangeLabel = date('M j', strtotime($rangeStart)) . ' – ' . date('M j', strtotime($rangeEnd));

// --- By App: which connected apps this advertiser actually has ads on

$byApp = $appRepo->adsCountByAppForUser($userId);

// --- Recent Ads: this advertiser's own 4 most recent, real rows ----

$recentAds = array_map('db_ad_row_to_display', $adRepo->findAllForUser($userId, 1, 4));

$pageTitle  = 'Dashboard';
$role       = 'advertiser';
$activeNav  = 'dashboard';
$baseHref   = '../';

$topbarActions = '
  <button type="button" class="db-icon-btn" title="Notifications"><i class="bi bi-bell"></i><span class="db-dot"></span></button>
  <a href="create-ad.php" class="btn btn-sk-primary btn-sm px-3"><i class="bi bi-plus-lg me-1"></i> Create Ad</a>
';
$searchPlaceholder = 'Search your ads…';

// $apps passed as [] — db_ad_row_to_display() already resolved the app
// name via the repository's JOIN (same convention as my-ads.php, 10.g).
$recentAdsRows = ads_table_rows($recentAds, [], $baseHref, false, false, 4);

ob_start();
?>
<div class="db-page-head">
  <div>
    <h1>Welcome back, <?= htmlspecialchars($currentUser->name) ?> 👋</h1>
    <p>Here's how your ads are performing across every connected Skoolyst app.</p>
  </div>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><?= stat_card('bi-megaphone-fill', 'Active Ads', (string) $statusCounts['active'], $activeAdsDelta, 'flat', '') ?></div>
  <div class="col-6 col-lg-3"><?= stat_card('bi-eye-fill', 'Impressions (30d)', number_format($performance['impressions_current']), $impressionsTrend['text'], $impressionsTrend['class'], 'secondary', $impressionsTrend['icon'], help_icon('impressions', $helpText)) ?></div>
  <div class="col-6 col-lg-3"><?= stat_card('bi-cursor-fill', 'Clicks (30d)', number_format($performance['clicks_current']), $clicksTrend['text'], $clicksTrend['class'], 'success', $clicksTrend['icon']) ?></div>
  <div class="col-6 col-lg-3"><?= stat_card('bi-percent', 'Avg. Click-Through Rate', number_format($ctrCurrent, 2) . '%', $ctrTrend['text'], $ctrTrend['class'], 'warning', $ctrTrend['icon'], help_icon('ctr', $helpText)) ?></div>
</div>

<div class="row g-3 mb-4">
  <!-- Impressions chart -->
  <div class="col-lg-8">
    <div class="db-card h-100">
      <div class="db-card__header">
        <div>
          <h3>Impressions, Last 7 Days</h3>
          <p>Across all placements and connected apps</p>
        </div>
        <span class="chip"><i class="bi bi-calendar3 me-1"></i> <?= htmlspecialchars($chartRangeLabel) ?></span>
      </div>
      <div class="db-card__body">
        <div id="impressions-chart" class="db-barchart"></div>
      </div>
    </div>
  </div>

  <!-- App breakdown -->
  <div class="col-lg-4">
    <div class="db-card h-100">
      <div class="db-card__header">
        <div>
          <h3>By App</h3>
          <p>Where your ads are running</p>
        </div>
      </div>
      <div class="db-card__body d-flex flex-column gap-3">
        <?php if (!$byApp): ?>
          <p class="small text-muted mb-0">You haven't placed any ads yet.</p>
        <?php endif; ?>
        <?php foreach ($byApp as $app): ?>
        <div class="d-flex align-items-center gap-2">
          <?= app_chip(['code' => $app['code'], 'name' => $app['name']]) ?>
          <span class="ms-auto small text-muted"><?= $app['total'] ?> <?= $app['total'] === 1 ? 'ad' : 'ads' ?></span>
        </div>
        <?php endforeach; ?>
        <hr class="my-1">
        <a href="create-ad.php" class="btn btn-sk-outline btn-sm w-100">
          <i class="bi bi-plus-lg me-1"></i> Advertise on another app
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Recent ads -->
<div class="db-card">
  <div class="db-card__header">
    <div>
      <h3>Recent Ads</h3>
      <p>Your latest campaigns and their current status</p>
    </div>
    <a href="my-ads.php" class="btn btn-sk-outline btn-sm">View All Ads</a>
  </div>
  <div class="db-table-wrap">
    <table class="db-table">
      <thead>
        <tr>
          <th>Ad</th><th>Status</th><th>Impressions</th><th>Clicks</th><th>CTR</th><th>Schedule</th><th></th>
        </tr>
      </thead>
      <tbody id="recent-ads-body"><?= $recentAdsRows ?></tbody>
    </table>
    <?php if (!$recentAds): ?>
      <div class="db-empty">
        <i class="bi bi-inboxes"></i>
        <h4>No ads yet</h4>
        <p>Create your first ad to start seeing impressions and clicks here.</p>
        <a href="create-ad.php" class="btn btn-sk-primary btn-sm">Create Ad</a>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();

$impressionsChartJson = json_encode($impressionsChartData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// Note: no client-side re-render of the "Recent Ads" rows here — the
// tbody above is already the real, server-rendered DB data. Re-running
// it through SkoolystAdsUI.renderAdsTable() would overwrite these rows
// with window.SkoolystAdsMock's mock ads (same bug my-ads.php's 10.g
// fix already had to work around for the full "My Ads" table).
$pageScript = <<<JS
document.addEventListener('DOMContentLoaded', function () {
  SkoolystAdsUI.renderBarChart('impressions-chart', {$impressionsChartJson});
  // 10.n — same real-row edit wiring as my-ads.php.
  SkoolystAdsUI.wireEditLinks('recent-ads-body');
});
JS;

require __DIR__ . '/../../views/layouts/app.php';
