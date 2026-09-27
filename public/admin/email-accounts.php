<?php
require __DIR__ . '/../../core/Autoload.php';
require __DIR__ . '/../../core/Env.php';
require __DIR__ . '/../../views/bootstrap.php';

use App\Auth\UserRepository;
use App\Email\ApiClientRepository;
use App\Email\EmailAccountRepository;
use Core\Auth\Middleware;

Core\Env::load(__DIR__ . '/../../.env');

$userId = Middleware::checkSession();
$currentUser = $userId !== null ? (new UserRepository())->findById($userId) : null;
if ($currentUser === null || !$currentUser->isAdmin()) {
    header('Location: ../index.html');
    exit;
}

$accountRepo = new EmailAccountRepository();
$accountRepo->resetIfNewDay();
$accounts = $accountRepo->all();
$clients = (new ApiClientRepository())->all();

$pageTitle  = 'Email Accounts';
$role       = 'admin';
$activeNav  = 'admin-email-accounts';
$baseHref   = '../';

$topbarActions = '<button type="button" class="btn btn-admin-primary btn-sm px-3 text-white" id="new-account-btn"><i class="bi bi-plus-lg me-1"></i> Add Account</button>';

function email_status_badge(string $status): string
{
    $map = ['active' => ['active', 'Active'], 'exhausted' => ['rejected', 'Exhausted'], 'disabled' => ['paused', 'Disabled']];
    [$class, $label] = $map[$status] ?? ['draft', $status];

    return '<span class="badge-status badge-status--' . $class . '">' . $label . '</span>';
}

ob_start();
?>
<?= csrf_field() ?>
<div class="db-page-head">
  <div>
    <h1>Email Accounts</h1>
    <p>Sender accounts used by the shared email API. Sends rotate through active accounts in order and move on automatically once one hits its daily limit; counters reset every day.</p>
  </div>
</div>

<div class="db-card mb-4">
  <div class="db-table-wrap">
    <table class="db-table">
      <thead><tr><th>Email</th><th>App Password</th><th>Sent Today</th><th>Received Today</th><th>Status</th><th></th></tr></thead>
      <tbody id="accounts-body">
      <?php foreach ($accounts as $a): ?>
        <tr data-account-id="<?= (int) $a['id'] ?>" data-email="<?= htmlspecialchars($a['email']) ?>" data-limit="<?= (int) $a['daily_limit'] ?>" data-status="<?= htmlspecialchars($a['status']) ?>">
          <td class="fw-bold"><?= htmlspecialchars($a['email']) ?></td>
          <td class="text-muted">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</td>
          <td><?= (int) $a['sent_count'] ?> / <?= (int) $a['daily_limit'] ?></td>
          <td><?= (int) $a['received_count'] ?> / <?= (int) $a['daily_limit'] ?></td>
          <td><?= email_status_badge($a['status']) ?></td>
          <td class="text-end">
            <div class="d-flex gap-2 justify-content-end">
              <button type="button" class="db-action-btn" data-edit-account title="Edit"><i class="bi bi-pencil"></i></button>
              <button type="button" class="db-action-btn db-action-btn--danger" data-delete-account title="Delete"><i class="bi bi-trash"></i></button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (!$accounts): ?>
    <div class="db-empty"><h4>No email accounts yet</h4><p>Add a Gmail account with an app password to start sending.</p></div>
  <?php endif; ?>
</div>

<div class="db-page-head">
  <div>
    <h2>API Clients</h2>
    <p>Apps allowed to call <code>POST /api/v1/email/send</code>. Keys are shown once when created or regenerated — only a hash is stored.</p>
  </div>
  <button type="button" class="btn btn-admin-primary btn-sm px-3 text-white" id="new-client-btn"><i class="bi bi-plus-lg me-1"></i> Add Client</button>
</div>

<div class="db-card">
  <div class="db-table-wrap">
    <table class="db-table">
      <thead><tr><th>App</th><th>Status</th><th>Last Used</th><th></th></tr></thead>
      <tbody id="clients-body">
      <?php foreach ($clients as $c): ?>
        <tr data-client-id="<?= (int) $c['id'] ?>" data-app="<?= htmlspecialchars($c['app_name']) ?>" data-active="<?= (int) $c['active'] ?>">
          <td class="fw-bold"><?= htmlspecialchars($c['app_name']) ?></td>
          <td><?= $c['active'] ? '<span class="badge-status badge-status--active">Active</span>' : '<span class="badge-status badge-status--paused">Disabled</span>' ?></td>
          <td class="text-muted small"><?= $c['last_used_at'] ? htmlspecialchars($c['last_used_at']) : 'Never' ?></td>
          <td class="text-end">
            <div class="d-flex gap-2 justify-content-end">
              <button type="button" class="db-action-btn" data-toggle-client title="<?= $c['active'] ? 'Disable' : 'Enable' ?>"><i class="bi bi-<?= $c['active'] ? 'pause-circle' : 'play-circle' ?>"></i></button>
              <button type="button" class="db-action-btn" data-regen-client title="Regenerate key"><i class="bi bi-arrow-repeat"></i></button>
              <button type="button" class="db-action-btn db-action-btn--danger" data-delete-client title="Delete"><i class="bi bi-trash"></i></button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (!$clients): ?>
    <div class="db-empty"><h4>No API clients yet</h4><p>Add one per app (e.g. skoolyst-blogs) to get an API key.</p></div>
  <?php endif; ?>
</div>

<!-- Account modal -->
<div class="modal fade db-modal" id="account-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="account-modal-title">Add Email Account</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body d-flex flex-column gap-3">
        <input type="hidden" id="account-id" value="">
        <div>
          <label class="db-form-label" for="account-email">Email</label>
          <input type="email" id="account-email" class="db-input" placeholder="sender@gmail.com">
        </div>
        <div>
          <label class="db-form-label" for="account-password">App Password</label>
          <input type="password" id="account-password" class="db-input" autocomplete="new-password">
          <div class="db-form-hint" id="account-password-hint">Stored encrypted. Never shown again.</div>
        </div>
        <div>
          <label class="db-form-label" for="account-limit">Daily Limit</label>
          <input type="number" id="account-limit" class="db-input" min="1" value="40">
        </div>
        <div id="account-status-wrap">
          <label class="db-form-label" for="account-status">Status</label>
          <select id="account-status" class="db-filter-select w-100">
            <option value="active">Active</option>
            <option value="disabled">Disabled</option>
            <option value="exhausted">Exhausted</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sk-outline" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-admin-primary text-white" id="account-submit">Save</button>
      </div>
    </div>
  </div>
</div>

<!-- Client modal -->
<div class="modal fade db-modal" id="client-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add API Client</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label class="db-form-label" for="client-app">App Name</label>
        <input type="text" id="client-app" class="db-input" placeholder="e.g. skoolyst-blogs">
        <div class="db-form-hint">Sent as <code>source_app</code>; must match exactly on every request.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sk-outline" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-admin-primary text-white" id="client-submit">Create Client</button>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();

$pageScript = <<<'JS'
(function () {
  'use strict';

  var csrfToken = document.getElementById('_csrf').value;

  function api(method, url, body) {
    return fetch(url, {
      method: method,
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: body ? JSON.stringify(body) : undefined,
    }).then(function (res) {
      return res.json().then(function (json) { return { ok: res.ok && json.success, json: json }; });
    });
  }

  function fail(result, fallback) {
    showToast((result.json.error && result.json.error.message) || fallback, 'error');
  }

  function reloadSoon() { window.setTimeout(function () { window.location.reload(); }, 700); }

  // ---- Accounts ----
  var accountModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('account-modal'));
  var f = {
    id: document.getElementById('account-id'),
    email: document.getElementById('account-email'),
    password: document.getElementById('account-password'),
    limit: document.getElementById('account-limit'),
    status: document.getElementById('account-status'),
    title: document.getElementById('account-modal-title'),
    hint: document.getElementById('account-password-hint'),
    statusWrap: document.getElementById('account-status-wrap'),
  };

  document.getElementById('new-account-btn').addEventListener('click', function () {
    f.id.value = ''; f.email.value = ''; f.password.value = ''; f.limit.value = 40; f.status.value = 'active';
    f.title.textContent = 'Add Email Account';
    f.hint.textContent = 'Stored encrypted. Never shown again.';
    f.statusWrap.style.display = 'none';
    accountModal.show();
  });

  document.getElementById('accounts-body').addEventListener('click', function (e) {
    var row = e.target.closest('tr[data-account-id]');
    if (!row) return;

    if (e.target.closest('[data-edit-account]')) {
      f.id.value = row.dataset.accountId; f.email.value = row.dataset.email; f.password.value = '';
      f.limit.value = row.dataset.limit; f.status.value = row.dataset.status;
      f.title.textContent = 'Edit Email Account';
      f.hint.textContent = 'Leave blank to keep the current password.';
      f.statusWrap.style.display = '';
      accountModal.show();
    } else if (e.target.closest('[data-delete-account]')) {
      confirmAction({
        title: 'Delete ' + row.dataset.email + '?',
        body: 'This also deletes every logged message tied to this account. It can\'t be undone.',
        confirmLabel: 'Delete Account',
        danger: true,
        onConfirm: function () {
          api('DELETE', '../api/v1/admin/email-accounts/' + row.dataset.accountId, { account_id: row.dataset.accountId })
            .then(function (r) { if (!r.ok) return fail(r, 'Could not delete this account.'); showToast('Account deleted.', 'success'); reloadSoon(); });
        },
      });
    }
  });

  document.getElementById('account-submit').addEventListener('click', function () {
    var isEdit = !!f.id.value;
    var email = f.email.value.trim();
    if (!email || (!isEdit && !f.password.value)) { showToast('Email and app password are required.', 'error'); return; }

    var body = { email: email, app_password: f.password.value, daily_limit: f.limit.value, status: f.status.value };
    if (isEdit) body.account_id = f.id.value;

    api(isEdit ? 'PATCH' : 'POST', '../api/v1/admin/email-accounts' + (isEdit ? '/' + f.id.value : ''), body)
      .then(function (r) {
        if (!r.ok) return fail(r, 'Could not save this account.');
        accountModal.hide(); showToast(isEdit ? 'Account updated.' : 'Account added.', 'success'); reloadSoon();
      });
  });

  // ---- Clients ----
  var clientModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('client-modal'));

  document.getElementById('new-client-btn').addEventListener('click', function () {
    document.getElementById('client-app').value = '';
    clientModal.show();
  });

  document.getElementById('client-submit').addEventListener('click', function () {
    var app = document.getElementById('client-app').value.trim();
    if (!app) { showToast('App name is required.', 'error'); return; }
    api('POST', '../api/v1/admin/email-clients', { app_name: app }).then(function (r) {
      if (!r.ok) return fail(r, 'Could not create this client.');
      clientModal.hide();
      window.prompt('API key for "' + app + '" - copy it now, it will not be shown again:', r.json.data.api_key);
      reloadSoon();
    });
  });

  document.getElementById('clients-body').addEventListener('click', function (e) {
    var row = e.target.closest('tr[data-client-id]');
    if (!row) return;
    var id = row.dataset.clientId, app = row.dataset.app;

    if (e.target.closest('[data-toggle-client]')) {
      var enable = row.dataset.active !== '1';
      api('PATCH', '../api/v1/admin/email-clients/' + id, { client_id: id, active: enable })
        .then(function (r) { if (!r.ok) return fail(r, 'Could not update this client.'); showToast(app + (enable ? ' enabled.' : ' disabled.'), 'success'); reloadSoon(); });
    } else if (e.target.closest('[data-regen-client]')) {
      confirmAction({
        title: 'Regenerate key for ' + app + '?',
        body: 'The old key stops working immediately.',
        confirmLabel: 'Regenerate',
        danger: true,
        onConfirm: function () {
          api('PATCH', '../api/v1/admin/email-clients/' + id, { client_id: id, regenerate: true }).then(function (r) {
            if (!r.ok) return fail(r, 'Could not regenerate the key.');
            window.prompt('New API key for "' + app + '" - copy it now, it will not be shown again:', r.json.data.api_key);
          });
        },
      });
    } else if (e.target.closest('[data-delete-client]')) {
      confirmAction({
        title: 'Delete ' + app + '?',
        body: 'That app will immediately lose access to the email API.',
        confirmLabel: 'Delete Client',
        danger: true,
        onConfirm: function () {
          api('DELETE', '../api/v1/admin/email-clients/' + id, { client_id: id })
            .then(function (r) { if (!r.ok) return fail(r, 'Could not delete this client.'); showToast('Client deleted.', 'success'); reloadSoon(); });
        },
      });
    }
  });
})();
JS;

require __DIR__ . '/../../views/layouts/app.php';
