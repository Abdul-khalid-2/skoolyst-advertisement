<?php
require __DIR__ . '/../../core/Autoload.php';
require __DIR__ . '/../../core/Env.php';
require __DIR__ . '/../../views/bootstrap.php';

use App\Auth\UserRepository;
use App\Email\EmailMessageRepository;
use App\Email\EmailQueueRepository;
use Core\Auth\Middleware;

Core\Env::load(__DIR__ . '/../../.env');

$userId = Middleware::checkSession();
$currentUser = $userId !== null ? (new UserRepository())->findById($userId) : null;
if ($currentUser === null || !$currentUser->isAdmin()) {
    header('Location: ../index.html');
    exit;
}

$queueRepo = new EmailQueueRepository();
$queueCounts = $queueRepo->countsByStatus();
// Pending/processing/failed only — a growing "sent" history here would
// just repeat rows already visible below in the inbox log itself.
$queueItems = array_values(array_filter($queueRepo->recent(30), fn ($q) => $q['status'] !== 'sent'));

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

<div class="db-card mb-4">
  <div class="db-card__header">
    <div>
      <h3>Send Queue</h3>
      <p>Outgoing emails are sent one at a time by a background worker, not immediately on request — see <code>cron/README.md</code>.</p>
    </div>
    <div class="d-flex gap-2">
      <span class="chip">Pending <?= (int) $queueCounts['pending'] ?></span>
      <span class="chip">Processing <?= (int) $queueCounts['processing'] ?></span>
      <span class="chip<?= $queueCounts['failed'] > 0 ? ' text-danger' : '' ?>">Failed <?= (int) $queueCounts['failed'] ?></span>
    </div>
  </div>
  <?php if ($queueItems): ?>
    <div class="db-table-wrap">
      <table class="db-table">
        <thead><tr><th>To</th><th>Subject</th><th>App</th><th>Status</th><th>Attempts</th><th>Last Error</th><th></th></tr></thead>
        <tbody id="queue-body">
        <?php foreach ($queueItems as $q): ?>
          <tr data-queue-id="<?= (int) $q['id'] ?>">
            <td class="small"><?= htmlspecialchars($q['to_email']) ?></td>
            <td class="small"><?= htmlspecialchars($q['subject']) ?></td>
            <td class="small text-muted"><?= htmlspecialchars($q['source_app']) ?></td>
            <td><?= $q['status'] === 'failed' ? '<span class="badge-status badge-status--rejected">Failed</span>' : ($q['status'] === 'processing' ? '<span class="badge-status badge-status--pending">Processing</span>' : '<span class="badge-status badge-status--paused">Pending</span>') ?></td>
            <td class="small"><?= (int) $q['attempts'] ?></td>
            <td class="small text-muted" style="max-width:260px;"><?= htmlspecialchars((string) $q['last_error']) ?></td>
            <td class="text-end">
              <?php if ($q['status'] === 'failed'): ?>
                <button type="button" class="btn btn-sk-outline btn-sm" data-retry-queue>Retry</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="db-empty"><h4>Queue is empty</h4><p>Nothing pending, processing, or failed right now.</p></div>
  <?php endif; ?>
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
      <thead><tr><th></th><th>Message</th><th>App</th><th>Account</th><th>Direction</th><th>Status</th><th>Date</th><th></th></tr></thead>
      <tbody id="messages-body">
      <?php foreach ($messages as $m): ?>
        <tr data-message-id="<?= (int) $m['id'] ?>" data-status="<?= htmlspecialchars($m['status']) ?>" data-pinned="<?= (int) $m['pinned'] ?>" class="<?= $m['status'] === 'unread' ? 'fw-bold' : '' ?><?= $m['pinned'] ? ' table-warning' : '' ?>">
          <td>
            <button type="button" class="db-action-btn<?= $m['pinned'] ? ' text-warning' : '' ?>" data-toggle-pin title="<?= $m['pinned'] ? 'Unpin' : 'Pin' ?>"><i class="bi <?= $m['pinned'] ? 'bi-pin-fill' : 'bi-pin-angle' ?>"></i></button>
          </td>
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
            <div class="d-flex gap-2 justify-content-end">
              <button type="button" class="db-action-btn" data-toggle-read title="<?= $m['status'] === 'unread' ? 'Mark read' : 'Mark unread' ?>"><i class="bi <?= $m['status'] === 'unread' ? 'bi-envelope-open' : 'bi-envelope' ?>"></i></button>
              <button type="button" class="db-action-btn db-action-btn--danger" data-delete-message title="Delete"><i class="bi bi-trash"></i></button>
            </div>
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
  var tbody = document.getElementById('messages-body');
  var queueBody = document.getElementById('queue-body');

  if (queueBody) {
    queueBody.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-retry-queue]');
      if (!btn) return;
      var row = btn.closest('tr[data-queue-id]');
      var id = row.dataset.queueId;

      btn.disabled = true;
      fetch('../api/v1/admin/email-queue/' + encodeURIComponent(id) + '/retry', {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({ queue_id: id }),
      })
        .then(function (res) { return res.json().then(function (json) { return { ok: res.ok && json.success, json: json }; }); })
        .then(function (result) {
          if (!result.ok) {
            btn.disabled = false;
            showToast((result.json.error && result.json.error.message) || 'Could not retry this email.', 'error');
            return;
          }
          showToast('Queued for retry — the worker will pick it up on its next run.', 'success');
          window.setTimeout(function () { window.location.reload(); }, 700);
        })
        .catch(function () {
          btn.disabled = false;
          showToast('Network error - please try again.', 'error');
        });
    });
  }

  function api(method, id, body) {
    return fetch('../api/v1/admin/email-messages/' + encodeURIComponent(id) + (method.suffix || ''), {
      method: method.verb,
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify(Object.assign({ message_id: id }, body || {})),
    }).then(function (res) { return res.json().then(function (json) { return { ok: res.ok && json.success, json: json }; }); });
  }

  function fail(result, fallback) {
    showToast((result.json.error && result.json.error.message) || fallback, 'error');
  }

  tbody.addEventListener('click', function (e) {
    var row = e.target.closest('tr[data-message-id]');
    if (!row) return;
    var id = row.dataset.messageId;

    var toggleReadBtn = e.target.closest('[data-toggle-read]');
    var togglePinBtn = e.target.closest('[data-toggle-pin]');
    var deleteBtn = e.target.closest('[data-delete-message]');

    if (toggleReadBtn) {
      var goingUnread = row.dataset.status !== 'unread';
      toggleReadBtn.disabled = true;
      api({ verb: 'PATCH', suffix: goingUnread ? '/unread' : '/read' }, id).then(function (result) {
        toggleReadBtn.disabled = false;
        if (!result.ok) return fail(result, 'Could not update this message.');

        row.dataset.status = goingUnread ? 'unread' : 'read';
        row.classList.toggle('fw-bold', goingUnread);
        row.querySelector('[data-status-cell]').innerHTML = goingUnread
          ? '<span class="badge-status badge-status--pending">Unread</span>'
          : '<span class="badge-status badge-status--ended">Read</span>';
        toggleReadBtn.title = goingUnread ? 'Mark read' : 'Mark unread';
        toggleReadBtn.innerHTML = '<i class="bi ' + (goingUnread ? 'bi-envelope-open' : 'bi-envelope') + '"></i>';
      });
      return;
    }

    if (togglePinBtn) {
      var pinning = row.dataset.pinned !== '1';
      togglePinBtn.disabled = true;
      api({ verb: 'PATCH', suffix: '/pin' }, id, { pinned: pinning }).then(function (result) {
        togglePinBtn.disabled = false;
        if (!result.ok) return fail(result, 'Could not update the pin.');

        row.dataset.pinned = pinning ? '1' : '0';
        row.classList.toggle('table-warning', pinning);
        togglePinBtn.classList.toggle('text-warning', pinning);
        togglePinBtn.title = pinning ? 'Unpin' : 'Pin';
        togglePinBtn.innerHTML = '<i class="bi ' + (pinning ? 'bi-pin-fill' : 'bi-pin-angle') + '"></i>';
        // Pin order only changes on reload — cheap enough for an admin tool, and
        // avoids re-sorting the whole table client-side while filters are open.
        showToast(pinning ? 'Message pinned.' : 'Message unpinned.', 'success');
      });
      return;
    }

    if (deleteBtn) {
      var title = row.querySelector('td:nth-child(2) div').textContent;
      confirmAction({
        title: 'Delete "' + title + '"?',
        body: 'This removes the message from the inbox log permanently.',
        confirmLabel: 'Delete Message',
        danger: true,
        onConfirm: function () {
          api({ verb: 'DELETE' }, id).then(function (result) {
            if (!result.ok) return fail(result, 'Could not delete this message.');
            showToast('Message deleted.', 'success');
            row.remove();
          });
        },
      });
    }
  });
})();
JS;

require __DIR__ . '/../../views/layouts/app.php';
