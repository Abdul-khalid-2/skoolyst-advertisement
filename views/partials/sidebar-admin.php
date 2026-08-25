<?php
/**
 * Admin sidebar. Expects $activeNav and $baseHref from the page.
 * Loaded by views/layouts/app.php when $role === 'admin'.
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
    <a href="#" class="db-nav-link disabled"><i class="bi bi-people-fill"></i> Advertisers <span class="db-nav-soon">Soon</span></a>
    <a href="<?= $baseHref ?>api-docs.php" class="db-nav-link<?= nav_active('api-docs', $activeNav) ?>"><i class="bi bi-code-slash"></i> API Docs</a>
  </nav>

  <div class="db-sidebar__footer">
    <a href="<?= $baseHref ?>dashboard/index.php" class="db-switcher">
      <i class="bi bi-megaphone-fill"></i> Switch to Advertiser View <i class="bi bi-chevron-right"></i>
    </a>
    <div class="db-user-card">
      <div class="db-avatar">KK</div>
      <div>
        <p class="db-user-card__name mb-0">Khalid Khan</p>
        <p class="db-user-card__role mb-0">Platform Admin</p>
      </div>
      <a href="<?= $baseHref ?>index.html" class="db-user-card__logout" title="Log out"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>
</aside>
