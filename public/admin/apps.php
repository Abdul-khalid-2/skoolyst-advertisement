<?php
require __DIR__ . '/../../core/Autoload.php';
require __DIR__ . '/../../core/Env.php';
require __DIR__ . '/../../views/bootstrap.php';

use App\Apps\AppRepository;
use App\Auth\UserRepository;
use Core\Auth\Middleware;

Core\Env::load(__DIR__ . '/../../.env');

// No login page exists yet (Section 6 was tested via direct API calls) —
// same admin-role check as admin/ads.php, not just a session check.
$userId = Middleware::checkSession();
$currentUser = $userId !== null ? (new UserRepository())->findById($userId) : null;
if ($currentUser === null || !$currentUser->isAdmin()) {
    header('Location: ../index.html');
    exit;
}

$appRepo = new AppRepository();
$apps = $appRepo->allWithCounts();

$pageTitle  = 'Connected Apps';
$role       = 'admin';
$activeNav  = 'admin-apps';
$baseHref   = '../';

$topbarActions = '<button type="button" class="btn btn-admin-primary btn-sm px-3 text-white" data-bs-toggle="modal" data-bs-target="#new-app-modal"><i class="bi bi-plus-lg me-1"></i> Connect New App</button>';

/**
 * One connected-app card (10.i). The API key itself is never shown
 * here — only its hash is ever persisted (AppRepository::createWithApiKey's
 * doc-block), so there's nothing to redisplay after the moment it was
 * issued/regenerated. That moment is handled separately, in JS, via a
 * one-time reveal right after the create/regenerate fetch succeeds.
 */
function render_app_card(array $app): string
{
    $statusBadge = $app['status'] === 'active'
        ? '<span class="badge-status badge-status--active">Active</span>'
        : '<span class="badge-status badge-status--paused">Paused</span>';

    return '<div class="col-md-6 col-xl-4">'
        . '<div class="db-card h-100" data-app-id="' . htmlspecialchars($app['id']) . '" data-app-name="' . htmlspecialchars($app['name']) . '">'
        . '<div class="db-card__body d-flex flex-column gap-3">'
        . '<div class="d-flex align-items-start gap-3">'
        . '<span class="app-badge__icon" style="margin:0;">' . htmlspecialchars($app['code']) . '</span>'
        . '<div class="flex-grow-1">'
        . '<div class="d-flex align-items-center gap-2">'
        . '<h3 class="mb-0" style="font-size:1rem;font-weight:700;">' . htmlspecialchars($app['name']) . '</h3>'
        . $statusBadge
        . '</div>'
        . '<p class="small text-muted mb-0">' . htmlspecialchars($app['domain']) . '</p>'
        . '</div>'
        . '<label class="db-switch" title="Enable / disable this connection">'
        . '<input type="checkbox" ' . ($app['status'] === 'active' ? 'checked' : '') . ' data-toggle-app="' . htmlspecialchars($app['id']) . '">'
        . '<span class="db-switch__track"></span>'
        . '</label>'
        . '</div>'
        . '<div class="d-flex gap-4">'
        . '<div><p class="db-stat__label mb-0">Placements</p><p class="fw-bold mb-0" data-placements-count>' . (int) $app['placements_count'] . '</p></div>'
        . '<div><p class="db-stat__label mb-0">Ads Running</p><p class="fw-bold mb-0">' . (int) $app['ads_count'] . '</p></div>'
        . '</div>'
        . '<button type="button" class="btn btn-sk-outline btn-sm" data-manage-placements="' . htmlspecialchars($app['id']) . '"><i class="bi bi-signpost-split me-1"></i> Manage Placements</button>'
        . '<div>'
        . '<label class="db-form-label mb-1">API Key</label>'
        . '<div class="key-reveal">'
        . '<span class="key-reveal__value text-muted">Hidden — regenerate to view a new key</span>'
        . '<button type="button" class="db-action-btn" title="Regenerate key" data-regen="' . htmlspecialchars($app['id']) . '"><i class="bi bi-arrow-repeat"></i></button>'
        . '</div>'
        . '</div>'
        . '<a href="../api-docs.php" class="btn btn-sk-outline btn-sm mt-1">View Integration Guide</a>'
        . '</div></div></div>';
}

ob_start();
?>
<?= csrf_field() ?>
<div class="db-page-head">
  <div>
    <h1>Connected Apps</h1>
    <p>Every site or app plugged into the shared AdEngine API, and the credentials it uses to request ads. <?= help_icon('api_key', $helpText) ?></p>
  </div>
</div>

<div class="row g-3" id="apps-grid"><?php foreach ($apps as $app) { echo render_app_card($app); } ?></div>

<!-- ============ NEW APP MODAL (its own form modal, distinct from the shared confirm modal) ============ -->
<div class="modal fade db-modal" id="new-app-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Connect a New App</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body d-flex flex-column gap-3">
        <div>
          <label class="db-form-label" for="new-app-name">App Name</label>
          <input type="text" id="new-app-name" class="db-input" placeholder="e.g. Skoolyst Marketplace">
        </div>
        <div>
          <label class="db-form-label" for="new-app-code">Short Code</label>
          <input type="text" id="new-app-code" class="db-input" placeholder="e.g. skm" maxlength="20">
          <div class="db-form-hint">A short, unique identifier for this app — letters/numbers only.</div>
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
        <button type="button" class="btn btn-admin-primary text-white" id="confirm-new-app">Connect App</button>
      </div>
    </div>
  </div>
</div>

<!-- ============ PLACEMENTS MODAL (10.o — per-app placement CRUD) ============ -->
<div class="modal fade db-modal" id="placements-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" data-app-id="">
      <div class="modal-header">
        <h5 class="modal-title">Placements — <span id="placements-modal-app-name">—</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body d-flex flex-column gap-3">
        <p class="small text-muted mb-0">
          Each connected app defines its own placement codes — the values a <code>GET /ads/serve?placement={code}</code>
          request from this app can ask for. A code only needs to be unique within this app.
        </p>
        <div id="placements-list" class="d-flex flex-column gap-2"></div>
        <p class="small text-muted mb-0 d-none" id="placements-empty">No placements yet — add one below.</p>
        <hr class="my-1">
        <div class="row g-2 align-items-end">
          <input type="hidden" id="placement-form-id" value="">
          <div class="col-md-4">
            <label class="db-form-label" for="placement-form-code">Code</label>
            <input type="text" id="placement-form-code" class="db-input" placeholder="e.g. homepage-banner" maxlength="100">
          </div>
          <div class="col-md-6">
            <label class="db-form-label" for="placement-form-label">Label</label>
            <input type="text" id="placement-form-label" class="db-input" placeholder="e.g. Homepage Banner" maxlength="150">
          </div>
          <div class="col-md-2 d-flex gap-2">
            <button type="button" class="btn btn-admin-primary text-white flex-grow-1" id="placement-form-submit">Add</button>
            <button type="button" class="db-action-btn d-none" id="placement-form-cancel" title="Cancel edit"><i class="bi bi-x-lg"></i></button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();

$pageScript = <<<'JS'
(function () {
  'use strict';

  var csrfToken = document.getElementById('_csrf').value;
  var grid = document.getElementById('apps-grid');

  function patchApp(appId, status, auditAction) {
    return fetch('../api/v1/admin/apps/' + encodeURIComponent(appId), {
      method: 'PATCH',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ app_id: appId, status: status }),
    }).then(function (res) {
      return res.json().then(function (json) { return { ok: res.ok, json: json }; });
    });
  }

  grid.addEventListener('change', function (e) {
    var input = e.target.closest('[data-toggle-app]');
    if (!input) return;

    var card = input.closest('[data-app-id]');
    var appId = card.dataset.appId;
    var appName = card.dataset.appName;
    var goingActive = input.checked;

    function apply() {
      patchApp(appId, goingActive ? 'active' : 'paused')
        .then(function (result) {
          if (!result.ok || !result.json.success) {
            var message = (result.json.error && result.json.error.message) || 'Could not update this app.';
            showToast(message, 'error');
            input.checked = !goingActive;
            return;
          }
          showToast(appName + ' is now ' + (goingActive ? 'active' : 'paused') + '.', goingActive ? 'success' : 'info');
          card.querySelector('.badge-status').outerHTML = goingActive
            ? '<span class="badge-status badge-status--active">Active</span>'
            : '<span class="badge-status badge-status--paused">Paused</span>';
        })
        .catch(function () {
          showToast('Network error — please try again.', 'error');
          input.checked = !goingActive;
        });
    }

    if (!goingActive) {
      confirmAction({
        title: 'Pause ' + appName + '?',
        body: 'Ads from ' + appName + ' will stop being served immediately. You can turn it back on any time.',
        confirmLabel: 'Pause App',
        danger: true,
        onConfirm: apply,
      });
      input.checked = true; // revert until confirmed; apply() flips it back on success
    } else {
      apply();
    }
  });

  grid.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-regen]');
    if (!btn) return;
    var card = btn.closest('[data-app-id]');
    var appId = card.dataset.appId;
    var appName = card.dataset.appName;

    confirmAction({
      title: 'Regenerate API key?',
      body: 'The old key will stop working immediately — update it in the connected app right away.',
      confirmLabel: 'Regenerate',
      danger: true,
      onConfirm: function () {
        btn.disabled = true;
        fetch('../api/v1/admin/apps/' + encodeURIComponent(appId) + '/regenerate-key', {
          method: 'PATCH',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
          body: JSON.stringify({ app_id: appId }),
        })
          .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
          .then(function (result) {
            btn.disabled = false;
            if (!result.ok || !result.json.success) {
              var message = (result.json.error && result.json.error.message) || 'Could not regenerate the key.';
              showToast(message, 'error');
              return;
            }
            // Shown exactly once — the server never stores or returns
            // the plaintext key again after this response.
            window.prompt('New API key for ' + appName + ' — copy it now, it will not be shown again:', result.json.data.api_key);
            showToast('New API key generated for ' + appName + '.', 'success');
          })
          .catch(function () {
            btn.disabled = false;
            showToast('Network error — please try again.', 'error');
          });
      },
    });
  });

  document.getElementById('confirm-new-app').addEventListener('click', function () {
    var nameEl = document.getElementById('new-app-name');
    var codeEl = document.getElementById('new-app-code');
    var domainEl = document.getElementById('new-app-domain');
    var name = nameEl.value.trim();
    var code = codeEl.value.trim();
    var domain = domainEl.value.trim();

    if (!name || !code || !domain) {
      showToast('Name, short code, and domain are all required.', 'error');
      return;
    }

    fetch('../api/v1/admin/apps', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify({ name: name, code: code, domain: domain }),
    })
      .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
      .then(function (result) {
        if (!result.ok || !result.json.success) {
          var message = (result.json.error && result.json.error.message) || 'Could not connect this app.';
          showToast(message, 'error');
          return;
        }
        var modalEl = document.getElementById('new-app-modal');
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        nameEl.value = '';
        codeEl.value = '';
        domainEl.value = '';
        // Shown exactly once, same as regenerate above.
        window.prompt('API key for "' + name + '" — copy it now, it will not be shown again:', result.json.data.api_key);
        showToast('"' + name + '" connected — an API key has been generated.', 'success');
        window.setTimeout(function () { window.location.reload(); }, 900);
      })
      .catch(function () {
        showToast('Network error — please try again.', 'error');
      });
  });

  // -----------------------------------------------------------------------
  // Placements (10.o) — per-app CRUD, opened via each card's
  // "Manage Placements" button. One shared modal, re-populated per
  // app on open rather than one modal instance per card.
  // -----------------------------------------------------------------------
  var placementsModalEl = document.getElementById('placements-modal');
  var placementsList = document.getElementById('placements-list');
  var placementsEmpty = document.getElementById('placements-empty');
  var placementFormId = document.getElementById('placement-form-id');
  var placementFormCode = document.getElementById('placement-form-code');
  var placementFormLabel = document.getElementById('placement-form-label');
  var placementFormSubmit = document.getElementById('placement-form-submit');
  var placementFormCancel = document.getElementById('placement-form-cancel');

  function currentAppId() {
    return placementsModalEl.querySelector('.modal-content').dataset.appId;
  }

  function resetPlacementForm() {
    placementFormId.value = '';
    placementFormCode.value = '';
    placementFormLabel.value = '';
    placementFormSubmit.textContent = 'Add';
    placementFormCancel.classList.add('d-none');
  }

  function renderPlacementRow(placement) {
    var row = document.createElement('div');
    row.className = 'd-flex align-items-center justify-content-between gap-2 p-2 rounded';
    row.style.background = 'var(--color-neutral-bg)';
    row.dataset.placementId = placement.id;
    row.dataset.placementCode = placement.code;
    row.dataset.placementLabel = placement.label;
    row.innerHTML =
      '<div class="flex-grow-1">' +
        '<span class="fw-bold">' + escapeHtml(placement.label) + '</span>' +
        ' <code class="small text-muted">' + escapeHtml(placement.code) + '</code>' +
      '</div>' +
      '<div class="d-flex gap-2">' +
        '<button type="button" class="db-action-btn" data-edit-placement title="Edit"><i class="bi bi-pencil"></i></button>' +
        '<button type="button" class="db-action-btn db-action-btn--danger" data-delete-placement title="Delete"><i class="bi bi-trash"></i></button>' +
      '</div>';
    return row;
  }

  function escapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  function updateCardPlacementsCount(appId, count) {
    var card = grid.querySelector('[data-app-id="' + appId + '"]');
    if (card) {
      var counter = card.querySelector('[data-placements-count]');
      if (counter) counter.textContent = count;
    }
  }

  function loadPlacements(appId) {
    placementsList.innerHTML = '';
    return fetch('../api/v1/admin/apps/' + encodeURIComponent(appId) + '/placements?app_id=' + encodeURIComponent(appId), {
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': csrfToken },
    })
      .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
      .then(function (result) {
        if (!result.ok || !result.json.success) {
          var message = (result.json.error && result.json.error.message) || 'Could not load placements.';
          showToast(message, 'error');
          return;
        }
        var list = result.json.data.placements || [];
        placementsEmpty.classList.toggle('d-none', list.length > 0);
        list.forEach(function (placement) { placementsList.appendChild(renderPlacementRow(placement)); });
        updateCardPlacementsCount(appId, list.length);
      })
      .catch(function () {
        showToast('Network error — please try again.', 'error');
      });
  }

  grid.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-manage-placements]');
    if (!btn) return;
    var card = btn.closest('[data-app-id]');
    var appId = card.dataset.appId;
    var appName = card.dataset.appName;

    placementsModalEl.querySelector('.modal-content').dataset.appId = appId;
    document.getElementById('placements-modal-app-name').textContent = appName;
    resetPlacementForm();
    loadPlacements(appId);
    bootstrap.Modal.getOrCreateInstance(placementsModalEl).show();
  });

  placementsList.addEventListener('click', function (e) {
    var row = e.target.closest('[data-placement-id]');
    if (!row) return;

    if (e.target.closest('[data-edit-placement]')) {
      placementFormId.value = row.dataset.placementId;
      placementFormCode.value = row.dataset.placementCode;
      placementFormLabel.value = row.dataset.placementLabel;
      placementFormSubmit.textContent = 'Save';
      placementFormCancel.classList.remove('d-none');
      placementFormCode.focus();
      return;
    }

    if (e.target.closest('[data-delete-placement]')) {
      var placementId = row.dataset.placementId;
      var placementLabel = row.dataset.placementLabel;
      var appId = currentAppId();

      confirmAction({
        title: 'Delete "' + placementLabel + '"?',
        body: 'This removes the placement code entirely. Apps requesting ads for it will get nothing back. This can\'t be undone.',
        confirmLabel: 'Delete Placement',
        danger: true,
        onConfirm: function () {
          fetch('../api/v1/admin/apps/' + encodeURIComponent(appId) + '/placements/' + encodeURIComponent(placementId), {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ placement_id: placementId }),
          })
            .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
            .then(function (result) {
              if (!result.ok || !result.json.success) {
                var message = (result.json.error && result.json.error.message) || 'Could not delete this placement.';
                showToast(message, 'error');
                return;
              }
              showToast('Placement deleted.', 'success');
              if (placementFormId.value === placementId) resetPlacementForm();
              loadPlacements(appId);
            })
            .catch(function () {
              showToast('Network error — please try again.', 'error');
            });
        },
      });
    }
  });

  placementFormCancel.addEventListener('click', resetPlacementForm);

  placementFormSubmit.addEventListener('click', function () {
    var appId = currentAppId();
    var placementId = placementFormId.value;
    var code = placementFormCode.value.trim();
    var label = placementFormLabel.value.trim();

    if (!code || !label) {
      showToast('Code and label are both required.', 'error');
      return;
    }

    var isEdit = !!placementId;
    var url = isEdit
      ? '../api/v1/admin/apps/' + encodeURIComponent(appId) + '/placements/' + encodeURIComponent(placementId)
      : '../api/v1/admin/apps/' + encodeURIComponent(appId) + '/placements';
    var body = isEdit
      ? { placement_id: placementId, code: code, label: label }
      : { app_id: appId, code: code, label: label };

    placementFormSubmit.disabled = true;
    fetch(url, {
      method: isEdit ? 'PATCH' : 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body: JSON.stringify(body),
    })
      .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
      .then(function (result) {
        placementFormSubmit.disabled = false;
        if (!result.ok || !result.json.success) {
          var message = (result.json.error && result.json.error.message) || 'Could not save this placement.';
          showToast(message, 'error');
          return;
        }
        showToast(isEdit ? 'Placement updated.' : 'Placement added.', 'success');
        resetPlacementForm();
        loadPlacements(appId);
      })
      .catch(function () {
        placementFormSubmit.disabled = false;
        showToast('Network error — please try again.', 'error');
      });
  });
})();
JS;

require __DIR__ . '/../../views/layouts/app.php';
