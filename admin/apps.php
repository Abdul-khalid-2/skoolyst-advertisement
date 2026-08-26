<?php
require __DIR__ . '/../views/bootstrap.php';

$pageTitle  = 'Connected Apps';
$role       = 'admin';
$activeNav  = 'admin-apps';
$baseHref   = '../';

$topbarActions = '<button type="button" class="btn btn-admin-primary btn-sm px-3 text-white" data-bs-toggle="modal" data-bs-target="#new-app-modal"><i class="bi bi-plus-lg me-1"></i> Connect New App</button>';

ob_start();
?>
<div class="db-page-head">
  <div>
    <h1>Connected Apps</h1>
    <p>Every site or app plugged into the shared AdEngine API, and the credentials it uses to request ads. <?= help_icon('api_key', $helpText) ?></p>
  </div>
</div>

<div class="row g-3" id="apps-grid"></div>

<!-- ============ NEW APP MODAL (UI only — its own form modal, distinct from the shared confirm modal) ============ -->
<div class="modal fade db-modal" id="new-app-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Connect a New App</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body d-flex flex-column gap-3">
        <?= csrf_field() ?>
        <div>
          <label class="db-form-label" for="new-app-name">App Name</label>
          <input type="text" id="new-app-name" class="db-input" placeholder="e.g. Skoolyst Marketplace">
        </div>
        <div>
          <label class="db-form-label" for="new-app-domain">Domain</label>
          <input type="text" id="new-app-domain" class="db-input" placeholder="e.g. marketplace.skoolyst.com">
        </div>
        <div class="db-form-hint">
          <i class="bi bi-info-circle me-1"></i>
          A live API key is generated automatically once the app is connected — see <a href="../api-docs.php">API Docs</a> for how to use it.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sk-outline" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-admin-primary text-white" id="confirm-new-app" data-bs-dismiss="modal">Connect App</button>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();

$pageScript = <<<'JS'
(function () {
  'use strict';

  function renderApps() {
    const grid = document.getElementById('apps-grid');
    grid.innerHTML = SkoolystAdsMock.apps.map(function (app) {
      const adCount = SkoolystAdsMock.ads.filter(function (a) { return a.app === app.id; }).length;
      const statusBadge = app.status === 'active'
        ? '<span class="badge-status badge-status--active">Active</span>'
        : '<span class="badge-status badge-status--paused">Paused</span>';

      return (
        '<div class="col-md-6 col-xl-4">' +
          '<div class="db-card h-100">' +
            '<div class="db-card__body d-flex flex-column gap-3">' +
              '<div class="d-flex align-items-start gap-3">' +
                '<span class="app-badge__icon" style="margin:0;">' + app.code + '</span>' +
                '<div class="flex-grow-1">' +
                  '<div class="d-flex align-items-center gap-2">' +
                    '<h3 class="mb-0" style="font-size:1rem;font-weight:700;">' + app.name + '</h3>' +
                    statusBadge +
                  '</div>' +
                  '<p class="small text-muted mb-0">' + app.domain + '</p>' +
                '</div>' +
                '<label class="db-switch" title="Enable / disable this connection">' +
                  '<input type="checkbox" ' + (app.status === 'active' ? 'checked' : '') + ' data-toggle-app="' + app.id + '">' +
                  '<span class="db-switch__track"></span>' +
                '</label>' +
              '</div>' +

              '<div class="d-flex gap-4">' +
                '<div><p class="db-stat__label mb-0">Placements</p><p class="fw-bold mb-0">' + app.placements + '</p></div>' +
                '<div><p class="db-stat__label mb-0">Ads Running</p><p class="fw-bold mb-0">' + adCount + '</p></div>' +
              '</div>' +

              '<div>' +
                '<label class="db-form-label mb-1">API Key</label>' +
                '<div class="key-reveal">' +
                  '<span class="key-reveal__value" id="key-' + app.id + '" data-full-value="' + app.apiKey.replace('...', '_REDACTED_') + '">' + app.apiKey + '</span>' +
                  '<button type="button" class="db-action-btn" data-copy-target="key-' + app.id + '" title="Copy key"><i class="bi bi-clipboard"></i></button>' +
                  '<button type="button" class="db-action-btn" title="Regenerate key" data-regen="' + app.id + '"><i class="bi bi-arrow-repeat"></i></button>' +
                '</div>' +
              '</div>' +

              '<a href="../api-docs.php" class="btn btn-sk-outline btn-sm mt-1">View Integration Guide</a>' +
            '</div>' +
          '</div>' +
        '</div>'
      );
    }).join('');

    grid.querySelectorAll('[data-toggle-app]').forEach(function (input) {
      input.addEventListener('change', function () {
        const app = SkoolystAdsMock.apps.find(function (a) { return a.id === input.dataset.toggleApp; });
        const goingActive = input.checked;
        if (!goingActive) {
          confirmAction({
            title: 'Pause ' + app.name + '?',
            body: 'Ads from ' + app.name + ' will stop being served immediately. You can turn it back on any time.',
            confirmLabel: 'Pause App',
            danger: true,
            onConfirm: function () {
              app.status = 'paused';
              showToast(app.name + ' is now paused.', 'info');
              renderApps();
            },
          });
          input.checked = true; // revert until confirmed
        } else {
          app.status = 'active';
          showToast(app.name + ' is now active.', 'success');
          renderApps();
        }
      });
    });

    grid.querySelectorAll('[data-regen]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        confirmAction({
          title: 'Regenerate API key?',
          body: 'The old key will stop working immediately — update it in the connected app right away.',
          confirmLabel: 'Regenerate',
          danger: true,
          onConfirm: function () {
            showToast('New API key generated. Update it in the connected app before the old one expires.', 'success');
          },
        });
      });
    });
  }

  document.getElementById('confirm-new-app').addEventListener('click', function () {
    const name = document.getElementById('new-app-name').value.trim();
    if (!name) { showToast('Give the app a name first.', 'error'); return; }
    showToast('"' + name + '" connected — an API key has been generated.', 'success');
    document.getElementById('new-app-name').value = '';
    document.getElementById('new-app-domain').value = '';
  });

  renderApps();
})();
JS;

require __DIR__ . '/../views/layouts/app.php';
