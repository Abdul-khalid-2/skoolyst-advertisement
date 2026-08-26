<?php
require __DIR__ . '/../../views/bootstrap.php';

$pageTitle  = 'My Ads';
$role       = 'advertiser';
$activeNav  = 'my-ads';
$baseHref   = '../';

$topbarActions = '<a href="create-ad.php" class="btn btn-sk-primary btn-sm px-3"><i class="bi bi-plus-lg me-1"></i> Create Ad</a>';
$searchPlaceholder = 'Search by title or advertiser…';

$allAdsRows = ads_table_rows($mockData['ads'], $mockData['apps'], $baseHref, false, false);

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
      <?php foreach ($mockData['apps'] as $app): ?>
        <option value="<?= htmlspecialchars($app['id']) ?>"><?= htmlspecialchars($app['name']) ?></option>
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

  <div class="db-empty" id="ads-empty" style="display:none;">
    <i class="bi bi-inboxes"></i>
    <h4>No ads match your filters</h4>
    <p>Try a different search term or status, or create a new ad to get started.</p>
    <a href="create-ad.php" class="btn btn-sk-primary btn-sm">Create Ad</a>
  </div>

  <div class="db-pagination">
    <span>Showing all matching results</span>
    <div class="db-pagination__pages"><button class="db-page-btn active" type="button">1</button></div>
  </div>
</div>
<?php
$content = ob_get_clean();

$pageScript = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
  SkoolystAdsUI.initTableFilters({
    tbodyId: 'ads-table-body',
    emptyId: 'ads-empty',
    countId: 'results-count',
    searchId: 'filter-search',
    statusId: 'filter-status',
    appId: 'filter-app',
    showAdvertiser: false,
    showApprovalActions: false,
  });
});
JS;

require __DIR__ . '/../../views/layouts/app.php';
