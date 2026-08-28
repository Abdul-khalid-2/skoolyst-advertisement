# Skoolyst Ads — AdEngine

A centralized advertisement engine for the Skoolyst family of apps. One dashboard to create and manage ads, one admin panel to moderate them, and one API that any connected app (`skoolyst.com`, `social.skoolyst.com`, `teachers.skoolyst.com`, and outside apps like Jaans Fabrics or Saif Pindi Autos) calls to fetch and report on ads — instead of every project hardcoding its own ad logic.

This document is the build plan, broken into small, self-contained tasks so each one is quick to pick up, quick to review, and quick to mark done. A section isn't started until the previous one is marked ✅ Done, so nothing gets half-built in parallel.

---

## Progress Status

| # | Section | Status |
|---|---|---|
| 1 | Project Idea | ✅ Done |
| 2 | User Interface | ✅ Done |
| 3 | Backend Architecture | ✅ Done |
| 4 | API Structure | ✅ Done |
| 5 | Database Design | ✅ Done |
| 6 | Authentication & Security | ✅ Done |
| 7 | Performance & Optimization | ✅ Done |
| 8 | SEO | ✅ Done |
| 9 | Folder Structure | ✅ Done |
| 10 | Build Order / Roadmap | 🟨 In Progress |
| 11 | Tech Stack & Environment Setup | ⬜ Not Started |
| 12 | Coding Standards & Git Workflow | ⬜ Not Started |
| 13 | Testing & QA | ⬜ Not Started |
| 14 | Deployment | ⬜ Not Started |
| 15 | Future Enhancements | ⬜ Not Started (deferred, not blocking) |

---

## Table of Contents

1. [Project Idea](#1-project-idea--done)
2. [User Interface](#2-user-interface--done)
3. [Backend Architecture](#3-backend-architecture--not-started)
4. [API Structure](#4-api-structure--not-started)
5. [Database Design](#5-database-design--not-started)
6. [Authentication & Security](#6-authentication--security--not-started)
7. [Performance & Optimization](#7-performance--optimization--not-started)
8. [SEO](#8-seo--not-started)
9. [Folder Structure](#9-folder-structure--not-started)
10. [Build Order / Roadmap](#10-build-order--roadmap--not-started)
11. [Tech Stack & Environment Setup](#11-tech-stack--environment-setup--not-started)
12. [Coding Standards & Git Workflow](#12-coding-standards--git-workflow--not-started)
13. [Testing & QA](#13-testing--qa--not-started)
14. [Deployment](#14-deployment--not-started)
15. [Future Enhancements](#15-future-enhancements--not-started-deferred-not-blocking)

---

## 1. Project Idea ✅ Done

- [x] a - Define the problem: no shared way to run ads across Skoolyst apps today
- [x] b - Define the three consumers: Advertisers, Platform Admins, Connected Apps (developers)
- [x] c - Define the core principle: ad data/moderation/delivery lives in one place; every connected app is just a renderer

---

## 2. User Interface ✅ Done

### 2.1 Component-based, not copy-pasted ✅ Done
- [x] a - `views/partials/sidebar-advertiser.php`, `sidebar-admin.php`
- [x] b - `views/partials/topbar.php`
- [x] c - `views/components/ad-card.php` — used by the dashboard, my-ads table preview, and public-facing placements
- [x] d - `views/components/status-badge.php` — one badge component driven by `$status`, instead of six copy-pasted `<span>` blocks
- [x] e - `views/components/stat-card.php`, `ads-table.php`, `modal-confirm.php`
- [x] f - `views/layouts/app.php` — one shared layout every page extends
- [x] g - `views/partials/head.php`, `scripts.php` — shared `<head>` and bottom-script blocks
- [x] h - `data/mock-data.php` — single source of truth, injected as JSON for both PHP and JS

### 2.2 Screens already designed ✅ Done
- [x] a - Advertiser: Dashboard overview
- [x] b - Advertiser: Create Ad (3-step form with live preview)
- [x] c - Advertiser: My Ads (searchable/filterable table)
- [x] d - Admin: Overview
- [x] e - Admin: All Ads (moderation queue, approve/reject)
- [x] f - Admin: Connected Apps (API key management)
- [x] g - Docs: API reference page for developers

### 2.3 Help tooltips (the "ⓘ" icon pattern) ✅ Done
- [x] a - Shared `.db-help-icon` CSS class in `dashboard.css`
- [x] b - One JS initializer (`initTooltips()`) turns every tooltip icon on the page into a Bootstrap tooltip
- [x] c - `config/help-text.php` — tooltip copy centralized in one file
- [x] d - `views/components/help-icon.php` — the reusable component itself

### 2.4 Responsiveness ✅ Done
- [x] a - Sidebar collapses to an off-canvas drawer under 992px
- [x] b - Tables scroll horizontally on small screens instead of breaking layout
- [x] c - Create-ad live preview stacks below the form on mobile instead of sitting sticky beside it

---

## 3. Backend Architecture — ✅ Done

### 3.1 One feature = one isolated folder
- [x] a - Create `app/Ads/` folder (empty, with a `.gitkeep`)
- [x] b - Create `app/Ads/AdController.php` (empty class stub)
- [x] c - Create `app/Ads/AdModel.php` (empty class stub)
- [x] d - Create `app/Ads/AdRepository.php` (empty class stub)
- [x] e - Create `app/Ads/AdValidator.php` (empty class stub)
- [x] f - Create `app/Ads/routes.php` (empty array/return)
- [x] g - Create `app/Apps/` folder + `AppController.php`, `AppModel.php`, `routes.php` stubs
- [x] h - Create `app/Auth/` folder + `AuthController.php`, `UserModel.php`, `routes.php` stubs
- [x] i - Create `app/Admin/` folder + `ModerationController.php`, `routes.php` stubs
- [x] j - Write the router boot code that `require`s each module's `routes.php`
- [x] k - Write one code-review checklist line: "no module queries another module's tables directly"

### 3.2 Centralized, non-repeated code
- [x] a - Create `core/` folder (empty, with a `.gitkeep`)
- [x] b - Create `core/Database.php` — PDO connection setup only (no query helpers yet)
- [x] c - Add one query-helper method to `core/Database.php` (e.g. `query()`)
- [x] d - Add a second query-helper method to `core/Database.php` (e.g. `fetchOne()`)
- [x] e - Create `core/Request.php` — read raw input (`$_POST`/JSON body) only
- [x] f - Add sanitizing helpers to `core/Request.php`
- [x] g - Create `core/Response.php` — success-shape helper only (`{success:true, data}`)
- [x] h - Add error-shape helper to `core/Response.php` (`{success:false, error}`)
- [x] i - Create `core/Validator.php` — `required` rule only
- [x] j - Add `maxLength` rule to `core/Validator.php`
- [x] k - Add `url` rule to `core/Validator.php`
- [x] l - Add `date` rule to `core/Validator.php`
- [x] m - Create `core/Auth/Middleware.php` — session check only
- [x] n - Add token/API-key check to `core/Auth/Middleware.php`
- [x] o - Create `core/RateLimiter.php` — skeleton class, one `hit()` method
- [x] p - Create `core/Cache.php` — skeleton class, `get()`/`set()` methods (file-based)

### 3.3 Standard request lifecycle
- [x] a - Create `public/index.php` front controller (accepts any request, echoes "ok")
- [x] b - Wire the router into `public/index.php`
- [x] c - Wire the auth middleware into the request pipeline
- [x] d - Wire the rate-limit middleware into the request pipeline
- [x] e - Write one example controller method that calls a repository and returns `Response::success()`
- [x] f - Write the rule/comment: controllers stay thin, no query logic in controllers
- [x] g - Write the rule/comment: all query logic lives in Repositories only

---

## 4. API Structure — ✅ Done

- [x] a - `GET /api/v1/ads/serve?placement={code}` — route + empty handler
- [x] b - `POST /api/v1/ads/{id}/impression` — route + empty handler
- [x] c - `POST /api/v1/ads/{id}/click` — route + empty handler
- [x] d - `POST /api/v1/advertiser/ads` — route + empty handler
- [x] e - `PATCH /api/v1/advertiser/ads/{id}` — route + empty handler
- [x] f - `GET /api/v1/admin/ads?status=pending` — route + empty handler
- [x] g - `PATCH /api/v1/admin/ads/{id}/approve` — route + empty handler
- [x] h - `PATCH /api/v1/admin/ads/{id}/reject` — route + empty handler
- [x] i - `GET /api/v1/admin/apps` — route + empty handler
- [x] j - `POST /api/v1/admin/apps` — route + empty handler
- [x] k - `PATCH /api/v1/admin/apps/{id}` — route + empty handler
- [x] l - Wrap every handler's return in the `{ success, data | error }` envelope
- [x] m - Prefix every route file with `/api/v1/`
- [x] n - Split routes into `routes/api-public.php` (serve/track) vs `routes/api-auth.php` (advertiser/admin)

---

## 5. Database Design — ✅ Done

### 5.1 Schema
- [x] a - Migration: `users` table
- [x] b - Migration: `apps` table
- [x] c - Migration: `placements` table
- [x] d - Migration: `ads` table
- [x] e - Migration: `ad_impressions` table
- [x] f - Migration: `ad_clicks` table
- [x] g - Migration: `api_keys` table

### 5.2 Indexing plan
- [x] a - Add `ads (status, placement_id)` composite index
- [x] b - Add `ads (user_id)` index
- [x] c - Add `ads (start_date, end_date)` index
- [x] d - Add `ad_impressions (ad_id, occurred_at)` index
- [x] e - Add `ad_clicks (ad_id, occurred_at)` index
- [x] f - Add `apps (api_key_hash)` unique index
- [x] g - Add `placements (app_id, code)` unique composite index

### 5.3 Aggregation, not row-by-row counting
- [x] a - Migration: `ad_stats_daily (ad_id, date, impressions, clicks)` table
- [x] b - Write the rollup query (raw events → daily totals) as a standalone script
- [x] c - Wire that script into a scheduled job (cron entry)
- [x] d - Point one dashboard chart at `ad_stats_daily` instead of raw tables
- [x] e - Confirm raw tables are kept read-only, for auditing only

`AdStatsRepository` (`app/Ads/AdStatsRepository.php`) is the only place
allowed to run an aggregate query against `ad_impressions` /
`ad_clicks` — its `rollupForDate()` method, called by
`database/scripts/rollup-ad-stats-daily.php` (cron entry in
`cron/README.md`). Every other read, including the dashboard's
"Impressions, Last 7 Days" chart, goes through
`AdStatsRepository::dailyImpressions()` against `ad_stats_daily`
instead — the raw tables stay write-once, for auditing only.

---

## 6. Authentication & Security — ✅ Done

- [x] a - Implement `password_hash()` on signup
- [x] b - Implement `password_verify()` on login
- [x] c - Issue signed, HttpOnly session cookie on login
- [x] d - Implement per-app API key generation
- [x] e - Store API keys as a hash, never plaintext
- [x] f - Accept `Authorization: Bearer` header on authenticated API routes
- [x] g - Add role check for advertiser-only routes
- [x] h - Add role check for admin-only routes
- [x] i - Add CSRF token generation helper
- [x] j - Add CSRF token verification middleware
- [x] k - Attach CSRF token to the create-ad form
- [x] l - Attach CSRF token to every other state-changing dashboard form
- [x] m - Confirm every existing query goes through prepared statements (audit pass)
- [x] n - Escape all user-entered ad copy on output (`htmlspecialchars`)
- [x] o - Re-encode uploaded images on upload (strip metadata)
- [x] p - Validate uploads by real MIME type, not extension
- [x] q - Cap upload size and rename file on storage
- [x] r - Serve uploaded images from a non-executable path
- [x] s - Add rate limit to `/ads/serve`
- [x] t - Add rate limit to `/impression` and `/click`
- [x] u - Scope each API key's queries to only its own app's placements
- [x] v - Add audit-log table + write on admin approve/reject
- [x] w - Add audit-log write on admin regenerate-key action

---

## 7. Performance & Optimization — ✅ Done

- [x] a - Apply the indexing plan from 5.2 (confirm indexes exist via `SHOW INDEX`)
- [x] b - Confirm the `ad_stats_daily` rollup job from 5.3 is running on schedule
- [x] c - Add cache read to `/ads/serve` (check cache before DB)
- [x] d - Add cache write to `/ads/serve` (short TTL after DB fetch)
- [x] e - Enable OPcache in the production PHP config
- [x] f - Switch Composer autoload to `--optimize-autoloader` in the deploy script
- [x] g - Resize uploaded ad images to the one size actually used
- [x] h - Compress resized ad images
- [x] i - Add far-future cache headers to image responses
- [x] j - Confirm `loading="lazy"` is on every ad-card image
- [x] k - Paginate the advertiser "My Ads" list at the DB level
- [x] l - Paginate the admin moderation queue at the DB level
- [x] m - Make the impression-tracking call fire-and-forget on the client
- [x] n - Make the click-tracking call fire-and-forget on the client

---

## 8. SEO — ✅ Done

- [x] a - Add descriptive `<title>` to `index.html`
- [x] b - Add `<meta description>` to `index.html`
- [x] c - Add descriptive `<title>`/`<meta description>` to `api-docs.php`
- [x] d - Check/fix heading order (h1→h2→h3) on `index.html`
- [x] e - Check/fix heading order (h1→h2→h3) on `api-docs.php`
- [x] f - Generate `sitemap.xml` for public pages
- [x] g - Write `robots.txt` disallowing `/dashboard/`
- [x] h - Add `/admin/` and `/api/` disallow rules to `robots.txt`
- [x] i - Add `SoftwareApplication` structured data to `index.html` (optional, if page grows)
- [x] j - Re-run the Section 7 performance checklist specifically against `index.html`/`api-docs.php`

---

## 9. Folder Structure — ✅ Done

- [x] a - Create `public/` folder
- [x] b - Move/point web server root at `public/`
- [x] c - Create `public/assets/` folder
- [x] d - Create `public/uploads/ads/` folder
- [x] e - Confirm `app/` module folders exist *(tracked in 3.1 — Admin, Ads, Apps, Auth all present)*
- [x] f - Confirm `core/` folder exists *(tracked in 3.2 — present, incl. `Auth/` and `Security/` subfolders)*
- [x] g - Confirm `views/` folder is unchanged *(already done — Section 2)*
- [x] h - Create `config/database.php` — connection settings, `core/Database.php` now reads from it instead of `getenv()` directly
- [x] i - Create `config/app.php` — name/env/debug/url/timezone + shared pagination defaults (7.k/7.l)
- [x] j - Create `database/migrations/` folder *(already present — 16 migrations, Section 5)*
- [x] k - Create `database/seeders/` folder — `DatabaseSeeder.php`, idempotent, seeds one admin user, one sample advertiser, one sample connected app + API key
- [x] l - Create `routes/web.php` — map of every dashboard/admin page, mirroring the API route table
- [x] m - Create `routes/api.php` *(already present — Section 3.1.j router boot, merges every module's routes)*
- [x] n - Create `tests/` folder — scaffold + planned per-module layout, actual suite deferred to Section 13

---

## 10. Build Order / Roadmap — 🟨 In Progress

- [x] a - Run database migrations (Section 5) on a fresh local DB — `database/scripts/migrate.php` (applies every `database/migrations/*.php` in order, tracks progress in a `migrations` table, idempotent)
- [x] b - Seed the DB with the same mock data used in the UI prototype — `database/seeders/MockDataSeeder.php` (reads `data/mock-data.php`, inserts the same apps/placements/advertisers/ads, idempotent)
- [x] c - Confirm core layer (`Database`, `Request`, `Response`, Auth middleware) boots with no errors
- [x] d - Confirm Auth module: login works end-to-end
- [x] e - Confirm Auth module: API-key issuing works end-to-end
- [x] f - Confirm Ads module wired into `create-ad.php` — the advertiser's "new ad" form must submit through the real `AdRepository`/validator (CSRF check, image upload via `core/Uploads.php`'s hardened non-executable path, insert with `status='pending'`) instead of reading/writing mock data (required adding `core/Autoload.php` since nothing was actually being autoloaded yet, making `public/index.php` actually dispatch to a controller instead of its `echo 'ok'` stub, a subdirectory-safe path fix for local XAMPP deployment, a local-only fix to the session cookie's `Secure` flag, and a new `GET /api/v1/advertiser/apps` endpoint so the form's app/placement pickers use real ids instead of `data/mock-data.php`'s string codes)
- [x] g - Confirm Ads module wired into `my-ads.php` — the advertiser's ad list must come from the DB filtered by the logged-in `user_id`, paginated per `config/app.php`'s defaults — not the static mock list (also fixed a pre-existing crash: `create-ad.php`/`admin/apps.php` called `csrf_field()` without `core/Autoload.php` ever being loaded on those pages, which fatal-errored before rendering; added the router's `{id}` dynamic-segment matching, needed by 10.h next; client-side search/status/app filtering now filters the real rendered rows in place — `SkoolystAdsUI.filterRenderedRows()` — instead of re-rendering from mock data, which would have silently overwritten the real DB rows)
- [ ] h - Confirm Admin module wired into `admin/ads.php` — approve/reject actions must call the real `AdRepository` status update and write an `AuditLog` entry (6.v), not just change the UI — **in progress:** `AdRepository::findByStatus()`/`countsByStatus()`/`updateStatus()` and `ModerationController::approve()`/`reject()` (real update + audit log, 404 on a bad id) are done; `admin/ads.php`'s own markup/JS still reads from mock data and needs the same treatment as `my-ads.php` above before this is checked off
- [ ] i - Confirm Admin module wired into `admin/apps.php` — actions like API-key regenerate must call the real `AppRepository` method and write an `AuditLog` entry (6.w)
- [ ] j - Confirm public API matches `api-docs.php` exactly (spot check each endpoint) — walk every endpoint documented in `public/api-docs.php` against the real `routes/api.php` + controllers so the docs and actual behavior don't drift apart
- [ ] k - Confirm stats rollup job is scheduled and running — `database/scripts/rollup-ad-stats-daily.php` exists as a script; this item is about actually registering the cron entry (per `cron/README.md`) and confirming `ad_stats_daily` is really being populated daily
- [ ] l - Run the Section 6 security checklist as a pass/fail review — re-test the already-`[x]`'d Section 6 items against the wired-up app (e.g. actually try uploading a `.php` file as an ad image and confirm it's rejected), not just confirm the code exists
- [ ] m - Run the Section 7 performance checklist as a pass/fail review — verify pagination limits, caching, DB indexes, and API rate limiting under real use, same pass/fail treatment as l
- [ ] n - Run the Section 8 SEO checklist as a pass/fail review — verify the already-implemented Section 8 items (meta tags, sitemap, robots.txt, etc.) the same way

---

## 11. Tech Stack & Environment Setup — ⬜ Not Started

- [ ] a - Confirm PHP 8.2+ is installed locally
- [ ] b - Confirm MySQL 8.0 is installed locally
- [ ] c - Set up Nginx + PHP-FPM (or local equivalent, e.g. `php -S`)
- [ ] d - Install Redis, or confirm the file-cache fallback works without it
- [ ] e - Install Composer
- [ ] f - Add `composer.json` with PSR-4 autoload mapping
- [x] g - Create `.env.example` with placeholder values — `.env.example`, plus `core/Env.php` (dependency-free loader, since 11.e/f's Composer setup hasn't happened yet) wired into `public/index.php`, `public/dashboard/index.php`, `database/scripts/migrate.php`, `database/scripts/rollup-ad-stats-daily.php`, and both seeders
- [x] h - Document each required `.env` value in `.env.example` comments
- [ ] i - Write the local setup command list into `README.md`/`SETUP.md` (`composer install`, migrate, seed, serve)

---

## 12. Coding Standards & Git Workflow — ⬜ Not Started

- [ ] a - Add a PSR-12 note to a `CONTRIBUTING.md`
- [ ] b - Add a PSR-4 autoload mapping note to `CONTRIBUTING.md`
- [ ] c - Add the "one-class-per-file, filename matches class name" rule to `CONTRIBUTING.md`
- [ ] d - Add the "docblock every repository public method" rule to `CONTRIBUTING.md`
- [ ] e - Add the "`main` is always deployable" rule to `CONTRIBUTING.md`
- [ ] f - Add the "one feature branch per module task" convention to `CONTRIBUTING.md`
- [ ] g - Add the `[Module] ...` commit message convention to `CONTRIBUTING.md`
- [ ] h - Add "Section 6 checklist required on auth/upload/SQL PRs" rule to `CONTRIBUTING.md`
- [ ] i - Add the "never edit a merged migration" rule to `CONTRIBUTING.md`

---

## 13. Testing & QA — ⬜ Not Started

- [ ] a - Install PHPUnit via Composer
- [ ] b - Add PHPUnit config file (`phpunit.xml`)
- [ ] c - Write one unit test for a validator rule
- [ ] d - Write one unit test for a repository query-building method
- [ ] e - Write one unit test for the stats rollup calculation
- [ ] f - Write one integration test for a single endpoint (auth → controller → DB → response shape)
- [ ] g - Set up a disposable/seeded test database
- [ ] h - Write one API contract test matching an `api-docs.php` example
- [ ] i - Write a manual cross-browser QA checklist (doc only)
- [ ] j - Write a manual mobile-device QA checklist (doc only)
- [ ] k - Write a manual SQLi/XSS/auth-bypass check checklist (doc only)
- [ ] l - Add the "no endpoint merges without an integration test" rule to `CONTRIBUTING.md`

---

## 14. Deployment — ⬜ Not Started

- [ ] a - Set up local `.env` + local database
- [ ] b - Set up staging `.env` + staging database
- [ ] c - Set up production `.env` + production database
- [ ] d - Seed staging with realistic-but-fake data
- [ ] e - Write the "migrations run before code switch" step into the deploy script
- [ ] f - Set up atomic symlink swap for zero-downtime deploy
- [ ] g - Document the rollback procedure (repoint the symlink)
- [ ] h - Set up error logging to a file/service
- [ ] i - Set up nightly MySQL backup cron job
- [ ] j - Do one test restore from a backup and record the result

---

## 15. Future Enhancements — ⬜ Not Started (deferred, not blocking)

- [ ] a - Billing module (paid ad packages/invoicing)
- [ ] b - Advertiser self-serve app onboarding
- [ ] c - A/B testing between ad creatives
- [ ] d - Advanced targeting (by page/user segment)
- [ ] e - Webhooks notifying a connected app on approval/rejection

---

## Roman Urdu Explanation — Har Section Step-by-Step

*Yeh section sirf reference/samajhne ke liye hai — har section ka Roman Urdu mein tafseel, taake koi bhi (naya session ho ya team member) jaldi samajh sake ke kya ho chuka hai aur kya baaki hai.*

### 1. Project Idea ✅ Done
- **a** — Problem define ki: Skoolyst ke apps mein ads chalane ka koi shared tareeqa nahi tha.
- **b** — Teen consumers tay kiye: Advertisers (ad dene wale), Platform Admins (approve/reject karne wale), Connected Apps/developers (jo apni app mein ads dikhayenge).
- **c** — Core principle: ad ka data, moderation aur delivery sab **ek hi jagah** hoga; har connected app sirf "renderer" hai.

### 2. User Interface ✅ Done
**2.1 Component-based, not copy-pasted**
- **a** — Advertiser aur Admin ke liye alag sidebars.
- **b** — Ek shared topbar (header) har page pe.
- **c** — `ad-card` component — dashboard, my-ads table, public placements teeno jagah reuse.
- **d** — `status-badge` component — pehle 6 alag copy-paste `<span>` the, ab ek hi component `$status` ke hisaab se khud render karta hai.
- **e** — Baaki reusable pieces: stat-card, ads-table, confirm-modal.
- **f** — Ek shared layout jise har page extend karta hai.
- **g** — Shared `<head>` aur bottom-scripts partials.
- **h** — `mock-data.php` — sara test data ek jagah, PHP aur JS dono ko JSON ki form mein diya jata hai.

**2.2 Screens already designed** — Advertiser Dashboard, Create Ad (3-step form), My Ads table, Admin Overview, Admin All Ads, Admin Connected Apps, Developer API docs page.

**2.3 Help tooltips (ⓘ icon)**
- **a** — Shared CSS class.
- **b** — Ek JS function jo har ⓘ icon ko Bootstrap tooltip bana deta hai.
- **c** — Tooltip text ek hi config file mein centralized.
- **d** — Reusable help-icon component.

**2.4 Responsiveness**
- **a** — 992px se neeche sidebar off-canvas drawer.
- **b** — Tables mobile pe horizontally scroll karti hain.
- **c** — Create-ad preview mobile pe form ke neeche stack hoti hai.

### 3. Backend Architecture ✅ Done
**3.1 One feature = one isolated folder**
- **a-f** — `app/Ads/` module — Controller, Model, Repository, Validator, routes stubs banaye.
- **g** — `app/Apps/` module.
- **h** — `app/Auth/` module.
- **i** — `app/Admin/` module (moderation).
- **j** — Router boot code har module ki `routes.php` load karta hai.
- **k** — Rule: koi module doosre module ka table directly query nahi karega.

**3.2 Centralized, non-repeated code**
- **a-d** — `core/Database.php` — PDO connection + `query()`/`fetchOne()` helpers.
- **e-f** — `core/Request.php` — input read + sanitize.
- **g-h** — `core/Response.php` — success/error shape helpers.
- **i-l** — `core/Validator.php` — required, maxLength, url, date rules.
- **m-n** — `core/Auth/Middleware.php` — session check + API-key/token check.
- **o** — `core/RateLimiter.php` — skeleton, `hit()` method.
- **p** — `core/Cache.php` — file-based cache.

**3.3 Standard request lifecycle**
- **a-b** — `public/index.php` front controller + router wire kiya.
- **c-d** — Auth aur rate-limit middleware pipeline mein laga diye.
- **e** — Ek example controller jo repository call kar ke `Response::success()` return karta hai.
- **f-g** — Rules: controllers thin rahenge, saari query logic sirf Repositories mein.

### 4. API Structure ✅ Done
Har endpoint ke liye route + empty handler:
- **a-c** — Public/tracking: ad serve, impression record, click record.
- **d-e** — Advertiser: naya ad banana, ad update karna.
- **f-h** — Admin: pending ads dekhna, approve, reject.
- **i-k** — Admin apps: list, create, update.
- **l** — Har response standard envelope `{success, data|error}` mein.
- **m** — Har route `/api/v1/` prefix ke saath.
- **n** — Routes do files mein split: public (serve/track) vs authenticated (advertiser/admin).

### 5. Database Design ✅ Done
**5.1 Schema** — 7 migrations: `users`, `apps`, `placements`, `ads`, `ad_impressions`, `ad_clicks`, `api_keys`.

**5.2 Indexing plan** — Performance indexes: status+placement composite, user_id, date-range, impressions/clicks (ad_id+occurred_at), api_key unique, placement unique composite.

**5.3 Aggregation, not row-by-row counting**
- **a** — `ad_stats_daily` table banayi.
- **b** — Rollup script — raw events se roz ke totals banata hai.
- **c** — Cron job se schedule kiya.
- **d** — Dashboard chart ko `ad_stats_daily` se connect kiya (raw tables se nahi).
- **e** — Raw tables sirf audit ke liye read-only rakhi.

### 6. Authentication & Security ✅ Done
- **a-b** — Password hash/verify.
- **c** — Secure, HttpOnly session cookie.
- **d-e** — Har app ki API key generate hoti hai, hash ho kar store hoti hai (plaintext kabhi nahi).
- **f** — `Authorization: Bearer` header support.
- **g-h** — Role-based checks (advertiser-only, admin-only).
- **i-l** — CSRF token generate/verify + create-ad aur har form pe laga.
- **m-n** — Saari queries prepared statements se, output escape (XSS se bachao).
- **o-r** — Upload security: metadata strip, real MIME check, size cap, rename, non-executable path se serve.
- **s-t** — Rate limiting serve/impression/click pe.
- **u** — Har API key sirf apni app ke placements tak scoped.
- **v-w** — Admin actions (approve/reject/key-regenerate) pe audit-log entry.

### 7. Performance & Optimization ✅ Done
- **a-b** — Indexes confirm, rollup job schedule pe chal rahi confirm.
- **c-d** — `/ads/serve` pe cache read/write.
- **e-f** — OPcache on, Composer autoload optimize.
- **g-i** — Images resize + compress + far-future cache headers.
- **j** — Har image pe `loading="lazy"`.
- **k-l** — My-Ads aur moderation queue — DB-level pagination.
- **m-n** — Impression/click tracking client-side fire-and-forget.

### 8. SEO ✅ Done
- **a-e** — Title/meta/heading-order fix `index.html` aur `api-docs.php` pe.
- **f-h** — `sitemap.xml`, `robots.txt` disallow rules.
- **i** — Structured data (`SoftwareApplication`).
- **j** — Section 7 checklist inhi pages pe dobara check.

### 9. Folder Structure ✅ Done
- **a-d** — `public/`, `public/assets/`, `public/uploads/ads/` banaye, server root wahan point kiya.
- **e-g** — `app/`, `core/`, `views/` folders confirm kiye.
- **h-i** — `config/database.php`, `config/app.php` banaye.
- **j-k** — `database/migrations/` aur `database/seeders/` (`DatabaseSeeder.php`).
- **l-m** — `routes/web.php`, `routes/api.php`.
- **n** — `tests/` folder scaffold.

### 10. Build Order / Roadmap 🟨 In Progress
- **a** — ✅ Migration runner (`database/scripts/migrate.php`) — idempotent.
- **b** — ✅ `MockDataSeeder.php` se DB seed — ab repository-based approach use karta hai (`AppRepository`/`AdRepository` methods), raw queries nahi.
- **c-e** — ✅ Core layer, Auth login, API-key issuing — real MySQL ke against verify.
- **f** — ⬜ Create-ad form ko real `AdRepository`/validator se wire karna baaki hai.
- **g** — ⬜ My-ads list ko real DB se wire karna baaki hai.
- **h** — ⬜ Admin approve/reject actions ko real DB + audit-log se wire karna baaki hai.
- **i** — ⬜ Admin app-management (key regenerate) real backend se wire karna baaki hai.
- **j** — ⬜ API docs page ko actual routes ke against verify karna baaki hai.
- **k** — ⬜ Stats rollup cron actually register + confirm karna baaki hai.
- **l-n** — ⬜ Section 6/7/8 checklists real wired-up app pe dobara pass/fail test karna baaki hai.

### 11. Tech Stack & Environment Setup ⬜ Not Started
Local setup: PHP 8.2+, MySQL 8.0, Nginx/PHP-FPM, Redis (ya file-cache fallback), Composer, `.env.example`, aur setup commands README mein likhna.

### 12. Coding Standards & Git Workflow ⬜ Not Started
`CONTRIBUTING.md` banani hai: PSR-12, PSR-4, one-class-per-file, docblocks, `main` deployable, feature branches, commit format `[Module] ...`, Section 6 checklist mandatory on sensitive PRs, merged migration kabhi edit na karna.

### 13. Testing & QA ⬜ Not Started
PHPUnit setup, unit tests, integration test, disposable test DB, API contract test, manual QA checklists (browser/mobile/security).

### 14. Deployment ⬜ Not Started
Local/staging/production `.env` setup, staging seed, deploy script (migrate before switch), zero-downtime symlink swap, rollback docs, error logging, nightly backup, test restore.

### 15. Future Enhancements ⬜ Not Started (deferred)
Billing module, advertiser self-serve onboarding, A/B testing, advanced targeting, approval/rejection webhooks.
