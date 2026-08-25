/**
 * Skoolyst Ads — Dashboard & Admin UI (static prototype)
 *
 * UI ONLY. There is no backend here — everything below reads from the
 * MOCK_* arrays and mutates them in memory for the duration of the page
 * view so buttons, filters and forms feel real. A future integration can
 * swap MOCK_ADS / MOCK_APPS for real fetch() calls to the AdEngine API
 * (see api-docs.html) without changing the render functions.
 */

(function () {
  'use strict';

  // -----------------------------------------------------------------------
  // 1. Mock data — stands in for GET /api/v1/advertiser/ads etc.
  //
  // Real data now comes from data/mock-data.php on the PHP side, injected
  // as window.SkoolystAdsMockData by views/partials/scripts.php — the
  // arrays below are only a FALLBACK for when this file runs with no PHP
  // behind it (e.g. a plain static file preview). Once Section 4 (API)
  // lands, both of these mock sources are replaced by a real fetch().
  // -----------------------------------------------------------------------
  const FALLBACK_APPS = [
    { id: 'sk', code: 'SK', name: 'Skoolyst', domain: 'skoolyst.com', apiKey: 'sk_live_9c1c...4f2a', status: 'active', placements: 4 },
    { id: 'ss', code: 'SS', name: 'Skoolyst Social', domain: 'social.skoolyst.com', apiKey: 'sk_live_2b7e...9a10', status: 'active', placements: 3 },
    { id: 'st', code: 'ST', name: 'Skoolyst Teachers', domain: 'teachers.skoolyst.com', apiKey: 'sk_live_af31...c88d', status: 'active', placements: 3 },
    { id: 'jf', code: 'JF', name: 'Jaans Fabrics', domain: 'jaansfabrics.com', apiKey: 'sk_live_11e0...77bb', status: 'active', placements: 2 },
    { id: 'sa', code: 'SA', name: 'Safi India Autos', domain: 'safiindiaautos.com', apiKey: 'sk_live_5d90...12ff', status: 'paused', placements: 2 },
  ];

  const FALLBACK_ADS = [
    {
      id: 'ad_1001',
      title: 'Admissions Open — Build Your Future With Tech',
      advertiser: 'Bright Path Computer Academy',
      description: 'Hands-on courses in web development, graphic design, and office skills. Evening batches available for working students.',
      image: '../assets/ad-1.svg',
      cta: 'Learn More',
      url: 'https://example.com/computer-academy',
      app: 'sk',
      placement: 'home_top',
      status: 'active',
      impressions: 48210,
      clicks: 1120,
      startDate: '2026-07-01',
      endDate: '2026-09-30',
    },
    {
      id: 'ad_1002',
      title: 'Speak Confidently in 8 Weeks',
      advertiser: 'Fluent English Learning Center',
      description: 'Small-group spoken English classes for students and professionals, with weekend batches and certified instructors.',
      image: '../assets/ad-2.svg',
      cta: 'Book a Seat',
      url: 'https://example.com/english-center',
      app: 'st',
      placement: 'teacher_profile_sidebar',
      status: 'pending',
      impressions: 0,
      clicks: 0,
      startDate: '2026-08-28',
      endDate: '2026-10-28',
    },
    {
      id: 'ad_1003',
      title: 'New Semester, New Books — Up to 20% Off',
      advertiser: 'Noor Educational Book Store',
      description: 'Textbooks, guides, and stationery for all grades in one place, with home delivery across the city.',
      image: '../assets/ad-3.svg',
      cta: 'Shop Now',
      url: 'https://example.com/book-store',
      app: 'sk',
      placement: 'blog_inline',
      status: 'active',
      impressions: 22870,
      clicks: 640,
      startDate: '2026-06-15',
      endDate: '2026-08-31',
    },
    {
      id: 'ad_1004',
      title: 'Custom Stitched Uniforms — School Rate Discounts',
      advertiser: 'Jaans Fabrics',
      description: 'Bulk school uniform stitching with measurement pickup and 10-day delivery across Karachi.',
      image: '../assets/ad-2.svg',
      cta: 'Get a Quote',
      url: 'https://example.com/jaans-fabrics',
      app: 'jf',
      placement: 'feed_inline',
      status: 'paused',
      impressions: 9310,
      clicks: 145,
      startDate: '2026-05-01',
      endDate: '2026-08-01',
    },
    {
      id: 'ad_1005',
      title: 'Free Career Counselling This Weekend',
      advertiser: 'Bright Path Computer Academy',
      description: 'One-on-one sessions for matric and intermediate students exploring tech careers.',
      image: '../assets/ad-1.svg',
      cta: 'Reserve a Slot',
      url: 'https://example.com/counselling',
      app: 'ss',
      placement: 'social_feed_inline',
      status: 'rejected',
      impressions: 0,
      clicks: 0,
      startDate: '2026-08-20',
      endDate: '2026-09-05',
      rejectionReason: 'Landing page did not match the ad claim. Please update the URL and resubmit.',
    },
    {
      id: 'ad_1006',
      title: 'Vehicle Inspection Package — 20% Off This Month',
      advertiser: 'Safi India Autos',
      description: 'Full 40-point inspection before you buy or sell a used vehicle.',
      image: '../assets/ad-3.svg',
      cta: 'Book Inspection',
      url: 'https://example.com/safi-autos',
      app: 'sa',
      placement: 'home_sidebar',
      status: 'draft',
      impressions: 0,
      clicks: 0,
      startDate: '',
      endDate: '',
    },
    {
      id: 'ad_1007',
      title: 'Parent-Teacher Meet Scheduling Made Easy',
      advertiser: 'Skoolyst',
      description: 'Let parents pick their own slots — no more back-and-forth on WhatsApp groups.',
      image: '../assets/ad-1.svg',
      cta: 'See How It Works',
      url: 'https://example.com/skoolyst-ptm',
      app: 'st',
      placement: 'teacher_dashboard_banner',
      status: 'ended',
      impressions: 61200,
      clicks: 2030,
      startDate: '2026-03-01',
      endDate: '2026-06-01',
    },
  ];

  const FALLBACK_PLACEMENTS = {
    sk: [
      { value: 'home_top', label: 'Home — Top Banner' },
      { value: 'home_sidebar', label: 'Home — Sidebar' },
      { value: 'blog_inline', label: 'Blog — Inline' },
    ],
    ss: [
      { value: 'social_feed_inline', label: 'Feed — Inline Card' },
      { value: 'social_sidebar', label: 'Sidebar' },
    ],
    st: [
      { value: 'teacher_profile_sidebar', label: 'Teacher Profile — Sidebar' },
      { value: 'teacher_dashboard_banner', label: 'Teacher Dashboard — Banner' },
    ],
    jf: [
      { value: 'feed_inline', label: 'Catalog — Inline' },
    ],
    sa: [
      { value: 'home_sidebar', label: 'Home — Sidebar' },
    ],
  };

  // Prefer the data injected by PHP (single source of truth); fall back
  // to the hardcoded copy above only if this page has no PHP behind it.
  const injected = window.SkoolystAdsMockData;
  const MOCK_APPS = (injected && injected.apps) || FALLBACK_APPS;
  const MOCK_ADS = (injected && injected.ads) || FALLBACK_ADS;
  const PLACEMENTS_BY_APP = (injected && injected.placementsByApp) || FALLBACK_PLACEMENTS;

  window.SkoolystAdsMock = { apps: MOCK_APPS, ads: MOCK_ADS, placementsByApp: PLACEMENTS_BY_APP };

  // -----------------------------------------------------------------------
  // 2. Small helpers
  // -----------------------------------------------------------------------
  function $all(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
  function $(sel, ctx) { return (ctx || document).querySelector(sel); }

  function appName(id) {
    const app = MOCK_APPS.find(function (a) { return a.id === id; });
    return app ? app.name : id;
  }

  function appCode(id) {
    const app = MOCK_APPS.find(function (a) { return a.id === id; });
    return app ? app.code : '?';
  }

  function formatNumber(n) {
    return new Intl.NumberFormat('en-US').format(n);
  }

  function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso + 'T00:00:00');
    if (isNaN(d.getTime())) return iso;
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function statusLabel(status) {
    const map = {
      active: 'Active', pending: 'Pending Review', paused: 'Paused',
      rejected: 'Rejected', draft: 'Draft', ended: 'Ended',
    };
    return map[status] || status;
  }

  // -----------------------------------------------------------------------
  // 3. Sidebar (mobile off-canvas)
  // -----------------------------------------------------------------------
  function initSidebar() {
    const sidebar = $('.db-sidebar');
    const backdrop = $('.db-sidebar-backdrop');
    const toggles = $all('[data-sidebar-toggle]');
    if (!sidebar) return;

    function open() {
      sidebar.classList.add('is-open');
      document.body.classList.add('db-sidebar-open');
      if (backdrop) backdrop.style.display = 'block';
    }
    function close() {
      sidebar.classList.remove('is-open');
      document.body.classList.remove('db-sidebar-open');
      if (backdrop) backdrop.style.display = 'none';
    }

    toggles.forEach(function (btn) {
      btn.addEventListener('click', function () {
        sidebar.classList.contains('is-open') ? close() : open();
      });
    });
    if (backdrop) backdrop.addEventListener('click', close);

    window.addEventListener('resize', function () {
      if (window.innerWidth >= 992) close();
    });
  }

  // -----------------------------------------------------------------------
  // 4. Toasts
  // -----------------------------------------------------------------------
  function ensureToastStack() {
    let stack = $('.db-toast-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.className = 'db-toast-stack';
      document.body.appendChild(stack);
    }
    return stack;
  }

  function showToast(message, type) {
    type = type || 'success';
    const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', info: 'bi-info-circle-fill' };
    const stack = ensureToastStack();
    const toast = document.createElement('div');
    toast.className = 'db-toast db-toast--' + type;
    toast.innerHTML = '<i class="bi ' + (icons[type] || icons.info) + '"></i><span></span>';
    toast.querySelector('span').textContent = message;
    stack.appendChild(toast);
    window.setTimeout(function () {
      toast.style.opacity = '0';
      toast.style.transition = 'opacity 0.2s ease';
      window.setTimeout(function () { toast.remove(); }, 220);
    }, 3200);
  }
  window.showToast = showToast;

  // -----------------------------------------------------------------------
  // 5b. Shared confirm modal — see views/components/modal-confirm.php.
  // One modal instance per page; every destructive action reuses it
  // instead of shipping its own modal markup.
  // -----------------------------------------------------------------------
  function confirmAction(opts) {
    const modalId = opts.modalId || 'confirm-modal';
    const modalEl = document.getElementById(modalId);
    if (!modalEl || !window.bootstrap) {
      // No shared modal on this page (or Bootstrap not loaded) — fall back
      // to a native confirm so the action still works.
      if (window.confirm(opts.body || 'Are you sure?')) opts.onConfirm();
      return;
    }
    document.getElementById(modalId + '-title').textContent = opts.title || 'Are you sure?';
    document.getElementById(modalId + '-body').textContent = opts.body || '';
    const confirmBtn = document.getElementById(modalId + '-confirm');
    confirmBtn.textContent = opts.confirmLabel || 'Confirm';
    confirmBtn.className = opts.danger ? 'btn-reject' : 'btn btn-sk-primary';

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    function handler() {
      modal.hide();
      confirmBtn.removeEventListener('click', handler);
      opts.onConfirm();
    }
    confirmBtn.addEventListener('click', handler);
    modal.show();
  }
  window.confirmAction = confirmAction;

  // -----------------------------------------------------------------------
  // 5. Ads table rendering (my-ads.html and admin/ads.html)
  // -----------------------------------------------------------------------
  function buildStatusBadge(status) {
    return '<span class="badge-status badge-status--' + status + '">' + statusLabel(status) + '</span>';
  }

  function renderAdsTable(opts) {
    const tbody = document.getElementById(opts.tbodyId);
    if (!tbody) return;
    const emptyState = document.getElementById(opts.emptyId);
    const countLabel = document.getElementById(opts.countId);
    const showAdvertiser = !!opts.showAdvertiser;
    const showApprovalActions = !!opts.showApprovalActions;

    let list = MOCK_ADS.slice();

    if (opts.getFilters) {
      const f = opts.getFilters();
      if (f.status && f.status !== 'all') list = list.filter(function (a) { return a.status === f.status; });
      if (f.app && f.app !== 'all') list = list.filter(function (a) { return a.app === f.app; });
      if (f.query) {
        const q = f.query.toLowerCase();
        list = list.filter(function (a) {
          return a.title.toLowerCase().indexOf(q) !== -1 ||
            a.advertiser.toLowerCase().indexOf(q) !== -1;
        });
      }
    }

    if (countLabel) countLabel.textContent = list.length + (list.length === 1 ? ' ad' : ' ads');

    if (opts.limit) list = list.slice(0, opts.limit);

    tbody.innerHTML = '';

    if (!list.length) {
      if (emptyState) emptyState.style.display = 'block';
      return;
    }
    if (emptyState) emptyState.style.display = 'none';

    list.forEach(function (ad) {
      const tr = document.createElement('tr');
      tr.dataset.adId = ad.id;

      const ctr = ad.impressions > 0 ? ((ad.clicks / ad.impressions) * 100).toFixed(1) + '%' : '—';

      tr.innerHTML =
        '<td>' +
          '<div class="d-flex align-items-center gap-3">' +
            '<img src="' + ad.image + '" class="db-table__thumb" alt="">' +
            '<div>' +
              '<p class="db-table__title">' + escapeHtml(ad.title) + '</p>' +
              '<p class="db-table__meta">' + (showAdvertiser ? escapeHtml(ad.advertiser) + ' &middot; ' : '') + appName(ad.app) + ' &middot; ' + ad.placement + '</p>' +
            '</div>' +
          '</div>' +
        '</td>' +
        '<td>' + buildStatusBadge(ad.status) + '</td>' +
        '<td>' + formatNumber(ad.impressions) + '</td>' +
        '<td>' + formatNumber(ad.clicks) + '</td>' +
        '<td>' + ctr + '</td>' +
        '<td>' + formatDate(ad.startDate) + ' &rarr; ' + formatDate(ad.endDate) + '</td>' +
        '<td>' + buildActionsCell(ad, showApprovalActions) + '</td>';

      tbody.appendChild(tr);
    });

    wireRowActions(tbody, opts);
  }

  function buildActionsCell(ad, showApprovalActions) {
    let html = '<div class="db-table__actions">';
    if (showApprovalActions && ad.status === 'pending') {
      html += '<button type="button" class="btn-approve" data-action="approve">Approve</button>';
      html += '<button type="button" class="btn-reject" data-action="reject">Reject</button>';
    } else {
      if (ad.status === 'active') {
        html += '<button type="button" class="db-action-btn" data-action="pause" title="Pause ad"><i class="bi bi-pause-fill"></i></button>';
      } else if (ad.status === 'paused' || ad.status === 'draft') {
        html += '<button type="button" class="db-action-btn db-action-btn--success" data-action="activate" title="Activate ad"><i class="bi bi-play-fill"></i></button>';
      }
      html += '<button type="button" class="db-action-btn" data-action="edit" title="Edit ad"><i class="bi bi-pencil"></i></button>';
      html += '<button type="button" class="db-action-btn db-action-btn--danger" data-action="delete" title="Delete ad"><i class="bi bi-trash3"></i></button>';
    }
    html += '</div>';
    return html;
  }

  function wireRowActions(tbody, opts) {
    $all('[data-action]', tbody).forEach(function (btn) {
      btn.addEventListener('click', function () {
        const tr = btn.closest('tr');
        const adId = tr.dataset.adId;
        const ad = MOCK_ADS.find(function (a) { return a.id === adId; });
        if (!ad) return;
        const action = btn.dataset.action;

        if (action === 'delete') {
          confirmAction({
            title: 'Delete this ad?',
            body: 'Delete "' + ad.title + '"? This cannot be undone.',
            confirmLabel: 'Delete',
            danger: true,
            onConfirm: function () {
              const idx = MOCK_ADS.indexOf(ad);
              MOCK_ADS.splice(idx, 1);
              renderAdsTable(opts);
              showToast('Ad deleted.', 'success');
            },
          });
          return;
        }
        if (action === 'pause') { ad.status = 'paused'; showToast('Ad paused.', 'info'); }
        if (action === 'activate') { ad.status = 'active'; showToast('Ad activated.', 'success'); }
        if (action === 'approve') { ad.status = 'active'; showToast('Ad approved and set live.', 'success'); }
        if (action === 'reject') {
          const reason = window.prompt('Reason for rejecting "' + ad.title + '" (shown to the advertiser):', 'Please review our ad content guidelines and resubmit.');
          if (reason === null) return;
          ad.status = 'rejected';
          ad.rejectionReason = reason;
          showToast('Ad rejected.', 'error');
        }
        if (action === 'edit') {
          window.location.href = 'create-ad.html?edit=' + encodeURIComponent(ad.id);
          return;
        }
        renderAdsTable(opts);
      });
    });
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  window.SkoolystAdsUI = window.SkoolystAdsUI || {};
  window.SkoolystAdsUI.renderAdsTable = renderAdsTable;
  window.SkoolystAdsUI.formatNumber = formatNumber;
  window.SkoolystAdsUI.formatDate = formatDate;
  window.SkoolystAdsUI.appName = appName;
  window.SkoolystAdsUI.appCode = appCode;
  window.SkoolystAdsUI.statusLabel = statusLabel;

  // -----------------------------------------------------------------------
  // 6. Simple bar chart renderer (CSS bars, no chart library — UI only)
  // -----------------------------------------------------------------------
  function renderBarChart(containerId, data) {
    const el = document.getElementById(containerId);
    if (!el) return;
    const max = Math.max.apply(null, data.map(function (d) { return d.value; }));
    el.innerHTML = data.map(function (d) {
      const pct = max ? Math.round((d.value / max) * 100) : 0;
      return (
        '<div class="db-barchart__col">' +
          '<div class="db-barchart__bar" style="height:100%;" title="' + d.label + ': ' + formatNumber(d.value) + '">' +
            '<span style="height:' + pct + '%;"></span>' +
          '</div>' +
          '<div class="db-barchart__label">' + d.label + '</div>' +
        '</div>'
      );
    }).join('');
  }
  window.SkoolystAdsUI.renderBarChart = renderBarChart;

  // -----------------------------------------------------------------------
  // 7. Copy-to-clipboard (API keys, code snippets)
  // -----------------------------------------------------------------------
  function initCopyButtons() {
    $all('[data-copy-target]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const target = document.getElementById(btn.dataset.copyTarget);
        if (!target) return;
        const text = target.dataset.fullValue || target.textContent;
        const done = function () {
          const original = btn.innerHTML;
          btn.innerHTML = '<i class="bi bi-check2"></i>';
          window.setTimeout(function () { btn.innerHTML = original; }, 1200);
          showToast('Copied to clipboard.', 'success');
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(done).catch(function () { showToast('Could not copy — select and copy manually.', 'error'); });
        } else {
          done();
        }
      });
    });
  }

  // -----------------------------------------------------------------------
  // 8. Code tabs (API docs — curl / JS / PHP)
  // -----------------------------------------------------------------------
  function initCodeTabs() {
    $all('.db-code-tabs').forEach(function (group) {
      const tabs = $all('.db-code-tab', group);
      const panelWrap = group.nextElementSibling;
      tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
          tabs.forEach(function (t) { t.classList.remove('active'); });
          tab.classList.add('active');
          if (!panelWrap) return;
          $all('[data-code-panel]', panelWrap).forEach(function (panel) {
            panel.style.display = (panel.dataset.codePanel === tab.dataset.codeTab) ? 'block' : 'none';
          });
        });
      });
    });
  }

  // -----------------------------------------------------------------------
  // 9. Docs scrollspy for the left-hand section nav
  // -----------------------------------------------------------------------
  function initDocsNav() {
    const nav = $('.db-doc-nav');
    if (!nav) return;
    const links = $all('a[href^="#"]', nav);
    const sections = links
      .map(function (l) { return document.getElementById(l.getAttribute('href').slice(1)); })
      .filter(Boolean);

    function onScroll() {
      let current = sections[0];
      sections.forEach(function (sec) {
        if (window.scrollY + 120 >= sec.offsetTop) current = sec;
      });
      links.forEach(function (l) {
        l.classList.toggle('active', current && l.getAttribute('href') === '#' + current.id);
      });
    }
    document.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  // -----------------------------------------------------------------------
  // 10. Filter/search wiring generic helper
  // -----------------------------------------------------------------------
  function initTableFilters(opts) {
    const searchEl = document.getElementById(opts.searchId);
    const statusEl = document.getElementById(opts.statusId);
    const appEl = document.getElementById(opts.appId);

    function currentFilters() {
      return {
        query: searchEl ? searchEl.value.trim() : '',
        status: statusEl ? statusEl.value : 'all',
        app: appEl ? appEl.value : 'all',
      };
    }

    const renderOpts = Object.assign({}, opts, { getFilters: currentFilters });

    [searchEl, statusEl, appEl].forEach(function (el) {
      if (!el) return;
      el.addEventListener('input', function () { renderAdsTable(renderOpts); });
      el.addEventListener('change', function () { renderAdsTable(renderOpts); });
    });

    renderAdsTable(renderOpts);
  }
  window.SkoolystAdsUI.initTableFilters = initTableFilters;

  // -----------------------------------------------------------------------
  // 11. Help tooltips — see views/components/help-icon.php.
  // One initializer turns every [data-bs-toggle="tooltip"] on the page
  // into a Bootstrap tooltip, so no page has to wire this up itself.
  // -----------------------------------------------------------------------
  function initTooltips() {
    if (!window.bootstrap) return;
    $all('[data-bs-toggle="tooltip"]').forEach(function (el) {
      bootstrap.Tooltip.getOrCreateInstance(el);
    });
  }

  // -----------------------------------------------------------------------
  // Init on every page that includes this script
  // -----------------------------------------------------------------------
  document.addEventListener('DOMContentLoaded', function () {
    initSidebar();
    initCopyButtons();
    initCodeTabs();
    initDocsNav();
    initTooltips();
  });
})();
