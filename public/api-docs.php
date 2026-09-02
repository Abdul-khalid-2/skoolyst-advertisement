<?php
require __DIR__ . '/../views/bootstrap.php';

$pageTitle  = 'API Docs';
$metaDescription = 'AdEngine API reference for Skoolyst Ads: authenticate with an API key, request ads to serve, and report impressions and clicks for any connected app.';
$role       = 'advertiser';
$activeNav  = 'api-docs';
$baseHref   = '';

$topbarActions = '<span class="chip"><i class="bi bi-tag me-1"></i> v1</span> <a href="admin/apps.php" class="btn btn-sk-outline btn-sm">Manage API Keys</a>';

$placementRows = '';
foreach ($mockData['apps'] as $app) {
    foreach (($mockData['placementsByApp'][$app['id']] ?? []) as $p) {
        $placementRows .= '<tr><td>' . app_chip($app) . '</td>'
            . '<td><code style="background:var(--color-neutral-bg-alt);padding:0.15rem 0.4rem;border-radius:4px;font-family:var(--font-mono);font-size:0.82rem;">' . htmlspecialchars($p['value']) . '</code></td>'
            . '<td class="text-muted small">' . htmlspecialchars($p['label']) . '</td></tr>';
    }
}

ob_start();
?>
<div class="db-page-head">
  <div>
    <h2>AdEngine API Reference</h2>
    <p>One API, every Skoolyst app. Request ads, report impressions and clicks, and keep every placement in sync with what's configured in the dashboard — no ad logic hardcoded per project.</p>
  </div>
</div>

<div class="db-doc-layout">

  <nav class="db-doc-nav">
    <a href="#getting-started">Getting Started</a>
    <a href="#authentication">Authentication</a>
    <a href="#serve-ad">Serve an Ad</a>
    <a href="#track-impression">Track Impression</a>
    <a href="#track-click">Track Click</a>
    <a href="#placements">Placement Codes</a>
    <a href="#errors">Errors</a>
    <a href="#rate-limits">Rate Limits</a>
  </nav>

  <div class="db-doc-content">

    <section class="db-doc-section" id="getting-started">
      <h3>Getting Started</h3>
      <p>The AdEngine API lets any connected app request ads for a given placement, then report back when an ad was seen or clicked. All Skoolyst properties — <code>skoolyst.com</code>, <code>social.skoolyst.com</code>, <code>teachers.skoolyst.com</code> — and outside apps like Jaans Fabrics or Saif Pindi Autos talk to the same three endpoints below.</p>
      <p class="muted">Base URL: <code>https://adds.skoolyst.com/api/v1</code></p>
      <h4>Integration flow</h4>
      <p>1. Request an ad for a placement on page load. 2. Render it using your own markup, matching the field names below. 3. Fire an impression once it's actually visible. 4. Fire a click event when the ad's link is opened.</p>
    </section>

    <section class="db-doc-section" id="authentication">
      <h3>Authentication</h3>
      <p>Every request is authenticated with a per-app API key, generated when an app is connected from <a href="admin/apps.php">Admin → Connected Apps</a>. Send it as a bearer token. <?= help_icon('api_key', $helpText) ?></p>
      <div class="endpoint-row">Authorization: Bearer <span style="color:#7dd3fc;">sk_live_xxxxxxxxxxxxxxxx</span></div>
      <p class="muted">Keys are scoped to one app and can only request or report on that app's own placements. Rotate a compromised key immediately from Connected Apps — the old key stops working the moment a new one is issued.</p>
    </section>

    <section class="db-doc-section" id="serve-ad">
      <h3>Serve an Ad</h3>
      <p>Returns one eligible ad for a given placement, or <code>null</code> if nothing is currently active for it. Only <code>active</code> ads scheduled for the current date are considered, and results are cached per app+placement for 30 seconds.</p>
      <div class="endpoint-row"><span class="endpoint-verb endpoint-verb--get">GET</span> /ads/serve?placement=home_top</div>

      <h4>Query Parameters</h4>
      <table class="db-param-table">
        <thead><tr><th>Parameter</th><th>Type</th><th>Description</th></tr></thead>
        <tbody>
          <tr><td><code>placement</code> <span class="param-required">Required</span></td><td>string</td><td>Placement code for this app. See <a href="#placements">Placement Codes</a>.</td></tr>
        </tbody>
      </table>
      <p class="muted">There's currently no <code>limit</code>/multi-ad option — one ad (or none) comes back per call.</p>

      <h4>Example</h4>
      <div class="api-preview-wrap">
        <div class="db-code-tabs">
          <button type="button" class="db-code-tab active" data-code-tab="curl">cURL</button>
          <button type="button" class="db-code-tab" data-code-tab="js">JavaScript</button>
          <button type="button" class="db-code-tab" data-code-tab="php">PHP</button>
        </div>
        <div>
          <pre class="api-preview-code" data-code-panel="curl" style="display:block;"><code>curl -X GET \
  "https://adds.skoolyst.com/api/v1/ads/serve?placement=home_top" \
  -H "Authorization: Bearer sk_live_xxxxxxxxxxxxxxxx"</code></pre>
          <pre class="api-preview-code" data-code-panel="js" style="display:none;"><code><span class="tok-key">const</span> res = <span class="tok-key">await</span> fetch(
  <span class="tok-str">"https://adds.skoolyst.com/api/v1/ads/serve?placement=home_top"</span>,
  { headers: { Authorization: <span class="tok-str">"Bearer sk_live_xxxxxxxxxxxxxxxx"</span> } }
);
<span class="tok-key">const</span> { data } = <span class="tok-key">await</span> res.json();
<span class="tok-key">const</span> ad = data.ad; // null if nothing is eligible</code></pre>
          <pre class="api-preview-code" data-code-panel="php" style="display:none;"><code>$response = file_get_contents(
  'https://adds.skoolyst.com/api/v1/ads/serve?placement=home_top',
  false,
  stream_context_create(['http' => [
    'header' => "Authorization: Bearer sk_live_xxxxxxxxxxxxxxxx"
  ]])
);
$ad = json_decode($response, true)['data']['ad']; // null if nothing is eligible</code></pre>
        </div>
      </div>

      <h4>Response</h4>
      <div class="api-preview-wrap">
        <div class="api-preview-header">
          <div class="api-preview-dots"><span></span><span></span><span></span></div>
          <span class="api-preview-endpoint">200 OK</span>
        </div>
        <pre class="api-preview-code"><code>{
  <span class="tok-key">"success"</span>: <span class="tok-bool">true</span>,
  <span class="tok-key">"data"</span>: {
    <span class="tok-key">"ad"</span>: {
      <span class="tok-key">"id"</span>: <span class="tok-str">1002</span>,
      <span class="tok-key">"title"</span>: <span class="tok-str">"Speak Confidently in 8 Weeks"</span>,
      <span class="tok-key">"description"</span>: <span class="tok-str">"Small-group spoken English classes with weekend batches."</span>,
      <span class="tok-key">"image_path"</span>: <span class="tok-str">"uploads/ads/3f1c9e2a...b7.jpg"</span>,
      <span class="tok-key">"cta_text"</span>: <span class="tok-str">"Book a Seat"</span>,
      <span class="tok-key">"click_url"</span>: <span class="tok-str">"https://fluentenglish.example.com/enroll"</span>
    }
  }
}</code></pre>
      </div>
      <p class="muted"><code>data.ad</code> is <code>null</code> when no active ad matches the placement — check for that before rendering. <code>image_path</code> is relative; resolve it against this API's own host (e.g. <code>https://ads.skoolyst.com/</code> + <code>image_path</code>) — served as a static file, not through a JSON endpoint. <code>click_url</code> is the advertiser's real destination as entered when the ad was created — AdEngine does not rewrite it into a tracking link and does not log clicks automatically. Send the visitor there yourself, and call <a href="#track-click">Track a Click</a> at the same time so it's counted.</p>
    </section>

    <section class="db-doc-section" id="track-impression">
      <h3>Track an Impression <?= help_icon('impressions', $helpText) ?></h3>
      <p>Call this once an ad has actually entered the viewport — not just when it was requested.</p>
      <div class="endpoint-row"><span class="endpoint-verb endpoint-verb--post">POST</span> /ads/{ad_id}/impression</div>
      <p class="muted">The <code>{ad_id}</code> in the path is illustrative only — the current implementation reads it from the request body, not the URL, so send it as a field:</p>
      <table class="db-param-table">
        <thead><tr><th>Field</th><th>Type</th><th>Description</th></tr></thead>
        <tbody>
          <tr><td><code>ad_id</code> <span class="param-required">Required</span></td><td>integer</td><td>The <code>id</code> from the matching <code>/ads/serve</code> response. Must belong to your app's own ads.</td></tr>
        </tbody>
      </table>
    </section>

    <section class="db-doc-section" id="track-click">
      <h3>Track a Click</h3>
      <p>Call this yourself whenever a visitor follows an ad's <code>click_url</code>. There's no automatic click-tracking redirect — <code>click_url</code> is the advertiser's real destination as-is, so if you don't call this endpoint the click won't be counted.</p>
      <div class="endpoint-row"><span class="endpoint-verb endpoint-verb--post">POST</span> /ads/{ad_id}/click</div>
      <p class="muted">Same body shape as Track an Impression — send <code>ad_id</code> as a field, not a URL segment.</p>
    </section>

    <section class="db-doc-section" id="placements">
      <h3>Placement Codes <?= help_icon('placement', $helpText) ?></h3>
      <p>Each connected app defines its own placement codes from <a href="admin/apps.php">Admin → Connected Apps</a>. Current placements:</p>
      <div class="db-table-wrap">
        <table class="db-table" style="min-width:520px;">
          <thead><tr><th>App</th><th>Placement Code</th><th>Description</th></tr></thead>
          <tbody><?= $placementRows ?></tbody>
        </table>
      </div>
    </section>

    <section class="db-doc-section" id="errors">
      <h3>Errors</h3>
      <p>Errors follow a consistent shape so client code can handle them the same way everywhere:</p>
      <div class="api-preview-wrap">
        <pre class="api-preview-code"><code>{
  <span class="tok-key">"success"</span>: <span class="tok-bool">false</span>,
  <span class="tok-key">"error"</span>: {
    <span class="tok-key">"code"</span>: <span class="tok-str">"validation_error"</span>,
    <span class="tok-key">"message"</span>: <span class="tok-str">"placement is required."</span>
  }
}</code></pre>
      </div>
      <table class="db-param-table">
        <thead><tr><th>HTTP Status</th><th>Code</th><th>Meaning</th></tr></thead>
        <tbody>
          <tr><td>401</td><td><code>unauthorized</code></td><td>Missing or revoked API key.</td></tr>
          <tr><td>404</td><td><code>not_found</code></td><td>The <code>ad_id</code> doesn't exist, or doesn't belong to your app.</td></tr>
          <tr><td>400</td><td><code>validation_error</code></td><td><code>placement</code> (on <code>/ads/serve</code>) is missing.</td></tr>
          <tr><td>429</td><td><em>(no code — message only)</em></td><td>Too many requests — see rate limits below.</td></tr>
        </tbody>
      </table>
      <p class="muted">An unrecognized or not-yours placement code doesn't currently return an error — <code>/ads/serve</code> just responds with <code>data.ad: null</code>, the same as when no ad is scheduled for a valid placement.</p>
    </section>

    <section class="db-doc-section" id="rate-limits">
      <h3>Rate Limits</h3>
      <p>Requests are rate-limited per API key (or per IP if unauthenticated): <strong>300 requests per minute</strong> for <code>/ads/serve</code> and the impression/click endpoints, <strong>60 requests per minute</strong> elsewhere. A request over the limit gets a <code>429</code> with no <code>X-RateLimit-*</code> headers — those aren't implemented yet, so don't rely on them to track your own usage.</p>
      <p class="muted">Need a higher limit for a high-traffic placement? Reach out from the Connected Apps page.</p>
    </section>

  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layouts/app.php';
