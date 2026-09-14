<?php
require __DIR__ . '/../../core/Autoload.php';
require __DIR__ . '/../../core/Env.php';
require __DIR__ . '/../../views/bootstrap.php';

use App\Auth\UserRepository;
use Core\Auth\Middleware;

Core\Env::load(__DIR__ . '/../../.env');

$userId = Middleware::checkSession();
$currentUser = $userId !== null ? (new UserRepository())->findById($userId) : null;
if ($currentUser === null || !$currentUser->isAdmin()) {
    header('Location: ../index.html');
    exit;
}

$userRepo = new UserRepository();
$users = $userRepo->allWithAdsCounts();

$pageTitle  = 'Advertisers';
$role       = 'admin';
$activeNav  = 'admin-advertisers';
$baseHref   = '../';

$topbarActions = '<button type="button" class="btn btn-admin-primary btn-sm px-3 text-white" data-bs-toggle="modal" data-bs-target="#user-form-modal" id="new-user-btn"><i class="bi bi-person-plus-fill me-1"></i> New User</button>';

function render_user_row(array $user, int $currentUserId): string
{
    $roleBadge = $user['role'] === 'admin'
        ? '<span class="badge-status badge-status--active">Admin</span>'
        : '<span class="badge-status badge-status--paused">Advertiser</span>';

    $isSelf = (int) $user['id'] === $currentUserId;
    $deleteBtn = $isSelf
        ? '<button type="button" class="db-action-btn" disabled title="You can\'t delete your own account"><i class="bi bi-trash"></i></button>'
        : '<button type="button" class="db-action-btn db-action-btn--danger" data-delete-user title="Delete"><i class="bi bi-trash"></i></button>';

    return '<tr data-user-id="' . htmlspecialchars((string) $user['id']) . '" data-role="' . htmlspecialchars($user['role']) . '"'
        . ' data-name="' . htmlspecialchars($user['name']) . '" data-email="' . htmlspecialchars($user['email']) . '">'
        . '<td><span class="fw-bold">' . htmlspecialchars($user['name']) . '</span></td>'
        . '<td class="text-muted">' . htmlspecialchars($user['email']) . '</td>'
        . '<td>' . $roleBadge . '</td>'
        . '<td>' . (int) $user['ads_count'] . '</td>'
        . '<td class="text-muted small">' . htmlspecialchars(date('M j, Y', strtotime($user['created_at']))) . '</td>'
        . '<td class="text-end">'
        . '<div class="d-flex gap-2 justify-content-end">'
        . '<button type="button" class="db-action-btn" data-edit-user title="Edit"><i class="bi bi-pencil"></i></button>'
        . $deleteBtn
        . '</div></td>'
        . '</tr>';
}

ob_start();
?>
<?= csrf_field() ?>
<div class="db-page-head">
  <div>
    <h1>Advertisers</h1>
    <p>Every account with access to Skoolyst Ads — advertisers and platform admins alike.</p>
  </div>
</div>

<div class="db-card">
  <div class="db-toolbar">
    <div class="db-search" style="display:flex;">
      <i class="bi bi-search"></i>
      <input type="search" id="filter-search" placeholder="Search by name or email…" aria-label="Search users">
    </div>
    <select id="filter-role" class="db-filter-select">
      <option value="all">All Roles</option>
      <option value="advertiser">Advertiser</option>
      <option value="admin">Admin</option>
    </select>
    <span class="ms-auto small text-muted" id="results-count"></span>
  </div>

  <div class="db-table-wrap">
    <table class="db-table">
      <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Ads</th><th>Joined</th><th></th></tr></thead>
      <tbody id="users-table-body"><?php foreach ($users as $user) { echo render_user_row($user, (int) $currentUser->id); } ?></tbody>
    </table>
  </div>

  <div class="db-empty" id="users-empty" style="display:<?= $users ? 'none' : '' ?>;">
    <i class="bi bi-people"></i>
    <h4>No users match this view</h4>
    <p>Try a different role filter or search term.</p>
  </div>
</div>

<!-- ============ CREATE / EDIT USER MODAL (shared, re-populated per action) ============ -->
<div class="modal fade db-modal" id="user-form-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="user-form-title">New User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body d-flex flex-column gap-3">
        <input type="hidden" id="user-form-id" value="">
        <div>
          <label class="db-form-label" for="user-form-name">Name</label>
          <input type="text" id="user-form-name" class="db-input" placeholder="e.g. Bright Path Computer Academy" maxlength="150">
        </div>
        <div>
          <label class="db-form-label" for="user-form-email">Email</label>
          <input type="email" id="user-form-email" class="db-input" placeholder="e.g. contact@brightpath.example">
        </div>
        <div>
          <label class="db-form-label" for="user-form-role">Role</label>
          <select id="user-form-role" class="db-filter-select w-100">
            <option value="advertiser">Advertiser</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div>
          <label class="db-form-label" for="user-form-password">Password</label>
          <input type="password" id="user-form-password" class="db-input" placeholder="Min. 8 characters" autocomplete="new-password">
          <div class="db-form-hint" id="user-form-password-hint">Leave blank to keep the current password.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sk-outline" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-admin-primary text-white" id="user-form-submit">Create User</button>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();

$currentUserIdJs = (int) $currentUser->id;

$pageScript = <<<JS
(function () {
  'use strict';

  var csrfToken = document.getElementById('_csrf').value;
  var tbody = document.getElementById('users-table-body');
  var currentUserId = {$currentUserIdJs};

  SkoolystAdsUI.filterRenderedRows({
    tbodyId: 'users-table-body',
    emptyId: 'users-empty',
    countId: 'results-count',
    searchId: 'filter-search',
    statusId: 'filter-role',
  });

  var modalEl = document.getElementById('user-form-modal');
  var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  var formTitle = document.getElementById('user-form-title');
  var formId = document.getElementById('user-form-id');
  var formName = document.getElementById('user-form-name');
  var formEmail = document.getElementById('user-form-email');
  var formRole = document.getElementById('user-form-role');
  var formPassword = document.getElementById('user-form-password');
  var formPasswordHint = document.getElementById('user-form-password-hint');
  var formSubmit = document.getElementById('user-form-submit');

  function resetForm() {
    formId.value = '';
    formName.value = '';
    formEmail.value = '';
    formRole.value = 'advertiser';
    formPassword.value = '';
    formTitle.textContent = 'New User';
    formSubmit.textContent = 'Create User';
    formPasswordHint.style.display = 'none';
    formRole.disabled = false;
  }

  resetForm();
  document.getElementById('new-user-btn').addEventListener('click', resetForm);

  tbody.addEventListener('click', function (e) {
    var row = e.target.closest('[data-user-id]');
    if (!row) return;

    if (e.target.closest('[data-edit-user]')) {
      formId.value = row.dataset.userId;
      formName.value = row.dataset.name;
      formEmail.value = row.dataset.email;
      formRole.value = row.dataset.role;
      formPassword.value = '';
      formTitle.textContent = 'Edit User';
      formSubmit.textContent = 'Save Changes';
      formPasswordHint.style.display = '';
      // An admin can't demote themselves away from admin (server-enforced
      // too — UserController::update) — disabling here just avoids a
      // round trip for the obvious case.
      formRole.disabled = parseInt(row.dataset.userId, 10) === currentUserId;
      modal.show();
      return;
    }

    if (e.target.closest('[data-delete-user]')) {
      var userId = row.dataset.userId;
      var name = row.dataset.name;
      var adsCount = row.children[3].textContent.trim();
      var adsWarning = (parseInt(adsCount, 10) > 0)
        ? ' This also permanently deletes all ' + adsCount + ' of their ads.'
        : '';

      confirmAction({
        title: 'Delete "' + name + '"?',
        body: 'This removes the account entirely and can\\'t be undone.' + adsWarning,
        confirmLabel: 'Delete User',
        danger: true,
        onConfirm: function () {
          fetch('../api/v1/admin/users/' + encodeURIComponent(userId), {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ user_id: userId }),
          })
            .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
            .then(function (result) {
              if (!result.ok || !result.json.success) {
                var message = (result.json.error && result.json.error.message) || 'Could not delete this user.';
                showToast(message, 'error');
                return;
              }
              showToast('User deleted.', 'success');
              row.remove();
            })
            .catch(function () {
              showToast('Network error — please try again.', 'error');
            });
        },
      });
    }
  });

  formSubmit.addEventListener('click', function () {
    var id = formId.value;
    var name = formName.value.trim();
    var email = formEmail.value.trim();
    var role = formRole.value;
    var password = formPassword.value;
    var isEdit = !!id;

    if (!name || !email) {
      showToast('Name and email are both required.', 'error');
      return;
    }
    if (!isEdit && !password) {
      showToast('A password is required for a new user.', 'error');
      return;
    }
    if (password && password.length < 8) {
      showToast('Password must be at least 8 characters.', 'error');
      return;
    }

    var url = isEdit ? '../api/v1/admin/users/' + encodeURIComponent(id) : '../api/v1/admin/users';
    var body = { name: name, email: email, role: role, password: password };
    if (isEdit) body.user_id = id;

    formSubmit.disabled = true;
    fetch(url, {
      method: isEdit ? 'PATCH' : 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify(body),
    })
      .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
      .then(function (result) {
        formSubmit.disabled = false;
        if (!result.ok || !result.json.success) {
          var message = (result.json.error && result.json.error.message) || 'Could not save this user.';
          showToast(message, 'error');
          return;
        }
        showToast(isEdit ? 'User updated.' : 'User created.', 'success');
        modal.hide();
        window.setTimeout(function () { window.location.reload(); }, 700);
      })
      .catch(function () {
        formSubmit.disabled = false;
        showToast('Network error — please try again.', 'error');
      });
  });
})();
JS;

require __DIR__ . '/../../views/layouts/app.php';
