<?php
/**
 * Shared topbar. Expects from the page:
 *   $pageTitle          string
 *   $topbarCrumb        string  optional breadcrumb HTML under the title
 *   $showSearch         bool    optional, default true
 *   $searchPlaceholder  string  optional
 *   $topbarActions      string  optional HTML for the right-hand side (buttons/chips)
 */
$showSearch = $showSearch ?? true;
$topbarActions = $topbarActions ?? '';
$topbarCrumb = $topbarCrumb ?? '';
?>
<header class="db-topbar">
  <button type="button" class="db-sidebar-toggle" data-sidebar-toggle aria-label="Toggle menu">
    <i class="bi bi-list"></i>
  </button>
  <div>
    <h1 class="db-topbar__title" id="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
    <?php if ($topbarCrumb !== ''): ?>
      <p class="db-topbar__crumb"><?= $topbarCrumb ?></p>
    <?php endif; ?>
  </div>
  <?php if ($showSearch): ?>
    <div class="db-search">
      <i class="bi bi-search"></i>
      <input type="search" placeholder="<?= htmlspecialchars($searchPlaceholder ?? 'Search…') ?>" aria-label="Search">
    </div>
  <?php endif; ?>
  <div class="db-topbar__actions">
    <?= $topbarActions ?>
  </div>
</header>
