<?php
require __DIR__ . '/../../core/Autoload.php';
require __DIR__ . '/../../core/Env.php';
require __DIR__ . '/../../views/bootstrap.php';

use App\Auth\UserRepository;
use App\Email\EmailMessageRepository;
use Core\Auth\Middleware;

Core\Env::load(__DIR__ . '/../../.env');

$userId = Middleware::checkSession();
$currentUser = $userId !== null ? (new UserRepository())->findById($userId) : null;
if ($currentUser === null || !$currentUser->isAdmin()) {
    header('Location: ../index.html');
    exit;
}

$messageRepo = new EmailMessageRepository();
$sourceApps = $messageRepo->sourceApps();

$filterApp = (string) ($_GET['source_app'] ?? '');
$filterStatus = (string) ($_GET['status'] ?? '');
if (!in_array($filterApp, $sourceApps, true)) {
    $filterApp = '';
}
if (!in_array($filterStatus, ['read', 'unread'], true)) {
    $filterStatus = '';
}

$messages = $messageRepo->list($filterApp ?: null, $filterStatus ?: null);

$pageTitle  = 'Email Inbox';
$role       = 'admin';
$activeNav  = 'admin-email-inbox';
$baseHref   = '../';

ob_start();
?>
<?= csrf_field() ?>
<div class="db-page-head">
  <div>
    <h1>Email Inbox</h1>
    <p>Every email sent (or received) through the shared email API, across all connected apps.</p>
  </div>
</div>

<div class="db-card">
  <form class="db-toolbar" method="get">
    <select name="source_app" class="db-filter-select" onchange="this.form.submit()">
      <option value="">All Apps</option>
      <?php foreach ($sourceApps as $app): ?>
        <option value="<?= htmlspecialchars($app) ?>"<?= $app === $filterApp ? ' selected' : '' ?>><?= htmlspecialchars($app) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="db-filter-select" onchange="this.form.submit()">
      <option value="">Read &amp; Unread</option>
      <option value="unread"<?= $filterStatus === 'unread' ? ' selected' : '' ?>>Unread</option>
      <option value="read"<?= $filterStatus === 'read' ? ' selected' : '' ?>>Read</option>
    </select>
    <span class="ms-auto small text-muted"><?= count($messages) ?> shown</span>
  </form>

  <div class="db-table-wrap">
    <table class="db-table">
      <thead><tr><th>Message</th><th>App</th><th>Account</th><th>Direction</th><th>Status</th><th>Date</th><th></th></tr></thead>
      <tbody id="messages-body">
      <?php foreach ($messages as $m): ?>
        <tr data-message-id="<?= (int) $m['id'] ?>" class="<?= $m['status'] === 'unread' ? 'fw-bold' : '' ?>">
          <td style="max-width:420px;">
            <div><?= htmlspecialchars($m['title']) ?></div>
            <div class="small text-muted fw-normal"><?= htmlspecialchars((string) $m['subtitle']) ?></div>
            <details class="small fw-normal mt-1"><summary class="text-muted">Show body</summary><div style="white-space:pre-wrap;"><?= htmlspecialchars($m['description']) ?></div></details>
          </td>
          <td><?= htmlspecialchars($m['source_app']) ?></td>
          <td class="text-muted small"><?= htmlspecialchars($m['account_email']) ?></td>
          <td><?= $m['direction'] === 'sent' ? 'Sent' : 'Received' ?></td>
          <td data-status-cell><?= $m['status'] === 'unread' ? '<span class="badge-status badge-status--pending">Unread</span>' : '<span class="badge-status badge-status--ended">Read</span>' ?></td>
          <td class="text-muted small"><?= htmlspecialchars($m['created_at']) ?></td>
          <td class="text-end">
            <?php if ($m['status'] === 'unread'): ?>
              <button type="button" class="btn btn-sk-outline btn-sm" data-mark-read>Mark read</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (!$messages): ?>
    <div class="db-empty"><h4>No messages</h4><p>Nothing matches these filters yet.</p></div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();

$pageScript = <<<'JS'
(function () {
  'use strict';

  var csrfToken = document.getElementById('_csrf').value;

  document.getElementById('messages-body').addEventListener('click', function (e) {
    var btn = e.target.closest('[data-mark-read]');
    if (!btn) return;
    var row = btn.closest('tr');
    var id = row.dataset.messageId;

    btn.disabled = true;
    fetch('../api/v1/admin/email-messages/' + encodeURIComponent(id) + '/read', {
      method: 'PATCH',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ message_id: id }),
    })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (!json.success) { btn.disabled = false; showToast('Could not mark as read.', 'error'); return; }
        row.classList.remove('fw-bold');
        row.querySelector('[data-status-cell]').innerHTML = '<span class="badge-status badge-status--ended">Read</span>';
        btn.remove();
      })
      .catch(function () { btn.disabled = false; showToast('Network error - please try again.', 'error'); });
  });
})();
JS;

require __DIR__ . '/../../views/layouts/app.php';
