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

// Same role check as admin/ads.php — a plain session check isn't
// enough here (an advertiser session would still pass it), so the
// user's role is checked directly.
$userId = Middleware::checkSession();
$currentUser = $userId !== null ? (new UserRepository())->findById($userId) : null;
if ($currentUser === null || !$currentUser->isAdmin()) {
    header('Location: ../index.html');
    exit;
}

$adRepo = new AdRepository();
$statsRepo = new AdStatsRepository();
$appRepo = new AppRepository();

// --- Stat cards: real, platform-wide counts/totals, no mock data ---

$statusCounts = $adRepo->countsByStatus();
$performance = $statsRepo->performanceSummary();
$connectedApps = $appRepo->all();
$activeAppsCount = count(array_filter($connectedApps, fn($a) => $a['status'] === 'active'));
$pausedAppsCount = count(array_filter($connectedApps, fn($a) => $a['status'] === 'paused'));

/**
 * Percent change from $previous to $current, or null when there's no
 * prior-period baseline to compare against — same helper as the
 * advertiser dashboard (public/dashboard/index.php), duplicated here
 * rather than shared since each page's $performance is scoped
 * differently (platform-wide vs. one advertiser).
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

$impressionsTrend = $trendDelta($percentChange($performance['impressions_current'], $performance['impressions_previous']));

// "Avg. review time" was never real (no moderated_at/updated_at column
// on `ads` to compute it from) — replaced with the oldest pending ad's
// real wait time, computed from `created_at`.
$oldestPendingAt = $adRepo->oldestPendingCreatedAt();
if ($oldestPendingAt === null) {
    $pendingSubLabel = 'Nothing waiting';
} else {
    $hoursWaiting = max(0, (int) floor((time() - strtotime($oldestPendingAt)) / 3600));
    $pendingSubLabel = $hoursWaiting < 24
        ? 'Oldest waiting ' . $hoursWaiting . 'h'
        : 'Oldest waiting ' . (int) floor($hoursWaiting / 24) . 'd';
}

// --- Platform impressions chart: last 7 days, zero-filled ----------

$days = 7;
$byDate = [];
foreach ($statsRepo->dailyImpressions($days) as $row) {
    $byDate[$row['date']] = $row['impressions'];
}

$impressionsChartData = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $impressionsChartData[] = [
        'label' => date('D', strtotime($date)),
        'value' => $byDate[$date] ?? 0,
    ];
}

// --- Needs Attention: real pending/rejected ads, oldest first ------

$attentionAds = $adRepo->findNeedsAttention(5);

// --- Top Performing Ads: real ads, ranked by lifetime clicks -------

$topAds = array_map('db_ad_row_to_display', $adRepo->findTopByClicks(5));

$pageTitle  = 'Admin Overview';
$role       = 'admin';
$activeNav  = 'admin-overview';
$baseHref   = '../';
$searchPlaceholder = 'Search ads, advertisers, apps…';

$topbarActions = '
  <button type="button" class="db-icon-btn" title="Notifications"><i class="bi bi-bell"></i><span class="db-dot"></span></button>
  <a href="ads.php" class="btn btn-admin-primary btn-sm px-3 text-white"><i class="bi bi-shield-check me-1"></i> Review Queue</a>
';

// $apps passed as [] — db_ad_row_to_display() already resolved the app
// name via the repository's JOIN (same convention as ads.php/10.h).
$topAdsRows = ads_table_rows($topAds, [], $baseHref, true, false, 5);

ob_start();
?>
<?= csrf_field() ?>
<div class="db-page-head">
  <div>
    <h1>Platform Overview</h1>
    <p>A birds-eye view of ad activity across every Skoolyst app.</p>
  </div>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><?= stat_card('bi-megaphone-fill', 'Total Ads', (string) $statusCounts['all'], 'Across ' . count($connectedApps) . ' apps', 'flat', 'admin') ?></div>
  <div class="col-6 col-lg-3"><?= stat_card('bi-hourglass-split', 'Pending Review', (string) $statusCounts['pending'], $pendingSubLabel, 'flat', 'warning') ?></div>
  <div class="col-6 col-lg-3"><?= stat_card('bi-eye-fill', 'Impressions (30d)', number_format($performance['impressions_current']), $impressionsTrend['text'], $impressionsTrend['class'], 'secondary', $impressionsTrend['icon']) ?></div>
  <div class="col-6 col-lg-3"><?= stat_card('bi-grid-3x3-gap-fill', 'Connected Apps', (string) count($connectedApps), $activeAppsCount . ' active &middot; ' . $pausedAppsCount . ' paused', 'flat', 'success') ?></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="db-card h-100">
      <div class="db-card__header">
        <div><h3>Platform Impressions, Last 7 Days</h3><p>Sum of all placements, all apps</p></div>
      </div>
      <div class="db-card__body"><div id="platform-chart" class="db-barchart"></div></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="db-card h-100">
      <div class="db-card__header">
        <div><h3>Needs Attention</h3><p>Ads waiting on a decision</p></div>
      </div>
      <div class="db-card__body d-flex flex-column gap-3">
        <?php if (!$attentionAds): ?>
          <p class="text-muted small mb-0">Nothing needs your attention right now.</p>
        <?php else: foreach ($attentionAds as $i => $ad): ?>
          <?php if ($i > 0): ?><hr class="my-1"><?php endif; ?>
          <div class="d-flex align-items-start gap-2">
            <img src="<?= htmlspecialchars($baseHref . ($ad['image_path'] ? 'uploads/ads/' . $ad['image_path'] : 'assets/img/ad-1.svg')) ?>" style="width:40px;height:40px;border-radius:8px;object-fit:cover;flex:0 0 auto;" alt="" loading="lazy">
            <div class="flex-grow-1">
              <p class="mb-0 small fw-bold"><?= htmlspecialchars($ad['title']) ?></p>
              <p class="mb-1 small text-muted"><?= htmlspecialchars($ad['advertiser_name']) ?></p>
              <?= status_badge($ad['status']) ?>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="db-card">
  <div class="db-card__header">
    <div><h3>Top Performing Ads</h3><p>Ranked by clicks in the last 30 days</p></div>
    <a href="ads.php" class="btn btn-sk-outline btn-sm">View All Ads</a>
  </div>
  <div class="db-table-wrap">
    <table class="db-table">
      <thead><tr><th>Ad</th><th>Status</th><th>Impressions</th><th>Clicks</th><th>CTR</th><th>Schedule</th><th></th></tr></thead>
      <tbody id="top-ads-body"><?= $topAdsRows ?></tbody>
    </table>
  </div>
  <?php if (!$topAds): ?>
    <div class="db-empty">
      <i class="bi bi-inboxes"></i>
      <h4>No ads yet</h4>
      <p>Once advertisers create ads, your top performers will show up here.</p>
    </div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();

$impressionsChartJson = json_encode($impressionsChartData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$pageScript = <<<JS
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    SkoolystAdsUI.renderBarChart('platform-chart', {$impressionsChartJson});
  });

  // Top Ads widget is already real, server-rendered DB data (like
  // dashboard/index.php's "Recent Ads") — just wire the Edit link and
  // the Pause/Activate/Delete buttons the same way ads.php does, so
  // this widget actually performs actions instead of sitting inert.
  var csrfEl = document.getElementById('_csrf');
  var csrfToken = csrfEl ? csrfEl.value : '';
  var tbody = document.getElementById('top-ads-body');

  SkoolystAdsUI.wireEditLinks('top-ads-body', '../dashboard/create-ad.php?edit=');

  if (tbody) {
    tbody.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-action]');
      if (!btn) return;
      var action = btn.dataset.action;
      if (action !== 'delete' && action !== 'pause' && action !== 'activate') return;

      var tr = btn.closest('tr');
      var adId = tr.dataset.adId;
      var title = tr.querySelector('.db-table__title').textContent;

      if (action === 'delete' && !window.confirm('Delete "' + title + '"? This cannot be undone.')) return;

      var endpoint = action === 'delete'
        ? '../api/v1/admin/ads/' + encodeURIComponent(adId)
        : '../api/v1/admin/ads/' + encodeURIComponent(adId) + '/' + action;
      var buttons = tr.querySelectorAll('[data-action]');
      buttons.forEach(function (b) { b.disabled = true; });

      fetch(endpoint, {
        method: action === 'delete' ? 'DELETE' : 'PATCH',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
        },
        body: JSON.stringify({ ad_id: adId }),
      })
        .then(function (res) {
          return res.json().then(function (json) { return { ok: res.ok, json: json }; });
        })
        .then(function (result) {
          if (!result.ok || !result.json.success) {
            var message = (result.json.error && result.json.error.message) || 'Could not update this ad.';
            showToast(message, 'error');
            buttons.forEach(function (b) { b.disabled = false; });
            return;
          }
          var successMessage = action === 'pause' ? 'Ad paused.'
            : action === 'activate' ? 'Ad activated.'
            : 'Ad deleted.';
          showToast(successMessage, 'success');
          window.setTimeout(function () { window.location.reload(); }, 700);
        })
        .catch(function () {
          showToast('Network error — please try again.', 'error');
          buttons.forEach(function (b) { b.disabled = false; });
        });
    });
  }
})();
JS;

require __DIR__ . '/../../views/layouts/app.php';
