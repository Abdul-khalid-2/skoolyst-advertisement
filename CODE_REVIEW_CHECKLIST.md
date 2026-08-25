# Code Review Checklist

Checked on every pull request before merge.

- [ ] No module queries another module's tables directly — cross-module data access goes only through that module's public repository methods (e.g. Ads calls `AppRepository::findByApiKey()`, never queries the `apps` table itself).
- [ ] Controllers stay thin — they validate input, call one repository method, and return a response. No query logic, no business logic beyond that, in a controller.
- [ ] All query logic lives in Repositories only — a Model holds data shape, a Controller orchestrates, but the SQL itself is written in the matching Repository class and nowhere else.
- [ ] Every API handler's response goes through `Response::success()`/`Response::error()` — no `echo`/`json_encode` calls bypassing the shared `{ success, data | error }` envelope.
