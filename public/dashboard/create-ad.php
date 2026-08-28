<?php
require __DIR__ . '/../../core/Autoload.php';
require __DIR__ . '/../../core/Env.php';
require __DIR__ . '/../../views/bootstrap.php';

use Core\Auth\Middleware;

Core\Env::load(__DIR__ . '/../../.env');

// Same session check as my-ads.php — an advertiser must be logged in
// to create an ad (the form posts to an authenticated API endpoint
// anyway, but the page itself was reachable by anyone until now).
$userId = Middleware::checkSession();
if ($userId === null) {
    header('Location: ../index.html');
    exit;
}

$pageTitle  = 'Create Ad';
$role       = 'advertiser';
$activeNav  = 'create-ad';
$baseHref   = '../';
$showSearch = false;

$topbarActions = '<a href="my-ads.php" class="btn btn-sk-outline btn-sm">Cancel</a>';
$topbarCrumb   = '<a href="my-ads.php" class="text-muted">My Ads</a> / <span id="page-crumb">New</span>';

ob_start();
?>
<!-- Step indicator -->
<div class="db-steps">
  <div class="db-step active" data-step-indicator="1"><span class="db-step__num">1</span> Ad Content</div>
  <div class="db-step-divider"></div>
  <div class="db-step" data-step-indicator="2"><span class="db-step__num">2</span> Placement &amp; App</div>
  <div class="db-step-divider"></div>
  <div class="db-step" data-step-indicator="3"><span class="db-step__num">3</span> Schedule &amp; Review</div>
</div>

<form id="ad-form" novalidate>
  <?= csrf_field() ?>
  <div class="row g-4">

    <!-- ================= FORM COLUMN ================= -->
    <div class="col-lg-7">

      <!-- STEP 1 -->
      <div class="db-card mb-4" data-step="1">
        <div class="db-card__header">
          <div><h3>Ad Content</h3><p>This is what people will see wherever your ad is shown</p></div>
        </div>
        <div class="db-card__body d-flex flex-column gap-3">

          <div>
            <label class="db-form-label" for="f-advertiser">Advertiser / Business Name</label>
            <input type="text" id="f-advertiser" class="db-input" placeholder="e.g. Bright Path Computer Academy" required>
          </div>

          <div>
            <label class="db-form-label" for="f-title">Ad Title</label>
            <input type="text" id="f-title" class="db-input" maxlength="70" placeholder="e.g. Admissions Open — Build Your Future With Tech" required>
            <div class="db-form-hint is-count">
              <span>Shown as the bold headline on the ad card.</span>
              <span><span id="title-count">0</span>/70</span>
            </div>
          </div>

          <div>
            <label class="db-form-label" for="f-desc">Description</label>
            <textarea id="f-desc" class="db-textarea" maxlength="160" placeholder="One or two sentences about the offer." required></textarea>
            <div class="db-form-hint is-count">
              <span>Keep it short — it may be truncated on smaller placements.</span>
              <span><span id="desc-count">0</span>/160</span>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-sm-6">
              <label class="db-form-label" for="f-cta">Button Text</label>
              <input type="text" id="f-cta" class="db-input" maxlength="24" placeholder="e.g. Book a Seat" required>
            </div>
            <div class="col-sm-6">
              <label class="db-form-label" for="f-url">Destination URL</label>
              <input type="url" id="f-url" class="db-input" placeholder="https://example.com/landing-page" required>
            </div>
          </div>

          <div>
            <label class="db-form-label">Ad Image <span class="optional">(recommended 4:3, JPG or PNG)</span></label>
            <label class="db-file-drop" id="file-drop" for="f-image">
              <i class="bi bi-cloud-arrow-up"></i>
              <span><strong>Click to upload</strong> or drag and drop</span>
            </label>
            <input type="file" id="f-image" accept="image/*" class="visually-hidden">
          </div>

        </div>
      </div>

      <!-- STEP 2 -->
      <div class="db-card mb-4" data-step="2">
        <div class="db-card__header">
          <div><h3>Placement &amp; App</h3><p>Choose which Skoolyst app and placement this ad should appear on</p></div>
        </div>
        <div class="db-card__body d-flex flex-column gap-3">
          <div>
            <label class="db-form-label">Target App</label>
            <div class="db-checkgrid" id="app-checkgrid"></div>
          </div>
          <div>
            <label class="db-form-label" for="f-placement">Placement <?= help_icon('placement', $helpText) ?></label>
            <select id="f-placement" class="db-select" required>
              <option value="">Select an app first</option>
            </select>
          </div>
        </div>
      </div>

      <!-- STEP 3 -->
      <div class="db-card mb-4" data-step="3">
        <div class="db-card__header">
          <div><h3>Schedule &amp; Review</h3><p>When should this ad run</p></div>
        </div>
        <div class="db-card__body d-flex flex-column gap-3">
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="db-form-label" for="f-start">Start Date</label>
              <input type="date" id="f-start" class="db-input">
            </div>
            <div class="col-sm-6">
              <label class="db-form-label" for="f-end">End Date <?= help_icon('schedule', $helpText) ?></label>
              <input type="date" id="f-end" class="db-input">
            </div>
          </div>
          <div class="db-form-hint">
            <i class="bi bi-info-circle me-1"></i> <?= htmlspecialchars($helpText['ad_status_pending']) ?>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-between">
        <button type="button" class="btn btn-sk-outline" id="btn-prev" disabled><i class="bi bi-arrow-left me-1"></i> Back</button>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-sk-outline" id="btn-save-draft">Save as Draft</button>
          <button type="button" class="btn btn-sk-primary" id="btn-next">Next <i class="bi bi-arrow-right ms-1"></i></button>
          <button type="submit" class="btn btn-sk-primary d-none" id="btn-submit"><i class="bi bi-send-check me-1"></i> Submit for Review</button>
        </div>
      </div>

    </div>

    <!-- ================= PREVIEW COLUMN ================= -->
    <div class="col-lg-5">
      <div class="db-preview-panel">
        <div class="db-preview-frame">
          <div class="db-preview-tabs">
            <button type="button" class="db-preview-tab active" data-preview-variant="main">Full Card</button>
            <button type="button" class="db-preview-tab" data-preview-variant="placement">Compact</button>
          </div>
          <div id="preview-mount"></div>
        </div>
        <p class="db-form-hint mt-2 mb-0"><i class="bi bi-eye me-1"></i> Live preview — updates as you type. Actual rendering may vary slightly per placement.</p>
      </div>
    </div>

  </div>
</form>
<?php
$content = ob_get_clean();

$pageScript = <<<JS
(function () {
  'use strict';

  // Real apps/placements (10.f) — fetched from the DB-backed
  // /api/v1/advertiser/apps endpoint instead of data/mock-data.php's
  // string codes, which can't satisfy the ads table's app_id/
  // placement_id foreign keys. Edit mode (below) still reads the
  // mock ad list; wiring my-ads.php/edit to the real API is 10.g,
  // not this item.
  let liveApps = [];
  let selectedApp = null;
  let selectedPlacementId = null;
  let currentStep = 1;
  const totalSteps = 3;
  let previewVariant = 'main';

  const params = new URLSearchParams(window.location.search);
  const editId = params.get('edit');
  const editingAd = editId ? SkoolystAdsMockData.ads.find(function (a) { return a.id === editId; }) : null;

  // Declared here (not next to fileInput/fileDrop below, where it used to
  // live) because wireCount() below calls updatePreview() immediately,
  // and updatePreview() reads this — a `let` read before its declaration
  // line runs throws "Cannot access before initialization" and silently
  // aborts the rest of this script, including all step-navigation wiring
  // further down. That's why "Next" previously did nothing at all.
  let currentImageSrc = editingAd ? editingAd.image : '../assets/img/ad-1.svg';

  if (editingAd) {
    document.getElementById('page-title').textContent = 'Edit Ad';
    document.getElementById('page-crumb').textContent = editingAd.title;
    document.getElementById('btn-save-draft').textContent = 'Save Changes';
  }

  const grid = document.getElementById('app-checkgrid');

  function renderAppGrid() {
    grid.innerHTML = liveApps.map(function (app) {
      return (
        '<label class="db-check-card">' +
          '<input type="radio" name="target-app" value="' + app.id + '">' +
          '<span><span>' + escapeHtml(app.name) + '</span><small>' + escapeHtml(app.domain) + '</small></span>' +
        '</label>'
      );
    }).join('') || '<p class="text-muted mb-0">No connected apps available yet.</p>';
  }

  grid.addEventListener('change', function (e) {
    if (e.target.name !== 'target-app') return;
    selectedApp = e.target.value;
    populatePlacements(selectedApp);
    updatePreview();
  });

  function populatePlacements(appId) {
    const select = document.getElementById('f-placement');
    const app = liveApps.find(function (a) { return String(a.id) === String(appId); });
    const options = app ? app.placements : [];
    selectedPlacementId = null;
    select.innerHTML = options.map(function (p) {
      return '<option value="' + p.id + '">' + escapeHtml(p.label) + '</option>';
    }).join('') || '<option value="">No placements for this app</option>';
  }

  document.getElementById('f-placement').addEventListener('change', function (e) {
    selectedPlacementId = e.target.value || null;
  });

  // Fetch real apps+placements, then (only for edit mode, which still
  // works off the mock ad) pre-select the ones matching the mock ad's
  // string codes if a same-named app happens to exist.
  fetch('{$baseHref}api/v1/advertiser/apps', { credentials: 'same-origin' })
    .then(function (res) { return res.json(); })
    .then(function (json) {
      if (!json.success) {
        showToast('Could not load connected apps.', 'error');
        return;
      }
      liveApps = json.data.apps;
      renderAppGrid();
      if (editingAd) {
        applyEditingAdSelection();
      }
    })
    .catch(function () {
      showToast('Could not load connected apps — check your connection.', 'error');
    });

  function applyEditingAdSelection() {
    document.getElementById('f-advertiser').value = editingAd.advertiser;
    document.getElementById('f-title').value = editingAd.title;
    document.getElementById('f-desc').value = editingAd.description;
    document.getElementById('f-cta').value = editingAd.cta;
    document.getElementById('f-url').value = editingAd.url;
    document.getElementById('f-start').value = editingAd.startDate;
    document.getElementById('f-end').value = editingAd.endDate;
    const drop = document.getElementById('file-drop');
    drop.classList.add('has-image');
    drop.innerHTML = '<img src="' + editingAd.image + '" alt="">';
    updatePreview();
  }

  function wireCount(inputId, countId) {
    const input = document.getElementById(inputId);
    const label = document.getElementById(countId);
    function update() {
      label.textContent = input.value.length;
      label.parentElement.querySelector('span:last-child').classList.toggle('is-over', input.value.length >= input.maxLength);
      updatePreview();
    }
    input.addEventListener('input', update);
    update();
  }
  wireCount('f-title', 'title-count');
  wireCount('f-desc', 'desc-count');
  ['f-advertiser', 'f-cta', 'f-url'].forEach(function (id) {
    document.getElementById(id).addEventListener('input', updatePreview);
  });

  const fileInput = document.getElementById('f-image');
  const fileDrop = document.getElementById('file-drop');

  fileInput.addEventListener('change', function () {
    const file = fileInput.files && fileInput.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function (e) {
      currentImageSrc = e.target.result;
      fileDrop.classList.add('has-image');
      fileDrop.innerHTML = '<img src="' + currentImageSrc + '" alt="">';
      updatePreview();
    };
    reader.readAsDataURL(file);
  });

  function updatePreview() {
    const advertiser = document.getElementById('f-advertiser').value || 'Your Business Name';
    const title = document.getElementById('f-title').value || 'Your ad headline appears here';
    const desc = document.getElementById('f-desc').value || 'Your ad description will appear here — keep it short and specific about the offer.';
    const cta = document.getElementById('f-cta').value || 'Learn More';

    const mount = document.getElementById('preview-mount');
    const compact = previewVariant === 'placement';

    mount.innerHTML =
      '<div class="pv-card' + (compact ? ' pv-card--placement' : '') + '">' +
        '<div class="pv-card__media"><img src="' + currentImageSrc + '" alt=""></div>' +
        '<div class="pv-card__body">' +
          (compact ? '' : '<div class="pv-card__advertiser">' + escapeHtml(advertiser) + '</div>') +
          '<p class="pv-card__title">' + escapeHtml(title) + '</p>' +
          (compact ? '' : '<p class="pv-card__desc">' + escapeHtml(desc) + '</p>') +
          '<span class="pv-card__cta">' + escapeHtml(cta) + ' <i class="bi bi-arrow-up-right"></i></span>' +
        '</div>' +
      '</div>';
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  document.querySelectorAll('[data-preview-variant]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('[data-preview-variant]').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      previewVariant = btn.dataset.previewVariant;
      updatePreview();
    });
  });

  const stepCards = document.querySelectorAll('[data-step]');
  const stepIndicators = document.querySelectorAll('[data-step-indicator]');
  const btnPrev = document.getElementById('btn-prev');
  const btnNext = document.getElementById('btn-next');
  const btnSubmit = document.getElementById('btn-submit');

  function showStep(step) {
    currentStep = step;
    stepCards.forEach(function (card) {
      card.style.display = (Number(card.dataset.step) === step) ? 'block' : 'none';
    });
    stepIndicators.forEach(function (ind) {
      const n = Number(ind.dataset.stepIndicator);
      ind.classList.toggle('active', n === step);
      ind.classList.toggle('done', n < step);
    });
    btnPrev.disabled = step === 1;
    btnNext.classList.toggle('d-none', step === totalSteps);
    btnSubmit.classList.toggle('d-none', step !== totalSteps);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  btnNext.addEventListener('click', function () {
    if (currentStep === 1 && !validateStep1()) return;
    if (currentStep === 2 && !validateStep2()) return;
    showStep(Math.min(currentStep + 1, totalSteps));
  });
  btnPrev.addEventListener('click', function () { showStep(Math.max(currentStep - 1, 1)); });

  function validateStep1() {
    const required = ['f-advertiser', 'f-title', 'f-desc', 'f-cta', 'f-url'];
    let ok = true;
    required.forEach(function (id) {
      const el = document.getElementById(id);
      if (!el.value.trim()) { el.classList.add('is-invalid'); ok = false; }
      else el.classList.remove('is-invalid');
    });
    if (!ok) showToast('Please fill in every field before continuing.', 'error');
    return ok;
  }

  function validateStep2() {
    if (!selectedApp) { showToast('Please choose which app this ad should run on.', 'error'); return false; }
    if (!document.getElementById('f-placement').value) { showToast('Please choose a placement.', 'error'); return false; }
    return true;
  }

  showStep(1);
  updatePreview();

  document.getElementById('ad-form').addEventListener('submit', function (e) {
    e.preventDefault();

    // Edit mode still goes through my-ads.php's real PATCH wiring
    // (10.g) — out of scope here. Only the "new ad" path below is
    // 10.f's target.
    if (editingAd) {
      showToast('Changes submitted for review.', 'success');
      window.setTimeout(function () { window.location.href = 'my-ads.php'; }, 900);
      return;
    }

    if (!selectedApp) {
      showToast('Please choose which app this ad should run on.', 'error');
      return;
    }
    if (!selectedPlacementId) {
      showToast('Please choose a placement.', 'error');
      return;
    }

    const formData = new FormData();
    formData.append('title', document.getElementById('f-title').value.trim());
    formData.append('description', document.getElementById('f-desc').value.trim());
    formData.append('cta_text', document.getElementById('f-cta').value.trim());
    formData.append('click_url', document.getElementById('f-url').value.trim());
    formData.append('start_date', document.getElementById('f-start').value);
    formData.append('end_date', document.getElementById('f-end').value);
    formData.append('app_id', selectedApp);
    formData.append('placement_id', selectedPlacementId);

    const file = fileInput.files && fileInput.files[0];
    if (file) formData.append('image', file);

    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Submitting…';

    fetch('{$baseHref}api/v1/advertiser/ads', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': document.getElementById('_csrf').value },
      body: formData
    })
      .then(function (res) {
        return res.json().then(function (json) { return { ok: res.ok, json: json }; });
      })
      .then(function (result) {
        if (!result.ok || !result.json.success) {
          const message = (result.json.error && result.json.error.message) || 'Could not submit ad.';
          showToast(message, 'error');
          btnSubmit.disabled = false;
          btnSubmit.innerHTML = '<i class="bi bi-send-check me-1"></i> Submit for Review';
          return;
        }
        showToast('Ad submitted for review.', 'success');
        window.setTimeout(function () { window.location.href = 'my-ads.php'; }, 900);
      })
      .catch(function () {
        showToast('Network error — please try again.', 'error');
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = '<i class="bi bi-send-check me-1"></i> Submit for Review';
      });
  });

  document.getElementById('btn-save-draft').addEventListener('click', function () {
    showToast('Draft saved.', 'info');
    window.setTimeout(function () { window.location.href = 'my-ads.php'; }, 900);
  });
})();
JS;

require __DIR__ . '/../../views/layouts/app.php';
