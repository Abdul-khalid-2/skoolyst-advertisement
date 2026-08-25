<?php
require __DIR__ . '/../views/bootstrap.php';

$pageTitle  = 'All Ads';
$role       = 'admin';
$activeNav  = 'admin-ads';
$baseHref   = '../';

$topbarActions = '<span class="chip"><i class="bi bi-shield-check me-1"></i> Moderation queue</span>';

$allAdsRows = ads_table_rows($mockData['ads'], $mockData['apps'], $baseHref, true, true);

$statusCounts = ['all' => count($mockData['ads'])];
foreach (['pending', 'active', 'paused', 'rejected', 'draft', 'ended'] as $s) {
    $statusCounts[$s] = count(array_filter($mockData['ads'], fn($a) => $a['status'] === $s));
}

ob_start();
?>
<div class="db-page-head">
  <div>
    <h1>Ad Moderation</h1>
    <p>Review, approve, and manage every ad submitted across all connected apps.</p>
  </div>
</div>

<div class="db-card">
  <div class="db-tabs" id="status-tabs">
    <div class="db-tab active" data-tab-status="all">All <span class="count" id="count-all"><?= $statusCounts['all'] ?></span></div>
    <div class="db-tab" data-tab-status="pending">Pending Review <span class="count" id="count-pending"><?= $statusCounts['pending'] ?></span></div>
    <div class="db-tab" data-tab-status="active">Active <span class="count" id="count-active"><?= $statusCounts['active'] ?></span></div>
    <div class="db-tab" data-tab-status="paused">Paused <span class="count" id="count-paused"><?= $statusCounts['paused'] ?></span></div>
    <div class="db-tab" data-tab-status="rejected">Rejected <span class="count" id="count-rejected"><?= $statusCounts['rejected'] ?></span></div>
    <div class="db-tab" data-tab-status="draft">Draft <span class="count" id="count-draft"><?= $statusCounts['draft'] ?></span></div>
    <div class="db-tab" data-tab-status="ended">Ended <span class="count" id="count-ended"><?= $statusCounts['ended'] ?></span></div>
  </div>

  <div class="db-toolbar">
    <div class="db-search" style="display:flex;">
      <i class="bi bi-search"></i>
      <input type="search" id="filter-search" placeholder="Search by title or advertiser…" aria-label="Search ads">
    </div>
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
      <thead><tr><th>Ad</th><th>Status</th><th>Impressions</th><th>Clicks</th><th>CTR</th><th>Schedule</th><th></th></tr></thead>
      <tbody id="ads-table-body"><?= $allAdsRows ?></tbody>
    </table>
  </div>

  <div class="db-empty" id="ads-empty" style="display:none;">
    <i class="bi bi-inboxes"></i>
    <h4>No ads match this view</h4>
    <p>Try a different tab, app filter, or search term.</p>
  </div>

  <div class="db-pagination">
    <span>Showing all matching results</span>
    <div class="db-pagination__pages"><button class="db-page-btn active" type="button">1</button></div>
  </div>
</div>
<?php
$content = ob_get_clean();

$pageScript = <<<'JS'
(function () {
  'use strict';

  let activeStatus = 'all';

  function currentFilters() {
    return {
      query: document.getElementById('filter-search').value.trim(),
      status: activeStatus,
      app: document.getElementById('filter-app').value,
    };
  }

  function render() {
    SkoolystAdsUI.renderAdsTable({
      tbodyId: 'ads-table-body',
      emptyId: 'ads-empty',
      countId: 'results-count',
      showAdvertiser: true,
      showApprovalActions: true,
      getFilters: currentFilters,
    });
    updateTabCounts();
  }

  function updateTabCounts() {
    const ads = SkoolystAdsMock.ads;
    const statuses = ['all', 'pending', 'active', 'paused', 'rejected', 'draft', 'ended'];
    statuses.forEach(function (s) {
      const el = document.getElementById('count-' + s);
      if (!el) return;
      const n = s === 'all' ? ads.length : ads.filter(function (a) { return a.status === s; }).length;
      el.textContent = n;
    });
  }

  document.getElementById('filter-search').addEventListener('input', render);
  document.getElementById('filter-app').addEventListener('change', render);

  document.querySelectorAll('[data-tab-status]').forEach(function (tab) {
    tab.addEventListener('click', function () {
      document.querySelectorAll('[data-tab-status]').forEach(function (t) { t.classList.remove('active'); });
      tab.classList.add('active');
      activeStatus = tab.dataset.tabStatus;
      render();
    });
  });

  render();
})();
JS;

require __DIR__ . '/../views/layouts/app.php';
