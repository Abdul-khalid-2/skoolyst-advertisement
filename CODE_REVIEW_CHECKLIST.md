# Code Review Checklist

Checked on every pull request before merge.

- [ ] No module queries another module's tables directly — cross-module data access goes only through that module's public repository methods (e.g. Ads calls `AppRepository::findByApiKey()`, never queries the `apps` table itself).
