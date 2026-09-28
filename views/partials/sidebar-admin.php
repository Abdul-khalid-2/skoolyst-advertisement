<?php
/**
 * Admin sidebar. Expects $activeNav, $baseHref, and $currentUser (the
 * logged-in UserModel — resolved by views/layouts/app.php if the page
 * itself didn't already) from the page.
 */
?>
<aside class="db-sidebar db-sidebar--admin">
  <a href="<?= $baseHref ?>admin/index.php" class="db-sidebar__brand">
    <span class="sk-brand-dot" aria-hidden="true"></span>
    Skoolyst Ads
    <span class="db-sidebar__mode">Admin</span>
  </a>

  <nav class="db-nav">
    <div class="db-nav-label">Overview</div>
    <a href="<?= $baseHref ?>admin/index.php" class="db-nav-link<?= nav_active('admin-overview', $activeNav) ?>"><i class="bi bi-speedometer2"></i> Overview</a>

    <div class="db-nav-label">Moderation</div>
    <a href="<?= $baseHref ?>admin/ads.php" class="db-nav-link<?= nav_active('admin-ads', $activeNav) ?>"><i class="bi bi-shield-check"></i> All Ads</a>

    <div class="db-nav-label">Platform</div>
    <a href="<?= $baseHref ?>admin/apps.php" class="db-nav-link<?= nav_active('admin-apps', $activeNav) ?>"><i class="bi bi-grid-3x3-gap-fill"></i> Connected Apps</a>
    <a href="<?= $baseHref ?>admin/advertisers.php" class="db-nav-link<?= nav_active('admin-advertisers', $activeNav) ?>"><i class="bi bi-people-fill"></i> Advertisers</a>
    <a href="<?= $baseHref ?>admin/email-accounts.php" class="db-nav-link<?= nav_active('admin-email-accounts', $activeNav) ?>"><i class="bi bi-envelope-at-fill"></i> Email Accounts</a>
    <a href="<?= $baseHref ?>admin/email-inbox.php" class="db-nav-link<?= nav_active('admin-email-inbox', $activeNav) ?>"><i class="bi bi-inbox-fill"></i> Email Inbox</a>
    <a href="<?= $baseHref ?>api-docs.php" class="db-nav-link<?= nav_active('api-docs', $activeNav) ?>"><i class="bi bi-code-slash"></i> API Docs</a>
  </nav>

  <div class="db-sidebar__footer">
    <div class="db-user-card">
      <a href="<?= $baseHref ?>profile.php" class="d-flex align-items-center gap-2 flex-grow-1" style="min-width:0;">
        <div class="db-avatar"><?= htmlspecialchars($currentUser !== null ? user_initials($currentUser->name) : '?') ?></div>
        <div style="min-width:0;">
          <p class="db-user-card__name mb-0 text-truncate"><?= htmlspecialchars($currentUser->name ?? 'Unknown') ?></p>
          <p class="db-user-card__role mb-0">Platform Admin</p>
        </div>
      </a>
      <a href="<?= $baseHref ?>index.html" class="db-user-card__logout" title="Log out"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>
</aside>
