<?php
/**
 * Shared app shell. Every dashboard/admin page builds its own $content via
 * output buffering, then requires this file once — so the <head>, sidebar,
 * topbar, and bottom scripts exist in exactly ONE place instead of being
 * copy-pasted into every page (the problem Section 2.1 exists to solve).
 *
 * Expected variables, set by the page BEFORE requiring this file:
 *   $pageTitle      string  Browser tab title AND topbar heading
 *   $role           string  'advertiser' | 'admin' — picks the sidebar partial
 *   $activeNav      string  which sidebar link gets the .active class
 *   $content        string  the page's own HTML, captured via ob_start()/ob_get_clean()
 *   $baseHref       string  path back to the project root ('../' from /dashboard or /admin, '' from root)
 *
 * Optional variables:
 *   $topbarActions, $topbarCrumb, $showSearch, $searchPlaceholder, $pageScript
 */
$baseHref = $baseHref ?? '';
?><!DOCTYPE html>
<html lang="en">
<?php require __DIR__ . '/../partials/head.php'; ?>
<body class="db-body<?= $role === 'admin' ? ' admin-theme' : '' ?>">

<div class="db-shell">
  <?php require __DIR__ . '/../partials/sidebar-' . $role . '.php'; ?>
  <div class="db-sidebar-backdrop"></div>

  <div class="db-main">
    <?php require __DIR__ . '/../partials/topbar.php'; ?>
    <main class="db-content">
<?= $content ?>
    </main>
  </div>
</div>

<?= modal_confirm() ?>

<?php require __DIR__ . '/../partials/scripts.php'; ?>
</body>
</html>
