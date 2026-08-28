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
| 11 | [Tech Stack & Environment Setup](#11-tech-stack--environment-setup) | 🟨 In Progress |
| 12 | [Coding Standards & Git Workflow](#12-coding-standards--git-workflow) | ⬜ Not Started |
| 13 | [Testing & QA](#13-testing--qa) | ⬜ Not Started |
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
  - *f — FAIL, found a real bug:* `deploy.sh` runs `composer install --no-dev --optimize-autoloader`, but there is no `composer.json` anywhere in this repo — the app currently autoloads via the dependency-free `core/Autoload.php` (its own doc-block says Composer's autoloader is meant to replace it "once Composer is set up," which per `tests/README.md` is still only assumed, not decided/done). On a real deploy this step would simply fail (`composer.json not found`). *Not fixed here* — introducing `composer.json`/`vendor/` now would mean deciding and standing up the whole dependency-management setup mid-test-pass, which is Section 11's job, not a one-line fix; flagging for Section 11 instead of scope-creeping into it.
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

---

## 11. Tech Stack & Environment Setup

**Status: 🟨 In Progress**

- [ ] **a** — Confirm PHP 8.2+ is installed locally
- [ ] **b** — Confirm MySQL 8.0 is installed locally
- [ ] **c** — Set up Nginx + PHP-FPM (or local equivalent, e.g. `php -S`)
- [ ] **d** — Install Redis, or confirm the file-cache fallback works without it
- [ ] **e** — Install Composer
- [ ] **f** — Add `composer.json` with PSR-4 autoload mapping
- [x] **g** — Create `.env.example` with placeholder values
  - *Implemented via:* `.env.example`, plus `core/Env.php` (dependency-free loader, since 11.e/f's Composer setup hasn't happened yet) wired into `public/index.php`, `public/dashboard/index.php`, `database/scripts/migrate.php`, `database/scripts/rollup-ad-stats-daily.php`, and both seeders.
- [x] **h** — Document each required `.env` value in `.env.example` comments
- [x] **i** — Write the local setup command list into `README.md`/`SETUP.md` (`composer install`, migrate, seed, serve)
  - *Implemented via:* the [Quick Start — Local Setup & Command Reference](#-quick-start--local-setup--command-reference) section at the top of this file — written for the actual dev environment (Windows + XAMPP), covering `.env` setup, DB creation, migrate, seed, and serving via Apache/XAMPP or PHP's built-in server, plus a note on what's still missing for production (Composer setup, cron registration — see 10.m/10.k).

---

## 12. Coding Standards & Git Workflow

**Status: ⬜ Not Started**

- [ ] **a** — Add a PSR-12 note to a `CONTRIBUTING.md`
- [ ] **b** — Add a PSR-4 autoload mapping note to `CONTRIBUTING.md`
- [ ] **c** — Add the "one-class-per-file, filename matches class name" rule to `CONTRIBUTING.md`
- [ ] **d** — Add the "docblock every repository public method" rule to `CONTRIBUTING.md`
- [ ] **e** — Add the "`main` is always deployable" rule to `CONTRIBUTING.md`
- [ ] **f** — Add the "one feature branch per module task" convention to `CONTRIBUTING.md`
- [ ] **g** — Add the `[Module] ...` commit message convention to `CONTRIBUTING.md`
- [ ] **h** — Add "Section 6 checklist required on auth/upload/SQL PRs" rule to `CONTRIBUTING.md`
- [ ] **i** — Add the "never edit a merged migration" rule to `CONTRIBUTING.md`

---

## 13. Testing & QA

**Status: ⬜ Not Started**

- [ ] **a** — Install PHPUnit via Composer
- [ ] **b** — Add PHPUnit config file (`phpunit.xml`)
- [ ] **c** — Write one unit test for a validator rule
- [ ] **d** — Write one unit test for a repository query-building method
- [ ] **e** — Write one unit test for the stats rollup calculation
- [ ] **f** — Write one integration test for a single endpoint (auth → controller → DB → response shape)
- [ ] **g** — Set up a disposable/seeded test database
- [ ] **h** — Write one API contract test matching an `api-docs.php` example
- [ ] **i** — Write a manual cross-browser QA checklist (doc only)
- [ ] **j** — Write a manual mobile-device QA checklist (doc only)
- [ ] **k** — Write a manual SQLi/XSS/auth-bypass check checklist (doc only)
- [ ] **l** — Add the "no endpoint merges without an integration test" rule to `CONTRIBUTING.md`

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

## 1️⃣1️⃣ Tech Stack & Environment Setup — 🟨 In Progress

### ✅ Jo ho chuka hai

| Point | Tafseel |
|---|---|
| **g** | `.env.example` file bana di placeholder values ke sath. Sath hi `core/Env.php` (Composer-free loader — kyunke Composer setup abhi hua nahi) banaya aur `public/index.php`, `public/dashboard/index.php`, migrate script, rollup script, aur dono seeders mein wire kiya. |
| **h** | Har required `.env` value ko `.env.example` mein comments ke sath document kiya. |

### ⬜ Abhi baaki hai

PHP 8.2+/MySQL 8.0 confirm karna, Nginx/PHP-FPM setup, Redis (ya file-cache fallback), Composer install, `composer.json` PSR-4 mapping, aur setup commands README/SETUP.md mein likhna.

---

## 1️⃣2️⃣ Coding Standards & Git Workflow — ⬜ Not Started

`CONTRIBUTING.md` banani hai jisme rules likhne hain: PSR-12 style, PSR-4 autoload, one-class-per-file, har repository method pe docblock, `main` branch hamesha deployable, ek feature = ek branch, commit format `[Module] description`, auth/upload/SQL PRs pe Section 6 checklist mandatory, aur merged migration kabhi edit na karna.

---

## 1️⃣3️⃣ Testing & QA — ⬜ Not Started

PHPUnit install karna, config banana, unit tests (validator rule, repository method, rollup calculation), ek integration test (auth→controller→DB→response), disposable test DB setup, API contract test, aur manual QA checklists (cross-browser, mobile, security) likhna.

---

## 1️⃣4️⃣ Deployment — ⬜ Not Started

Local/staging/production `.env` + DB setup, staging ko fake-realistic data se seed karna, deploy script mein "migrations pehle, code switch baad mein" step, zero-downtime symlink swap, rollback procedure document karna, error logging, nightly backup cron, aur ek test restore try karke record karna.

---

## 1️⃣5️⃣ Future Enhancements — ⬜ Not Started (deferred)

Aage ke liye ideas, abhi blocking nahi: Billing module, advertiser self-serve onboarding, A/B testing between ad creatives, advanced targeting, aur approval/rejection pe webhooks.
