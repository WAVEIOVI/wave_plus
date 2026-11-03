# WAVE PLUS — Project Engineering Rules

These instructions apply to every coding agent working in this repository. Follow them for new modules, features, bug fixes, refactors, tests, and UI work. Task-specific skills under `.agents/skills/` explain the workflow; this file defines always-on project constraints.

## 1. Project realities and architecture

- Backend: PHP 8.2+, Laravel 12, Sanctum, Spatie Laravel Permission, Spatie Translatable, Laravel Query Builder, Media Library, and Browsershot where already used.
- Frontend: Vue 3 Composition API with `<script setup>`, Vuexy, Vuetify 3, Pinia, Vue Router auto-routing, Vite, Vue I18n, and the existing project design tokens/styles.
- Backend business modules live under `app/Modules/` (current examples include `BusinessPartners`, `Catalog`, `ClientAssets`, `Crm`, `Finance`, `Hr`, `Inventory`, `Logistics`, `OperationalHub`, `Procurement`, and `Shared`). Inspect the actual folder and namespace before adding a module file.
- API module routes commonly live in `app/Modules/<Module>/routes/api.php` and are explicitly required from `routes/api.php`. Verify registration before assuming a route is active.
- Auto-routed pages live under `resources/js/pages/`; route generation is configured in `vite.config.js` using `unplugin-vue-router`. Follow nearby `definePage()` metadata and navigation conventions.
- Larger page/view implementations are also under `resources/js/views/`. Shared Vue components live under `resources/js/components/shared/`; composables under `resources/js/composables/`; Pinia stores under `resources/js/stores/`; styles under `resources/styles/`.
- Vuexy internals live under `resources/js/@core/`. Prefer app-level wrappers/extensions. Do not edit or replace Vuexy core files unless the task genuinely requires it and existing extension points have been checked.
- The current repo contains both `$api` usage (from `resources/js/utils/api.js`) and an Axios-compatible wrapper (`resources/js/utils/axios.js`). Do not assume every feature uses Axios, introduce another HTTP client, or rewrite clients globally. Follow the closest working feature/store and preserve its response/error contract.
- Active agent skills live under `.agents/skills/<skill-name>/SKILL.md` (e.g. `wave-plus-module-development`, `wave-plus-frontend-engineering`, `wave-plus-safe-refactor`, and `wave-plus-verification`). Always inspect the corresponding skill for workflow instructions before starting a task.

Read `docs/agent/WAVE_PLUS_ENGINEERING_REFERENCE.md` when working on module boundaries, standard page families, shared UI components (`Wp*`), composables, or verification patterns. Verify paths against the live tree; docs are guidance, not proof that a component exists or is correct.

## 2. Source-of-truth order

When instructions or implementations conflict, use this order:

1. The user's explicit requirement and confirmed business rules for the current task.
2. Current database/API contracts, security constraints, tests, and demonstrated production behavior.
3. This file and the project references under `docs/agent/`.
4. The nearest comparable implementation that is verified to work and is consistent with the above.
5. Generic framework recommendations or legacy code.

Do not blindly copy old/dirty modules, generated code, Vuexy demos, or a neighbouring implementation with known bugs. Reuse its useful patterns, not its defects. If a conflict cannot be resolved safely, preserve behavior, state the conflict, and avoid speculative redesign.

## 3. Work safely and keep the scope narrow

- Inspect current git status or file state before editing. In workspace directories where `.git` is not directly initialized, take extra caution not to overwrite user edits, never delete or revert work you did not author, and never run destructive clean/reset operations.
- Trace the full affected flow before changing it: route → middleware/policy → request validation → controller/service/model/resource → database; and page → store/API client → component/composable → translations.
- Search for existing components, composables, stores, validation, authorization, and business logic before creating another version.
- Prefer a minimal coherent patch. Edit existing files when appropriate; create new files only when the architecture benefits. No unrelated cleanup, mass renaming, speculative abstractions, or project-wide formatting.
- Preserve existing public API shapes, route names, route metadata, IDs, relationships, permissions, and user workflows unless the task explicitly requires a documented migration.
- Do not claim that code, tests, migrations, builds, screenshots, or browser behavior have been verified unless they were actually checked. Clearly report environment blockers and remaining uncertainty.

## 4. Security and data integrity are invariants

- Enforce authentication, authorization, tenancy/scope, validation, and state transitions on the backend. Hiding a frontend control is never authorization.
- Use existing Laravel middleware, policies/gates, Spatie permissions, Form Requests, transactions, and API resources where they fit the established module. Do not rely on client-supplied roles, device flags, timestamps, totals, status, ownership, or identity as proof of trust.
- Never expose stack traces, SQL, secrets, internal exception text, or sensitive fields in API responses or logs visible to users.
- Never introduce passwords, API keys, `.env` values, personal data, production database dumps, or real log contents into source control, generated reports, or test fixtures.
- Treat business transactions as stateful workflows. Enforce legal transitions and cross-module contracts on the backend; prevent duplicate submissions and use transactions for atomic multi-record updates.
- Database safety: do not delete, rename, or rewrite an existing migration merely to make a fresh install pass. Assume a migration may already have run in another environment. Prefer a new, reversible migration; preserve existing data and identifiers. Only consolidate historical migrations after explicitly verifying the target is a disposable pre-release database and that no deployed/shared database depends on that history.
- Before destructive data/schema changes, inspect foreign keys, indexes, existing data, references, and upgrade/rollback behavior. Do not run destructive commands against a user database without explicit authorization.
- Do not change authentication architecture, token/session storage, CASL, or role semantics as an incidental part of another task.

## 5. Frontend standards

- Use Vue 3 Composition API and `<script setup>` following nearby app code. Use Vuetify/Vuexy components and project SCSS tokens; do not introduce Tailwind, another component library, a new router, a new form library, or a global design system without explicit approval.
- Follow existing auto-import conventions; check `vite.config.js`, `auto-imports.d.ts`, and component registration before adding imports or changing configuration.
- Keep reusable `Wp*` presentation components generic: configurable props, clear emits/slots, no hard-coded domain API calls, and no duplicated domain calculations. Keep domain-specific rules in the module/service/store that owns them.
- Use Pinia and the existing API client convention for the feature. Keep request state, errors, cancellation/race handling, and response normalization consistent with the selected endpoint contract.
- User-facing text must use the existing Vue I18n/localization conventions. Do not hardcode interface labels or add a second translation mechanism.
- Mobile-first is mandatory: no horizontal overflow, cramped dialogs, hover-only actions, inaccessible tiny icon buttons, or desktop-only data workflows. On mobile, use responsive cards/list patterns where the standard table becomes impractical.
- Lists should use server-side search, filtering, sorting, and pagination when the endpoint supports them. Never load an unbounded entity collection solely to filter it in the browser. Empty, loading, error/retry, no-results, and permission states must be considered.
- Reuse existing shared dialog, page-header, breadcrumb, tab, table, status, loading, and confirmation patterns after checking their actual APIs. Do not create a duplicate because an existing component is imperfect; improve it safely or document a scoped reason for a new one.

## 6. Incremental module delivery policy

- Develop WAVE PLUS module-by-module. Do not hold the roadmap hostage to cosmetic polish or every non-critical edge case. The user explicitly accepts moving forward with a controlled backlog of roughly 10–15% remaining improvements when the core module is functional.
- Before recommending the next module, make sure the current module's core workflows, persistence/API contract, access control, and required cross-module integrations work; no known P1 security/data-integrity defect or release-blocking build/test failure may be silently carried forward.
- Separate findings into: **Blocker** (critical security, data corruption, broken core workflow, incompatible contract, migration risk that can damage existing data, or reproducible build failure), **Before module sign-off** (important functional gaps), and **Backlog** (polish, secondary features, non-blocking UX improvements).
- Do not label a whole module “unfinished” only because backlog items remain. Give the user a clear go/no-go recommendation and identify exactly which remaining issues block progression versus can be revisited in the planned consolidation round.

## 7. Verification and completion report
 
- Add/update tests for changed behavior, especially authorization, validation, relationships, status transitions, API contracts, and regression cases.
- Run the smallest relevant test first, then broader tests and builds when feasible. Available project verification commands include:
  - `php artisan test --filter=<Name>` (targeted backend tests)
  - `php artisan test` (full backend test suite)
  - `npm run test:shared-ui` (Node native unit tests for frontend shared composables)
  - `npm run lint:check` (non-mutating ESLint check across JavaScript and Vue code)
  - `npm run build` (Vite production bundle build)
  - `composer pint:test` (PHP code style check) and `composer pint` (code style auto-fix)
  - Targeted PHP syntax checks using `php -l <file>`
- Beware that the configured `npm run lint` includes `--fix` across the project. Do not run a repository-wide auto-fix for a narrow task; use targeted non-mutating checks (`npm run lint:check` or `npx eslint <target> -c .eslintrc.cjs`) where possible, or explicitly inspect every resulting change.
- Verify migration safety separately from fresh-install success. If `vendor/`, `node_modules/`, a database, or required services are unavailable, say so and report static checks separately.
- Finish with: (1) concise summary, (2) changed files grouped by purpose, (3) tests/checks actually run and exact results, (4) migration/configuration/manual steps, (5) remaining gaps or blockers. Never claim “complete” if a critical requirement is still unresolved.
