<?php
require __DIR__ . '/../views/bootstrap.php';

$pageTitle  = 'Dashboard';
$role       = 'advertiser';
$activeNav  = 'dashboard';
$baseHref   = '../';

$topbarActions = '
  <button type="button" class="db-icon-btn" title="Notifications"><i class="bi bi-bell"></i><span class="db-dot"></span></button>
  <a href="create-ad.php" class="btn btn-sk-primary btn-sm px-3"><i class="bi bi-plus-lg me-1"></i> Create Ad</a>
';
$searchPlaceholder = 'Search your ads…';

$recentAdsRows = ads_table_rows($mockData['ads'], $mockData['apps'], $baseHref, false, false, 4);

ob_start();
?>
<div class="db-page-head">
  <div>
    <h1>Welcome back, Khalid 👋</h1>
    <p>Here's how your ads are performing across every connected Skoolyst app.</p>
  </div>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><?= stat_card('bi-megaphone-fill', 'Active Ads', '3', '1 pending review', 'flat', '') ?></div>
  <div class="col-6 col-lg-3"><?= stat_card('bi-eye-fill', 'Impressions (30d)', '141.6K', '12.4% vs last month', 'up', 'secondary', 'bi-arrow-up-short', help_icon('impressions', $helpText)) ?></div>
  <div class="col-6 col-lg-3"><?= stat_card('bi-cursor-fill', 'Clicks (30d)', '3,935', '8.1% vs last month', 'up', 'success', 'bi-arrow-up-short') ?></div>
  <div class="col-6 col-lg-3"><?= stat_card('bi-percent', 'Avg. Click-Through Rate', '2.78%', '0.3pt vs last month', 'down', 'warning', 'bi-arrow-down-short', help_icon('ctr', $helpText)) ?></div>
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
        <span class="chip"><i class="bi bi-calendar3 me-1"></i> Aug 19 – Aug 25</span>
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
        <?php foreach (['sk' => '2 ads', 'st' => '1 ad', 'ss' => '1 ad'] as $appId => $countLabel): $app = app_by_id($mockData['apps'], $appId); ?>
        <div class="d-flex align-items-center gap-2">
          <?= app_chip($app) ?>
          <span class="ms-auto small text-muted"><?= htmlspecialchars($countLabel) ?></span>
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
  </div>
</div>
<?php
$content = ob_get_clean();

$pageScript = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
  SkoolystAdsUI.renderBarChart('impressions-chart', [
    { label: 'Mon', value: 4200 },
    { label: 'Tue', value: 5100 },
    { label: 'Wed', value: 4800 },
    { label: 'Thu', value: 6300 },
    { label: 'Fri', value: 7100 },
    { label: 'Sat', value: 5600 },
    { label: 'Sun', value: 4950 },
  ]);

  // Re-render on top of the server-rendered rows above once JS is ready,
  // so the "recent ads" widget stays interactive (edit/pause/delete).
  SkoolystAdsUI.renderAdsTable({
    tbodyId: 'recent-ads-body',
    emptyId: null,
    countId: null,
    showAdvertiser: false,
    showApprovalActions: false,
    limit: 4,
  });
});
JS;

require __DIR__ . '/../views/layouts/app.php';
