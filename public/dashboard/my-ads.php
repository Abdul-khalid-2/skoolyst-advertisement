<?php
require __DIR__ . '/../../core/Autoload.php';
require __DIR__ . '/../../core/Env.php';
require __DIR__ . '/../../views/bootstrap.php';

use App\Ads\AdRepository;
use App\Apps\AppRepository;
use Core\Auth\Middleware;

Core\Env::load(__DIR__ . '/../../.env');

// No login page exists yet (Section 6 was tested via direct API calls) —
// this page still needs a real session to know whose ads to show, so a
// visitor with none is sent to the marketing page rather than shown
// someone else's data or a fatal error.
$userId = Middleware::checkSession();
if ($userId === null) {
    header('Location: ../index.html');
    exit;
}

$adRepo = new AdRepository();
$appRepo = new AppRepository();

$appConfig = require __DIR__ . '/../../config/app.php';
$perPage = $appConfig['pagination']['default_per_page'];

$page = max(1, (int) ($_GET['page'] ?? 1));
$totalAds = $adRepo->countForUser($userId);
$totalPages = max(1, (int) ceil($totalAds / $perPage));
$page = min($page, $totalPages);

$rows = array_map('db_ad_row_to_display', $adRepo->findAllForUser($userId, $page, $perPage));
$connectedApps = $appRepo->all();

$pageTitle  = 'My Ads';
$role       = 'advertiser';
$activeNav  = 'my-ads';
$baseHref   = '../';

$topbarActions = '<a href="create-ad.php" class="btn btn-sk-primary btn-sm px-3"><i class="bi bi-plus-lg me-1"></i> Create Ad</a>';
$searchPlaceholder = 'Search by title or advertiser…';

// $apps passed as [] — db_ad_row_to_display() already resolved the app
// name via the repository's JOIN, and app_name_by_id() falls back to
// returning that name unchanged when it finds no match (see
// views/components/ads-table.php).
$allAdsRows = ads_table_rows($rows, [], $baseHref, false, false);

ob_start();
?>
<div class="db-page-head">
  <div>
    <h1>My Ads</h1>
    <p>Manage every ad you've created across all connected Skoolyst apps.</p>
  </div>
</div>

<div class="db-card">
  <div class="db-toolbar">
    <div class="db-search" style="display:flex;">
      <i class="bi bi-search"></i>
      <input type="search" id="filter-search" placeholder="Search by title or advertiser…" aria-label="Search ads">
    </div>
    <select id="filter-status" class="db-filter-select">
      <option value="all">All Statuses</option>
      <option value="active">Active</option>
      <option value="pending">Pending Review</option>
      <option value="paused">Paused</option>
      <option value="rejected">Rejected</option>
      <option value="draft">Draft</option>
      <option value="ended">Ended</option>
    </select>
    <select id="filter-app" class="db-filter-select">
      <option value="all">All Apps</option>
      <?php foreach ($connectedApps as $app): ?>
        <option value="<?= htmlspecialchars($app['name']) ?>"><?= htmlspecialchars($app['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <span class="ms-auto small text-muted" id="results-count"></span>
  </div>

  <div class="db-table-wrap">
    <table class="db-table">
      <thead>
        <tr><th>Ad</th><th>Status</th><th>Impressions</th><th>Clicks</th><th>CTR</th><th>Schedule</th><th></th></tr>
      </thead>
      <tbody id="ads-table-body"><?= $allAdsRows ?></tbody>
    </table>
  </div>

  <div class="db-empty" id="ads-empty" style="display:<?= $rows ? 'none' : '' ?>;">
    <i class="bi bi-inboxes"></i>
    <h4>No ads match your filters</h4>
    <p>Try a different search term or status, or create a new ad to get started.</p>
    <a href="create-ad.php" class="btn btn-sk-primary btn-sm">Create Ad</a>
  </div>

  <div class="db-pagination">
    <span>Page <?= $page ?> of <?= $totalPages ?> (<?= number_format($totalAds) ?> total)</span>
    <div class="db-pagination__pages">
      <?php if ($page > 1): ?>
        <a class="db-page-btn" href="?page=<?= $page - 1 ?>">Prev</a>
      <?php endif; ?>
      <button class="db-page-btn active" type="button"><?= $page ?></button>
      <?php if ($page < $totalPages): ?>
        <a class="db-page-btn" href="?page=<?= $page + 1 ?>">Next</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();

$pageScript = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
  SkoolystAdsUI.filterRenderedRows({
    tbodyId: 'ads-table-body',
    emptyId: 'ads-empty',
    countId: 'results-count',
    searchId: 'filter-search',
    statusId: 'filter-status',
    appId: 'filter-app',
  });
});
JS;

require __DIR__ . '/../../views/layouts/app.php';
