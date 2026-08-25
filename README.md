# Skoolyst Ads — AdEngine

A centralized advertisement engine for the Skoolyst family of apps. One dashboard to create and manage ads, one admin panel to moderate them, and one API that any connected app (`skoolyst.com`, `social.skoolyst.com`, `teachers.skoolyst.com`, and outside apps like Jaans Fabrics or Safi India Autos) calls to fetch and report on ads — instead of every project hardcoding its own ad logic.

This document is the build plan: what the product is, how the UI is structured, and how the backend, database, security, and performance layers will be built in PHP + MySQL. The UI in this repo (`/dashboard`, `/admin`, `/api-docs.html`) is already built as a static prototype — this plan describes what sits behind it.

---

## Progress Status

Each section below is implemented one at a time — a section isn't started until the previous one is marked ✅ Done, so nothing gets half-built in parallel.

| # | Section | Status |
|---|---|---|
| 1 | Project Idea | ✅ Done |
| 2 | User Interface | ✅ Done |
| 3 | Backend Architecture | ⬜ Not Started |
| 4 | API Structure | ⬜ Not Started |
| 5 | Database Design | ⬜ Not Started |
| 6 | Authentication & Security | ⬜ Not Started |
| 7 | Performance & Optimization | ⬜ Not Started |
| 8 | SEO | ⬜ Not Started |
| 9 | Folder Structure | ⬜ Not Started |
| 10 | Build Order / Roadmap | ⬜ Not Started |
| 11 | Tech Stack & Environment Setup | ⬜ Not Started |
| 12 | Coding Standards & Git Workflow | ⬜ Not Started |
| 13 | Testing & QA | ⬜ Not Started |
| 14 | Deployment | ⬜ Not Started |
| 15 | Future Enhancements | ⬜ Not Started |

---

## Table of Contents

1. [Project Idea](#1-project-idea) — ✅ Done
2. [User Interface](#2-user-interface) — ✅ Done
3. [Backend Architecture](#3-backend-architecture)
4. [API Structure](#4-api-structure)
5. [Database Design](#5-database-design)
6. [Authentication & Security](#6-authentication--security)
7. [Performance & Optimization](#7-performance--optimization)
8. [SEO](#8-seo)
9. [Folder Structure](#9-folder-structure)
10. [Build Order / Roadmap](#10-build-order--roadmap)
11. [Tech Stack & Environment Setup](#11-tech-stack--environment-setup)
12. [Coding Standards & Git Workflow](#12-coding-standards--git-workflow)
13. [Testing & QA](#13-testing--qa)
14. [Deployment](#14-deployment)
15. [Future Enhancements](#15-future-enhancements)

---

## 1. Project Idea ✅ Done

**Status:** already decided and documented — no further work needed on this section. The idea below is exactly what sections 2–15 are built against; if it ever changes, every later section needs re-checking against the new definition.

**Problem:** Each Skoolyst property currently has no shared way to run sponsored ads. Adding ads to a new app today would mean writing the ad logic, storage, and rendering again from scratch for that app.

**Solution:** One AdEngine service with three consumers:

| Consumer | What they do |
|---|---|
| **Advertisers** | Create an ad (title, image, description, CTA, target app/placement, schedule), track its impressions/clicks. |
| **Platform Admins** | Approve or reject submitted ads, manage which apps are connected and their API keys, watch platform-wide performance. |
| **Connected Apps (developers)** | Call one API to fetch ads for a placement and report back impressions/clicks — no ad-specific code lives in their own app. |

**Core principle:** the ad's data model, moderation state, and delivery logic live in one place. Every connected app is just a *renderer* of whatever the API returns.

---

## 2. User Interface ✅ Done

**Status:** implemented and pushed on branch `claude-66`. The pages are now real PHP views built on the shared layout/partial/component system described below — not just a static prototype. Specifically: `views/layouts/app.php` (shared shell), `views/partials/*` (head, sidebar-advertiser, sidebar-admin, topbar, scripts), `views/components/*` (status-badge, stat-card, app-chip, help-icon, ads-table, modal-confirm), and `data/mock-data.php` as the single source of truth for both PHP and injected JS. All 7 pages (`dashboard/index.php`, `create-ad.php`, `my-ads.php`, `admin/index.php`, `ads.php`, `apps.php`, `api-docs.php`) run on this system.

The UI is already built as static HTML/CSS/JS (Bootstrap 5 + vanilla JS) so backend work can plug into working markup rather than designing while coding the API. Two principles carried through every screen:

### 2.1 Component-based, not copy-pasted
Rather than repeating the sidebar, topbar, ad-card, badge, and table markup on every page, the real (PHP) build turns each into a **reusable partial**:

- `views/partials/sidebar-advertiser.php`, `sidebar-admin.php`
- `views/partials/topbar.php`
- `views/components/ad-card.php` — used by the dashboard, my-ads table preview, and the public-facing placements
- `views/components/status-badge.php` — one badge component driven by a `$status` variable, instead of six copy-pasted `<span>` blocks
- `views/components/stat-card.php`, `table.php`, `modal-confirm.php`

This is the same intent already visible in the CSS: one `dashboard.css` token system (`--db-sidebar-w`, `badge-status--*`, `.db-card`, `.db-stat`) shared by every dashboard and admin page, instead of each page inventing its own styles. In PHP, that becomes shared **partials + a shared layout file** (`views/layouts/app.php`) that every page extends, so a design change (e.g. sidebar width, badge color) is made once.

### 2.2 Screens already designed
- **Advertiser:** Dashboard overview → Create Ad (3-step form with live preview) → My Ads (searchable/filterable table)
- **Admin:** Overview → All Ads (moderation queue, approve/reject) → Connected Apps (API key management)
- **Docs:** API reference for developers integrating the ad-serving endpoint

### 2.3 Help tooltips (the "ⓘ" icon pattern)
Every form field or stat that needs a short explanation gets a small **ⓘ icon** next to its label, using one shared tooltip component instead of custom popovers per page:

```html
<label class="db-form-label">
  Click-Through Rate
  <i class="bi bi-info-circle db-help-icon"
     data-bs-toggle="tooltip"
     data-bs-placement="top"
     title="Clicks divided by impressions, over the selected period."></i>
</label>
```

- One CSS class (`.db-help-icon`) styles every instance the same way.
- One line of JS initializes all tooltips on the page: `document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el))`.
- Copy for each tooltip lives in a single PHP array (`config/help-text.php`) keyed by field name, so wording is edited in one place, not hunted down across templates.

### 2.4 Responsiveness
Sidebar collapses to an off-canvas drawer under 992px (already implemented in `dashboard.css` / `dashboard.js`); tables scroll horizontally on small screens instead of breaking layout; the create-ad live preview stacks below the form on mobile instead of sitting sticky beside it.

---

## 3. Backend Architecture

**Stack:** PHP 8.x, no framework lock-in assumed — a lightweight custom MVC (Laravel is also a drop-in option given your existing Laravel background; the structure below maps directly onto Laravel's app/Http/Controllers, app/Models, routes/web.php if you choose to use it instead of a custom router).

### 3.1 Why "one feature = one isolated folder"
The explicit goal is: **changing the Ads feature should never risk breaking the Apps feature or the Auth feature.** That's solved with **feature-based modules**, not layer-based sprawl:

```
app/
  Ads/
    AdController.php
    AdModel.php
    AdRepository.php
    AdValidator.php
    routes.php
  Apps/
    AppController.php
    AppModel.php
    routes.php
  Auth/
    AuthController.php
    UserModel.php
    routes.php
  Admin/
    ModerationController.php
    routes.php
```

Each module:
- Owns its own routes file, merged into the router at boot (`routes/api.php` just does `require app/Ads/routes.php;` etc.) — adding a route to Ads never touches the Apps routes file.
- Talks to other modules **only through their public interface** (e.g. Ads calls `AppRepository::findByApiKey()`, never queries the `apps` table directly). This is what actually prevents cross-feature breakage — not just folder separation.
- Has its own validator/request class, so a stricter rule on ad titles doesn't ripple into the app-registration form.

### 3.2 Centralized, non-repeated code
Shared logic that *every* module needs lives once, in a `core/` (or `app/Support/`) layer, and modules depend on it — never on each other:

```
core/
  Database.php        // one PDO connection wrapper, reused everywhere
  Request.php          // input sanitizing, used by every controller
  Response.php          // one place that shapes every JSON response: {success, data, error}
  Validator.php         // base validation rules (required, max length, url, date)
  Auth/Middleware.php  // session/token check, reused by every protected route
  RateLimiter.php
  Cache.php
```

Rule of thumb: if two modules would need the same 5+ lines, it moves to `core/`. This is the backend equivalent of the shared `dashboard.css`/`dashboard.js` on the frontend — one source of truth, imported everywhere, edited once.

### 3.3 Standard request lifecycle
```
Request → Router → Middleware (auth, rate limit) → Controller → Validator → Repository → Model/DB → Response
```
Controllers stay thin (validate input, call a repository method, return a response) — all query logic lives in Repositories, which is what keeps a change to *how* ads are fetched from ever touching *how* ads are displayed.

---

## 4. API Structure

This is the contract every connected app relies on (also documented for developers in `api-docs.html`). It has to be the most stable part of the system, since external apps depend on it.

| Method | Endpoint | Purpose |
|---|---|---|
| `GET`  | `/api/v1/ads/serve?placement={code}` | Return eligible, active ads for a placement |
| `POST` | `/api/v1/ads/{id}/impression` | Log that an ad was actually seen |
| `POST` | `/api/v1/ads/{id}/click` | Log a click (also used as the public redirect URL) |
| `POST` | `/api/v1/advertiser/ads` | Advertiser creates an ad (dashboard use) |
| `PATCH`| `/api/v1/advertiser/ads/{id}` | Advertiser edits/pauses/resumes an ad |
| `GET`  | `/api/v1/admin/ads?status=pending` | Admin moderation queue |
| `PATCH`| `/api/v1/admin/ads/{id}/approve` \| `/reject` | Admin decision |
| `GET`/`POST`/`PATCH` | `/api/v1/admin/apps` | Manage connected apps & API keys |

**Design rules:**
- Every response follows one envelope: `{ "success": bool, "data" | "error": {...} }` — client code never has to branch on response shape per endpoint.
- Versioned from day one (`/api/v1/...`) so a breaking change later ships as `/v2/` without touching every connected app at once.
- Public "serve" and "track" endpoints are separate from the authenticated advertiser/admin endpoints, so a public app's key can never accidentally reach moderation actions (enforced by scoped API keys — see Security).

---

## 5. Database Design

MySQL, InnoDB, `utf8mb4`. Core tables:

```sql
users            (id, name, email, password_hash, role ENUM('advertiser','admin'), created_at)
apps             (id, name, code, domain, api_key_hash, status ENUM('active','paused'), created_at)
placements       (id, app_id FK, code, label, created_at)
ads              (id, user_id FK, app_id FK, placement_id FK, title, description,
                  image_path, cta_text, click_url, status ENUM('draft','pending','active',
                  'paused','rejected','ended'), rejection_reason, start_date, end_date, created_at)
ad_impressions   (id, ad_id FK, occurred_at, ip_hash, user_agent_hash)
ad_clicks        (id, ad_id FK, occurred_at, ip_hash, user_agent_hash)
api_keys         (id, app_id FK, key_hash, last_used_at, revoked_at)
```

### 5.1 Indexing plan
Indexes are added for exactly the columns the app actually filters/sorts/joins on — not by default on everything, since every extra index also slows down writes:

- `ads (status, placement_id)` — composite index, because `/ads/serve` always filters by both together.
- `ads (user_id)` — advertiser's "My Ads" list.
- `ads (start_date, end_date)` — scheduling checks on every serve request.
- `ad_impressions (ad_id, occurred_at)` / `ad_clicks (ad_id, occurred_at)` — powers the "impressions last 7 days" chart without scanning the whole table.
- `apps (api_key_hash)` — unique index; every authenticated request looks a key up by this.
- `placements (app_id, code)` — unique composite; a placement code only has to be unique *within* an app.

### 5.2 Aggregation, not row-by-row counting
`ad_impressions`/`ad_clicks` will grow fast. Rather than `COUNT(*)` on raw rows for every dashboard load, a scheduled job rolls daily totals into a small `ad_stats_daily (ad_id, date, impressions, clicks)` table, which is what the dashboard charts actually query. Raw event tables stay for auditing/fraud checks only.

---

## 6. Authentication & Security

| Concern | Approach |
|---|---|
| **Password storage** | `password_hash()` (bcrypt/argon2), never reversible encryption. |
| **Session/API auth** | Dashboard uses signed, HttpOnly session cookies; connected apps use a per-app API key (`Authorization: Bearer sk_live_...`), stored as a hash — never in plaintext — same pattern as the key shown in Admin → Connected Apps. |
| **Role-based access** | Every route declares the roles allowed to hit it (`advertiser`, `admin`); middleware rejects anything else with a 403 before it reaches the controller. |
| **CSRF** | Token on every state-changing dashboard form (create/edit/delete ad, app connect). |
| **SQL injection** | 100% prepared statements / parameterized queries — no string-built SQL anywhere, enforced through the shared `Database.php` wrapper so there's no per-feature place to get it wrong. |
| **XSS** | All user-entered ad copy is escaped on output (`htmlspecialchars`); uploaded images are re-encoded on upload, not served as-uploaded, to strip any embedded scripts/malformed metadata. |
| **File upload safety** | MIME-type + real image validation (not just file extension), size cap, renamed on storage (no user-controlled filenames), served from a non-executable path. |
| **Rate limiting** | Per-API-key limits on `/ads/serve` and tracking endpoints (documented in `api-docs.html`) to stop one misbehaving integration from overwhelming the database. |
| **Scoped API keys** | A key issued to one app can only request/report on that app's own placements — enforced at the query level, not just hidden in the UI. |
| **Audit trail** | Every admin approve/reject/regenerate-key action is logged with who, what, and when — separate from the impressions/clicks tables. |

---

## 7. Performance & Optimization

- **Query efficiency:** the indexing plan above + the daily-rollup stats table are the two biggest wins — most dashboard queries should hit an index and read a small pre-aggregated table, not scan millions of raw event rows.
- **Caching:** `/ads/serve` responses are cacheable per placement for a short TTL (e.g. 30–60s) since ad rotation doesn't need to be real-time; this cuts database load dramatically on high-traffic placements. A simple Redis or file-based cache in `core/Cache.php` sits in front of the repository layer.
- **PHP-level:** OPcache enabled in production; autoloading via Composer's optimized class map instead of dev-mode autoloading.
- **Images:** ad images are resized/compressed on upload to the sizes actually used by the ad card (no serving a 4000px source image into a 300px card), served with far-future cache headers and lazy-loaded (`loading="lazy"`, already used in the existing `app.js`).
- **Frontend:** one shared `dashboard.css`/`dashboard.js` bundle (already the case) instead of per-page styles/scripts, so the browser caches it once across every dashboard page instead of re-downloading near-duplicate CSS per page.
- **Pagination everywhere:** ad lists (advertiser and admin) are paginated at the database level (`LIMIT`/`OFFSET` or keyset pagination for the admin queue, which will be the larger table) — never "load everything and filter in JS" once real data volume shows up.
- **Async tracking:** impression/click tracking calls are fire-and-forget from the connected app's perspective (don't block ad rendering waiting on the tracking response).

---

## 8. SEO

Ads themselves are served into *other* apps' pages, so most SEO value belongs to those host pages — but the parts of this project that are public-facing get standard treatment:

- **Public marketing page (`index.html`) & API docs:** descriptive `<title>`/`<meta description>` per page (already present), semantic heading structure (`h1` → `h2` → `h3`, already followed in the docs layout), clean crawlable URLs.
- **Sitemap & robots.txt:** generated for the public marketing/docs pages; the advertiser dashboard, admin panel, and raw API endpoints are disallowed from crawling (`/dashboard/`, `/admin/`, `/api/` in `robots.txt`) since they're private, authenticated surfaces.
- **Structured data:** not applicable to the dashboard/admin UI; if the public marketing page grows, `Organization`/`SoftwareApplication` schema can be added there.
- **Performance-as-SEO:** since Core Web Vitals affect ranking for the public pages, the same performance work above (image optimization, caching, minimal render-blocking CSS/JS) doubles as SEO work for `index.html` and `api-docs.html`.

---

## 9. Folder Structure

Putting the plan together, the eventual project tree:

```
skoolyst-ads/
├── public/                     # web root — only this is publicly reachable
│   ├── index.php                # single entry point / front controller
│   ├── assets/                  # compiled/optimized css, js, images
│   └── uploads/ads/             # resized ad images only
├── app/
│   ├── Ads/                     # feature module
│   ├── Apps/                    # feature module
│   ├── Auth/                    # feature module
│   ├── Admin/                   # feature module
│   └── Support/                 # shared, cross-module helpers
├── core/                        # framework-agnostic base (DB, Request, Response, Cache, Middleware)
├── views/
│   ├── layouts/app.php
│   ├── partials/                # sidebar, topbar, footer
│   └── components/              # ad-card, status-badge, stat-card, tooltip
├── config/
│   ├── database.php
│   ├── help-text.php            # tooltip copy, single source
│   └── app.php
├── database/
│   ├── migrations/
│   └── seeders/
├── routes/
│   ├── web.php                  # merges each module's routes.php
│   └── api.php
└── tests/
```

---

## 10. Build Order / Roadmap

1. **Database first** — migrations for `users`, `apps`, `placements`, `ads`, `ad_impressions`, `ad_clicks`, `api_keys`; seed with the same mock data already used in the UI prototype so the frontend can be reconnected with minimal changes.
2. **Core layer** — `Database.php`, `Request.php`, `Response.php`, `Auth middleware`, before any feature module, since every module depends on these.
3. **Auth module** — login/session for advertisers and admins; API-key issuing for apps.
4. **Ads module (advertiser side)** — create/edit/list ads, wired into the existing `create-ad.html` / `my-ads.html` UI.
5. **Admin module** — moderation queue and connected-apps management, wired into `admin/ads.html` / `admin/apps.html`.
6. **Public API** — `/ads/serve`, `/impression`, `/click`, matching `api-docs.html` exactly so nothing documented ever drifts from what's actually built.
7. **Stats rollup job** — daily aggregation once real traffic exists.
8. **Hardening pass** — rate limiting, audit logging, security review against the checklist in Section 6.
9. **Performance pass** — caching layer, image pipeline, pagination on any list that's grown.
10. **SEO pass** — sitemap/robots.txt, meta review on public-facing pages.

---

## 11. Tech Stack & Environment Setup

| Layer | Choice | Why |
|---|---|---|
| Language | PHP 8.2+ | Typed properties, enums, and match expressions keep the module code (Section 3) readable without a heavy framework. |
| Database | MySQL 8.0 | Window functions and CTEs make the daily-rollup stats job (Section 5.2) straightforward. |
| Web server | Nginx + PHP-FPM | Serves `public/` only; PHP-FPM process isolation keeps one slow request from blocking others. |
| Cache | Redis (file-based cache as a fallback on shared hosting) | Backs `core/Cache.php` for the `/ads/serve` response cache. |
| Dependency manager | Composer | Autoloading, and optionally Laravel components (e.g. `illuminate/database`) if the custom router in Section 3 is swapped for full Laravel later — the module structure in `app/` maps onto Laravel without a rewrite. |
| Front-end | Bootstrap 5, Bootstrap Icons, vanilla JS | Matches what's already built in `/dashboard` and `/admin` — no new build tooling introduced. |

**Local environment**

```
git clone <repo-url> skoolyst-ads
cd skoolyst-ads
composer install
cp .env.example .env          # DB credentials, API base URL, app key
php database/migrate.php      # runs migrations in database/migrations/
php database/seed.php         # loads the same mock data used in the UI prototype
php -S localhost:8000 -t public
```

**`.env` values needed at minimum:**

```
DB_HOST=127.0.0.1
DB_NAME=skoolyst_ads
DB_USER=
DB_PASS=
APP_KEY=            # random 32-byte string, used to sign session cookies
APP_URL=http://localhost:8000
CACHE_DRIVER=file   # file | redis
```

No secrets (`.env`, API keys, `APP_KEY`) are ever committed — `.env` stays in `.gitignore`, and `.env.example` documents the shape without real values.

---

## 12. Coding Standards & Git Workflow

**Standards**
- PSR-12 code style; PSR-4 autoloading (`App\Ads\AdController`, mapped from `app/Ads/AdController.php`).
- One class per file, file name matches class name — no exceptions, since that's what keeps the "isolated feature folder" promise in Section 3 actually true.
- Every module's public methods get a short docblock describing what it expects/returns — especially the repository methods other modules are allowed to call.

**Git workflow**
- `main` is always deployable.
- One feature branch per module task — e.g. `feature/ads-create-endpoint`, `feature/admin-approve-flow` — which naturally stays small because each module is already isolated.
- Commit messages describe the module touched: `[Ads] add validation for click_url`, `[Admin] add rejection reason to approve/reject`.
- Pull requests are reviewed against the Section 6 security checklist before merge for anything touching auth, uploads, or raw SQL.
- Database migrations are never edited after merge to `main` — a mistake gets a new migration, so every environment replays the same history.

---

## 13. Testing & QA

| Layer | What's tested | Tooling |
|---|---|---|
| Unit | Validators, repository query-building logic, the stats rollup calculation | PHPUnit |
| Integration | Full request lifecycle per endpoint (auth → controller → DB → response shape) against a disposable test database | PHPUnit + a seeded test DB |
| API contract | Every endpoint in Section 4 matches exactly what `api-docs.html` documents — response shape, status codes, error codes | A small contract test suite run against the docs examples |
| Manual QA | Cross-browser/responsive pass on the dashboard and admin UI, since that's hand-built HTML/CSS/JS | Checklist pass on Chrome, Safari, and a real mobile device before each release |
| Security | SQL injection, XSS, and auth-bypass attempts on every new endpoint | Manual pass using the Section 6 checklist + `sqlmap`/OWASP ZAP spot checks before major releases |

New endpoints are not merged without at least one integration test — this is what actually enforces the "changing Ads doesn't break Apps" goal from Section 3, beyond just folder separation.

---

## 14. Deployment

- **Environments:** local → staging → production, each with its own `.env` and its own database — staging seeded with realistic-but-fake data, never a copy of real advertiser data.
- **Migrations run on deploy**, before the new code is switched in, so the schema is always ahead of the code that expects it.
- **Zero-downtime releases:** new code is deployed to a fresh directory and symlinked in (or via the host's built-in atomic deploy), so a deploy never serves a half-updated codebase.
- **Rollback plan:** keep the previous release's symlink target on disk; rolling back is re-pointing the symlink, not re-deploying.
- **Monitoring:** error logging (PHP errors + a catch-all in `Response.php` for uncaught exceptions) shipped somewhere queryable (e.g. a log file rotated daily, or a lightweight error tracker) — silent failures on the public `/ads/serve` endpoint would be invisible to every connected app otherwise.
- **Backups:** nightly MySQL dump, retained on a rolling window, tested with an occasional real restore — a backup that's never been restored is not a verified backup.

---

## 15. Future Enhancements

Deliberately out of scope for the first build, listed so they're not forgotten:

- **Billing** — the dashboard nav already has a disabled "Billing" placeholder; wiring up paid ad packages/invoicing is a distinct module once the free/manual-approval flow is proven.
- **Advertiser self-serve app onboarding** — today, connecting a new app is an admin action (Admin → Connected Apps); a self-serve request-and-approve flow could follow the same pattern as ad moderation.
- **A/B testing between ad creatives** — the schema already supports multiple ads per placement; rotating and comparing performance between variants is an analytics layer on top, not a schema change.
- **Advanced targeting** (by page, by user segment) — would extend `placements` rather than replace it.
- **Webhooks** — notify a connected app the moment an ad is approved/rejected, instead of the app having to poll.
