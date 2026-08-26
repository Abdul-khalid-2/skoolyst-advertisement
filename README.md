# Skoolyst Ads — AdEngine

A centralized advertisement engine for the Skoolyst family of apps. One dashboard to create and manage ads, one admin panel to moderate them, and one API that any connected app (`skoolyst.com`, `social.skoolyst.com`, `teachers.skoolyst.com`, and outside apps like Jaans Fabrics or Safi India Autos) calls to fetch and report on ads — instead of every project hardcoding its own ad logic.

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
| 8 | SEO | ⬜ Not Started |
| 9 | Folder Structure | ⬜ Not Started |
| 10 | Build Order / Roadmap | ⬜ Not Started |
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

## 8. SEO — ⬜ Not Started

- [x] a - Add descriptive `<title>` to `index.html`
- [x] b - Add `<meta description>` to `index.html`
- [ ] c - Add descriptive `<title>`/`<meta description>` to `api-docs.php`
- [ ] d - Check/fix heading order (h1→h2→h3) on `index.html`
- [ ] e - Check/fix heading order (h1→h2→h3) on `api-docs.php`
- [ ] f - Generate `sitemap.xml` for public pages
- [ ] g - Write `robots.txt` disallowing `/dashboard/`
- [ ] h - Add `/admin/` and `/api/` disallow rules to `robots.txt`
- [ ] i - Add `SoftwareApplication` structured data to `index.html` (optional, if page grows)
- [ ] j - Re-run the Section 7 performance checklist specifically against `index.html`/`api-docs.php`

---

## 9. Folder Structure — ⬜ Not Started

- [ ] a - Create `public/` folder
- [ ] b - Move/point web server root at `public/`
- [ ] c - Create `public/assets/` folder
- [ ] d - Create `public/uploads/ads/` folder
- [ ] e - Confirm `app/` module folders exist *(tracked in 3.1)*
- [ ] f - Confirm `core/` folder exists *(tracked in 3.2)*
- [ ] g - Confirm `views/` folder is unchanged *(already done — Section 2)*
- [ ] h - Create `config/database.php`
- [ ] i - Create `config/app.php`
- [ ] j - Create `database/migrations/` folder
- [ ] k - Create `database/seeders/` folder
- [ ] l - Create `routes/web.php`
- [ ] m - Create `routes/api.php`
- [ ] n - Create `tests/` folder

---

## 10. Build Order / Roadmap — ⬜ Not Started

- [ ] a - Run database migrations (Section 5) on a fresh local DB
- [ ] b - Seed the DB with the same mock data used in the UI prototype
- [ ] c - Confirm core layer (`Database`, `Request`, `Response`, Auth middleware) boots with no errors
- [ ] d - Confirm Auth module: login works end-to-end
- [ ] e - Confirm Auth module: API-key issuing works end-to-end
- [ ] f - Confirm Ads module wired into `create-ad.php`
- [ ] g - Confirm Ads module wired into `my-ads.php`
- [ ] h - Confirm Admin module wired into `admin/ads.php`
- [ ] i - Confirm Admin module wired into `admin/apps.php`
- [ ] j - Confirm public API matches `api-docs.php` exactly (spot check each endpoint)
- [ ] k - Confirm stats rollup job is scheduled and running
- [ ] l - Run the Section 6 security checklist as a pass/fail review
- [ ] m - Run the Section 7 performance checklist as a pass/fail review
- [ ] n - Run the Section 8 SEO checklist as a pass/fail review

---

## 11. Tech Stack & Environment Setup — ⬜ Not Started

- [ ] a - Confirm PHP 8.2+ is installed locally
- [ ] b - Confirm MySQL 8.0 is installed locally
- [ ] c - Set up Nginx + PHP-FPM (or local equivalent, e.g. `php -S`)
- [ ] d - Install Redis, or confirm the file-cache fallback works without it
- [ ] e - Install Composer
- [ ] f - Add `composer.json` with PSR-4 autoload mapping
- [ ] g - Create `.env.example` with placeholder values
- [ ] h - Document each required `.env` value in `.env.example` comments
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
