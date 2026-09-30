<?php
/**
 * Public marketing-site shell — the same navbar/footer chrome as
 * public/index.html, for pages that need to live on the public website
 * (not gated behind the admin/advertiser dashboard) but aren't the
 * single-page landing page itself. Currently just api-docs: the API
 * reference is documentation for anyone integrating with the platform,
 * not an internal admin/advertiser tool, so it shouldn't require a
 * login or show dashboard sidebar chrome (10.r).
 *
 * Expected variables, set by the page BEFORE requiring this file:
 *   $pageTitle   string  Browser tab title (via head.php)
 *   $content     string  the page's own HTML, captured via ob_start()/ob_get_clean()
 *   $baseHref    string  path back to the project root ('' from public/ root, same convention as app.php)
 *
 * Optional: $metaDescription, $pageScript (raw JS appended after dashboard.js).
 */
$baseHref = $baseHref ?? '';
$pageScript = $pageScript ?? '';
?><!DOCTYPE html>
<html lang="en">
<?php require __DIR__ . '/../partials/head.php'; ?>
<body>

  <!-- ============ NAVBAR (same markup/classes as index.html's) ============ -->
  <nav class="navbar navbar-expand-lg sk-navbar sticky-top py-3">
    <div class="container container-xl">
      <a class="navbar-brand sk-brand" href="<?= $baseHref ?>index.html">
        <span class="sk-brand-dot" aria-hidden="true"></span>
        Skoolyst
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#skNav" aria-controls="skNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="skNav">
        <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link sk-nav-link" href="<?= $baseHref ?>index.html#top">Home</a></li>
          <li class="nav-item"><a class="nav-link sk-nav-link" href="<?= $baseHref ?>index.html#schools">Schools</a></li>
          <li class="nav-item"><a class="nav-link sk-nav-link" href="<?= $baseHref ?>index.html#teachers">Teachers</a></li>
          <li class="nav-item"><a class="nav-link sk-nav-link" href="<?= $baseHref ?>index.html#parents">Parents</a></li>
          <li class="nav-item"><a class="nav-link sk-nav-link" href="<?= $baseHref ?>index.html#students">Students</a></li>
          <li class="nav-item"><a class="nav-link sk-nav-link active" href="<?= $baseHref ?>api-docs">API Docs</a></li>
        </ul>
        <div class="d-flex gap-2" id="nav-auth-buttons">
          <a href="<?= $baseHref ?>login" class="btn btn-sk-outline">Log In</a>
          <a href="<?= $baseHref ?>signup" class="btn btn-sk-primary">Sign Up</a>
        </div>
      </div>
    </div>
  </nav>

  <main class="container container-xl sk-public-page">
<?= $content ?>
  </main>

  <!-- ============ FOOTER (same markup/classes as index.html's) ============ -->
  <footer class="sk-footer">
    <div class="container container-xl">
      <div class="row gy-4">
        <div class="col-md-5">
          <h3>Skoolyst Ads</h3>
          <p class="mt-2 mb-0" style="max-width: 360px;">
            Centralized advertisement management for the whole Skoolyst family of apps.
          </p>
        </div>
        <div class="col-md-3">
          <h4 class="text-white-50 text-uppercase small mb-3" style="letter-spacing:.05em;">Links</h4>
          <ul class="list-unstyled d-flex flex-column gap-2">
            <li><a href="<?= $baseHref ?>index.html#top">Home</a></li>
            <li><a href="<?= $baseHref ?>dashboard/index">Advertiser Dashboard</a></li>
            <li><a href="<?= $baseHref ?>admin/index">Admin</a></li>
            <li><a href="<?= $baseHref ?>api-docs">API Docs</a></li>
            <li><a href="#">Privacy</a></li>
            <li><a href="#">Terms</a></li>
          </ul>
        </div>
        <div class="col-md-4">
          <h4 class="text-white-50 text-uppercase small mb-3" style="letter-spacing:.05em;">Follow</h4>
          <div class="d-flex gap-2">
            <a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
            <a href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
            <a href="#" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
          </div>
        </div>
      </div>
      <div class="sk-footer-bottom text-center">
        &copy; <span id="footer-year"></span> Skoolyst Ads. Part of the Skoolyst platform.
      </div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>document.getElementById('footer-year').textContent = new Date().getFullYear();</script>
  <script src="<?= $baseHref ?>assets/js/app.js"></script>
  <script>
  (function () {
    'use strict';
    // Same session-aware swap as index.html's navbar — a logged-in
    // visitor sees Dashboard/Create an Ad instead of Log In/Sign Up.
    fetch('<?= $baseHref ?>api/v1/auth/session', { credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (json) {
        if (!json.success || !json.data.loggedIn) return;
        var container = document.getElementById('nav-auth-buttons');
        var dashboardHref = json.data.role === 'admin' ? '<?= $baseHref ?>admin/index' : '<?= $baseHref ?>dashboard/index';
        container.innerHTML =
          '<a href="' + dashboardHref + '" class="btn btn-sk-outline">Dashboard</a>' +
          '<a href="<?= $baseHref ?>dashboard/create-ad" class="btn btn-sk-primary">Create an Ad</a>';
      })
      .catch(function () { /* stay on Log In/Sign Up if the check fails */ });
  })();
  </script>

  <?php
  // dashboard.js is still needed here (not just on the dashboard shell) —
  // it's what powers this page's own code-tab switcher, docs-nav
  // scrollspy, copy buttons, and tooltips (initCodeTabs/initDocsNav/
  // initCopyButtons/initTooltips). Every other initializer it runs
  // (initSidebar, initLogout, ...) no-ops safely when its target
  // elements don't exist on a page with no dashboard sidebar.
  $dashboardJsPath = __DIR__ . '/../../public/assets/js/dashboard.js';
  $dashboardJsVer  = file_exists($dashboardJsPath) ? filemtime($dashboardJsPath) : time();
  ?>
  <script src="<?= $baseHref ?>assets/js/dashboard.js?v=<?= $dashboardJsVer ?>"></script>
  <?php if ($pageScript !== ''): ?>
  <script>
  <?= $pageScript ?>
  </script>
  <?php endif; ?>
</body>
</html>
