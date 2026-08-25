<?php
/**
 * Advertiser sidebar. Expects $activeNav and $baseHref from the page.
 * Loaded by views/layouts/app.php when $role === 'advertiser'.
 */
?>
<aside class="db-sidebar">
  <a href="<?= $baseHref ?>dashboard/index.php" class="db-sidebar__brand">
    <span class="sk-brand-dot" aria-hidden="true"></span>
    Skoolyst Ads
    <span class="db-sidebar__mode">Advertiser</span>
  </a>

  <nav class="db-nav">
    <div class="db-nav-label">Overview</div>
    <a href="<?= $baseHref ?>dashboard/index.php" class="db-nav-link<?= nav_active('dashboard', $activeNav) ?>"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>

    <div class="db-nav-label">Advertising</div>
    <a href="<?= $baseHref ?>dashboard/create-ad.php" class="db-nav-link<?= nav_active('create-ad', $activeNav) ?>"><i class="bi bi-plus-circle-fill"></i> Create Ad</a>
    <a href="<?= $baseHref ?>dashboard/my-ads.php" class="db-nav-link<?= nav_active('my-ads', $activeNav) ?>"><i class="bi bi-megaphone-fill"></i> My Ads</a>

    <div class="db-nav-label">Account</div>
    <a href="#" class="db-nav-link disabled"><i class="bi bi-credit-card-fill"></i> Billing <span class="db-nav-soon">Soon</span></a>
    <a href="#" class="db-nav-link disabled"><i class="bi bi-gear-fill"></i> Settings <span class="db-nav-soon">Soon</span></a>
    <a href="<?= $baseHref ?>api-docs.php" class="db-nav-link<?= nav_active('api-docs', $activeNav) ?>"><i class="bi bi-code-slash"></i> API Docs</a>
  </nav>

  <div class="db-sidebar__footer">
    <a href="<?= $baseHref ?>admin/index.php" class="db-switcher">
      <i class="bi bi-shield-lock-fill"></i> Switch to Admin View <i class="bi bi-chevron-right"></i>
    </a>
    <div class="db-user-card">
      <div class="db-avatar">KK</div>
      <div>
        <p class="db-user-card__name mb-0">Khalid Khan</p>
        <p class="db-user-card__role mb-0">Advertiser</p>
      </div>
      <a href="<?= $baseHref ?>index.html" class="db-user-card__logout" title="Log out"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>
</aside>
