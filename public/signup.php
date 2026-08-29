<?php
require __DIR__ . '/../core/Autoload.php';
require __DIR__ . '/../core/Env.php';

use Core\Auth\Middleware;
use App\Auth\UserRepository;

Core\Env::load(__DIR__ . '/../.env');

// Same already-logged-in redirect as login.php.
$existingUserId = Middleware::checkSession();
if ($existingUserId !== null) {
    $existingUser = (new UserRepository())->findById($existingUserId);
    header('Location: ' . ($existingUser !== null && $existingUser->isAdmin() ? 'admin/index.php' : 'dashboard/my-ads.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign Up — Skoolyst Ads</title>
  <meta name="robots" content="noindex">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="sk-auth-page">
  <div class="sk-auth-card">
    <a href="index.html" class="sk-brand">
      <span class="sk-brand-dot" aria-hidden="true"></span>
      Skoolyst
    </a>
    <h1>Create your advertiser account</h1>
    <p class="sk-auth-sub">Sign up to create and manage ads across the Skoolyst family of apps.</p>

    <div class="sk-auth-alert sk-auth-alert--error" id="alert-error"></div>

    <form id="signup-form" novalidate>
      <div class="mb-3">
        <label for="f-name" class="form-label">Full name</label>
        <input type="text" class="form-control" id="f-name" required autocomplete="name">
      </div>
      <div class="mb-3">
        <label for="f-email" class="form-label">Email address</label>
        <input type="email" class="form-control" id="f-email" required autocomplete="email">
      </div>
      <div class="mb-3">
        <label for="f-password" class="form-label">Password</label>
        <input type="password" class="form-control" id="f-password" required autocomplete="new-password" minlength="8">
        <div class="form-text">At least 8 characters.</div>
      </div>
      <div class="mb-3">
        <label for="f-password-confirm" class="form-label">Confirm password</label>
        <input type="password" class="form-control" id="f-password-confirm" required autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-sk-primary w-100" id="btn-signup">Sign Up</button>
    </form>

    <p class="sk-auth-footer">Already have an account? <a href="login.php">Log in</a></p>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  'use strict';

  var alertError = document.getElementById('alert-error');
  var btn = document.getElementById('btn-signup');
  var originalBtnText = btn.textContent;

  document.getElementById('signup-form').addEventListener('submit', function (e) {
    e.preventDefault();
    alertError.style.display = 'none';

    var name = document.getElementById('f-name').value.trim();
    var email = document.getElementById('f-email').value.trim();
    var password = document.getElementById('f-password').value;
    var passwordConfirm = document.getElementById('f-password-confirm').value;

    if (!name || !email || !password) {
      alertError.textContent = 'Please fill in every field.';
      alertError.style.display = 'block';
      return;
    }
    if (password.length < 8) {
      alertError.textContent = 'Password must be at least 8 characters.';
      alertError.style.display = 'block';
      return;
    }
    if (password !== passwordConfirm) {
      alertError.textContent = 'Passwords do not match.';
      alertError.style.display = 'block';
      return;
    }

    btn.disabled = true;
    btn.textContent = 'Creating account…';

    fetch('api/v1/auth/register', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: name, email: email, password: password })
    })
      .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
      .then(function (result) {
        if (!result.ok || !result.json.success) {
          alertError.textContent = (result.json.error && result.json.error.message) || 'Could not create your account.';
          alertError.style.display = 'block';
          btn.disabled = false;
          btn.textContent = originalBtnText;
          return;
        }
        // register() doesn't start a session by itself (Section 6 design —
        // signup and login are separate steps), so send them to log in.
        window.location.href = 'login.php?registered=1';
      })
      .catch(function () {
        alertError.textContent = 'Network error — please try again.';
        alertError.style.display = 'block';
        btn.disabled = false;
        btn.textContent = originalBtnText;
      });
  });
})();
</script>
</body>
</html>
