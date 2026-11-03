---
name: wave-plus-module-development
description: Develops or extends a WAVE PLUS ERP module or end-to-end business feature across Laravel, database, APIs, Vuexy/Vuetify pages, Pinia stores, permissions, localization, and tests. Use for new modules, module migrations, features, business workflows, and cross-module integrations.
---

# WAVE PLUS Module Development

Follow this workflow whenever adding a module, migrating a module to the cleaned architecture, or implementing an end-to-end feature. Project-wide invariants are in the repository-root `AGENTS.md`.

## Phase 1 — Establish the real baseline

1. Inspect current git status/diff and confirm the actual repository root. Do not assume an uploaded report or older archive describes the current branch.
2. Read the relevant task requirements and `docs/agent/WAVE_PLUS_ENGINEERING_REFERENCE.md`.
3. Map the target feature across the live codebase: migrations/schema, models/relationships, requests, policies/middleware, services/actions, controllers, API resources, route registration, seeds/factories, tests, Pinia store, page route, views/components, composables, and translations.
4. Inspect at least one comparable implementation and verify it is working and consistent. If both a legacy/dirty implementation and a cleaned implementation exist, compare only the relevant behavior and preserve known business requirements; do not migrate legacy defects.
5. Create a brief internal checklist of requirements, dependencies, affected files, API contract, permission boundary, edge cases, and tests. Keep the change scoped to the requested module and necessary shared contracts.

## Phase 2 — Define the module contract before coding

For every non-trivial feature, verify these questions before implementation:

- What is the source of truth for each field and business rule?
- Which records/entities own the relationships and lifecycle?
- Who can read, create, update, delete, approve, export, and perform transitions?
- What are the request/response shape, pagination/filter/sort rules, validation errors, and status codes?
- Which fields are user-editable versus computed/system-managed?
- What happens on duplicate submission, partial failure, concurrency, inactive/archived records, and invalid state transitions?
- Which other modules consume the data, and which module owns the behavior?

Do not introduce a new table, status, abstraction, module boundary, or permission name unless requirements and current architecture justify it. Do not silently change existing API fields or resource shapes.

## Phase 3 — Implement backend and persistence coherently

1. Follow `app/Modules/<Module>/` and the actual namespace/file conventions. Check whether the module route file is included from `routes/api.php`; do not assume a new route file is auto-loaded.
2. Reuse existing models and relationships where they represent the domain. Match the target tables' current key/ULID conventions; never assume all tables use the same primary key style.
3. Validate untrusted input at the API boundary using the existing request-validation pattern. Validate related-record existence and scope, uniqueness, enum/status values, date/quantity/price boundaries, and legal workflow transitions.
4. Keep controllers focused. Reuse an existing service/action pattern for complex or multi-step business operations. Use transactions where several writes must succeed or fail together.
5. Keep calculations in one authoritative domain layer. For document lines, pricing, discount, tax, totals, inventory, attendance, or status transitions, do not create competing frontend and backend implementations. The UI may preview calculations, but the backend must validate and recalculate authoritative values.
6. Follow existing API resource/serialization patterns and avoid leaking internal fields or unneeded relationships. Use server-side query/filter/sort/pagination conventions and whitelist sortable/filterable fields.
7. Apply auth, policies/permissions, and module access middleware to every sensitive route. Public endpoints require deliberate abuse controls, validation, safe errors, and rate limiting.
8. Add new migrations for schema changes unless safe historical migration consolidation is explicitly authorized and proven appropriate. Include indexes, foreign keys, constraints, data/backfill handling, and rollback considerations. Test upgrades against an existing schema when possible.
9. Update factories/seeders only as needed, keeping seed data deterministic, valid, and non-sensitive.

## Phase 4 — Implement frontend from the existing architecture

1. Keep route pages under `resources/js/pages/` and follow `unplugin-vue-router`, `definePage()`, route naming, layout, module, and access metadata used in comparable pages. Check navigation/access conventions instead of inventing another router pattern.
2. Use `resources/js/views/` for larger feature views if this is the established pattern for the target module; keep route shells thin when appropriate. Do not move whole directories solely to make them look uniform.
3. Use existing Pinia stores under `resources/js/stores/`. Follow the closest canonical request client and response convention. In this repo, `$api` and an Axios-compatible wrapper both exist; do not introduce a second client or mix approaches without cause.
4. Search `resources/js/components/shared/` and `resources/js/composables/` before writing a new shared component/composable. Prefer the existing `Wp*` components and extend them compatibly when they are the right abstraction.
5. Match the entity type to the correct UX family:
   - Master/entity: list → create/edit workspace or page → read-only detail dialog when useful.
   - Secondary resource: child list/section → add/edit dialog; avoid unnecessary dedicated pages.
   - Configuration: compact searchable list → small add/edit dialog → confirm dialog.
   - Business document: list → document workspace/editor → lines, totals, status/actions, relations, attachments, activity/history, and relevant preview.
   - Planning: calendar/planner/timeline/board according to the work; Execution: mobile-usable workspace/board/timeline.
6. Implement the complete states: initial loading, background loading if applicable, empty list, no search results, API error/retry, validation errors, disabled/read-only permissions, success, and unsaved changes.
7. Use i18n for visible strings; support existing themes and responsive behavior. Do not ship placeholder text, inert buttons, static fake features, or TODO-only pages as completed work.
8. Ensure the frontend never substitutes for backend validation/authorization. Disable repeated submit while pending, handle server validation errors, avoid stale async responses overwriting newer selections, and refresh/invalidate related state coherently.

## Phase 5 — Test integration and regressions

- Add tests for normal success, validation failures, permission denial, unauthorized cross-record access, duplicate requests, related-record constraints, and the feature's meaningful edge cases.
- For cross-module changes, test the producer contract and at least one consumer behavior. Do not duplicate another module's data or business logic to make integration easier.
- Validate a fresh path and an existing-record edit path. For workflows, test permitted and forbidden status transitions.
- Run relevant feature/unit tests and frontend tests/build when the environment allows (`php artisan test --filter=<Name>`, `npm run test:shared-ui`, `npm run lint:check`, `npm run build`, `composer pint:test`). Inspect `docs/agent/WAVE_PLUS_ENGINEERING_REFERENCE.md` for the full verification matrix.
- For UI work, inspect rendered pages/screenshots or run browser tests when those tools are available. If they are unavailable, say visual validation was not performed rather than claiming pixel-perfect success.

## Decision rule: reuse, extend, or create?

1. **Reuse** an existing component/service if its contract fits and its behavior is correct.
2. **Extend** it if the new requirement is a genuine generalization and existing consumers can remain compatible.
3. **Create** a new abstraction only if reuse/extension would couple unrelated domains, require many conditionals, or make the API less understandable.
4. Never create `V2`, `New`, or duplicate helpers as a way to avoid understanding an existing implementation. If a replacement is truly required, state the migration/deprecation plan and update consumers in the same scoped task.

## Completion gate and roadmap decision

Before reporting completion, verify backend + database + frontend + access control + localization + tests as relevant. Report what is implemented, exact files changed, actual checks and results, untested paths, migration/configuration requirements, and remaining gaps. A visually complete page backed by missing API/business behavior is not a completed module feature.

WAVE PLUS follows incremental module delivery: do not chase 100% polish before moving forward. Recommend progression when the core workflows, persistence/API contract, authorization, and required integrations are reliable, even if 10–15% of non-critical enhancements remain. Keep those items in a prioritized backlog. Block progression for unresolved critical security/data-integrity issues, broken core workflows, unsafe migration behavior, incompatible cross-module contracts, or confirmed release-blocking failures. Explicitly label each remaining issue as **Blocker**, **Before sign-off**, or **Backlog**; do not blur them together.
