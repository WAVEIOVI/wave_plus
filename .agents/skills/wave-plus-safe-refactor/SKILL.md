---
name: wave-plus-safe-refactor
description: Audits, debugs, fixes, cleans, or refactors existing WAVE PLUS code while preserving business behavior, API contracts, database compatibility, permissions, and project structure. Use for bug fixes, cleanup rounds, code reviews, migrations, and regression repair.
---

# WAVE PLUS Safe Audit and Refactor

Use this skill for existing-code fixes, cleanup passes, security remediation, and quality audits. The goal is the smallest safe change that fixes the root cause—not a broad rewrite.

## 1. Establish facts before editing

- Inspect git status/diff and identify user work that must remain untouched.
- Trace the reported symptom to an executable path. Read the caller, callee, route/middleware, validation, data model, UI state, and API contract as applicable.
- Reproduce with a focused test, existing test, or deterministic manual check before changing code when practical.
- Search all consumers of the target function, component, field, event, route, permission, or response shape before changing its contract.
- Compare intended behavior against explicit business rules and tests, not just against the current implementation.
- Classify findings as confirmed bug, likely risk, design debt, or style improvement. Do not state an unverified suspicion as a proven vulnerability.

## 2. Fix the root cause, not the symptom

1. Write down the expected invariant and the failing case.
2. Identify the narrowest layer that owns the behavior (backend authorization, domain service, API resource, state store, or presentation component).
3. Add a regression test that fails before the fix where feasible.
4. Implement the smallest coherent fix at the owning layer; update callers if the contract must change.
5. Add boundary/error handling and tests for adjacent edge cases.
6. Re-run the regression test and the relevant neighboring tests.

Examples: do not repair unauthorized access only by hiding a button; do not fix a stale search result only with a spinner; do not fix wrong totals by patching text in a Vue component; do not fix a broken deployed schema by editing an old migration without checking migration history.

## 3. Refactor boundaries

- Preserve current behavior unless the task explicitly asks for a behavior change. If the behavior is a bug, document the intended corrected behavior.
- Avoid unrelated file moves, folder renames, broad formatting, package upgrades, global auto-fixes, mass component rewrites, or API renames.
- Do not remove code merely because text search shows no direct usage. Check auto-imports, dynamic imports, route discovery, string-based names, events, permission metadata, tests, scheduled jobs, and external API consumers.
- Do not duplicate business logic as a shortcut. Consolidate only when an authoritative reusable abstraction improves clarity without broad regressions.
- For shared components/composables, inspect all consumers and preserve backward compatibility or migrate all affected consumers within scope.
- For authorization/authentication, role, session/token, and permission changes, trace both browser and API flows. Avoid incidental architecture switches.
- For migrations, data cleanup, or identifier changes, inspect existing environments/data first. Prefer additive, reversible changes and test migration upgrades; do not assume fresh-install success means safe upgrade.

## 4. Security review checklist

For sensitive endpoints/features, explicitly check:
- Authentication and account-active state on each relevant request.
- Backend role/permission checks and object-level ownership/scope.
- Trust boundaries: never trust client-supplied identity, roles, device IDs, timestamps, totals, approval flags, or status transitions without server verification.
- Validation and rate limiting for public or high-risk operations.
- Duplicate/replay handling, transactions, concurrency, and idempotency where relevant.
- Safe client-facing errors; diagnostics belong in server logs without secrets.
- Token/session expiry, revocation, and CSRF/XSS considerations if auth is affected.
- Regression tests for both allowed and denied cases.

## 5. Verification discipline

- Run a focused test first and then appropriate broader tests/checks (`php artisan test --filter=<Name>`, `npm run test:shared-ui`, `npm run build`).
- Avoid running auto-fix commands across the repository for a narrow repair. In this project, `npm run lint` and `composer pint` mutate code; prefer targeted non-mutating checks (`npm run lint:check` or `npx eslint <target> -c .eslintrc.cjs`, `composer pint:test`) when feasible and inspect every diff.
- Distinguish syntax/static validation from actual integration tests. A PHP syntax pass does not prove routes work; a successful frontend build does not prove the page behavior is correct.
- If tests require unavailable dependencies/services or a database that is not available, say exactly what could not be run.
- After edits, inspect the diff for unrelated changes, secrets, generated assets, debug code, duplicate files, and accidental schema/API changes.

## Required final report

Use this compact structure:

1. **Verdict:** fixed / partially fixed / not fixed, with reasons.
2. **Root cause:** what made the behavior fail.
3. **Changes:** file paths and what each change does.
4. **Verification:** exact test/check commands and pass/fail/blocker results.
5. **Compatibility/security:** API, permissions, data/migrations, and consumers affected.
6. **Remaining issues:** only real uncompleted work; prioritize by severity.

Never report an issue as fixed solely because the code was edited. Require verification appropriate to the risk.
