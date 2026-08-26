/**
 * Skoolyst Ads — static prototype
 *
 * This file is written so the demo data source can later be swapped for a
 * real API response (e.g. fetch('/api/v1/ads/serve?placement=home_top'))
 * without touching renderAdvertisement() or any DOM-building code.
 *
 * Real API responses are expected to already match this shape:
 *   { id, title, description, advertiser, image_url, cta_text, click_url }
 */

(function () {
  'use strict';

  // ---------------------------------------------------------------------
  // 1. Demo data (stand-in for a future GET /api/v1/ads/serve response)
  // ---------------------------------------------------------------------
  const MAIN_DEMO_ADS = [
    {
      id: 'ad_1001',
      advertiser: 'Bright Path Computer Academy',
      title: 'Admissions Open — Build Your Future With Tech',
      description:
        'Hands-on courses in web development, graphic design, and office skills. Evening batches available for working students.',
      image_url: 'assets/ad-1.svg',
      cta_text: 'Learn More',
      click_url: 'https://example.com/computer-academy',
    },
    {
      id: 'ad_1002',
      advertiser: 'Fluent English Learning Center',
      title: 'Speak Confidently in 8 Weeks',
      description:
        'Small-group spoken English classes for students and professionals, with weekend batches and certified instructors.',
      image_url: 'assets/ad-2.svg',
      cta_text: 'Book a Seat',
      click_url: 'https://example.com/english-center',
    },
    {
      id: 'ad_1003',
      advertiser: 'Noor Educational Book Store',
      title: 'New Semester, New Books — Up to 20% Off',
      description:
        'Textbooks, guides, and stationery for all grades in one place, with home delivery across the city.',
      image_url: 'assets/ad-3.svg',
      cta_text: 'Shop Now',
      click_url: 'https://example.com/book-store',
    },
  ];

  const PLACEMENT_PREVIEWS = [
    {
      placement: 'home_top',
      ad: MAIN_DEMO_ADS[1],
    },
    {
      placement: 'home_sidebar',
      ad: MAIN_DEMO_ADS[2],
    },
    {
      placement: 'blog_inline',
      ad: MAIN_DEMO_ADS[0],
    },
  ];

  // ---------------------------------------------------------------------
  // 2. Reusable, XSS-safe renderer — builds nodes with createElement/
  //    textContent so it never trusts ad copy as HTML, whether the data
  //    comes from this static array or a real API later.
  // ---------------------------------------------------------------------

  // 7.m/7.n — impression/click tracking fires-and-forgets: it never
  // blocks rendering or navigation, and the page never waits on or
  // checks its result. sendBeacon is preferred because it's built for
  // exactly this (queued by the browser, survives the page unloading
  // right after a click); fetch with keepalive is the fallback for
  // browsers without sendBeacon.
  function trackAdEvent(adId, eventType) {
    if (!adId) return;

    const url = '/api/v1/ads/' + encodeURIComponent(adId) + '/' + eventType;

    if (navigator.sendBeacon) {
      navigator.sendBeacon(url);
      return;
    }

    fetch(url, { method: 'POST', keepalive: true }).catch(function () {
      // Deliberately ignored — a dropped tracking ping should never
      // surface as an error to the visitor.
    });
  }

  function renderAdvertisement(ad, container, variant) {
    variant = variant || 'main'; // 'main' | 'placement'
    container.innerHTML = '';

    if (variant === 'placement') {
      container.appendChild(buildPlacementAd(ad));
    } else {
      container.appendChild(buildMainAd(ad));
    }

    trackAdEvent(ad.id, 'impression');
  }

  function buildMainAd(ad) {
    const card = el('article', 'ad-card');
    const inner = el('div', 'ad-card__inner');

    const media = el('div', 'ad-card__media');
    const img = document.createElement('img');
    img.src = ad.image_url;
    img.alt = ad.advertiser + ' — ' + ad.title;
    img.loading = 'lazy';
    media.appendChild(img);

    const body = el('div', 'ad-card__body');

    const advertiser = el('span', 'ad-card__advertiser');
    advertiser.textContent = ad.advertiser;

    const title = document.createElement('h3');
    title.className = 'ad-card__title';
    title.textContent = ad.title;

    const desc = document.createElement('p');
    desc.className = 'ad-card__desc';
    desc.textContent = ad.description;

    const ctaRow = el('div', 'ad-card__cta-row');
    const cta = document.createElement('a');
    cta.className = 'btn-ad-cta';
    cta.href = ad.click_url;
    cta.target = '_blank';
    cta.rel = 'noopener sponsored';
    cta.textContent = ad.cta_text;
    const ctaIcon = document.createElement('i');
    ctaIcon.className = 'bi bi-arrow-up-right';
    cta.appendChild(ctaIcon);
    cta.addEventListener('click', function () { trackAdEvent(ad.id, 'click'); });

    const urlSpan = el('span', 'ad-card__url');
    urlSpan.textContent = safeHostname(ad.click_url);

    ctaRow.appendChild(cta);
    ctaRow.appendChild(urlSpan);

    body.appendChild(advertiser);
    body.appendChild(title);
    body.appendChild(desc);
    body.appendChild(ctaRow);

    inner.appendChild(media);
    inner.appendChild(body);
    card.appendChild(inner);

    return card;
  }

  function buildPlacementAd(ad) {
    const wrap = el('div', 'placement-ad');

    const img = document.createElement('img');
    img.src = ad.image_url;
    img.alt = ad.advertiser + ' advertisement';
    img.loading = 'lazy';

    const body = el('div', 'placement-ad__body');
    const title = document.createElement('p');
    title.className = 'placement-ad__title';
    title.textContent = ad.title;

    const desc = document.createElement('p');
    desc.className = 'placement-ad__desc';
    desc.textContent = truncate(ad.description, 70);

    const cta = document.createElement('a');
    cta.className = 'placement-ad__cta';
    cta.href = ad.click_url;
    cta.target = '_blank';
    cta.rel = 'noopener sponsored';
    cta.textContent = ad.cta_text + ' \u2192';
    cta.addEventListener('click', function () { trackAdEvent(ad.id, 'click'); });

    body.appendChild(title);
    body.appendChild(desc);
    body.appendChild(cta);

    wrap.appendChild(img);
    wrap.appendChild(body);

    return wrap;
  }

  function el(tag, className) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    return node;
  }

  function truncate(text, max) {
    return text.length > max ? text.slice(0, max).trim() + '\u2026' : text;
  }

  function safeHostname(url) {
    try {
      return new URL(url).hostname;
    } catch (e) {
      return '';
    }
  }

  // ---------------------------------------------------------------------
  // 3. Main ad rotation — loops back to the first ad after the last
  // ---------------------------------------------------------------------
  let currentAdIndex = 0;

  function showMainAd(index) {
    const container = document.getElementById('main-ad-container');
    if (!container) return;
    renderAdvertisement(MAIN_DEMO_ADS[index], container, 'main');

    const sponsoredLabel = document.getElementById('main-ad-sponsored-label');
    if (sponsoredLabel) sponsoredLabel.hidden = false;
  }

  function initMainAdRotation() {
    showMainAd(currentAdIndex);

    const loadBtn = document.getElementById('load-another-ad');
    if (!loadBtn) return;

    loadBtn.addEventListener('click', function () {
      loadBtn.classList.add('is-loading');
      loadBtn.disabled = true;

      // Tiny delay so the spin affordance is visible; also mirrors the
      // shape a real network request will have later.
      window.setTimeout(function () {
        currentAdIndex = (currentAdIndex + 1) % MAIN_DEMO_ADS.length; // loops back to ad 1
        showMainAd(currentAdIndex);
        loadBtn.classList.remove('is-loading');
        loadBtn.disabled = false;
        loadBtn.focus();
      }, 280);
    });
  }

  // ---------------------------------------------------------------------
  // 4. Placement previews — render on page load
  // ---------------------------------------------------------------------
  function initPlacementPreviews() {
    PLACEMENT_PREVIEWS.forEach(function (entry) {
      const container = document.querySelector(
        '[data-placement-container="' + entry.placement + '"]'
      );
      if (container) {
        renderAdvertisement(entry.ad, container, 'placement');
      }
    });
  }

  // ---------------------------------------------------------------------
  // Init
  // ---------------------------------------------------------------------
  document.addEventListener('DOMContentLoaded', function () {
    initMainAdRotation();
    initPlacementPreviews();
  });
})();
