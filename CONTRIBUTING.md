# Contributing to Skoolyst Ads

Conventions this codebase already follows — written down so they stay
consistent as more people (or more sessions) touch it. Each rule below
notes where it's already visible in the repo, not just an aspiration.

## Code style — PSR-12

Follow [PSR-12](https://www.php-fig.org/psr/psr-12/): 4-space indent, one
statement per line, opening braces on their own line for classes/methods,
`declare(strict_types=1)` not required project-wide but don't fight it
where it's already present. Nothing exotic — this is standard PHP
formatting, and `app/`/`core/` already follow it.

## Autoloading — PSR-4

`composer.json` maps:

```json
"App\\": "app/",
"Core\\": "core/"
```

This mirrors `core/Autoload.php`'s manual mapping (kept as the current
runtime autoloader — see that file's own doc-block for why both exist
side by side). New classes go under the matching namespace/folder:
`App\Ads\...` → `app/Ads/`, `Core\...` → `core/`. Don't add a class that
doesn't fit one of these two roots without discussing the mapping first.

## One class per file, filename matches class name

`AdRepository` lives in `AdRepository.php`, `AdController` in
`AdController.php`, and so on — every file under `app/` and `core/`
already follows this. No multi-class files, no mismatched names. This
is also what makes PSR-4 autoloading work at all, so it's not just
style — breaking it breaks the autoloader.

## Docblock every repository public method

Every `*Repository` class's public methods get a docblock explaining
what the method returns and any non-obvious constraint (e.g. "always
scoped by `$appId`", "results are paginated, see `$page`/`$perPage`").
`AdRepository.php`'s methods are the reference example — follow that
level of detail, not just a one-line `@return` stub.

## `main` is always deployable

Nothing broken merges to `main`. If a change is incomplete, it stays on
its branch until it's actually working — no "fix in a follow-up commit"
merged into `main` in a known-broken state. This project's own testing
pattern (verify live before committing — real DB, real HTTP requests,
real browser where relevant, not just reading code) is what makes this
practical to hold to.

## One feature branch per module task

Branch per task, scoped to one module/feature at a time (e.g.
`claude-00` in this repo's history has been used as a single long-lived
work branch per the project owner's explicit instruction — the
convention going forward for new contributors is a fresh branch per
task, merged or rebased when done, rather than accumulating unrelated
work on one branch).

## Commit message convention: `[Module] ...`

Every commit starts with the module/area it touches in square brackets,
followed by a short description of what changed and why (not just
"fix bug"). This repo's own history is the reference:

```
[Security] Fix CSRF middleware wrongly blocking public routes
[Ads] Fix impression/click column bug, verify stats rollup end-to-end
[Docs] Record 10.l/10.m/10.n findings; fix missing image cache headers
[Auth/Ads] Add login.php/signup.php pages, /api/v1/auth/session endpoint
```

Multiple modules touched at once → `[ModuleA/ModuleB]`. Keep the
description specific enough that `git log --oneline` alone tells you
what actually changed, without opening the diff.

## Section 6 checklist required on auth/upload/SQL PRs

Any change touching authentication, file uploads, or raw SQL must be
checked against the [Section 6 (Authentication & Security)](README.md#6-authentication--security)
checklist before merging — not just "looks right," but re-tested the
way Section 6 items were originally verified (real requests, real file
uploads, not just code review). Two real bugs (CSRF middleware
exemption logic, a no-op `RateLimiter` stub) were only caught this way,
not by reading the code — see the [Bug Fixes](README.md#bug-fixes-manual-qa)
log for both. That's the bar: a PR touching this surface without a
live re-test isn't done yet.

## Never edit a merged migration

Once a migration file has been applied anywhere (even just locally) and
committed, it's immutable — don't edit it to "fix" something, write a
new migration instead. `database/scripts/migrate.php` tracks which
migrations have already run; editing an applied migration file changes
nothing for environments that already ran the old version, silently
creating drift between them. If a column/index was wrong, add a new
migration that corrects it.
