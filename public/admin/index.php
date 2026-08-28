<?php
require __DIR__ . '/../../core/Autoload.php';
require __DIR__ . '/../../core/Env.php';
require __DIR__ . '/../../views/bootstrap.php';

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

$pageTitle  = 'Admin Overview';
$role       = 'admin';
$activeNav  = 'admin-overview';
$baseHref   = '../';
$searchPlaceholder = 'Search ads, advertisers, apps…';

$topbarActions = '
  <button type="button" class="db-icon-btn" title="Notifications"><i class="bi bi-bell"></i><span class="db-dot"></span></button>
  <a href="ads.php" class="btn btn-admin-primary btn-sm px-3 text-white"><i class="bi bi-shield-check me-1"></i> Review Queue</a>
';

$topAdsRows = ads_table_rows($mockData['ads'], $mockData['apps'], $baseHref, true, false, 5);

// "Needs attention" — anything pending or rejected
$attentionAds = array_values(array_filter($mockData['ads'], function ($a) {
    return in_array($a['status'], ['pending', 'rejected'], true);
}));

ob_start();
?>
<div class="db-page-head">
  <div>
    <h1>Platform Overview</h1>
    <p>A birds-eye view of ad activity across every Skoolyst app.</p>
  </div>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><?= stat_card('bi-megaphone-fill', 'Total Ads', (string) count($mockData['ads']), 'Across ' . count($mockData['apps']) . ' apps', 'flat', 'admin') ?></div>
  <div class="col-6 col-lg-3"><?= stat_card('bi-hourglass-split', 'Pending Review', (string) count(array_filter($mockData['ads'], fn($a) => $a['status'] === 'pending')), 'Avg. review time: 6h', 'flat', 'warning') ?></div>
  <div class="col-6 col-lg-3"><?= stat_card('bi-eye-fill', 'Impressions (30d)', '1.87M', '9.6% vs last month', 'up', 'secondary', 'bi-arrow-up-short') ?></div>
  <div class="col-6 col-lg-3"><?= stat_card('bi-grid-3x3-gap-fill', 'Connected Apps', (string) count($mockData['apps']), count(array_filter($mockData['apps'], fn($a) => $a['status'] === 'active')) . ' active &middot; ' . count(array_filter($mockData['apps'], fn($a) => $a['status'] === 'paused')) . ' paused', 'flat', 'success') ?></div>
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
            <img src="<?= htmlspecialchars($baseHref . $ad['image']) ?>" style="width:40px;height:40px;border-radius:8px;object-fit:cover;flex:0 0 auto;" alt="" loading="lazy">
            <div class="flex-grow-1">
              <p class="mb-0 small fw-bold"><?= htmlspecialchars($ad['title']) ?></p>
              <p class="mb-1 small text-muted"><?= htmlspecialchars($ad['advertiser']) ?></p>
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
</div>
<?php
$content = ob_get_clean();

$pageScript = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
  SkoolystAdsUI.renderBarChart('platform-chart', [
    { label: 'Mon', value: 241000 },
    { label: 'Tue', value: 268000 },
    { label: 'Wed', value: 252000 },
    { label: 'Thu', value: 289000 },
    { label: 'Fri', value: 301000 },
    { label: 'Sat', value: 279000 },
    { label: 'Sun', value: 246000 },
  ]);

  SkoolystAdsMock.ads.sort(function (a, b) { return b.clicks - a.clicks; });
  SkoolystAdsUI.renderAdsTable({
    tbodyId: 'top-ads-body',
    emptyId: null,
    countId: null,
    showAdvertiser: true,
    showApprovalActions: false,
    limit: 5,
  });
});
JS;

require __DIR__ . '/../../views/layouts/app.php';
