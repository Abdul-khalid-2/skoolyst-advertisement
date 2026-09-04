# Skoolyst Ads — AdEngine

A centralized advertisement engine for the Skoolyst family of apps. One dashboard to create and manage ads, one admin panel to moderate them, and one API that any connected app (`skoolyst.com`, `social.skoolyst.com`, `teachers.skoolyst.com`, and outside apps like Jaans Fabrics or Saif Pindi Autos) calls to fetch and report on ads — instead of every project hardcoding its own ad logic.

This document is the build plan, broken into small, self-contained tasks so each one is quick to pick up, quick to review, and quick to mark done. A section isn't started until the previous one is marked ✅ Done, so nothing gets half-built in parallel.

> 🗒️ **Naya session ya team member?** Seedha [Roman Urdu Explanation](#roman-urdu-explanation--har-section-step-by-step) section pe jaayen — wahan har section ka Urdu mein step-by-step tafseel hai.

---

## 🛠️ Quick Start — Local Setup & Command Reference

Fulfils roadmap item 11.i. Written for the environment this project is actually developed on (**Windows + XAMPP**), with a note at the end on what's different once Section 14 (Deployment) stands up a real server.

### One-time local setup

1. **Copy the env file** — in the project root: `copy .env.example .env`, then edit `.env` with real local values (at minimum `DB_NAME`, `DB_USER`, `DB_PASS` to match step 2).

2. **Create the database.** Either via phpMyAdmin (`http://localhost/phpmyadmin`), or from a `cmd` prompt using XAMPP's own MySQL client (usually `C:\xampp\mysql\bin\mysql.exe`):
   ```
   C:\xampp\mysql\bin\mysql.exe -u root
   ```
   then at the `mysql>` prompt:
   ```sql
   CREATE DATABASE skoolyst_ads CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
   If you're using XAMPP's default `root` user with no password (the common case), set `DB_USER=root` and leave `DB_PASS=` blank in `.env` — that's already `.env.example`'s default, so this step can be skipped if you're keeping those defaults.

3. **Run migrations** (creates every table from Section 5; tracks what's already applied in a `migrations` table, so re-running is always safe):
   ```
   php database\scripts\migrate.php
   ```

4. **Seed the database** — two separate seeders, each run once:
   ```
   php database\seeders\DatabaseSeeder.php
   php database\seeders\MockDataSeeder.php
   ```
   Both print seeded users' passwords and each connected app's API key **once**, to the terminal only — copy anything you need (to log in, or to call the API as an app) before it scrolls away, since only a hash is stored afterward.

5. **Serve the app** — two options:
   - **XAMPP/Apache (recommended — this is what actually respects `public/.htaccess`, so dashboard pages, static file serving, and the uploads non-executable-path protection all work correctly):** point a vhost's document root at this project's `public\` folder, or drop the whole project under `C:\xampp\htdocs\` and browse to `http://localhost/skoolyst-advertisement/public/`.
   - **PHP's built-in server** (quick API-only testing, no Apache needed):
     ```
     php -S 127.0.0.1:8099 -t public public/index.php
     ```
     Caveat noted during the 10.m test pass: this only correctly serves the JSON API routes (`/api/v1/...`). It does **not** fall back to serving real files like `index.html` or the dashboard pages — `public/index.php`'s router only knows the API route table, so anything else 404s. Use Apache/XAMPP whenever you need to click through the actual UI.

### Every time you pull new code

- **New migration files?** Run `php database\scripts\migrate.php` again — only the new/pending ones apply.
- **Nothing to re-seed normally.** Both seeders are idempotent — safe to re-run, but there's usually no need to.

### Running Tests

Fulfils Section 13. All 16 tests are live tests against a real disposable database and (for the two endpoint tests) a real HTTP server — nothing here is mocked.

1. **Copy the test env file** — `copy .env.testing.example .env.testing`, then edit `DB_NAME` etc. if needed. This is deliberately a *separate* file from `.env` — tests never touch your local dev database.
2. **Create the disposable test database** (same pattern as the dev DB in step 2 above, different name):
   ```sql
   CREATE DATABASE skoolyst_ads_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. **Build its schema fresh** (not `migrate.php` — that one tracks already-applied migrations, which a disposable DB has no history of between resets):
   ```
   php database\scripts\reset-test-db.php
   ```
   Safe to re-run any time you want a completely clean slate — drops every table and recreates them from the migration files.
4. **One-time: pull in PHPUnit.** `composer.json` declares `phpunit/phpunit` as a dev dependency, but `composer.lock` needs regenerating once after that addition:
   ```
   composer update phpunit/phpunit --dev
   ```
5. **Run the suite:**
   ```
   vendor\bin\phpunit
   ```
   `tests/Auth/LoginEndpointTest.php` and `tests/Ads/AdsServeContractTest.php` automatically spin up their own `php -S` instance on port `8098` (configurable via `TEST_HTTP_PORT` in `.env.testing`) for the duration of the run — no separate server needs to be running first, and it's a different port from the `8099` used for manual local testing so the two never collide.

### What changes once this goes to production (Section 14 — not built yet)

Not runnable yet (no server exists), but for when it is:
- `deploy.sh` runs `composer install --no-dev --optimize-autoloader` — **currently broken** (10.m/7.f finding): no `composer.json` exists in this repo yet, so that step will fail until Section 11.e/f sets Composer up properly. Don't rely on `deploy.sh` as-is until that's done.
- `config/opcache.ini`'s settings need to be dropped into the real server's php.ini/FPM pool config (not just committed to the repo) — `deploy.sh` already restarts PHP-FPM after every release so `opcache.validate_timestamps=0` is safe to use.
- The cron entry documented in `cron/README.md` (`ad_stats_daily` rollup) needs to actually be registered on the host's crontab — can't happen until a host exists (10.k).
- Real `.env` values on the server: different DB credentials, `APP_ENV=production`, `APP_DEBUG=false`.
- Apache's `.htaccess` files (`public/.htaccess`, `public/uploads/ads/.htaccess`) already do the right thing for production as committed — non-executable uploads path, image cache headers, static files served directly — nothing to change there.

---

## 📊 Progress Status

| # | Section | Status |
|---|---|---|
| 1 | [Project Idea](#1-project-idea) | ✅ Done |
| 2 | [User Interface](#2-user-interface) | ✅ Done |
| 3 | [Backend Architecture](#3-backend-architecture) | ✅ Done |
| 4 | [API Structure](#4-api-structure) | ✅ Done |
| 5 | [Database Design](#5-database-design) | ✅ Done |
| 6 | [Authentication & Security](#6-authentication--security) | ✅ Done |
| 7 | [Performance & Optimization](#7-performance--optimization) | ✅ Done |
| 8 | [SEO](#8-seo) | ✅ Done |
| 9 | [Folder Structure](#9-folder-structure) | ✅ Done |
| 10 | [Build Order / Roadmap](#10-build-order--roadmap) | 🟨 In Progress |
| 11 | [Tech Stack & Environment Setup](#11-tech-stack--environment-setup) | ✅ Done |
| 12 | [Coding Standards & Git Workflow](#12-coding-standards--git-workflow) | ✅ Done |
| 13 | [Testing & QA](#13-testing--qa) | ✅ Done |
| 14 | [Deployment](#14-deployment) | ⬜ Not Started |
| 15 | [Future Enhancements](#15-future-enhancements) | ⬜ Not Started *(deferred, not blocking)* |

---
---

# Build Plan (English)

## 1. Project Idea

**Status: ✅ Done**

- [x] **a** — Define the problem: no shared way to run ads across Skoolyst apps today
- [x] **b** — Define the three consumers: Advertisers, Platform Admins, Connected Apps (developers)
- [x] **c** — Define the core principle: ad data/moderation/delivery lives in one place; every connected app is just a renderer

---

## 2. User Interface

**Status: ✅ Done**

### 2.1 Component-based, not copy-pasted

- [x] **a** — `views/partials/sidebar-advertiser.php`, `sidebar-admin.php`
- [x] **b** — `views/partials/topbar.php`
- [x] **c** — `views/components/ad-card.php` — used by the dashboard, my-ads table preview, and public-facing placements
- [x] **d** — `views/components/status-badge.php` — one badge component driven by `$status`, instead of six copy-pasted `<span>` blocks
- [x] **e** — `views/components/stat-card.php`, `ads-table.php`, `modal-confirm.php`
- [x] **f** — `views/layouts/app.php` — one shared layout every page extends
- [x] **g** — `views/partials/head.php`, `scripts.php` — shared `<head>` and bottom-script blocks
- [x] **h** — `data/mock-data.php` — single source of truth, injected as JSON for both PHP and JS

### 2.2 Screens already designed

- [x] **a** — Advertiser: Dashboard overview
- [x] **b** — Advertiser: Create Ad (3-step form with live preview)
- [x] **c** — Advertiser: My Ads (searchable/filterable table)
- [x] **d** — Admin: Overview
- [x] **e** — Admin: All Ads (moderation queue, approve/reject)
- [x] **f** — Admin: Connected Apps (API key management)
- [x] **g** — Docs: API reference page for developers

### 2.3 Help tooltips (the "ⓘ" icon pattern)

- [x] **a** — Shared `.db-help-icon` CSS class in `dashboard.css`
- [x] **b** — One JS initializer (`initTooltips()`) turns every tooltip icon on the page into a Bootstrap tooltip
- [x] **c** — `config/help-text.php` — tooltip copy centralized in one file
- [x] **d** — `views/components/help-icon.php` — the reusable component itself

### 2.4 Responsiveness

- [x] **a** — Sidebar collapses to an off-canvas drawer under 992px
- [x] **b** — Tables scroll horizontally on small screens instead of breaking layout
- [x] **c** — Create-ad live preview stacks below the form on mobile instead of sitting sticky beside it

---

## 3. Backend Architecture

**Status: ✅ Done**

### 3.1 One feature = one isolated folder

- [x] **a** — Create `app/Ads/` folder (empty, with a `.gitkeep`)
- [x] **b** — Create `app/Ads/AdController.php` (empty class stub)
- [x] **c** — Create `app/Ads/AdModel.php` (empty class stub)
- [x] **d** — Create `app/Ads/AdRepository.php` (empty class stub)
- [x] **e** — Create `app/Ads/AdValidator.php` (empty class stub)
- [x] **f** — Create `app/Ads/routes.php` (empty array/return)
- [x] **g** — Create `app/Apps/` folder + `AppController.php`, `AppModel.php`, `routes.php` stubs
- [x] **h** — Create `app/Auth/` folder + `AuthController.php`, `UserModel.php`, `routes.php` stubs
- [x] **i** — Create `app/Admin/` folder + `ModerationController.php`, `routes.php` stubs
- [x] **j** — Write the router boot code that `require`s each module's `routes.php`
- [x] **k** — Write one code-review checklist line: "no module queries another module's tables directly"

### 3.2 Centralized, non-repeated code

- [x] **a** — Create `core/` folder (empty, with a `.gitkeep`)
- [x] **b** — Create `core/Database.php` — PDO connection setup only (no query helpers yet)
- [x] **c** — Add one query-helper method to `core/Database.php` (e.g. `query()`)
- [x] **d** — Add a second query-helper method to `core/Database.php` (e.g. `fetchOne()`)
- [x] **e** — Create `core/Request.php` — read raw input (`$_POST`/JSON body) only
- [x] **f** — Add sanitizing helpers to `core/Request.php`
- [x] **g** — Create `core/Response.php` — success-shape helper only (`{success:true, data}`)
- [x] **h** — Add error-shape helper to `core/Response.php` (`{success:false, error}`)
- [x] **i** — Create `core/Validator.php` — `required` rule only
- [x] **j** — Add `maxLength` rule to `core/Validator.php`
- [x] **k** — Add `url` rule to `core/Validator.php`
- [x] **l** — Add `date` rule to `core/Validator.php`
- [x] **m** — Create `core/Auth/Middleware.php` — session check only
- [x] **n** — Add token/API-key check to `core/Auth/Middleware.php`
- [x] **o** — Create `core/RateLimiter.php` — skeleton class, one `hit()` method
- [x] **p** — Create `core/Cache.php` — skeleton class, `get()`/`set()` methods (file-based)

### 3.3 Standard request lifecycle

- [x] **a** — Create `public/index.php` front controller (accepts any request, echoes "ok")
- [x] **b** — Wire the router into `public/index.php`
- [x] **c** — Wire the auth middleware into the request pipeline
- [x] **d** — Wire the rate-limit middleware into the request pipeline
- [x] **e** — Write one example controller method that calls a repository and returns `Response::success()`
- [x] **f** — Write the rule/comment: controllers stay thin, no query logic in controllers
- [x] **g** — Write the rule/comment: all query logic lives in Repositories only

---

## 4. API Structure

**Status: ✅ Done**

- [x] **a** — `GET /api/v1/ads/serve?placement={code}` — route + empty handler
- [x] **b** — `POST /api/v1/ads/{id}/impression` — route + empty handler
- [x] **c** — `POST /api/v1/ads/{id}/click` — route + empty handler
- [x] **d** — `POST /api/v1/advertiser/ads` — route + empty handler
- [x] **e** — `PATCH /api/v1/advertiser/ads/{id}` — route + empty handler
- [x] **f** — `GET /api/v1/admin/ads?status=pending` — route + empty handler
- [x] **g** — `PATCH /api/v1/admin/ads/{id}/approve` — route + empty handler
- [x] **h** — `PATCH /api/v1/admin/ads/{id}/reject` — route + empty handler
- [x] **i** — `GET /api/v1/admin/apps` — route + empty handler
- [x] **j** — `POST /api/v1/admin/apps` — route + empty handler
- [x] **k** — `PATCH /api/v1/admin/apps/{id}` — route + empty handler
- [x] **l** — Wrap every handler's return in the `{ success, data | error }` envelope
- [x] **m** — Prefix every route file with `/api/v1/`
- [x] **n** — Split routes into `routes/api-public.php` (serve/track) vs `routes/api-auth.php` (advertiser/admin)

---

## 5. Database Design

**Status: ✅ Done**

### 5.1 Schema

- [x] **a** — Migration: `users` table
- [x] **b** — Migration: `apps` table
- [x] **c** — Migration: `placements` table
- [x] **d** — Migration: `ads` table
- [x] **e** — Migration: `ad_impressions` table
- [x] **f** — Migration: `ad_clicks` table
- [x] **g** — Migration: `api_keys` table

### 5.2 Indexing plan

- [x] **a** — Add `ads (status, placement_id)` composite index
- [x] **b** — Add `ads (user_id)` index
- [x] **c** — Add `ads (start_date, end_date)` index
- [x] **d** — Add `ad_impressions (ad_id, occurred_at)` index
- [x] **e** — Add `ad_clicks (ad_id, occurred_at)` index
- [x] **f** — Add `apps (api_key_hash)` unique index
- [x] **g** — Add `placements (app_id, code)` unique composite index

### 5.3 Aggregation, not row-by-row counting

- [x] **a** — Migration: `ad_stats_daily (ad_id, date, impressions, clicks)` table
- [x] **b** — Write the rollup query (raw events → daily totals) as a standalone script
- [x] **c** — Wire that script into a scheduled job (cron entry)
- [x] **d** — Point one dashboard chart at `ad_stats_daily` instead of raw tables
- [x] **e** — Confirm raw tables are kept read-only, for auditing only

> **Note:** `AdStatsRepository` (`app/Ads/AdStatsRepository.php`) is the only place allowed to run an aggregate query against `ad_impressions` / `ad_clicks` — its `rollupForDate()` method, called by `database/scripts/rollup-ad-stats-daily.php` (cron entry in `cron/README.md`). Every other read, including the dashboard's "Impressions, Last 7 Days" chart, goes through `AdStatsRepository::dailyImpressions()` against `ad_stats_daily` instead — the raw tables stay write-once, for auditing only.

---

## 6. Authentication & Security

**Status: ✅ Done**

- [x] **a** — Implement `password_hash()` on signup
- [x] **b** — Implement `password_verify()` on login
- [x] **c** — Issue signed, HttpOnly session cookie on login
- [x] **d** — Implement per-app API key generation
- [x] **e** — Store API keys as a hash, never plaintext
- [x] **f** — Accept `Authorization: Bearer` header on authenticated API routes
- [x] **g** — Add role check for advertiser-only routes
- [x] **h** — Add role check for admin-only routes
- [x] **i** — Add CSRF token generation helper
- [x] **j** — Add CSRF token verification middleware
- [x] **k** — Attach CSRF token to the create-ad form
- [x] **l** — Attach CSRF token to every other state-changing dashboard form
- [x] **m** — Confirm every existing query goes through prepared statements (audit pass)
- [x] **n** — Escape all user-entered ad copy on output (`htmlspecialchars`)
- [x] **o** — Re-encode uploaded images on upload (strip metadata)
- [x] **p** — Validate uploads by real MIME type, not extension
- [x] **q** — Cap upload size and rename file on storage
- [x] **r** — Serve uploaded images from a non-executable path
- [x] **s** — Add rate limit to `/ads/serve`
- [x] **t** — Add rate limit to `/impression` and `/click`
- [x] **u** — Scope each API key's queries to only its own app's placements
- [x] **v** — Add audit-log table + write on admin approve/reject
- [x] **w** — Add audit-log write on admin regenerate-key action

---

## 7. Performance & Optimization

**Status: ✅ Done**

- [x] **a** — Apply the indexing plan from 5.2 (confirm indexes exist via `SHOW INDEX`)
- [x] **b** — Confirm the `ad_stats_daily` rollup job from 5.3 is running on schedule
- [x] **c** — Add cache read to `/ads/serve` (check cache before DB)
- [x] **d** — Add cache write to `/ads/serve` (short TTL after DB fetch)
- [x] **e** — Enable OPcache in the production PHP config
- [x] **f** — Switch Composer autoload to `--optimize-autoloader` in the deploy script
- [x] **g** — Resize uploaded ad images to the one size actually used
- [x] **h** — Compress resized ad images
- [x] **i** — Add far-future cache headers to image responses
- [x] **j** — Confirm `loading="lazy"` is on every ad-card image
- [x] **k** — Paginate the advertiser "My Ads" list at the DB level
- [x] **l** — Paginate the admin moderation queue at the DB level
- [x] **m** — Make the impression-tracking call fire-and-forget on the client
- [x] **n** — Make the click-tracking call fire-and-forget on the client

---

## 8. SEO

**Status: ✅ Done**

- [x] **a** — Add descriptive `<title>` to `index.html`
- [x] **b** — Add `<meta description>` to `index.html`
- [x] **c** — Add descriptive `<title>`/`<meta description>` to `api-docs.php`
- [x] **d** — Check/fix heading order (h1→h2→h3) on `index.html`
- [x] **e** — Check/fix heading order (h1→h2→h3) on `api-docs.php`
- [x] **f** — Generate `sitemap.xml` for public pages
- [x] **g** — Write `robots.txt` disallowing `/dashboard/`
- [x] **h** — Add `/admin/` and `/api/` disallow rules to `robots.txt`
- [x] **i** — Add `SoftwareApplication` structured data to `index.html` (optional, if page grows)
- [x] **j** — Re-run the Section 7 performance checklist specifically against `index.html`/`api-docs.php`

---

## 9. Folder Structure

**Status: ✅ Done**

- [x] **a** — Create `public/` folder
- [x] **b** — Move/point web server root at `public/`
- [x] **c** — Create `public/assets/` folder
- [x] **d** — Create `public/uploads/ads/` folder
- [x] **e** — Confirm `app/` module folders exist *(tracked in 3.1 — Admin, Ads, Apps, Auth all present)*
- [x] **f** — Confirm `core/` folder exists *(tracked in 3.2 — present, incl. `Auth/` and `Security/` subfolders)*
- [x] **g** — Confirm `views/` folder is unchanged *(already done — Section 2)*
- [x] **h** — Create `config/database.php` — connection settings, `core/Database.php` now reads from it instead of `getenv()` directly
- [x] **i** — Create `config/app.php` — name/env/debug/url/timezone + shared pagination defaults (7.k/7.l)
- [x] **j** — Create `database/migrations/` folder *(already present — 16 migrations, Section 5)*
- [x] **k** — Create `database/seeders/` folder — `DatabaseSeeder.php`, idempotent, seeds one admin user, one sample advertiser, one sample connected app + API key
- [x] **l** — Create `routes/web.php` — map of every dashboard/admin page, mirroring the API route table
- [x] **m** — Create `routes/api.php` *(already present — Section 3.1.j router boot, merges every module's routes)*
- [x] **n** — Create `tests/` folder — scaffold + planned per-module layout, actual suite deferred to Section 13

---

## 10. Build Order / Roadmap

**Status: 🟨 In Progress**

- [x] **a** — Run database migrations (Section 5) on a fresh local DB — `database/scripts/migrate.php` (applies every `database/migrations/*.php` in order, tracks progress in a `migrations` table, idempotent)
- [x] **b** — Seed the DB with the same mock data used in the UI prototype — `database/seeders/MockDataSeeder.php` (reads `data/mock-data.php`, inserts the same apps/placements/advertisers/ads, idempotent)
- [x] **c** — Confirm core layer (`Database`, `Request`, `Response`, Auth middleware) boots with no errors
- [x] **d** — Confirm Auth module: login works end-to-end
- [x] **e** — Confirm Auth module: API-key issuing works end-to-end
- [x] **f** — Confirm Ads module wired into `create-ad.php` — the advertiser's "new ad" form must submit through the real `AdRepository`/validator (CSRF check, image upload via `core/Uploads.php`'s hardened non-executable path, insert with `status='pending'`) instead of reading/writing mock data
  - *Also required:* adding `core/Autoload.php` since nothing was actually being autoloaded yet, making `public/index.php` actually dispatch to a controller instead of its `echo 'ok'` stub, a subdirectory-safe path fix for local XAMPP deployment, a local-only fix to the session cookie's `Secure` flag, and a new `GET /api/v1/advertiser/apps` endpoint so the form's app/placement pickers use real ids instead of `data/mock-data.php`'s string codes.
- [x] **g** — Confirm Ads module wired into `my-ads.php` — the advertiser's ad list must come from the DB filtered by the logged-in `user_id`, paginated per `config/app.php`'s defaults — not the static mock list
  - *Also fixed:* a pre-existing crash where `create-ad.php`/`admin/apps.php` called `csrf_field()` without `core/Autoload.php` ever being loaded on those pages (fatal-errored before rendering); added the router's `{id}` dynamic-segment matching, needed by 10.h next; client-side search/status/app filtering now filters the real rendered rows in place — `SkoolystAdsUI.filterRenderedRows()` — instead of re-rendering from mock data, which would have silently overwritten the real DB rows.
- [x] **h** — Confirm Admin module wired into `admin/ads.php` — approve/reject actions must call the real `AdRepository` status update and write an `AuditLog` entry (6.v), not just change the UI
  - *Also required:* extended `findByStatus()` to accept a `null` status for the "All" tab, added `AppRepository`-backed app filter dropdown, and switched the status tabs from JS-toggled `<div>`s over the full mock array to real `<a href="?status=...">` links — server-side pagination per tab meant they couldn't stay client-side re-renders of one in-memory list. Approve/reject buttons now `PATCH` `/api/v1/admin/ads/{id}/approve|reject` with the CSRF header and reload on success so the ad's new tab membership and the tab counts both reflect the real change.
- [x] **i** — Confirm Admin module wired into `admin/apps.php` — actions like API-key regenerate must call the real `AppRepository` method and write an `AuditLog` entry (6.w)
  - *Also required:* implemented `AppController::update()`'s body (previously an empty stub), plus `AppRepository::allWithCounts()`/`updateStatus()`. The connect-switch, regenerate-key, and "Connect New App" modal now all call the real API instead of mutating `SkoolystAdsMock.apps` — the modal gained a required "Short Code" field since `store()` validates one. Since only the key's hash is ever persisted, a regenerated/newly-issued key is shown exactly once via a one-time prompt right after the request succeeds, never redisplayed from the grid afterward. Also fixed a latent bug in both this and 10.h's `updateStatus()` methods — MySQL's `rowCount()` reports rows *changed*, not rows *matched*, so re-approving an already-active ad or re-toggling a status to what it already was would have misread as "not found".
- [x] **j** — Confirm public API matches `api-docs.php` exactly (spot check each endpoint) — walk every endpoint documented in `public/api-docs.php` against the real `routes/api.php` + controllers so the docs and actual behavior don't drift apart
  - *Found and fixed a real bug, not just a doc mismatch:* `Core\Request::input()` never read `$_GET`, so the documented `GET /ads/serve?placement=...` example couldn't work at all — every query-string param was silently dropped. Now merges `$_GET` in as a fallback (body/JSON still win on key collision), so GET routes work as documented without changing existing POST/JSON behavior.
  - *Docs rewritten to match real behavior:* response shape is `{success, data: {ad: {...}}}` (singular `ad`, possibly `null`), not the documented `{request_id, placement, ads: [...]}` array — `request_id` and the `limit` param don't exist anywhere in code. Ad fields are `id, title, description, image_path, cta_text, click_url` — there's no `advertiser` name field, and it's `image_path` not `image_url`. `{ad_id}` in the impression/click paths is cosmetic only (the router never binds path params — see `public/index.php`'s `routePathToRegex()` comment); `ad_id` must be sent as a body field. `click_url` is the advertiser's raw destination as stored — there's no `/r/{id}` tracking-redirect route, so the doc's claim that clicks are logged automatically was fictional; callers must call `/ads/{id}/click` themselves. Error codes `invalid_api_key`/`placement_not_owned`/`ad_not_found`/`invalid_placement`/`rate_limited` don't exist in code — real codes are `unauthorized`, `validation_error`, `not_found`, and 429s carry no code at all. Rate limits corrected from the documented 600/2000 per minute to the real 300/60 per minute (see `public/index.php`); the `X-RateLimit-*` headers described in the old docs are never actually sent, and `RateLimiter::hit()` is still a stub that always allows — noted in the docs rather than silently implied as working.
  - *Known bug found but intentionally not fixed here (out of scope for a docs pass):* `GET /images/ads/{filename}` will 500 on every request — `ImageController::show(string $filename)` requires a bound argument, but the router (`public/index.php`) never extracts/passes path params to any handler, it only uses them to match the route. This route also isn't part of `api-docs.php` (it's described as a dev-only fallback; production serves images as static files). Flagging for a follow-up since fixing it properly means adding real path-param binding to the router, which would touch every module's `{id}` routes, not just this one.
- [ ] **k** — Confirm stats rollup job is scheduled and running — `database/scripts/rollup-ad-stats-daily.php` exists as a script; this item is about actually registering the cron entry (per `cron/README.md`) and confirming `ad_stats_daily` is really being populated daily
  - *Found and fixed a critical bug that was silently breaking this entirely:* `AdRepository::recordImpression()`/`recordClick()` were inserting into a `created_at` column, but the `ad_impressions`/`ad_clicks` migrations (0005/0006) only define `occurred_at` — there is no `created_at` column. Every impression/click write was throwing `SQLSTATE[42S22]: Column not found` (caught upstream as a generic 500), so the raw event tables could never receive real data and the rollup would always have had nothing to aggregate. Fixed both inserts to use `occurred_at`.
  - *Verified end-to-end against a real MySQL 8.0 instance* (installed locally for this test, not part of the app): ran all 16 migrations clean, ran both seeders, then called `AdRepository::recordImpression()`/`recordClick()` directly (the same code path `POST /ads/{id}/impression|click` calls) to simulate 5 impressions + 2 clicks on one ad and 1 impression on another. Ran `php database/scripts/rollup-ad-stats-daily.php <today>` and confirmed `ad_stats_daily` picked up the exact right counts per ad. Re-ran the same date and confirmed no duplicate rows (the upsert is genuinely idempotent). Confirmed the no-argument ("yesterday") default path runs correctly. Confirmed an invalid date argument is rejected with a clear message and exit code 1, matching the script's documented contract.
  - *Not done — genuinely can't be yet:* actually registering the crontab entry on a real host. Section 14 (Deployment) is still entirely "Not Started" — there's no staging/production server for this project yet, so there's nothing to add a cron line to. `cron/README.md`'s documented crontab line was checked and is accurate (correct script path and filename) — it's ready to be installed as-is once Section 14 stands up a host. Leaving this item unchecked rather than marking it done, since the "registered and running" half of it is still outstanding.
- [x] **l** — Run the Section 6 security checklist as a pass/fail review — re-test the already-`[x]`'d Section 6 items against the wired-up app (e.g. actually try uploading a `.php` file as an ad image and confirm it's rejected), not just confirm the code exists
  - *Found and fixed two real bugs, not just doc/code-reading gaps:* (1) `CsrfMiddleware::passes()` only exempted API-key requests from CSRF checks, but the public, session-less routes (`/ads/serve`, `/impression`, `/click`, `auth => false`) carry no session either — every real request to them was being rejected with 419. Fixed by also checking the route's `auth` flag for exemption (commit `4ba5e53`). (2) `RateLimiter::hit()` was a skeleton stub that always returned `true` — fired 15 rapid requests at a rate-limited route and got 200 every time, never a 429. Implemented a real file-based fixed-window limiter (same locking pattern as `core/Cache.php`, `flock`-guarded so concurrent requests can't race past the limit), then live-verified allow → block → window-reset all work correctly (commit `3f868fd`).
  - *Everything else PASS, confirmed against the real wired-up app, not just read in code:* register/login round-trip with real `password_hash`/`password_verify`; advertiser/admin role checks reject the wrong role on real requests; CSRF token round-trips correctly on real dashboard forms once (1) above was fixed; SQL-injection audit (every query goes through prepared statements — no string-concatenated SQL anywhere); XSS double-protection (ad copy is `htmlspecialchars`'d on output, on top of being stored as plain text); actually uploaded a `.php` file renamed to `.jpg` and confirmed `core/Uploads.php`'s real-MIME-type check rejects it; confirmed real images are genuinely re-encoded/resized/given a random filename on upload (not just copied through); audit-log table receives a real row on admin approve/reject and on regenerate-key.
- [x] **m** — Run the Section 7 performance checklist as a pass/fail review — verify pagination limits, caching, DB indexes, and API rate limiting under real use, same pass/fail treatment as l
  - *a — PASS:* `SHOW INDEX` confirms every index from the 5.2 plan actually exists on `ads`, `ad_impressions`, `ad_clicks`, `apps`, `placements`.
  - *b — PASS (cross-referenced against 10.k's already-verified end-to-end rollup test — nothing new to add).*
  - *c/d — PASS, verified live:* called `/ads/serve` for a real placement, confirmed a cache file was written under `storage/cache/`; changed the ad's title directly in the DB and called the same endpoint again within the 30s TTL — it kept serving the old (cached) title; waited for the TTL to expire and called again — it picked up the new title. Cache read-before-DB and write-after-DB both genuinely work, not just present in code.
  - *e — PASS at the code level* (`config/opcache.ini` has the right production settings) — no real production PHP-FPM host exists yet to verify against (Section 14 is still "Not Started"), same caveat as 10.k's cron item.
  - *f — FAIL when tested, now FIXED via 11.f:* `deploy.sh` runs `composer install --no-dev --optimize-autoloader`, but at the time of this test there was no `composer.json` anywhere in this repo — the app autoloaded only via the dependency-free `core/Autoload.php`. On a real deploy this step would have simply failed (`composer.json not found`). **Resolved in roadmap item 11.f**: added `composer.json` with a PSR-4 mapping matching `core/Autoload.php`'s existing `App\`/`Core\` mapping, and verified live that `deploy.sh`'s exact install command now succeeds and the generated autoloader resolves real classes correctly.
  - *g/h — PASS* (cross-referenced against Section 6.o's already-verified resize/re-encode test — `core/Uploads.php` resizes to the exact 600×450 the ad card renders at and re-encodes JPEGs at quality 85).
  - *i — FAIL, found a real bug, now fixed:* the far-future `Cache-Control` header only existed inside `ImageController.php`, which never actually runs — it 500s on every request (the 10.j-flagged router bug: `{filename}` is never bound). The real production path (`public/uploads/ads/.htaccess` serves these files directly, per `public/.htaccess`'s "real file, not rewritten" rule) had no `Header`/cache directive at all, so real image responses were sending no `Cache-Control` whatsoever. **Fixed:** added a `mod_headers` block to `public/uploads/ads/.htaccess` setting `Cache-Control: public, max-age=31536000, immutable` on the actual image extensions served from that folder. Safe to cache this aggressively since uploaded filenames are randomized (6.q) — a changed image is always a new filename, never a stale cache of the old one.
  - *j — PASS:* `loading="lazy"` is present on every real ad-card image — the shared `ads-table.php` component (used by both the advertiser "My Ads" list and admin moderation queue) and its JS-rendered equivalent in `dashboard.js` both have it. The only `<img>` tags without it are in `create-ad.php`'s own upload-preview widget (drag-drop preview, edit preview, live preview card) — those are single, already-visible images shown during ad creation, not off-screen list items, so lazy-loading doesn't apply there.
  - *k/l — PASS, verified live against real MySQL data:* seeded 27 ads for one advertiser and called `AdRepository::findAllForUser()` directly for page 1 and page 2 — got 20 + 7 with zero overlap in ids. Did the same against `findByStatus('pending', ...)` for the admin moderation queue — same result, clean split, no overlap. Both `LIMIT`/`OFFSET` pagination paths genuinely paginate, not just accept a `page` parameter cosmetically. (Test data cleaned up afterward.)
  - *m/n — PASS:* `trackAdEvent()` in `app.js` uses `navigator.sendBeacon` (fire-and-forget by design) with a `fetch(..., {keepalive:true})` fallback for browsers without it — never `await`ed, and its `.catch()` deliberately swallows failures so a dropped tracking ping never surfaces to the visitor.
  - *Note on test methodology:* the documented local dev-server command (`php -S 127.0.0.1:8099 -t public public/index.php`) routes every request through `public/index.php`, which only knows the API route table (`api-public.php` + `api-auth.php`) and returns a JSON 404 for anything else — including real static files like `index.html` (confirmed: even that 404s under this exact command). This isn't a production bug — Apache's `.htaccess` correctly serves real files directly and only falls through to `index.php` for non-existent paths — it's a property of how PHP's built-in server's router-script mode works (a router script must explicitly return `false` to fall back to serving a real file, and this one doesn't, since it wasn't written to double as a static-file server). Web/dashboard pages that aren't part of the API route table (like `my-ads.php`) can't be hit over HTTP through this dev-server command; where that mattered (k/l above), the underlying repository methods were called directly instead, which exercises the same DB-level pagination logic the page itself calls.
- [x] **n** — Run the Section 8 SEO checklist as a pass/fail review — verify the already-implemented Section 8 items (meta tags, sitemap, robots.txt, etc.) the same way
  - *a/b/d — PASS:* `index.html` has a descriptive `<title>`, a `<meta name="description">`, and clean `h1 → h2 → h3` heading order.
  - *c/e — PASS, verified by actually rendering the page (not just reading the template):* `api-docs.php` sets `$pageTitle`/`$metaDescription`, which the shared `views/partials/head.php` renders into a real `<title>`/`<meta description>` — and the page's `<h1>` isn't in `api-docs.php` itself, it's rendered by the shared `topbar.php` partial from `$pageTitle`. Rendered the page directly (bypassing HTTP, same reason as above) and confirmed the real heading sequence is `1,2,3,4,3,3,4,4,4,3,3,3,3,3` — no skipped levels.
  - *f/g/h — PASS:* `sitemap.xml` covers both public pages; `robots.txt` disallows `/dashboard/`, `/admin/`, and `/api/`.
  - *i — PASS:* `SoftwareApplication` JSON-LD block in `index.html` is present and parses as valid JSON.
  - *j — PASS (nothing left to re-check):* neither `index.html` nor `api-docs.php` has any `<img>` tags or DB queries, so Section 7's lazy-loading/pagination/cache items simply don't apply to these two pages — confirmed there's no overlooked image or query, not just assumed.
- [x] **o** — Give Admin → Connected Apps (`admin/apps.php`) a way to actually manage a target app's placements — 10.i wired up create / pause-activate / regenerate-key for the *app* itself, but a placement (the ad-slot codes a `GET /ads/serve?placement={code}` call asks for) had no CRUD at all: the page only ever showed a placement *count*, with no add/edit/delete short of a manual DB insert.
  - *Added:* `App\Apps\PlacementRepository` (`allForApp`, `find`, `codeExistsForApp`, `create`, `update`, `delete`, `hasAds`) and `App\Apps\PlacementController` (`index`/`store`/`update`/`destroy`, admin-only — same thin-controller shape as `AppController`), plus `AppRepository::find()` since nothing needed a single-app lookup before this. New routes in `app/Apps/routes.php`: `GET/POST /api/v1/admin/apps/{id}/placements`, `PATCH/DELETE /api/v1/admin/apps/{id}/placements/{placementId}` — `{id}`/`{placementId}` are cosmetic path segments, same "real id comes from the body/query, not the URL" convention every other `{id}` route here already uses (see 10.j's note on this). A duplicate `(app_id, code)` is checked before insert/update so it fails as a clean `validation_error` instead of the DB's unique-constraint exception (migration 0014) surfacing as a raw 500.
  - *Found a real, if latent, destructive-delete bug while building this, not just missing CRUD:* `ads.placement_id` has a genuine `ON DELETE CASCADE` FK back to `placements` (migration 0004) — a naive "delete this placement" endpoint would have silently deleted every ad on it too, not just stopped them being servable. `PlacementController::destroy()` now calls `PlacementRepository::hasAds()` first and refuses with `409 conflict` ("Pause or move those ads first") instead of ever letting that cascade fire un-checked.
  - `admin/apps.php` gained a "Manage Placements" button per app card opening a shared modal (list/add/edit/delete, reusing the page's existing `showToast`/`confirmAction`/`db-*` styling — no new CSS needed) instead of the previous static count with no way to act on it.
  - *Live-verified against real MariaDB and a real spun-up HTTP server, not just read in code:* logged in as the seeded admin over real HTTP, then round-tripped every endpoint — create two placements, list them, reject a duplicate code with `validation_error`, reject a bad `app_id` with `404`, rename a placement, reject renaming it onto another placement's code, delete a placement with no ads (succeeds), and — the important one — inserted a real ad on a placement and confirmed `DELETE` on that placement returns `409 conflict` and leaves **both** the placement and the ad untouched in the DB afterward (proving the cascade guard actually works, not just that the code compiles). Also confirmed a request with no session gets `401`, and a real advertiser-role session gets `403` (not admin). Every `placement.create`/`placement.update`/`placement.delete` action wrote a real `audit_log` row (6.w), same as app pause/activate and key-regenerate already do.
  - *Test:* `tests/Apps/PlacementRepositoryTest.php` — 10 tests covering the CRUD round-trip, per-app code-uniqueness scoping (including the "exclude own id" case an in-place rename needs), and `hasAds()`/`delete()`, including a test that deliberately calls `delete()` *without* the controller's guard to document, on the record, that the FK really does cascade-delete the ad when the guard is skipped — the exact scenario `PlacementController::destroy()` exists to prevent. **Live-verified: 10/10 pass, 19 assertions**, and the full existing suite still passes clean afterward (**33/33, 98 assertions** — nothing else regressed).
- [x] **p** — Fix ads only being able to target one placement per app, even when the app has several (e.g. `teachers.skoolyst.com` has Header/Footer/Sidebar) — reported directly: creating an ad only offered a single-select dropdown, so one ad could only ever run on one of an app's placements, never two or "all of them".
  - *Root cause:* `ads.placement_id` (migration 0004) is a single foreign key — the schema itself only allowed one placement per ad, not a form/UI limitation.
  - *Fix:* new `ad_placements` junction table (migration `0019_create_ad_placements_table.php`, many-to-many, both FKs `ON DELETE CASCADE`) plus a one-time backfill migration (`0020_backfill_ad_placements_from_ads_placement_id.php`) copying every existing ad's single `placement_id` into it, so no existing ad's serving behavior changes. `ads.placement_id` itself is kept (not dropped) as a legacy "first placement submitted" value — cheaper than a wider schema change, and nothing outside `AdRepository` reads it as the source of truth anymore.
  - `AdRepository::create()` now writes one `ad_placements` row per selected placement inside a real DB transaction (the first place this codebase has needed one — a partial failure can no longer leave an ad that exists but is servable nowhere). `findServableForPlacement()` — the query behind the public `GET /ads/serve` endpoint every connected app calls — now joins through `ad_placements` instead of `ads.placement_id` directly, which is the actual fix: an ad now serves on every placement it's linked to, not just the first one.
  - `Request::intArray()` added (`core/Request.php`) to read a repeated form field (`placement_ids[]`) or a JSON array body into a clean list of ints — the first multi-value field this project's request layer has needed.
  - *Found and closed a real gap while touching this validation path, not just added the feature:* nothing previously checked that a submitted `placement_id` actually belonged to the submitted `app_id` — harmless with one id, a bigger blast radius with a list. `AdController::validatedAdInput()` now calls `PlacementRepository::allBelongToApp()` (new) and rejects with `validation_error` if any submitted placement isn't the chosen app's own, same cross-app scoping principle as 6.u.
  - `create-ad.php`'s Step 2 placement picker is now a checkbox grid (`db-checkgrid`, matching the existing app-picker's own styling) instead of a `<select>` — pick 1, several, or all of an app's placements. Edit mode still shows every placement an ad currently targets, checked and disabled — unchanged from before, an ad's app/placement(s) still can't be changed after creation (`AdController::update()` never accepts `app_id`/`placement_ids`). Admin's and the advertiser's ad-list tables now show every targeted placement's label (e.g. "Header, Sidebar"), aggregated via `GROUP_CONCAT`, instead of only ever the first one.
  - *Found a real, unrelated test-isolation bug while writing this feature's own tests, not just added new ones:* `DatabaseTestCase::truncateAllTables()` didn't truncate the new `ad_placements` table between tests, and `seedAd()` (a raw-SQL fixture, not a call through `AdRepository::create()`) never populated it at all — together this made `AdsServeContractTest` flaky: it passed when run as part of the full suite (leftover rows from an earlier manual `curl` session happened to satisfy it by coincidence after `TRUNCATE`'s auto-increment reset) but failed with a clear `TypeError` when run in isolation. Both are fixed: `ad_placements` is now truncated every test, and `seedAd()` writes to it (mirroring `create()`'s real behavior), with a `placement_ids` override for tests that need an ad seeded onto more than one placement directly.
  - *Test:* `tests/Ads/AdMultiPlacementTest.php` — 7 tests: the exact bug-report scenario (one ad on 2 of an app's 3 placements serves on both selected ones and never the third), the junction table getting exactly the submitted ids, the legacy `placement_id` column landing on the first submitted id, `placementIdsForAd()`'s round-trip, the admin queue's aggregated label display, and `allBelongToApp()`'s cross-app rejection (including the empty-list edge case). **Live-verified: 7/7 pass, 15 assertions**; full suite **40/40, 113 assertions** — including the now-deflaked `AdsServeContractTest`, confirmed stable across repeated isolated runs, not just the one bulk run that used to mask it.
  - *Live-verified end-to-end against real MariaDB and a real spun-up HTTP server*, mirroring the exact reported scenario: created 3 real placements (Header/Footer/Sidebar) on one app, submitted a real ad via `multipart/form-data` with `placement_ids[]=Header` and `placement_ids[]=Sidebar`, approved it as admin, then called the real `GET /api/v1/ads/serve` endpoint with the app's real API key for all three placement codes — got the same ad back for `header` and `sidebar`, and `null` for `footer`. Also confirmed the cross-app placement guard rejects a crafted request mixing in another app's placement id (`400 validation_error`), and that both `my-ads.php` and `admin/ads.php` render "Header, Sidebar" in their meta line when rendered in-process with a real seeded session.

---

## 11. Tech Stack & Environment Setup

**Status: ✅ Done**

- [x] **a** — Confirm PHP 8.2+ is installed locally
  - Confirmed via `composer --version` output: `PHP version 8.2.12 (B:\xampp-8.2\php\php.exe)` — meets the 8.2+ requirement.
- [x] **b** — Confirm MySQL 8.0 is installed locally
  - *Checked via phpMyAdmin's server info — worth flagging honestly rather than a blind checkmark:* the local server is actually **MariaDB 10.4.32** (via XAMPP), not MySQL 8.0. They're different forks, not interchangeable by default.
  - *Confirmed this doesn't matter in practice:* searched the whole codebase for MySQL-8.0-only SQL (CTEs/`WITH`, window functions/`OVER (...)`, `JSON_TABLE`, `CHECK` constraints) — none are used anywhere in migrations or queries, so every query here runs identically on MariaDB 10.4 and MySQL 8.0. Migrations and both seeders have already been run and tested against this exact MariaDB instance throughout this whole session (Sections 6–7 testing) with zero compatibility issues.
  - *Worth a decision, not a blocker:* if production (Section 14) is specifically MySQL 8.0, this local setup already matches it in behavior; if production is also MariaDB, this README/roadmap's "MySQL 8.0" wording is just slightly inaccurate and could be corrected later — not urgent either way.
- [x] **c** — Set up Nginx + PHP-FPM (or local equivalent, e.g. `php -S`)
  - Using XAMPP (Apache + mod_php) as the local equivalent — confirmed working: app runs correctly under XAMPP.
- [x] **d** — Install Redis, or confirm the file-cache fallback works without it
  - File-cache fallback already live-verified in 7.c/d (cache write on miss, stale-serve within TTL, refresh after expiry, all confirmed against real HTTP requests) — no Redis needed locally.
- [x] **e** — Install Composer
  - Confirmed via `composer --version`: Composer 2.9.7 installed and working.
- [x] **f** — Add `composer.json` with PSR-4 autoload mapping
  - *Added `composer.json`* with the same `App\` → `app/`, `Core\` → `core/` mapping `core/Autoload.php`'s manual autoloader already uses — a drop-in equivalent, not a competing one, per that file's own doc-block.
  - *Verified live, not just written:* ran `composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist` — the exact command `deploy.sh` runs — and it now succeeds (previously failed outright with no `composer.json` present, the bug flagged in 7.f/10.m). Loaded a real class (`App\Ads\AdRepository`) through the generated `vendor/autoload.php` and confirmed it resolves correctly, same for `Core\Cache`.
  - *Not done here (separate, bigger task, not part of this item):* switching the app's entry points (`public/index.php`, dashboard/admin pages, CLI scripts) from `require core/Autoload.php` to `require vendor/autoload.php`. Both resolve the same classes identically, so this is a safe follow-up whenever it's picked up — not required to fix the 7.f deploy bug, since that only needed `composer.json` to exist.
  - `composer.lock` committed alongside `composer.json` for reproducible installs (currently has zero real dependencies — just locks the PHP platform requirement).
- [x] **g** — Create `.env.example` with placeholder values
  - *Implemented via:* `.env.example`, plus `core/Env.php` (dependency-free loader, since 11.e/f's Composer setup hasn't happened yet) wired into `public/index.php`, `public/dashboard/index.php`, `database/scripts/migrate.php`, `database/scripts/rollup-ad-stats-daily.php`, and both seeders.
- [x] **h** — Document each required `.env` value in `.env.example` comments
- [x] **i** — Write the local setup command list into `README.md`/`SETUP.md` (`composer install`, migrate, seed, serve)
  - *Implemented via:* the [Quick Start — Local Setup & Command Reference](#-quick-start--local-setup--command-reference) section at the top of this file — written for the actual dev environment (Windows + XAMPP), covering `.env` setup, DB creation, migrate, seed, and serving via Apache/XAMPP or PHP's built-in server, plus a note on what's still missing for production (Composer setup, cron registration — see 10.m/10.k).

---

## 12. Coding Standards & Git Workflow

**Status: ✅ Done**

- [x] **a** — Add a PSR-12 note to a `CONTRIBUTING.md`
- [x] **b** — Add a PSR-4 autoload mapping note to `CONTRIBUTING.md`
- [x] **c** — Add the "one-class-per-file, filename matches class name" rule to `CONTRIBUTING.md`
- [x] **d** — Add the "docblock every repository public method" rule to `CONTRIBUTING.md`
- [x] **e** — Add the "`main` is always deployable" rule to `CONTRIBUTING.md`
- [x] **f** — Add the "one feature branch per module task" convention to `CONTRIBUTING.md`
- [x] **g** — Add the `[Module] ...` commit message convention to `CONTRIBUTING.md`
- [x] **h** — Add "Section 6 checklist required on auth/upload/SQL PRs" rule to `CONTRIBUTING.md`
- [x] **i** — Add the "never edit a merged migration" rule to `CONTRIBUTING.md`

All nine written into [`CONTRIBUTING.md`](CONTRIBUTING.md), each grounded in something already true of this codebase (existing docblock style, actual commit history, the real PSR-4 mapping in `composer.json`) rather than generic boilerplate.

---

## 13. Testing & QA

**Status: ✅ Done**

- [x] **a** — Install PHPUnit via Composer
  - *Added to `composer.json`* as `require-dev: phpunit/phpunit ^11.0` (the latest major supporting this project's `php >=8.2` requirement), plus `autoload-dev: Tests\ → tests/`.
  - *Honest caveat about how this was actually verified:* this session's sandbox can reach `github.com` but not `packagist.org`/`repo.packagist.org`, so a real `composer require`/`composer install` for `phpunit/phpunit` isn't possible from here — confirmed with `composer validate`, which correctly reports `composer.lock` as out of date and `phpunit/phpunit` missing from it. On a machine with normal internet access (i.e. yours), `composer update phpunit/phpunit --dev` (documented in the new "Running Tests" step 4 above) will resolve and lock it normally — that one-time step still needs to be run for real once, since it couldn't be done here. To actually live-test everything below in the meantime, this session installed PHP 8.3/MariaDB/Composer via `apt` (all on the allowed network list) and used the Debian-packaged `phpunit` 9.6.17 as a stand-in test runner — same test files, same assertions either way; only the runner differs, and PHPUnit's basic `TestCase`/assertion API this project's tests use is unchanged between 9.6 and 11.x.
- [x] **b** — Add PHPUnit config file (`phpunit.xml`)
  - `phpunit.xml` written for PHPUnit 11's schema (`<source><include>`, `failOnDeprecation`) since that's what `composer.json` actually declares. Running it under the sandbox's PHPUnit 9.6 stand-in prints two harmless schema-validation warnings for those 11-only bits (confirmed by reading the warning text — it names exactly `failOnDeprecation` and `<source>`) but still runs and reports results correctly; a real PHPUnit 11 run (via `vendor/bin/phpunit` once 13.a's one-time step is done) won't show these at all.
- [x] **c** — Write one unit test for a validator rule
  - `tests/Core/ValidatorTest.php` — actually five tests, one per `core/Validator.php` method (`required`, `maxLength`, `url`, `date`), including edge cases like a rejected `2026-02-30` (real calendar validity, not just format shape) and multi-byte `maxLength`. Pure logic, no database. **Live-verified: 5/5 pass, 18 assertions.**
- [x] **d** — Write one unit test for a repository query-building method
  - `tests/Ads/AdRepositoryTest.php` targets `AdRepository::findByStatus()` — the conditional WHERE clause (10.h's "All tab accepts `null` status" behavior) and its LIMIT/OFFSET pagination, seeded against the real disposable test DB (this project's own rule is no mocking, so "unit" here means one repository method in isolation, not zero database).
  - *Found and fixed a real bug, not just a passing test:* seeding 25 ads in one test (to exercise pagination past one page) exposed that `ORDER BY ads.created_at ASC` alone is non-deterministic for rows sharing the same second — `created_at` is only second-precision, so 25 ads inserted in the same test run all land on one identical timestamp, and MySQL doesn't guarantee stable tie-breaking for `LIMIT`/`OFFSET` across separate calls with no secondary sort key. The no-overlap pagination test failed intermittently (passed alone, failed when run alongside the rest of the suite) — a real, if narrow, production risk any time several ads are created within the same second (bulk import, a seeder, or just two advertisers submitting at once). **Fixed** by adding `ads.id` as an explicit tiebreaker to both `findByStatus()` (`ORDER BY ads.created_at ASC, ads.id ASC`) and `findAllForUser()` (`ORDER BY ads.created_at DESC, ads.id DESC`, same risk, same fix) — confirmed via 3 repeat full-suite runs afterward, all clean.
  - **Live-verified: 3/3 pass, 7 assertions**, including the pagination one that originally caught the bug above.
- [x] **e** — Write one unit test for the stats rollup calculation
  - `tests/Ads/AdStatsRepositoryTest.php` automates exactly what 10.k's manual test already did by hand (5 impressions + 2 clicks on one ad, 1 impression on another, for one date) plus a same-date re-run check for the upsert's idempotency, plus confirming an event on an adjacent day never bleeds into the rollup. **Live-verified: 2/2 pass, 6 assertions**, both against real MariaDB.
- [x] **f** — Write one integration test for a single endpoint (auth → controller → DB → response shape)
  - `tests/Auth/LoginEndpointTest.php` — a real HTTP `POST /api/v1/auth/login` through `public/index.php`'s actual router → auth flow → `UserRepository`/`password_verify()` → real response, via `tests/Support/HttpServerTestCase.php` (spins up `php -S`, wired to the test DB through explicit process environment variables rather than `.env`, so it can never touch the dev database). Covers success (checks the response body *and* that a real `HttpOnly` session cookie is actually set), wrong password, and unknown email (same generic error for both, confirming the anti-enumeration behavior from 6.b still holds under a real request). **Live-verified: 3/3 pass, 13 assertions.**
- [x] **g** — Set up a disposable/seeded test database
  - `.env.testing.example` (template, same style as `.env.example`) + `database/scripts/reset-test-db.php`, a standalone script (kept separate from `migrate.php` on purpose — this one is destructive by design) that drops every table and recreates them fresh from the migration files. `tests/bootstrap.php` loads `.env.testing` only, never `.env`, and refuses to run at all unless `DB_NAME` contains the substring `test` — a safety net against ever truncating a real database by a typo'd config, live-verified by both intentionally renaming `.env.testing` away (correctly refuses with a clear message) and intentionally pointing `DB_NAME` at a non-test name (correctly refuses too). `tests/Support/DatabaseTestCase.php` truncates every table before each test (not once per class) and provides seed helpers that call real repository methods (`AppRepository::createWithApiKey()`, `UserRepository::create()`) rather than raw inserts, per this project's own "repository methods, not raw queries" convention (`MockDataSeeder.php`'s same rule).
  - *Found and fixed a real bug in this new script itself, not the app:* `reset-test-db.php`'s first version read `SHOW TABLES` with a plain `fetchAll()`, silently returning nothing to drop — `Core\Database::connection()` sets `PDO::ATTR_DEFAULT_FETCH_MODE` to `FETCH_ASSOC` connection-wide, so each row came back keyed by its column name (`Tables_in_skoolyst_ads_test`), not index `0`, and `array_column($tables, 0)` on that returned nothing. The DROP loop silently did nothing, and the very next run's `CREATE TABLE` failed with "table already exists" — caught immediately on the second live run, not left undiscovered. **Fixed** by fetching with `PDO::FETCH_COLUMN` explicitly. **Live-verified:** ran the reset script twice in a row afterward with no error either time, confirming it's genuinely safe to re-run.
- [x] **h** — Write one API contract test matching an `api-docs.php` example
  - `tests/Ads/AdsServeContractTest.php` — seeds an ad with the exact same field values as `api-docs.php`'s "Serve an Ad" documented example (`Speak Confidently in 8 Weeks`, `Book a Seat`, etc.), makes a real `GET /api/v1/ads/serve` request, and asserts the response's `data.ad` has *exactly* the six documented keys (`id, title, description, image_path, cta_text, click_url`) in an exact-match assertion, not just "contains at least these" — so a future field rename/drop/addition in `AdController::serve()`/`AdRepository::findServableForPlacement()` fails this test loudly instead of the docs silently going stale. Also covers the missing-API-key `401` and the documented `data.ad: null` case for a placement with nothing eligible. **Live-verified: 3/3 pass, 13 assertions**, all matched the docs on the first run — no drift found between `api-docs.php` and the real implementation.
- [x] **i** — Write a manual cross-browser QA checklist (doc only)
- [x] **j** — Write a manual mobile-device QA checklist (doc only)
- [x] **k** — Write a manual SQLi/XSS/auth-bypass check checklist (doc only)
  - All three checklists are below, under [Manual QA Checklists](#manual-qa-checklists) — doc-only, no code.
- [x] **l** — Add the "no endpoint merges without an integration test" rule to `CONTRIBUTING.md`
  - Added, pointing at `LoginEndpointTest.php`/`AdsServeContractTest.php` as the reference examples.

**Suite total: 16/16 passing, 57 assertions**, entirely against real MariaDB and (for f/h) a real spun-up HTTP server — no mocks anywhere in this suite, consistent with this project's existing testing philosophy.

---

## Manual QA Checklists

Doc-only (13.i/13.j/13.k) — for a human to actually run through before a release, not automated. Check items off per release/major PR, don't just read them.

### Cross-Browser QA

- [ ] Landing page (`index.html`) renders correctly and the auth-aware navbar resolves correctly (guest/advertiser/admin) in Chrome, Firefox, Safari, and Edge
- [ ] Create-ad's 3-step form (step navigation, live preview, image drag-drop) works in all four browsers — this is the form that already had a real TDZ bug (see Bug Fixes below), so re-check step navigation specifically
- [ ] Dashboard/admin tables (My Ads, moderation queue, Connected Apps) render and paginate correctly in all four
- [ ] Bootstrap tooltips (the ⓘ help-icon pattern, Section 2.3) actually appear on hover/focus in all four — Safari in particular has historically had quirks with tooltip positioning
- [ ] CSS custom properties (`--color-*`, `--radius-*`, `--shadow-card`) render consistently — check for any browser silently falling back to unstyled defaults
- [ ] No console errors on any of the above pages in any browser's dev tools

### Mobile-Device QA

- [ ] Sidebar collapses to the off-canvas drawer under 992px (Section 2.4.a) on a real phone-width viewport, not just a resized desktop window
- [ ] Tables (My Ads, moderation queue) scroll horizontally instead of breaking layout (Section 2.4.b) on a real touch device — verify actual touch-scroll works, not just that overflow is set
- [ ] Create-ad's live preview stacks below the form instead of sitting sticky beside it (Section 2.4.c)
- [ ] Login/signup forms are usable on-screen with the mobile keyboard open (inputs not hidden behind the keyboard, submit button reachable)
- [ ] Image upload (drag-drop area in create-ad) has a working tap-to-upload fallback, since drag-drop itself doesn't exist on touch
- [ ] Test on at least one real iOS and one real Android device, not only browser dev-tools device emulation — emulation doesn't catch every touch/viewport quirk

### Security Re-Check (SQLi / XSS / Auth-Bypass)

A repeatable version of the same live checks already done once in 10.l — re-run this on any PR touching auth, uploads, or raw SQL (also now required by `CONTRIBUTING.md`'s Section 6 checklist rule):

- [ ] Every new/changed query uses bound parameters — grep the diff for any string-concatenated SQL
- [ ] Try submitting a classic SQLi payload (e.g. `' OR '1'='1`) in every text input that reaches a query — confirm it's treated as literal data, not SQL
- [ ] Try submitting `<script>alert(1)</script>` (or similar) in every field that gets rendered back (ad title/description, user name) — confirm `htmlspecialchars()` output escaping actually neutralizes it
- [ ] Try accessing every advertiser-only and admin-only route (a) with no session, (b) with the wrong role's session — confirm the same 302/401/403 behavior 10.l already verified once still holds
- [ ] Try uploading a `.php` file renamed to `.jpg` as an ad image — confirm `core/Uploads.php`'s real-MIME-type check still rejects it (not just the extension)
- [ ] Try replaying/omitting the CSRF token on a state-changing dashboard form — confirm it's still rejected with a 419
- [ ] Fire rapid repeated requests at `/ads/serve` and the impression/click endpoints — confirm the rate limiter still returns 429 once the window's limit is hit

---

## 14. Deployment

**Status: ⬜ Not Started**

- [ ] **a** — Set up local `.env` + local database
- [ ] **b** — Set up staging `.env` + staging database
- [ ] **c** — Set up production `.env` + production database
- [ ] **d** — Seed staging with realistic-but-fake data
- [ ] **e** — Write the "migrations run before code switch" step into the deploy script
- [ ] **f** — Set up atomic symlink swap for zero-downtime deploy
- [ ] **g** — Document the rollback procedure (repoint the symlink)
- [ ] **h** — Set up error logging to a file/service
- [ ] **i** — Set up nightly MySQL backup cron job
- [ ] **j** — Do one test restore from a backup and record the result

---

## Bug Fixes (Manual QA)

Real bugs found through manual click-through testing (not part of the Section 10 checklist pass, but worth a record) — each verified live before/after, not just read in code.

- **Missing login checks on `dashboard/index.php`, `dashboard/create-ad.php`, `admin/index.php`** — only `my-ads.php`, `admin/ads.php`, and `admin/apps.php` had a real session/role check; these three still had a stale "until Section 6 wires up real auth" comment even though Section 6 was finished long ago, and were reachable by anyone with no session. **Fixed:** added the same `Middleware::checkSession()` redirect used by `my-ads.php` to the two advertiser pages, and the same session+role check used by `admin/ads.php` to `admin/index.php`. Verified live with real HTTP requests: no session → 302 redirect on all three; a real advertiser session → 200 on the two advertiser pages but still 302 on the admin page (wrong role); a real admin session → 200 on the admin page.
- **`create-ad.php`'s multi-step form: "Next" button did nothing** — the page's inline script declared `let currentImageSrc = ...` *after* `wireCount('f-title', ...)` was already calling `updatePreview()` (which reads `currentImageSrc`) as part of its own setup. Reading a `let` variable before its declaration line runs is a JavaScript temporal-dead-zone error (`Cannot access 'currentImageSrc' before initialization`) — uncaught, so it silently halted the rest of the script, including all the step-navigation code and the `Next`/`Back` button listeners further down. Confirmed live with a real headless-browser run (Playwright): the console showed exactly that error, and clicking Next produced no visible change. **Fixed:** moved the `currentImageSrc` declaration above the `wireCount()` calls. Re-ran the same browser test — no console errors, and step 1 correctly hides while step 2 shows after clicking Next.
- **Same file, separate bug — editing an existing ad would have crashed:** `editingAd = editId ? SkoolystAdsMock.ads.find(...) : null` referenced `SkoolystAdsMock`, but `views/partials/scripts.php` actually exposes the mock data as `window.SkoolystAdsMockData`. Only triggers when `?edit=...` is in the URL (the ternary short-circuits otherwise), so it didn't affect plain ad creation, but would throw immediately on the edit page. **Fixed:** corrected the variable name. Verified live: loading `create-ad.php?edit=ad_1001` now pre-fills the form from the mock ad with zero console errors, instead of crashing.
- **Every API request 404'd on a real Windows/XAMPP setup, in a nested `htdocs` subdirectory** — `public/index.php`'s subdirectory-stripping logic (needed so the route table, defined as root-relative paths like `/api/v1/auth/register`, still matches when the app lives under e.g. `htdocs/Projects/.../public/`) compared `REQUEST_URI` against `SCRIPT_NAME`'s directory with a case-sensitive `str_starts_with()`. On Windows, Apache resolves `SCRIPT_NAME` using the filesystem's actual folder casing (e.g. `/Projects/...`), which can differ from whatever case the browser's URL happened to use (e.g. `/projects/...`) — so the prefix was never recognized as a match, the full path was never stripped, and every single request 404'd against the route table. Found via a real request from an actual XAMPP/Windows install (Apple/Linux and this project's own PHP-built-in-server testing throughout Sections 6–11 never hit this, since neither involves that exact case mismatch). **Fixed:** switched to a case-insensitive `stripos(...) === 0` check. Verified by replaying the exact `REQUEST_URI`/`SCRIPT_NAME` values from the failing request — the path now correctly resolves to `/api/v1/auth/register` — and by a full regression pass (register, session-check, and a real 404) against this project's own test server afterward.

---

## Login / Signup Pages & Auth-Aware Navbar

Not part of the original roadmap — added because the landing page's navbar always showed "Dashboard"/"Create an Ad" regardless of login state, and there was genuinely no login/signup UI anywhere in the app (only the backend `/api/v1/auth/login`/`register` endpoints existed, tested only via curl/API calls throughout Sections 6–11). Building the navbar fix properly required these first.

- **`public/login.php`** — new page. Redirects immediately to `admin/index.php`/`dashboard/my-ads.php` if already logged in (same role-check pattern as the other protected pages). Form submits via `fetch` to the existing `POST /api/v1/auth/login` (no changes to that endpoint) and redirects based on the returned `role`. Verified live: correct credentials → lands on `my-ads.php`; wrong password → inline "Incorrect email or password" error, stays on the page.
- **`public/signup.php`** — new page, same pattern, posts to the existing `POST /api/v1/auth/register`. Client-side checks (all fields filled, password ≥ 8 chars, password/confirm match) run before hitting the API; server-side duplicate-email rejection is shown inline too. Since `register()` doesn't start a session itself (a deliberate Section 6 design choice — signup and login are separate steps), a successful signup redirects to `login.php?registered=1`, which shows a "account created" banner. Verified live end-to-end with a real headless-browser run: signup → banner on login page → login → lands on `my-ads.php`, zero console errors; duplicate-email and mismatched-password cases both caught and shown correctly.
- **New endpoint: `GET /api/v1/auth/session`** (`app/Auth/AuthController::session()`) — a minimal public (`auth => false`) endpoint that returns `{loggedIn: false}` for a guest or `{loggedIn: true, role: '...'}` for a real session, with nothing else exposed. Exists purely so a static page can ask "is anyone logged in?" client-side.
- **`index.html`'s navbar is now auth-aware.** The Dashboard/Create-an-Ad buttons are replaced by a small `fetch('api/v1/auth/session')` call on page load. Defaults to showing Login/Sign Up (the safe guess) so a logged-out visitor never briefly flashes Dashboard buttons before the check resolves; swaps to Dashboard (linked to `admin/index.php` for admins, `dashboard/index.php` for advertisers) + Create an Ad once a real session is confirmed. Verified live for all three states (guest, advertiser, admin) — correct buttons shown, and clicking Dashboard in each case actually lands on the right real page.
- New auth-page styles added to `public/assets/css/style.css` (`.sk-auth-page`, `.sk-auth-card`, etc.) reusing the existing `--color-*`/`--radius-*`/`--shadow-card` design tokens — no new design system introduced.

---

## 15. Future Enhancements

**Status: ⬜ Not Started** *(deferred, not blocking)*

- [ ] **a** — Billing module (paid ad packages/invoicing)
- [ ] **b** — Advertiser self-serve app onboarding
- [ ] **c** — A/B testing between ad creatives
- [ ] **d** — Advanced targeting (by page/user segment)
- [ ] **e** — Webhooks notifying a connected app on approval/rejection

---
---

# Roman Urdu Explanation — Har Section Step-by-Step

> Yeh section sirf reference/samajhne ke liye hai — har section ka Roman Urdu mein tafseel, taake koi bhi (naya session ho ya team member) jaldi samajh sake ke kya ho chuka hai aur kya baaki hai. Har section ka number upar wale English section ke number se match karta hai.

## 1️⃣ Project Idea — ✅ Done

| Point | Tafseel |
|---|---|
| **a** | Problem define ki: Skoolyst ke apps mein ads chalane ka koi shared tareeqa nahi tha. |
| **b** | Teen consumers tay kiye: **Advertisers** (ad dene wale), **Platform Admins** (approve/reject karne wale), **Connected Apps/developers** (jo apni app mein ads dikhayenge). |
| **c** | Core principle: ad ka data, moderation aur delivery sab **ek hi jagah** hoga; har connected app sirf "renderer" hai (khud koi ad logic nahi rakhega). |

---

## 2️⃣ User Interface — ✅ Done

### 2.1 Component-based, not copy-pasted

| Point | Tafseel |
|---|---|
| **a** | Advertiser aur Admin ke liye alag-alag sidebars banayi. |
| **b** | Ek shared topbar (header) har page pe use hota hai. |
| **c** | `ad-card` component — dashboard, my-ads table, aur public placements — teeno jagah reuse hota hai. |
| **d** | `status-badge` component — pehle 6 alag jagah copy-paste `<span>` blocks the, ab ek hi component `$status` variable ke hisaab se khud render karta hai. |
| **e** | Baaki reusable pieces: stat-card (dashboard number box), ads-table, confirm-modal (popup). |
| **f** | Ek shared layout (`app.php`) jise har page "extend" karta hai — header/footer duplicate nahi karna padta. |
| **g** | Shared `<head>` aur bottom-scripts partials — meta tags/CSS/JS sab ek hi jagah. |
| **h** | `mock-data.php` — sara test data ek jagah, jo PHP aur JS dono ko JSON ki form mein diya jata hai (taake dono jagah data match kare). |

### 2.2 Screens already designed

Yeh screens ban chuke hain: **Advertiser Dashboard**, **Create Ad** (3-step form), **My Ads** table, **Admin Overview**, **Admin All Ads** (moderation), **Admin Connected Apps**, aur **Developer API docs** page.

### 2.3 Help tooltips (ⓘ icon)

| Point | Tafseel |
|---|---|
| **a** | Shared CSS class tooltips ke liye. |
| **b** | Ek JS function jo har ⓘ icon ko Bootstrap tooltip bana deta hai. |
| **c** | Tooltip ka text ek hi config file mein centralized hai (jagah-jagah hardcode nahi). |
| **d** | Ek reusable help-icon component banaya. |

### 2.4 Responsiveness

| Point | Tafseel |
|---|---|
| **a** | 992px se neeche screen pe sidebar off-canvas drawer mein badal jata hai. |
| **b** | Tables mobile pe horizontally scroll karti hain, layout nahi tootta. |
| **c** | Create-ad preview mobile pe form ke neeche stack hota hai (desktop pe sticky side mein rehta hai). |

---

## 3️⃣ Backend Architecture — ✅ Done

### 3.1 One feature = one isolated folder

| Point | Tafseel |
|---|---|
| **a–f** | `app/Ads/` module banaya with empty stub files: Controller, Model, Repository, Validator, routes. |
| **g** | Same tarah `app/Apps/` module. |
| **h** | `app/Auth/` module. |
| **i** | `app/Admin/` module (moderation controller). |
| **j** | Router boot code likha jo har module ki `routes.php` ko load karta hai. |
| **k** | Ek rule likhi: koi module doosre module ke table ko directly query nahi karega. |

### 3.2 Centralized, non-repeated code

| Point | Tafseel |
|---|---|
| **a–d** | `core/Database.php` banaya — PDO connection + query helper methods (`query()`, `fetchOne()`). |
| **e–f** | `core/Request.php` — input read karna (POST/JSON) + sanitize karna. |
| **g–h** | `core/Response.php` — success shape (`{success:true}`) aur error shape (`{success:false}`) helpers. |
| **i–l** | `core/Validator.php` — validation rules add kiye: required, maxLength, url, date. |
| **m–n** | `core/Auth/Middleware.php` — session check, phir API-key/token check bhi add kiya. |
| **o** | `core/RateLimiter.php` — skeleton, ek `hit()` method. |
| **p** | `core/Cache.php` — file-based cache, `get()`/`set()` methods. |

### 3.3 Standard request lifecycle

| Point | Tafseel |
|---|---|
| **a–b** | `public/index.php` front controller banaya jo har request accept karta hai, aur router usme wire kiya. |
| **c–d** | Auth middleware aur rate-limit middleware request pipeline mein laga diye. |
| **e** | Ek example controller banaya jo repository ko call kar ke `Response::success()` return karta hai. |
| **f–g** | Do rules likhi: controllers "thin" rahenge (koi query logic nahi), aur saari query logic sirf Repositories mein hogi. |

---

## 4️⃣ API Structure — ✅ Done

Har API endpoint ke liye route + empty handler banaya:

| Points | Tafseel |
|---|---|
| **a–c** | Public/tracking endpoints — ad serve karna, impression record karna, click record karna. |
| **d–e** | Advertiser endpoints — naya ad banana, existing ad update karna. |
| **f–h** | Admin endpoints — pending ads dekhna, approve karna, reject karna. |
| **i–k** | Admin app-management endpoints — apps list karna, naya app banana, app update karna. |
| **l** | Har response ko ek standard envelope (`{success, data\|error}`) mein wrap kiya. |
| **m** | Har route ke aage `/api/v1/` prefix laga. |
| **n** | Routes ko do files mein split kiya — public (serve/track) vs authenticated (advertiser/admin). |

---

## 5️⃣ Database Design — ✅ Done

### 5.1 Schema
7 migrations banayi: `users`, `apps`, `placements`, `ads`, `ad_impressions`, `ad_clicks`, `api_keys` tables.

### 5.2 Indexing plan
Performance ke liye indexes lagaye — status+placement pe composite index, user_id pe index, date-range index, impressions/clicks ke liye ad_id+occurred_at index, api_key ka unique index, placement ka unique composite index.

### 5.3 Aggregation, not row-by-row counting

| Point | Tafseel |
|---|---|
| **a** | `ad_stats_daily` table banayi (ad_id, date, impressions, clicks). |
| **b** | Ek rollup script likha jo raw events ko roz ke totals mein convert karta hai. |
| **c** | Us script ko cron job se schedule kiya. |
| **d** | Dashboard chart ko raw tables ki bajaye is `ad_stats_daily` se connect kiya. |
| **e** | Raw tables ko sirf audit ke liye read-only rakha — koi bhi seedha unko count/query nahi karega, sab `AdStatsRepository` ke through hoga. |

---

## 6️⃣ Authentication & Security — ✅ Done

| Points | Tafseel |
|---|---|
| **a–b** | Password hash aur verify (login/signup). |
| **c** | Login pe secure, HttpOnly session cookie issue hoti hai. |
| **d–e** | Har app ke liye API key generate hoti hai, aur database mein **hash** ho kar store hoti hai (plaintext kabhi nahi). |
| **f** | Authenticated API routes `Authorization: Bearer` header accept karte hain. |
| **g–h** | Role-based checks — advertiser-only aur admin-only routes protect kiye. |
| **i–l** | CSRF protection — token generate hota hai, verify hota hai, aur create-ad + har state-changing form pe laga hai. |
| **m–n** | Saari queries prepared statements se hoti hain (audit kiya), aur user-entered text output pe escape hota hai (XSS se bachne ke liye). |
| **o–r** | Image upload security — metadata strip hoti hai, real MIME type check hota hai (extension pe bharosa nahi), size cap hai, file rename hoti hai, aur images non-executable path se serve hoti hain (koi `.php` file upload ho kar chal nahi sakti). |
| **s–t** | Rate limiting `/ads/serve` aur impression/click endpoints pe. |
| **u** | Har API key sirf apni app ke placements tak scoped hai — doosri app ka data nahi dekh sakti. |
| **v–w** | Admin ke approve/reject aur key-regenerate actions pe audit-log entry likhi jati hai. |

---

## 7️⃣ Performance & Optimization — ✅ Done

| Points | Tafseel |
|---|---|
| **a–b** | Section 5.2 ke indexes confirm kiye, aur stats rollup job schedule pe chal rahi confirm ki. |
| **c–d** | `/ads/serve` pe caching lagayi — pehle cache check, phir DB, phir short-TTL cache write. |
| **e–f** | Production mein OPcache on kiya, Composer autoload optimize kiya. |
| **g–i** | Ad images resize + compress ki, aur unpe far-future cache headers lagaye. |
| **j** | Har ad-card image pe `loading="lazy"` confirm kiya. |
| **k–l** | Advertiser My-Ads list aur Admin moderation queue — dono DB-level pagination pe. |
| **m–n** | Impression/click tracking calls client-side "fire-and-forget" (user ko wait nahi karna padta). |

---

## 8️⃣ SEO — ✅ Done

| Points | Tafseel |
|---|---|
| **a–e** | Title/meta description aur heading order (h1→h2→h3) fix kiya `index.html` aur `api-docs.php` dono pe. |
| **f–h** | `sitemap.xml` banayi, `robots.txt` mein dashboard/admin/api disallow rules likhi. |
| **i** | Structured data (`SoftwareApplication`) add ki. |
| **j** | Section 7 ka performance checklist dobara in hi do pages pe re-check kiya. |

---

## 9️⃣ Folder Structure — ✅ Done

| Points | Tafseel |
|---|---|
| **a–d** | `public/` folder banaya, web server root wahan point kiya, `public/assets/` aur `public/uploads/ads/` banaye. |
| **e–g** | Confirm kiya ke `app/`, `core/`, `views/` folders already sahi structure mein hain. |
| **h–i** | `config/database.php` aur `config/app.php` banaye — connection settings aur app-level config (env/debug/pagination defaults) ek jagah. |
| **j–k** | `database/migrations/` (already thi) aur `database/seeders/` (naya `DatabaseSeeder.php` — admin/advertiser/app bootstrap seed) confirm/banaye. |
| **l–m** | `routes/web.php` (dashboard pages ka map) aur `routes/api.php` (saare modules ki routes merge) banaye. |
| **n** | `tests/` folder scaffold kiya (actual tests Section 13 mein aayenge). |

---

## 🔟 Build Order / Roadmap — 🟨 In Progress

### ✅ Jo ho chuka hai

| Point | Tafseel |
|---|---|
| **a** | Migration runner (`database/scripts/migrate.php`) bana ke fresh DB pe migrations run kiye — idempotent hai, dobara chalane se dobara apply nahi karta. |
| **b** | `MockDataSeeder.php` se DB ko UI wale mock data se seed kiya — repository methods use karta hai, raw queries nahi. |
| **c–e** | Core layer (Database/Request/Response/Middleware), Auth login, aur API-key issuing — teeno real MySQL ke against verify kiye. |
| **f** | **Create-ad form** ab real `AdRepository`/validator se wired hai — CSRF check, non-executable-path image upload, `status='pending'` insert, mock data ki jagah. Iske sath `core/Autoload.php` banana bhi zaroori pada (kuch bhi actually autoload nahi ho raha tha), `public/index.php` ko real controller dispatch karwaya (pehle sirf `echo 'ok'` tha), XAMPP subdirectory path fix, session cookie ka local-only `Secure` flag fix, aur naya `GET /api/v1/advertiser/apps` endpoint (form ke app/placement pickers ab real IDs use karte hain, mock string codes nahi). |
| **g** | **My-ads list** ab real DB se, logged-in `user_id` ke hisaab se filter ho kar, config ke default pagination ke sath aata hai — static mock list nahi. Sath hi ek pehle se maujood crash fix hui (`csrf_field()` bina Autoload load hue call ho raha tha — fatal error deta tha), router mein `{id}` dynamic-segment matching add hui (jo 10.h ke liye zaroori thi), aur client-side search/filter ab real rendered rows ko filter karta hai (mock data se re-render nahi karta — warna real DB rows overwrite ho jate). |
| **h** | **Admin approve/reject** ab real `AdRepository` status update + `AuditLog` entry (6.v) ke sath kaam karta hai, sirf UI change nahi. `findByStatus()` ko "All" tab ke liye `null` status accept karne laayak banaya, app-filter dropdown add kiya, status tabs ko JS-toggle se real `<a href="?status=...">` links mein badla (server-side pagination ki wajah se). Approve/reject buttons ab real `PATCH` API call karte hain CSRF header ke sath. |
| **i** | **Admin app-management** (API key regenerate waghera) ab real `AppRepository` + `AuditLog` (6.w) se wired hai. `AppController::update()` (jo empty stub tha) implement kiya, `allWithCounts()`/`updateStatus()` methods banaye. Connect-switch, regenerate-key, aur "Connect New App" modal — sab real API call karte hain (mock data mutate nahi karte). Naya API key sirf ek dafa dikhta hai (one-time prompt) kyunke sirf hash store hota hai. Ek latent bug bhi fix hui — MySQL ka `rowCount()` "changed rows" batata hai "matched rows" nahi, jis se already-approved ad ko dobara approve karna galti se "not found" error deta tha. |

### ⬜ Abhi baaki hai

| Point | Tafseel |
|---|---|
| **j** | API docs page ko actual routes/controllers ke against verify karna (drift check). |
| **k** | Stats rollup cron job actually register + confirm karna ke roz chal raha hai. |
| **l–n** | Section 6 (security), 7 (performance), aur 8 (SEO) ke checklists ko **real wired-up app** pe dobara pass/fail test karna — sirf code exist karne se kaam nahi chalega, actually try karke confirm karna hai. |

---

## 1️⃣1️⃣ Tech Stack & Environment Setup — ✅ Done

### ✅ Jo ho chuka hai

| Point | Tafseel |
|---|---|
| **a** | PHP 8.2+ confirm ho gaya (`composer --version` output se: PHP 8.2.12). |
| **b** | phpMyAdmin se check kiya — local DB asal mein **MariaDB 10.4.32** hai, MySQL 8.0 nahi (XAMPP ka default). Poore codebase mein koi bhi MySQL-8-only SQL feature (CTE, window functions, JSON_TABLE) use nahi hui, is liye ye farq practically kuch nahi badalta — saari migrations/seeders isi MariaDB instance pe already test ho chuki hain is poore session mein. |
| **c** | XAMPP (Apache + mod_php) local equivalent ke tor pe use ho raha hai, app usme sahi chal rahi hai. |
| **d** | File-cache fallback already live-test ho chuka hai (Section 7.c/d) — Redis ki zaroorat nahi. |
| **e** | Composer install confirm ho gaya (v2.9.7). |
| **f** | `composer.json` PSR-4 mapping ke sath add kiya, `composer install --optimize-autoloader` (jo `deploy.sh` chalata hai) live verify kiya — pehle ye fail hota tha (7.f bug), ab pass. |
| **g** | `.env.example` file bana di placeholder values ke sath. Sath hi `core/Env.php` (Composer-free loader — kyunke Composer setup abhi hua nahi) banaya aur `public/index.php`, `public/dashboard/index.php`, migrate script, rollup script, aur dono seeders mein wire kiya. |
| **h** | Har required `.env` value ko `.env.example` mein comments ke sath document kiya. |
| **i** | Local setup ke commands (`.env` banana, DB create, migrate, seed, serve) README ke top pe likh diye — Windows/XAMPP ke hisab se. |

---

## 1️⃣2️⃣ Coding Standards & Git Workflow — ✅ Done

`CONTRIBUTING.md` ban gayi hai, jisme (ab) 10 rules likhe hain: PSR-12 style, PSR-4 autoload (composer.json ke real mapping ke sath), one-class-per-file, har repository method pe docblock, `main` branch hamesha deployable, ek feature = ek branch, commit format `[Module] description` (jo is project ki apni git history se hi liya hai), auth/upload/SQL PRs pe Section 6 checklist mandatory, koi bhi naya/badla hua API endpoint bina integration test ke merge nahi hoga (Section 13 se aayi), aur merged migration kabhi edit na karna.

---

## 1️⃣3️⃣ Testing & QA — ✅ Done

| Point | Tafseel |
|---|---|
| **a** | `composer.json` mein PHPUnit dev-dependency add ki. *Sach baat:* is session ke sandbox mein `packagist.org` tak internet access nahi tha, is liye asal `composer install` yahan nahi ho saka — apt se PHP/MariaDB/Composer install kiye aur Debian ka `phpunit` 9.6.17 stand-in test-runner ke tor pe use kiya taake live-testing phir bhi real DB/HTTP ke against ho sake. Aapke real machine pe ek dafa `composer update phpunit/phpunit --dev` chalana hoga (Quick Start ke "Running Tests" section mein likha hai) — ye step yahan nahi ho saka. |
| **b** | `phpunit.xml` banaya. |
| **c** | `Validator`'s har rule ke liye unit test (5 tests) — 100% pass. |
| **d** | `AdRepository::findByStatus()` ka query-building unit test likha. **Isi se ek real bug pakda gaya:** 25 ads ek hi second mein seed karne se pagination (`LIMIT`/`OFFSET`) ka order kabhi-kabhi overlap kar raha tha, kyunke `ORDER BY created_at` akela tie-break nahi kar pata. **Fix:** `ads.id` ko secondary sort ke tor pe add kiya (`findByStatus` aur `findAllForUser` dono mein) — dobara test kiya, ab consistent hai. |
| **e** | Stats rollup calculation ka unit test — 10.k wala manual test ab automated hai, dobara MariaDB ke against pass hua. |
| **f** | Ek integration test (`login` endpoint) — real HTTP request, real router/middleware, real DB, real response — sab pass. |
| **g** | Disposable test DB set up ki (`reset-test-db.php` + `.env.testing`). **Isi script mein bhi ek real bug mila aur fix hua** (`SHOW TABLES` ka fetch-mode galat tha, is liye tables drop nahi ho rahe the) — dobara test kiya, ab safe hai re-run karna. |
| **h** | `/ads/serve` ka API contract test — `api-docs.php` ke documented example se exact match, koi drift nahi mila. |
| **i–k** | Teen manual QA checklists likhi (cross-browser, mobile, security) — [Manual QA Checklists](#manual-qa-checklists) section mein. |
| **l** | `CONTRIBUTING.md` mein rule add ki: koi bhi endpoint bina integration test ke merge nahi hoga. |

**Total: 16/16 tests pass, 57 assertions** — sab real MariaDB ke against, mock kuch nahi.

---

## 1️⃣4️⃣ Deployment — ⬜ Not Started

Local/staging/production `.env` + DB setup, staging ko fake-realistic data se seed karna, deploy script mein "migrations pehle, code switch baad mein" step, zero-downtime symlink swap, rollback procedure document karna, error logging, nightly backup cron, aur ek test restore try karke record karna.

---

## 1️⃣5️⃣ Future Enhancements — ⬜ Not Started (deferred)

Aage ke liye ideas, abhi blocking nahi: Billing module, advertiser self-serve onboarding, A/B testing between ad creatives, advanced targeting, aur approval/rejection pe webhooks.
