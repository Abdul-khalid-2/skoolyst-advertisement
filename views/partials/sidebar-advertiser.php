<?php
/**
 * Advertiser sidebar. Expects $activeNav, $baseHref, and $currentUser
 * (the logged-in UserModel — resolved by views/layouts/app.php if the
 * page itself didn't already) from the page.
 */
?>
<aside class="db-sidebar">
  <a href="<?= $baseHref ?>dashboard/index" class="db-sidebar__brand">
    <span class="sk-brand-dot" aria-hidden="true"></span>
    Skoolyst Ads
    <span class="db-sidebar__mode">Advertiser</span>
  </a>

  <nav class="db-nav">
    <div class="db-nav-label">Overview</div>
    <a href="<?= $baseHref ?>dashboard/index" class="db-nav-link<?= nav_active('dashboard', $activeNav) ?>"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>

    <div class="db-nav-label">Advertising</div>
    <a href="<?= $baseHref ?>dashboard/create-ad" class="db-nav-link<?= nav_active('create-ad', $activeNav) ?>"><i class="bi bi-plus-circle-fill"></i> Create Ad</a>
    <a href="<?= $baseHref ?>dashboard/my-ads" class="db-nav-link<?= nav_active('my-ads', $activeNav) ?>"><i class="bi bi-megaphone-fill"></i> My Ads</a>

    <div class="db-nav-label">Account</div>
    <a href="#" class="db-nav-link disabled"><i class="bi bi-credit-card-fill"></i> Billing <span class="db-nav-soon">Soon</span></a>
    <a href="<?= $baseHref ?>profile" class="db-nav-link<?= nav_active('profile', $activeNav) ?>"><i class="bi bi-gear-fill"></i> Settings</a>
    <a href="<?= $baseHref ?>api-docs" class="db-nav-link<?= nav_active('api-docs', $activeNav) ?>"><i class="bi bi-code-slash"></i> API Docs</a>
  </nav>

  <div class="db-sidebar__footer">
    <div class="db-user-card">
      <a href="<?= $baseHref ?>profile" class="d-flex align-items-center gap-2 flex-grow-1" style="min-width:0;">
        <div class="db-avatar"><?= htmlspecialchars($currentUser !== null ? user_initials($currentUser->name) : '?') ?></div>
        <div style="min-width:0;">
          <p class="db-user-card__name mb-0 text-truncate"><?= htmlspecialchars($currentUser->name ?? 'Unknown') ?></p>
          <p class="db-user-card__role mb-0">Advertiser</p>
        </div>
      </a>
      <a href="<?= $baseHref ?>index.html" class="db-user-card__logout" title="Log out"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>
</aside>
