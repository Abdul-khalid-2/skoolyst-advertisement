<?php
require __DIR__ . '/../core/Autoload.php';
require __DIR__ . '/../core/Env.php';
require __DIR__ . '/../views/bootstrap.php';

use App\Auth\UserRepository;
use Core\Auth\Middleware;

Core\Env::load(__DIR__ . '/../.env');

// Self-service profile — any logged-in role, admin or advertiser. Not a
// role-gated page like admin/*.php: it just shows/edits whoever is
// currently signed in, same session check as my-ads.php.
$userId = Middleware::checkSession();
if ($userId === null) {
    header('Location: index.html');
    exit;
}

$currentUser = (new UserRepository())->findById($userId);
if ($currentUser === null) {
    header('Location: index.html');
    exit;
}

$pageTitle  = 'My Profile';
$role       = $currentUser->isAdmin() ? 'admin' : 'advertiser';
$activeNav  = 'profile';
$baseHref   = '';

ob_start();
?>
<?= csrf_field() ?>
<div class="db-page-head">
  <div>
    <h1>My Profile</h1>
    <p>View and update your own account details.</p>
  </div>
</div>

<div class="db-card" style="max-width:520px;">
  <div class="db-card__body d-flex flex-column gap-3">
    <div class="d-flex align-items-center gap-3 mb-2">
      <div class="db-avatar" style="width:56px;height:56px;font-size:1.2rem;"><?= htmlspecialchars(user_initials($currentUser->name)) ?></div>
      <div>
        <p class="fw-bold mb-0" style="font-size:1.1rem;"><?= htmlspecialchars($currentUser->name) ?></p>
        <p class="small text-muted mb-0"><?= $currentUser->isAdmin() ? 'Platform Admin' : 'Advertiser' ?> &middot; Joined <?= htmlspecialchars(date('M j, Y', strtotime($currentUser->createdAt))) ?></p>
      </div>
    </div>

    <div>
      <label class="db-form-label" for="profile-name">Name</label>
      <input type="text" id="profile-name" class="db-input" value="<?= htmlspecialchars($currentUser->name) ?>" maxlength="150">
    </div>
    <div>
      <label class="db-form-label" for="profile-email">Email</label>
      <input type="email" id="profile-email" class="db-input" value="<?= htmlspecialchars($currentUser->email) ?>">
    </div>

    <hr class="my-1">
    <p class="small text-muted mb-0">Change Password <span class="fw-normal">(leave blank to keep your current password)</span></p>
    <div>
      <label class="db-form-label" for="profile-current-password">Current Password</label>
      <input type="password" id="profile-current-password" class="db-input" autocomplete="current-password">
    </div>
    <div>
      <label class="db-form-label" for="profile-new-password">New Password</label>
      <input type="password" id="profile-new-password" class="db-input" autocomplete="new-password" placeholder="Min. 8 characters">
    </div>

    <div>
      <button type="button" class="btn btn-sk-primary" id="profile-submit">Save Changes</button>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();

$pageScript = <<<'JS'
(function () {
  'use strict';

  var csrfToken = document.getElementById('_csrf').value;
  var submitBtn = document.getElementById('profile-submit');

  submitBtn.addEventListener('click', function () {
    var name = document.getElementById('profile-name').value.trim();
    var email = document.getElementById('profile-email').value.trim();
    var currentPassword = document.getElementById('profile-current-password').value;
    var newPassword = document.getElementById('profile-new-password').value;

    if (!name || !email) {
      showToast('Name and email are both required.', 'error');
      return;
    }
    if (newPassword && !currentPassword) {
      showToast('Enter your current password to set a new one.', 'error');
      return;
    }
    if (newPassword && newPassword.length < 8) {
      showToast('New password must be at least 8 characters.', 'error');
      return;
    }

    submitBtn.disabled = true;
    fetch('api/v1/auth/profile', {
      method: 'PATCH',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ name: name, email: email, current_password: currentPassword, new_password: newPassword }),
    })
      .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
      .then(function (result) {
        submitBtn.disabled = false;
        if (!result.ok || !result.json.success) {
          var message = (result.json.error && result.json.error.message) || 'Could not save your changes.';
          showToast(message, 'error');
          return;
        }
        showToast('Profile updated.', 'success');
        document.getElementById('profile-current-password').value = '';
        document.getElementById('profile-new-password').value = '';
        window.setTimeout(function () { window.location.reload(); }, 700);
      })
      .catch(function () {
        submitBtn.disabled = false;
        showToast('Network error — please try again.', 'error');
      });
  });
})();
JS;

require __DIR__ . '/../views/layouts/app.php';
