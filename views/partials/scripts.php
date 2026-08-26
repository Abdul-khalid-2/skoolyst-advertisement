<?php
/**
 * Shared bottom-of-body scripts. Injects the single mock-data source
 * (data/mock-data.php) as JSON so js/dashboard.js has one place to read
 * from instead of hardcoding its own copy — this is what Section 3/4 will
 * later swap for a real fetch() to the API without touching any page.
 *
 * Expects from the page: $baseHref, $mockData, and optionally $pageScript
 * (raw JS appended after dashboard.js, e.g. for page-specific behaviour).
 */
$pageScript = $pageScript ?? '';

// Image paths in mock-data.php are root-relative ("assets/img/ad-1.svg");
// prefix them with this page's $baseHref before handing the data to JS.
$jsMockData = $mockData;
if (!empty($jsMockData['ads'])) {
    foreach ($jsMockData['ads'] as &$ad) {
        $ad['image'] = $baseHref . $ad['image'];
    }
    unset($ad);
}
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>window.SkoolystAdsMockData = <?= json_encode($jsMockData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= $baseHref ?>assets/js/dashboard.js"></script>
<?php if ($pageScript !== ''): ?>
<script>
<?= $pageScript ?>
</script>
<?php endif; ?>
