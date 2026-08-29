<?php
require __DIR__ . '/../core/Autoload.php';
require __DIR__ . '/../core/Env.php';

use Core\Auth\Middleware;
use App\Auth\UserRepository;

Core\Env::load(__DIR__ . '/../.env');

// Already logged in? Skip the form and go straight to the right place —
// same role check pattern as dashboard/index.php and admin/index.php.
$existingUserId = Middleware::checkSession();
if ($existingUserId !== null) {
    $existingUser = (new UserRepository())->findById($existingUserId);
    header('Location: ' . ($existingUser !== null && $existingUser->isAdmin() ? 'admin/index.php' : 'dashboard/my-ads.php'));
    exit;
}

$registered = isset($_GET['registered']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Log In — Skoolyst Ads</title>
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
    <h1>Log in to your account</h1>
    <p class="sk-auth-sub">Manage your ads and see how they're performing.</p>

    <div class="sk-auth-alert sk-auth-alert--success" id="alert-success">
      Account created — log in below to continue.
    </div>
    <div class="sk-auth-alert sk-auth-alert--error" id="alert-error"></div>

    <form id="login-form" novalidate>
      <div class="mb-3">
        <label for="f-email" class="form-label">Email address</label>
        <input type="email" class="form-control" id="f-email" required autocomplete="email">
      </div>
      <div class="mb-3">
        <label for="f-password" class="form-label">Password</label>
        <input type="password" class="form-control" id="f-password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-sk-primary w-100" id="btn-login">Log In</button>
    </form>

    <p class="sk-auth-footer">Don't have an account? <a href="signup.php">Sign up</a></p>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  'use strict';

  var alertSuccess = document.getElementById('alert-success');
  var alertError = document.getElementById('alert-error');
  var registered = <?= $registered ? 'true' : 'false' ?>;
  if (registered) alertSuccess.style.display = 'block';

  var btn = document.getElementById('btn-login');
  var originalBtnText = btn.textContent;

  document.getElementById('login-form').addEventListener('submit', function (e) {
    e.preventDefault();

    alertError.style.display = 'none';
    alertSuccess.style.display = 'none';

    var email = document.getElementById('f-email').value.trim();
    var password = document.getElementById('f-password').value;

    if (!email || !password) {
      alertError.textContent = 'Please enter both your email and password.';
      alertError.style.display = 'block';
      return;
    }

    btn.disabled = true;
    btn.textContent = 'Logging in…';

    fetch('api/v1/auth/login', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: email, password: password })
    })
      .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
      .then(function (result) {
        if (!result.ok || !result.json.success) {
          alertError.textContent = (result.json.error && result.json.error.message) || 'Could not log in.';
          alertError.style.display = 'block';
          btn.disabled = false;
          btn.textContent = originalBtnText;
          return;
        }
        window.location.href = result.json.data.role === 'admin' ? 'admin/index.php' : 'dashboard/my-ads.php';
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
