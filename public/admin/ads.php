<?php
require __DIR__ . '/../../core/Autoload.php';
require __DIR__ . '/../../core/Env.php';
require __DIR__ . '/../../views/bootstrap.php';

use App\Ads\AdRepository;
use App\Auth\UserRepository;
use Core\Auth\Middleware;

Core\Env::load(__DIR__ . '/../../.env');

// No login page exists yet (Section 6 was tested via direct API calls) —
// a plain session check isn't enough here (an advertiser session would
// still pass it), so the user's role is checked directly, same as
// ModerationController::approve()/reject() do via Middleware::requireRole().
$userId = Middleware::checkSession();
$currentUser = $userId !== null ? (new UserRepository())->findById($userId) : null;
if ($currentUser === null || !$currentUser->isAdmin()) {
    header('Location: ../index.html');
    exit;
}

$adRepo = new AdRepository();

$appConfig = require __DIR__ . '/../../config/app.php';
$perPage = $appConfig['pagination']['default_per_page'];

$validStatuses = ['all', 'pending', 'active', 'paused', 'rejected', 'draft', 'ended'];
$activeStatus = $_GET['status'] ?? 'all';
if (!in_array($activeStatus, $validStatuses, true)) {
    $activeStatus = 'all';
}

$statusCounts = $adRepo->countsByStatus();
$totalForTab = $statusCounts[$activeStatus] ?? 0;
$totalPages = max(1, (int) ceil($totalForTab / $perPage));

$page = max(1, (int) ($_GET['page'] ?? 1));
$page = min($page, $totalPages);

$queryStatus = $activeStatus === 'all' ? null : $activeStatus;
$rows = array_map('db_ad_row_to_display', $adRepo->findByStatus($queryStatus, $page, $perPage));

// Every connected app's name, for the "All Apps" filter dropdown —
// same source as create-ad.php/my-ads.php, never mock data.
$connectedApps = (new \App\Apps\AppRepository())->all();

$pageTitle  = 'All Ads';
$role       = 'admin';
$activeNav  = 'admin-ads';
$baseHref   = '../';

$topbarActions = '<span class="chip"><i class="bi bi-shield-check me-1"></i> Moderation queue</span>';

// $apps passed as [] — db_ad_row_to_display() already resolved the app
// name via the repository's JOIN (see views/components/ads-table.php).
$allAdsRows = ads_table_rows($rows, [], $baseHref, true, true);

function tab_url(string $status): string
{
    return $status === 'all' ? '?status=all' : '?status=' . urlencode($status);
}

ob_start();
?>
<?= csrf_field() ?>
<div class="db-page-head">
  <div>
    <h1>Ad Moderation</h1>
    <p>Review, approve, and manage every ad submitted across all connected apps.</p>
  </div>
</div>

<div class="db-card">
  <div class="db-tabs" id="status-tabs">
    <a class="db-tab<?= $activeStatus === 'all' ? ' active' : '' ?>" href="<?= tab_url('all') ?>">All <span class="count"><?= $statusCounts['all'] ?></span></a>
    <a class="db-tab<?= $activeStatus === 'pending' ? ' active' : '' ?>" href="<?= tab_url('pending') ?>">Pending Review <span class="count"><?= $statusCounts['pending'] ?></span></a>
    <a class="db-tab<?= $activeStatus === 'active' ? ' active' : '' ?>" href="<?= tab_url('active') ?>">Active <span class="count"><?= $statusCounts['active'] ?></span></a>
    <a class="db-tab<?= $activeStatus === 'paused' ? ' active' : '' ?>" href="<?= tab_url('paused') ?>">Paused <span class="count"><?= $statusCounts['paused'] ?></span></a>
    <a class="db-tab<?= $activeStatus === 'rejected' ? ' active' : '' ?>" href="<?= tab_url('rejected') ?>">Rejected <span class="count"><?= $statusCounts['rejected'] ?></span></a>
    <a class="db-tab<?= $activeStatus === 'draft' ? ' active' : '' ?>" href="<?= tab_url('draft') ?>">Draft <span class="count"><?= $statusCounts['draft'] ?></span></a>
    <a class="db-tab<?= $activeStatus === 'ended' ? ' active' : '' ?>" href="<?= tab_url('ended') ?>">Ended <span class="count"><?= $statusCounts['ended'] ?></span></a>
  </div>

  <div class="db-toolbar">
    <div class="db-search" style="display:flex;">
      <i class="bi bi-search"></i>
      <input type="search" id="filter-search" placeholder="Search by title or advertiser…" aria-label="Search ads">
    </div>
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
      <thead><tr><th>Ad</th><th>Status</th><th>Impressions</th><th>Clicks</th><th>CTR</th><th>Schedule</th><th></th></tr></thead>
      <tbody id="ads-table-body"><?= $allAdsRows ?></tbody>
    </table>
  </div>

  <div class="db-empty" id="ads-empty" style="display:<?= $rows ? 'none' : '' ?>;">
    <i class="bi bi-inboxes"></i>
    <h4>No ads match this view</h4>
    <p>Try a different tab, app filter, or search term.</p>
  </div>

  <div class="db-pagination">
    <span>Page <?= $page ?> of <?= $totalPages ?> (<?= number_format($totalForTab) ?> total)</span>
    <div class="db-pagination__pages">
      <?php if ($page > 1): ?>
        <a class="db-page-btn" href="?status=<?= urlencode($activeStatus) ?>&page=<?= $page - 1 ?>">Prev</a>
      <?php endif; ?>
      <button class="db-page-btn active" type="button"><?= $page ?></button>
      <?php if ($page < $totalPages): ?>
        <a class="db-page-btn" href="?status=<?= urlencode($activeStatus) ?>&page=<?= $page + 1 ?>">Next</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();

$pageScript = <<<'JS'
(function () {
  'use strict';

  var csrfToken = document.getElementById('_csrf').value;
  var tbody = document.getElementById('ads-table-body');

  SkoolystAdsUI.filterRenderedRows({
    tbodyId: 'ads-table-body',
    emptyId: 'ads-empty',
    countId: 'results-count',
    searchId: 'filter-search',
    appId: 'filter-app',
  });

  // 10.n admin-edit follow-up — Edit was rendered on every non-pending
  // row (views/components/ads-table.php) but never wired on this page,
  // so it did nothing. create-ad.php lives one level up and back down
  // from here (public/admin/ -> public/dashboard/), unlike my-ads.php's
  // same-directory default.
  SkoolystAdsUI.wireEditLinks('ads-table-body', '../dashboard/create-ad.php?edit=');

  // Approve/reject (10.h) call the real admin API and only touch the
  // DOM once the server confirms the change — status/app/pagination
  // filters above are all page-scoped, so on success the simplest
  // correct move is a fresh navigation back to this same tab, which
  // reflects both the new counts and the ad's new (likely different)
  // tab membership.
  tbody.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-action]');
    if (!btn) return;
    var action = btn.dataset.action;
    if (action !== 'approve' && action !== 'reject') return;

    var tr = btn.closest('tr');
    var adId = tr.dataset.adId;
    var title = tr.querySelector('.db-table__title').textContent;

    var body = { ad_id: adId };
    if (action === 'reject') {
      var reason = window.prompt('Reason for rejecting "' + title + '" (shown to the advertiser):', 'Please review our ad content guidelines and resubmit.');
      if (reason === null) return;
      body.reason = reason;
    }

    var endpoint = '../api/v1/admin/ads/' + encodeURIComponent(adId) + '/' + action;
    var buttons = tr.querySelectorAll('[data-action]');
    buttons.forEach(function (b) { b.disabled = true; });

    fetch(endpoint, {
      method: 'PATCH',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
      },
      body: JSON.stringify(body),
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
        showToast(action === 'approve' ? 'Ad approved and set live.' : 'Ad rejected.', 'success');
        window.setTimeout(function () { window.location.reload(); }, 700);
      })
      .catch(function () {
        showToast('Network error — please try again.', 'error');
        buttons.forEach(function (b) { b.disabled = false; });
      });
  });
})();
JS;

require __DIR__ . '/../../views/layouts/app.php';
