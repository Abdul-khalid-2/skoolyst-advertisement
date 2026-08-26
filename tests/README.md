# Tests

Folder scaffold for Section 9.n — the actual test suite (framework
choice, test cases, CI wiring) is Section 13's job, not this one.

Planned layout, mirroring `app/`'s one-feature-per-folder structure
(3.1) so a module's tests sit next to the module they cover in name,
even though the code itself stays in `app/`:

```
tests/
  Ads/         — AdRepository, AdValidator, ads.serve/impression/click
  Apps/        — AppRepository (API-key issue/hash/regenerate)
  Auth/        — UserRepository, Middleware (session + API-key checks)
  Admin/       — moderation actions, audit-log writes
  Core/        — Database, Request, Response, Validator, RateLimiter, Cache
```

Nothing in here runs yet — no framework is wired up until Section 13
picks one (PHPUnit is the default assumption, per `composer` already
being the dependency manager per `deploy.sh`, but that's confirmed,
not decided, in this section).
