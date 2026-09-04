<?php
require __DIR__ . '/../../core/Autoload.php';
require __DIR__ . '/../../core/Env.php';
require __DIR__ . '/../../views/bootstrap.php';

use App\Auth\UserRepository;
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

// This page is now also how an admin edits any ad from the
// moderation table (see admin/ads.php's edit button) — the form,
// preview, and validation are all identical, only which API
// endpoints get called (advertiser-owned vs admin-unscoped) and
// where Cancel/success navigate back to differ. An admin with no
// `edit` id has no supported reason to be here (admin ad *creation*
// isn't a thing) — bounce straight back to the moderation queue.
$currentUser = (new UserRepository())->findById($userId);
$isAdmin = $currentUser !== null && $currentUser->isAdmin();

if ($isAdmin && !isset($_GET['edit'])) {
    header('Location: ../admin/ads.php');
    exit;
}

$backHref  = $isAdmin ? '../admin/ads.php' : 'my-ads.php';
$backLabel = $isAdmin ? 'All Ads' : 'My Ads';

$pageTitle  = 'Create Ad';
$role       = $isAdmin ? 'admin' : 'advertiser';
$activeNav  = $isAdmin ? 'admin-ads' : 'create-ad';
$baseHref   = '../';
$showSearch = false;

$topbarActions = '<a href="' . $backHref . '" class="btn btn-sk-outline btn-sm">Cancel</a>';
$topbarCrumb   = '<a href="' . $backHref . '" class="text-muted">' . $backLabel . '</a> / <span id="page-crumb">New</span>';

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
          <div><h3>Placement &amp; App</h3><p>Choose which Skoolyst app and placement(s) this ad should appear on</p></div>
        </div>
        <div class="db-card__body d-flex flex-column gap-3">
          <div>
            <label class="db-form-label">Target App</label>
            <div class="db-checkgrid" id="app-checkgrid"></div>
          </div>
          <div>
            <label class="db-form-label">Placements <?= help_icon('placement', $helpText) ?></label>
            <p class="db-form-hint mt-0 mb-2">Pick one, several, or all of this app's placements — the ad will run on every one you select.</p>
            <div class="db-checkgrid" id="placement-checkgrid">
              <p class="text-muted mb-0">Select an app first.</p>
            </div>
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

$jsIsAdmin = $isAdmin ? 'true' : 'false';

$pageScript = <<<JS
(function () {
  'use strict';

  // 10.n admin-edit follow-up — an admin opening this page from
  // admin/ads.php hits the unscoped /api/v1/admin/ads/... endpoints
  // (AdController::adminShow()/adminUpdate()/adminUpdateImage())
  // instead of the ownership-scoped /api/v1/advertiser/ads/...
  // ones, since the logged-in admin doesn't own the ad being edited.
  // backHref matches \$backHref in the PHP above (My Ads vs the
  // moderation queue) for every redirect below.
  const isAdmin = {$jsIsAdmin};
  const backHref = '{$backHref}';

  // Real apps/placements (10.f), fetched from the DB-backed
  // /api/v1/advertiser/apps endpoint instead of data/mock-data.php's
  // string codes, which can't satisfy the ads table's app_id/
  // placement_id foreign keys.
  let liveApps = [];
  let selectedApp = null;
  // 10.p — an ad can now target more than one of its app's
  // placements, so this is a Set of ids rather than one value.
  let selectedPlacementIds = new Set();
  let currentStep = 1;
  const totalSteps = 3;
  let previewVariant = 'main';

  const params = new URLSearchParams(window.location.search);
  const editId = params.get('edit');
  // Populated async by loadEditingAd() below, from the real
  // AdController::show() endpoint — not data/mock-data.php. Every
  // place below that reads `editingAd` runs after that fetch resolves
  // (either chained off it, or off a later user action like clicking
  // Submit), never synchronously at page-load time, so this starting
  // as null is safe.
  let editingAd = null;

  // Declared here (not next to fileInput/fileDrop below, where it used to
  // live) because wireCount() below calls updatePreview() immediately,
  // and updatePreview() reads this — a `let` read before its declaration
  // line runs throws "Cannot access before initialization" and silently
  // aborts the rest of this script, including all step-navigation wiring
  // further down. That's why "Next" previously did nothing at all.
  let currentImageSrc = '../assets/img/ad-1.svg';

  const grid = document.getElementById('app-checkgrid');
  const placementGrid = document.getElementById('placement-checkgrid');

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

  // 10.p — renders every placement the chosen app defines as its own
  // checkbox (not a single-select <select>), so an ad can be checked
  // onto 1, several, or all of them at once instead of exactly one.
  function populatePlacements(appId) {
    const app = liveApps.find(function (a) { return String(a.id) === String(appId); });
    const options = app ? app.placements : [];
    selectedPlacementIds = new Set();
    placementGrid.innerHTML = options.map(function (p) {
      return (
        '<label class="db-check-card">' +
          '<input type="checkbox" name="target-placement" value="' + p.id + '">' +
          '<span>' + escapeHtml(p.label) + '</span>' +
        '</label>'
      );
    }).join('') || '<p class="text-muted mb-0">No placements for this app.</p>';
  }

  placementGrid.addEventListener('change', function (e) {
    if (e.target.name !== 'target-placement') return;
    if (e.target.checked) {
      selectedPlacementIds.add(e.target.value);
    } else {
      selectedPlacementIds.delete(e.target.value);
    }
  });

  // Real apps/placements are needed either way: a new ad's Step 2
  // picker, or (edit mode) to resolve the ad's stored app_id/
  // placement_id into the names shown in that same step. Admins hit
  // a separate admin-only endpoint returning the same apps+placements
  // shape (AppController::index(), the admin Connected Apps listing,
  // doesn't include placements at all).
  fetch('{$baseHref}api/v1/' + (isAdmin ? 'admin/apps/for-ad-form' : 'advertiser/apps'), { credentials: 'same-origin' })
    .then(function (res) { return res.json(); })
    .then(function (json) {
      if (!json.success) {
        showToast('Could not load connected apps.', 'error');
        return;
      }
      liveApps = json.data.apps;
      renderAppGrid();
      if (editId) {
        loadEditingAd();
      }
    })
    .catch(function () {
      showToast('Could not load connected apps — check your connection.', 'error');
    });

  // 10.n — real edit mode: loads the advertiser's own ad from the DB
  // (AdController::show(), ownership-checked server-side against the
  // logged-in session — not a client-side id lookup) instead of the
  // old data/mock-data.php array. `ad_id` is sent as a query param
  // because the router matches `{id}` in the path but never binds it
  // (see routePathToRegex()'s doc-block in public/index.php); the
  // real value has to travel as a field Request::int() actually reads.
  function loadEditingAd() {
    const endpointBase = isAdmin ? 'admin/ads/' : 'advertiser/ads/';
    const url = '{$baseHref}api/v1/' + endpointBase + encodeURIComponent(editId) + '?ad_id=' + encodeURIComponent(editId);
    fetch(url, { credentials: 'same-origin' })
      .then(function (res) {
        return res.json().then(function (json) { return { ok: res.ok, json: json }; });
      })
      .then(function (result) {
        if (!result.ok || !result.json.success) {
          showToast('Could not load this ad — it may not exist' + (isAdmin ? '.' : ' or belong to your account.'), 'error');
          window.setTimeout(function () { window.location.href = backHref; }, 1200);
          return;
        }
        editingAd = result.json.data.ad;
        applyEditingAdSelection();
      })
      .catch(function () {
        showToast('Network error loading this ad — please try again.', 'error');
      });
  }

  function applyEditingAdSelection() {
    document.getElementById('page-title').textContent = 'Edit Ad';
    document.getElementById('page-crumb').textContent = editingAd.title;
    // This button is the real quick-save action in edit mode (see the
    // click handler near the bottom) — it isn't a draft anymore once
    // there's a real ad row behind it, so its label changes to match.
    document.getElementById('btn-save-draft').textContent = 'Save Changes';

    document.getElementById('f-advertiser').value = editingAd.advertiser_name || '';
    document.getElementById('f-title').value = editingAd.title;
    document.getElementById('f-desc').value = editingAd.description || '';
    document.getElementById('f-cta').value = editingAd.cta_text || '';
    document.getElementById('f-url').value = editingAd.click_url || '';
    document.getElementById('f-start').value = editingAd.start_date || '';
    document.getElementById('f-end').value = editingAd.end_date || '';
    // Re-fire 'input' so wireCount()'s character-count labels (which
    // only update on a real input event, not a programmatic .value
    // assignment) reflect the loaded text right away.
    document.getElementById('f-title').dispatchEvent(new Event('input'));
    document.getElementById('f-desc').dispatchEvent(new Event('input'));

    if (editingAd.image_path) {
      currentImageSrc = '{$baseHref}uploads/ads/' + editingAd.image_path;
      const drop = document.getElementById('file-drop');
      drop.classList.add('has-image');
      drop.innerHTML = '<img src="' + currentImageSrc + '" alt="">';
    }

    // An ad's app/placement(s) can't be changed once created —
    // AdController::update() never accepts app_id/placement_ids, so
    // the ad stays on whichever it was submitted with. Step 2 still
    // shows which app/placements those are, but pre-selected and
    // locked rather than left as a live picker.
    selectedApp = String(editingAd.app_id);
    const radio = grid.querySelector('input[value="' + selectedApp + '"]');
    if (radio) { radio.checked = true; }
    Array.prototype.forEach.call(grid.querySelectorAll('input[name="target-app"]'), function (input) {
      input.disabled = true;
    });
    // populatePlacements() resets selectedPlacementIds as part of
    // rebuilding the checkboxes for the new app — so the ad's real
    // placement_ids have to be (re-)applied after that call, not
    // before. Every placement the ad currently serves on
    // (AdController::show()/adminShow() attach the full list, not
    // just the legacy single placement_id) gets checked and disabled.
    populatePlacements(selectedApp);
    const editingPlacementIds = (editingAd.placement_ids || [editingAd.placement_id]).map(String);
    selectedPlacementIds = new Set(editingPlacementIds);
    Array.prototype.forEach.call(placementGrid.querySelectorAll('input[name="target-placement"]'), function (input) {
      input.checked = editingPlacementIds.indexOf(input.value) !== -1;
      input.disabled = true;
    });

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
    if (selectedPlacementIds.size === 0) { showToast('Please choose at least one placement.', 'error'); return false; }
    return true;
  }

  showStep(1);
  updatePreview();

  // 10.n — edit mode's real fields, mirroring what
  // AdController::validatedAdInput() actually requires (advertiser_name,
  // title, and click_url — description/cta_text aren't required
  // server-side). Kept separate from validateStep1(), which also
  // requires 'f-desc'/'f-cta' as a step-by-step UX choice for new ads,
  // not because the backend needs them.
  function validateEditFields() {
    const advertiser = document.getElementById('f-advertiser');
    const title = document.getElementById('f-title');
    const url = document.getElementById('f-url');
    let ok = true;
    if (!advertiser.value.trim()) { advertiser.classList.add('is-invalid'); ok = false; } else advertiser.classList.remove('is-invalid');
    if (!title.value.trim()) { title.classList.add('is-invalid'); ok = false; } else title.classList.remove('is-invalid');
    if (!url.value.trim()) { url.classList.add('is-invalid'); ok = false; } else url.classList.remove('is-invalid');
    if (!ok) showToast('Advertiser name, title, and destination URL are required.', 'error');
    return ok;
  }

  function setSavingState(saving) {
    btnSubmit.disabled = saving;
    document.getElementById('btn-save-draft').disabled = saving;
    btnSubmit.innerHTML = saving
      ? '<i class="bi bi-hourglass-split me-1"></i> Saving…'
      : '<i class="bi bi-send-check me-1"></i> Submit for Review';
  }

  function finishEditSave() {
    showToast('Changes saved.', 'success');
    window.setTimeout(function () { window.location.href = backHref; }, 900);
  }

  // 10.n — real edit save: PATCH the text fields as JSON (this is
  // AdController::update(), the same endpoint (un)wired since 10.f),
  // then — only if a new image was actually chosen — a second,
  // separate POST for the image (AdController::updateImage()). Two
  // requests, not one, because PHP only ever populates \$_FILES for a
  // multipart body on POST, never on PATCH/PUT, so an image change
  // can't ride along on the PATCH regardless of how it's encoded
  // client-side.
  function submitEdit() {
    if (!validateEditFields()) return;

    const payload = {
      ad_id: editingAd.id,
      advertiser_name: document.getElementById('f-advertiser').value.trim(),
      title: document.getElementById('f-title').value.trim(),
      description: document.getElementById('f-desc').value.trim(),
      cta_text: document.getElementById('f-cta').value.trim(),
      click_url: document.getElementById('f-url').value.trim(),
      start_date: document.getElementById('f-start').value,
      end_date: document.getElementById('f-end').value,
    };

    setSavingState(true);

    const endpointBase = isAdmin ? 'admin/ads/' : 'advertiser/ads/';

    fetch('{$baseHref}api/v1/' + endpointBase + encodeURIComponent(editingAd.id), {
      method: 'PATCH',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': document.getElementById('_csrf').value,
      },
      body: JSON.stringify(payload),
    })
      .then(function (res) {
        return res.json().then(function (json) { return { ok: res.ok, json: json }; });
      })
      .then(function (result) {
        if (!result.ok || !result.json.success) {
          const message = (result.json.error && result.json.error.message) || 'Could not save changes.';
          showToast(message, 'error');
          setSavingState(false);
          return;
        }

        const newImageFile = fileInput.files && fileInput.files[0];
        if (!newImageFile) {
          finishEditSave();
          return;
        }

        const imageForm = new FormData();
        imageForm.append('ad_id', editingAd.id);
        imageForm.append('image', newImageFile);

        fetch('{$baseHref}api/v1/' + endpointBase + encodeURIComponent(editingAd.id) + '/image', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'X-CSRF-Token': document.getElementById('_csrf').value },
          body: imageForm,
        })
          .then(function (res) {
            return res.json().then(function (json) { return { ok: res.ok, json: json }; });
          })
          .then(function (imgResult) {
            if (!imgResult.ok || !imgResult.json.success) {
              const message = (imgResult.json.error && imgResult.json.error.message) || 'Could not upload the new image.';
              showToast('Ad details saved, but ' + message.charAt(0).toLowerCase() + message.slice(1), 'error');
            }
            finishEditSave();
          })
          .catch(function () {
            showToast('Ad details saved, but the new image could not be uploaded — check your connection.', 'error');
            finishEditSave();
          });
      })
      .catch(function () {
        showToast('Network error — please try again.', 'error');
        setSavingState(false);
      });
  }

  document.getElementById('ad-form').addEventListener('submit', function (e) {
    e.preventDefault();

    if (editingAd) {
      submitEdit();
      return;
    }

    if (!selectedApp) {
      showToast('Please choose which app this ad should run on.', 'error');
      return;
    }
    if (selectedPlacementIds.size === 0) {
      showToast('Please choose at least one placement.', 'error');
      return;
    }

    const formData = new FormData();
    formData.append('advertiser_name', document.getElementById('f-advertiser').value.trim());
    formData.append('title', document.getElementById('f-title').value.trim());
    formData.append('description', document.getElementById('f-desc').value.trim());
    formData.append('cta_text', document.getElementById('f-cta').value.trim());
    formData.append('click_url', document.getElementById('f-url').value.trim());
    formData.append('start_date', document.getElementById('f-start').value);
    formData.append('end_date', document.getElementById('f-end').value);
    formData.append('app_id', selectedApp);
    selectedPlacementIds.forEach(function (id) { formData.append('placement_ids[]', id); });

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
    // 10.n — in edit mode this button is relabeled "Save Changes"
    // (applyEditingAdSelection() above) and is the primary save
    // action, reachable from any step without clicking through the
    // whole wizard — so it now actually saves, via the same
    // submitEdit() the form's real Submit button uses. The "new ad"
    // draft path below (still a placeholder — no draft-saving
    // endpoint exists yet) is unchanged.
    if (editingAd) {
      submitEdit();
      return;
    }
    showToast('Draft saved.', 'info');
    window.setTimeout(function () { window.location.href = 'my-ads.php'; }, 900);
  });
})();
JS;

require __DIR__ . '/../../views/layouts/app.php';
