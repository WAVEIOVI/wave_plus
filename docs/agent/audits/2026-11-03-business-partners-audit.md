# Business Partners — Complete Technical Audit

**Date:** 2026-11-03 · **Scope:** BusinessPartners module only (Clients, Suppliers, Service Providers, Organisations)
**Mode:** Read-only audit. No source files were modified. All findings were verified against the actual source code; runtime verification results are reported explicitly in §9.

---

## 1. Executive summary and verdict

### Verdict: **NO — not yet ready for official closure, but close.** Conditional verdict: fix the 3 P1 items below and the cosmetic verification failures, then close. There are **no P0 findings** — no data-integrity corruption, no broken core workflow at the backend level, no authentication bypass.

What works and is genuinely solid:

- **Architecture:** Clean module layout (`app/Modules/BusinessPartners/`) with a canonical polymorphic owner map (`Support/PartnerMorph.php`), consistent route/controller/service/request/resource layering, and near-identical treatment of the four partner entities.
- **Database:** ULID PKs, translatable JSON identity columns, `softDeletes()` on all four partners, well-built composite indexes on polymorphic owners, cascading junction FK with uniqueness, idempotency key on bulk email batches. All 15 BP-related migrations have run in the audited environment (`php artisan migrate:status`).
- **Security design:** Per-route policy authorization (`PartnerPolicy` via `AuthorizesPartnerRequests`), module gate middleware (`EnsureBpModuleAccess`), file uploads processed through shared `MediaValidationService` with server-derived metadata (client mime/size never trusted), authorized download endpoint through Media Library, per-record authorization inside bulk email preview/send flows, strict id validation on exports (`ulid|distinct|exists ... whereNull deleted_at`), no client-trusted status/totals.
- **Archive safety (specifically audited):** Cross-module FKs referencing `clients`/`suppliers` use `restrictOnDelete()` (e.g. `database/migrations/2026_04_25_100009_create_quotations_table.php:17`, `2026_04_25_100007_create_sales_orders_table.php:18`, `2026_04_22_175348_create_purchase_orders_table.php:18`, `2026_10_04_200007_create_supplier_invoices_tables.php:16`), and partner deletion is a soft delete. Archiving therefore **cannot silently break historical quotations, sales orders, POs, GRNs or supplier invoices**; a hard delete attempt would surface a managed HTTP 409 (`Services/PartnerWriteService.php:delete()`).
- **Tests:** 4 focused suites (`tests/Feature/BusinessPartners/`) covering CRUD, fine-grained authorization, export isolation (no fallback to "all records"), soft-delete rejection, contact↔address uniqueness, HQ sync, logo source switching, contextual email, bulk email including idempotency — **50 tests / 301 assertions, all passing**.
- **Frontend:** Proper orchestration layers (`useBusinessPartnerListPage` → `useListPage` → Pinia stores → `$api`), parameterized list queries covered by shared-UI unit tests (77/77 passing), full CRUD + sub-resource + export/email action coverage per entity.

What is missing (the exact closure blockers):

| # | Finding | Severity |
|---|---------|----------|
| F1 | Frontend CASL gating uses module-level subjects (`create-BusinessPartners`) that **do not exist in the permission seeder**; per-resource permissions (`create-clients`, …) exist but are never checked client-side | **P1** |
| F2 | Bulk email batch endpoints (`showBatch`, `listBatches`) return **any user's batches** (names, resolved recipient emails, error messages) with no ownership or ownership/permission scoping beyond module access | **P1** |
| F3 | `SendPdfEmailJob::handleListExport()` emails a **unbounded entire list** of partners to arbitrary recipients (`->get()` with no cap), diverging from the 500-record cap in the controller PDF/CSV exports | **P1** |

P2 findings (should be fixed, not release-blocking architecture issues): missing regression tests for F2/document-download denials; style/verification debt (16 files failing Pint, CRLF endings). P3: dead `scopeSearch` scopes, dead `position`/`title` property reads, notes column type drift, dashboard email exposure to narrower-permission users.

---

## 2. Architecture assessment by layer

| Layer | Location | Assessment |
|---|---|---|
| Database | `database/migrations/2026_02_26_020001…020010`, `2026_11_03_000001_create_bp_bulk_email_batches_tables.php` | Consistent. See §3. |
| Models | `app/Modules/BusinessPartners/Models/` (12 models) | All four partners: `HasUlids`, `GeneratesReference` (CL/SU/SP/OR prefixes, formatted `XX00001`), `HasTranslations`, `InteractsWithMedia` (single-file `logo` collection + thumb/preview conversions), `SoftDeletes`, identical morph relations (`addresses`, `contacts`, `bankAccounts`, `vatCertificates`, `documents`), `scopeActive`, `scopeSearch`. Consistent casts. |
| Services | `Services/PartnerQueryService.php`, `PartnerWriteService.php`, `AddressManagementService.php`, `BusinessPartnerContextualEmailService.php`, `BusinessPartnerEmailSearchService.php`, `PartnerDashboardService.php`, `BpContactAddressService.php` | Clear separation of concerns: query (pagination caps, JSON-path search), write (transaction + logo sync + managed FK-conflict 409), addresses (transactional HQ snapshot sync via `saveQuietly`), email engine (template resolution, per-record authorization, isolated conversations). |
| Controllers | `Controllers/` (15) | Thin; consistent across entities; explicit `authorize()` calls before any data access; `Concerns/AuthorizesPartnerRequests.php` centralizes nested-owner authorization. |
| Routes | `routes/api.php` (registered at `routes/api.php:87`) | Symmetric across 4 entities; `['auth:sanctum', 'bp.access']`; wildcard nested routes ordered after literal routes; partner type slugs resolved by `PartnerMorph::classFor()` (unknown slug → 404). |
| Validation | `Requests/` (21 FormRequests) | Strict allow-lists incl. enum `in:` lists mirroring migrations, `ulid|exists` FK validation, `unique:clients,tax_id`, per-language translatable array validation, JSON-decoded `metadata` via `prepareForValidation`. |
| API Resources | `Resources/` (10) | Present for every entity; used consistently in controller responses (`data` + `total` contract). |
| Jobs / Mail | `Jobs/SendPdfEmailJob.php`, `Jobs/ProcessBpBulkEmailItemJob.php`, `Mail/BusinessPartnerDocumentMail.php` | Queueable, retry 3×, backoff, `SerializesModels` where needed; per-item progress updates via `BpBulkEmailBatch::updateProgress()`. |
| Frontend pages | `resources/js/pages/business-partners/` (9 files) | Auto-routed list/edit pages per entity + module index redirect. |
| Stores | `useClientStore.js`, `useSupplierStore.js`, `useOrganisationStore.js`, `useServiceProviderStore.js` | Full CRUD, sub-resources, contact↔address linking, FormData uploads with `_method` spoofing, blob exports/downloads. |
| Composables | `resources/js/composables/business-partners/` (18) | Entity-suffixed orchestration on top of a shared generic core (`useBusinessPartnerListPage`, `useBusinessPartnerActions`, `useBusinessPartnerDialogs`) — good reuse, low duplication. |
| Shared components | `resources/js/components/shared/` (`WpPartnerCard`, `WpAddressSiteCard`, etc.) | Generic, config-driven presentation components; no domain API calls inside. |

**Consistency check across the four entities:** QueryBuilder filters/sorts/includes, `options`, `meta`, `show` eager-loading, `store/update/destroy` wiring are structurally identical for Clients, Suppliers, Service Providers, Organisations. Confirmed by direct reading; differences are only partner-specific fields. ✅

---

## 3. Database → Backend → API → Frontend consistency analysis

- **Migration ↔ Model ↔ FormRequest validation stay in sync**, checked field-by-field for Clients (the reference entity) and spot-checked for the other three: enum lists (`legal_status`, `status`, `client_category`, supplier/SP/organisation specifics) match between migration, validator and model casts.
- **API contract:** every list endpoint returns `{data, total}` (`PartnerQueryService::paginate()`, lines 57–73); nested sub-resources return `{data: Resource::collection}`; create/update return `{message, data}` with 201 on create. The Pinia stores consume exactly this shape (`response.data || []`, `response.total`).
- **Route names referenced from other modules** (`business-partners-clients-id`, etc. in `app/Http/Controllers/GlobalSearchController.php:64-109`, `app/Modules/Communications/Email/Services/EmailRecordLinkService.php:314-317`) match the auto-generated typed routes in `typed-router.d.ts:23-30`. ✅
- **Permissions referenced everywhere** (`read-clients` etc.) all exist in `database/seeders/RolesAndPermissionsSeeder.php:76-95`. ✅ — except the module-level `create-/manage-BusinessPartners` LEDs expected by the frontend (Finding F1).
- **Sort/search:** JSON-path search per language (`legal_name->{'en'} like`, `applySearch`) parallels Spatie QueryBuilder filters; frontend sort-field mapping (`businessPartnerListQuery.js` + `sortFieldMap` in composables) targets allowed sorts only.

One noted contract drift: `SendPdfEmailJob` (list context) has **no record cap**, while `ClientPdfController`/`SupplierController`/etc. dump capped (500) or selected-ids-only datasets — see F3.

---

## 4. Shared components and code-standard compliance

- **Reuses existing standards:** shared `$api` client, Pinia store conventions, shared `Wp*` components, shared `MediaValidationService`, `DocumentRenderService`, `GeneratesReference` trait, `Browsershot` via `DocumentRenderService` trait wrapper (`Traits/GeneratesPdf.php`). No second HTTP client, no Tailwind, no new architecture. ✅
- **`PartnerMorph` is a good module contract:** URL slugs are the only public cross-module identifier; morph types remain class names for existing production rows (documented in code).
- **Negative findings:**
  - Dead code: `scopeSearch()` in all four partner models has **no caller** anywhere in `app/` (verified by search); it also searches JSON columns with `LIKE` which is only partially effective.
  - `BusinessPartnerContextualEmailService::getPreparationData()` reads `$contact->position` and `$address->title`, which **do not exist** (`bp_contacts.job_title`, `bp_addresses.label`); labels silently degrade to `'Contact'` / hardcoded French `'Filiale'`. Cosmetic but real.
  - Minor style drift: 10 of 60 module files lack `declare(strict_types=1)` while others have it; `notes` column is `text` for clients/service-providers but `json` for suppliers/organisations (migrations 020001 vs 020002). No runtime effect; hurts consistency.

---

## 5. Security and authorization findings

**Verified sound:**

1. Route gate: `auth:sanctum` + `EnsureBpModuleAccess` (module-level fallback chain: Admin/Super Admin roles, `read-BusinessPartners`, or any of the 16 per-resource permissions).
2. Model-level policy chain: `PartnerPolicy` sub-classes check `read-<subject>` etc. per entity (`Policies/*.php`).
3. Nested resources: index/store authorize the **owner** (`authorizePartnerView/Update`); show/update/destroy authorize the record's owner via `authorizeAddressable` — no IDOR path found; morph map prevents class injection.
4. Exports: `ExportClientsRequest` validates explicit `ids` (ULID, distinct, exists + not soft-deleted), then `PartnerQueryService::getSelectedClientsForExport()` reauthorizes `viewAny` + `view` per record. Tests prove no fallback to "all records".
5. Document upload/download: file validated through `MediaValidationService` (CATEGORY_DOCUMENT), server-derived metadata, authorized `GET /bp/documents/{document}/download` → `$media->toResponse()`.
6. Emails: contextual send requires partner `view` **plus** Communications permission (`authorizeUser()`), sender profile resolved via `resolveAuthorizedProfile()`; unresolved placeholders rejected; per-record authorization inside bulk preview/batch creation (marked skip, not exception).

**Findings:**

| ID | Finding | Severity | Evidence |
|---|---|---|---|
| F1 | **Frontend permission wiring does not match the backend/seeder contract.** Pages gate create/delete with `ability.can('<action>', 'BusinessPartners')` (`resources/js/plugins/casl/ability.js:71-74`, `pages/business-partners/clients/list/index.vue:249,321` and siblings), i.e. permission `create-BusinessPartners`/`manage-BusinessPartners`. The seeder defines **only** `read-BusinessPartners` at module level plus per-resource rights (`database/seeders/RolesAndPermissionsSeeder.php:76-95`). Result: users whose rights come only from `create-clients` etc. (e.g. fine-grained roles) can create/delete per the backend but the UI disables the buttons (backend still enforces 403 for those it doesn't allow — no security hole, but a broken/absent core workflow for non-admin users). Backend authorization itself is fine. | **P1** (UX/workflow blocking, not a privilege escalation) | `ability.js:72-73`; seeder lines above; page greps in §9. |
| F2 | **`showBatch` / `listBatches` have no per-user scoping.** Any user passing `bp.access` can read any batch (including resolved recipient emails, error messages, partner names of colleagues) (`Controllers/BusinessPartnerEmailController.php`, `showBatch()` / `listBatches()` near the end of the file; route `routes/api.php` `bulk-email/batches…`). Batch data contains personal data (email addresses). | **P1** (unscoped operational data exposure within the module; not a global breach) | Controller + routes read directly. |
| F3 | **List-email job is unbounded.** `Jobs/SendPdfEmailJob.php:handleListExport()` (≈lines 126–139) loads *all* partners (`->get()`, no cap) into a Browsershot PDF and emails it to the caller-provided recipients, authorized only by `viewAny` at dispatch (`Traits/HasPartnerActions.php:sendEmailList()`). Downloads are capped at 500 and contextual bulk email is capped at 250 — this path is the only unbounded one. DoS/timeout risk on large datasets and a blunt "email the whole database" primitive. | **P1** (operational risk + asymmetry) | Job + trait read directly. |
| S4 | Dashboard KPI/recent-partners endpoint exposes all four entity types' names/status/emails to users with only partial per-entity read rights (`Services/PartnerDashboardService.php` + route `GET bp/dashboard-stats` gated only by `bp.access`). Internal-scope leak; low sensitivity. | P3 | Read directly. |
| S5 | `authorizeUser()` first line `Gate::forUser($user)->has('view')` is a no-op ability-definition check, not a permission check (`Services/BusinessPartnerContextualEmailService.php`). Harmless (the gate *is* then actually authorized), but misleading. Also grants email rights on `read-Communications` — intentional cross-module coupling, documented here. | P3 | Read directly. |
| S6 | Export CSV formulas: `ClientCsvController` writes raw name/status values into CSV cells without guard (e.g. `=` prefixes). Whether `DocumentRenderService::streamCsvResponse()` neutralizes formula injection was **not verified** (not inspected). Flagged as verification gap, not as confirmed defect. | P3 (unverified) | — |

**Frontend-only display gating (`$can`, `useAbility`) is present on create/delete buttons but never replaces backend authorization** — confirmed backend is authoritative on every route.

---

## 6. Business logic and cross-module integration findings

- **Archive/delete behavior (the specific concern):** ✅ safe. Soft deletes on partners + `restrictOnDelete()` FKs in CRM (quotations, sales orders, contracts), Procurement (POs → suppliers), Finance (supplier invoices), Client Assets (client sites/equipment). `PartnerWriteService::delete()` converts FK violations to a managed 409. Module routes never hard-delete. Deleting an address/quote-referenced contact from Procurement side uses `nullOnDelete()` on `supplier_contact_id` — historicals stay intact.
- Dependent modules reach BP data through the **CRC-contract endpoints** (`/bp/{entity}/options`) rather than loading partner models directly — verified for Communications' `RecipientSearchService` and the global search controller. ✅
- `EmailRecordLinkService` / `EmailBusinessContextService` map BP record types with their own permission checks (`read-BusinessPartners` fallbacks) consistent with this module's policy semantics.
- **Gap:** in-flight creation flows of dependent modules (e.g. sales-order wizard, purchase-order wizard) presumably call `/bp/<entity>/options`; the options endpoint filters by `status` only when a `status` param is supplied — archived (`deleted_at`) partners *are* excluded automatically via `SoftDeletes`, but inactive/suspended/blacklisted are **not** filtered out unless the caller passes `status=active`. Whether each consumer passes that was not exhaustively verified across all modules (single option-contract audit); flagged as a cross-module follow-up, not a confirmed defect.

---

## 7. Duplications, technical debt and performance

| ID | Observation | Severity |
|---|---|---|
| D1 | `scopeSearch()` duplicated/unused in 4 models (dead code, JSON-LIKE weakness) | P3 |
| D2 | Legacy `$contact->position` / `$address->title` reads (breaking multi-lang recipients-list labels) | P3 |
| D3 | `notes` column type drift `text` vs `json` between partner tables | P3 |
| D4 | 10 of 60 module files missing `declare(strict_types=1)`; CRLF line endings in policies/middleware | P3 (Pint) |
| D5 | **16 module files fail `vendor/bin/pint --test`** (line_ending, unary_operator_spaces, class_attributes_separation, single_blank_line_at_eof). Full list captured; auto-fixable via `composer pint` when the audit moratorium is lifted. | P2 (verification debt) |
| D6 | Pinia stores contain snake_case fields that trip the `camelcase` ESLint rule — verified this is a **project-wide convention deviation** also present in non-BP stores (`useRfqStore.js`, etc.): 74 of 94 ESLint errors, not BP-specific. | P3 (project-wide) |
| P-1 | `listBatches()` paginates with `->paginate($request->integer('per_page', 15))` without min/max clamping (unlike `PartnerQueryService`) — a `per_page=100000` would still hit the DB but result set is bounded by user count; low risk. | P3 |
| P-2 | `meta()` endpoints run 5 sequential `COUNT(*)` queries per entity per list page mount (e.g. `ClientController::meta()`); could be one grouped query. Fine at current scale. | P3 |
| P-3 | Dashboard `timeSeries()` streams full created_at rows through PHP instead of a SQL GROUP BY month; fine at low volume, grows linearly with DB size. | P3 |

---

## 8. Automated tests — coverage and gaps

Existing, all passing (50 tests / 301 assertions):

- `tests/Feature/BusinessPartners/BusinessPartnerCrudAndAuthorizationTest.php` — permission matrix (viewer/creator), create/search/validation, legacy `-1` cap, supplier round-trip, organisation soft-delete, address ownership/primary uniqueness, cross-partner contact–address link rejection, contact primary-location uniqueness, logo source switch, document metadata JSON, multilingual fields, HQ sync, dashboard payload.
- `tests/Feature/BusinessPartners/ClientExportTest.php` — 21 tests: auth/permission matrix on CSV/PDF/single-PDF, selection isolation, rejection of soft-deleted/invalid ids, headers/BOM/filename.
- `tests/Feature/BusinessPartners/BusinessPartnerContextualEmailTest.php` — prepare/send round trips for all 4 types, manual recipient not linked, unresolved placeholders rejected, unauthorized users blocked.
- `tests/Feature/BusinessPartners/BusinessPartnerBulkEmailTest.php` — preview skips, dispatched jobs + isolated emails, contact rule, individual PDF failure isolation, idempotency-key deduplication, batch endpoints, Communications record-link integration.

**Gaps (explicit, not speculative):**

| ID | Missing scenario | Severity |
|---|---|---|
| T1 | No test that a user **without ownership or elevated rights** is denied `GET bp/bulk-email/batches[/{id}]` — untestable today because the denial doesn't exist (F2) | P2 |
| T2 | No test for `GET bp/documents/{id}/download` returning 403 for a non-viewer (download path uniquely uses `view`) | P2 |
| T3 | PDF/CSV export suites are client-only; supplier/SP/organisation export endpoints lack the selection-isolation tests | P2 |
| T4 | No test of `SendPdfEmailJob` list-context behavior (would also have surfaced F3's unboundedness) | P2 |

---

## 9. Verification — actually executed (runtime) vs static inspection

Commands executed in this audit (Windows/Git Bash, project root):

| Check | Command (abridged) | Result |
|---|---|---|
| PHP syntax, module + middleware | `find app/Modules/BusinessPartners -name '*.php' \| xargs -0 php -l` | ✅ exit 0, all files `No syntax errors` |
| PHP style (Pint, non-mutating) | `vendor/bin/pint --test app/Modules/BusinessPartners app/Http/Middleware/EnsureBpModuleAccess.php` | ❌ exit 1 — **16 files fail** (line_ending/unary/class-separation; see §7 D5) |
| Backend regression suite | `php artisan test tests/Feature/BusinessPartners` (sqlite in-memory, queue sync) | ✅ **50 tests, 301 assertions, 0 failures**; both filter runs (`BusinessPartner\|ClientExport` and full directory) confirmed |
| Shared-UI unit tests | `npm run test:shared-ui` | ✅ **77/77 pass**, incl. `buildBusinessPartnerListQuery` |
| Frontend lint (targeted, non-mutating) | `npx eslint "resources/js/composables/business-partners/**" "resources/js/stores/useClientStore.js" … "resources/js/pages/business-partners/**" -c .eslintrc.cjs` | ❌ exit 1 — **94 errors in 4 files** (74× `camelcase` — verified identical in non-BP stores, i.e. project-wide; 19× `padding-line-between-statements`, 1× `newline-before-return`) |
| Production build | `npm run build` | ✅ success, built in 1m 24s |
| Migration status | `php artisan migrate:status` | ✅ all 15 BP migrations `Ran`; `2026_11_03_000001_create_bp_bulk_email_batches_tables` present |

**Not executed / not verifiable here:** full-project `php artisan test` (out of module scope by task instructions), browser/E2E interaction testing, actual SMTP delivery, and users' live permission provisioning. Static-inspection-only items are marked per-finding above (notably CSV formula handling, S6).

---

## 10. Severity classification summary

- **P0 (Critical):** none.
- **P1 (High):** F1 permission wiring (frontend subjects unused by seeder), F2 unscoped batch endpoints, F3 unbounded list-email job.
- **P2 (Medium):** D5 Pint failures; T1–T4 test gaps.
- **P3 (Low):** D1–D4, D6, S4–S6, P-1..P-3 as itemized above.

---

## 11. Recommended fixes, in priority order

1. **F2 — scope batch endpoints before anything** (it is the only cross-user data exposure):
   - `showBatch()`: restrict to `created_by_user_id = request->user()` or require an explicit permission (e.g. `read-BusinessPartners` as an elevated case, else decline);
   - `listBatches()`: add the same scope; clamp `per_page` (reuse `PartnerQueryService::paginate`).
   - Add regression test T1.
2. **F1 — fix frontend permission subjects:** either (a) seed the missing `create-/edit-/delete-/manage-BusinessPartners` permissions and keep UI granularity, or (b) change UI checks to per-resource subjects (`$can('create','Clients')`, supplier/SP/organisation equivalents) so fine-grained roles work end-to-end. Then add a route-level test proving per-resource role create/delete works (matching existing `test_reader_can_list_but_cannot_create`).
3. **F3 — cap the list-email job:** add the same 500-row cap used by `StorePdf`/export paths (or a `LIMIT n` constant shared with controllers), log if truncated, and preferably reuse the export-selection dataset.
4. **D5 — run `composer pint` scoped to the module + `app/Http/Middleware/EnsureBpModuleAccess.php`** (mutating fix, deliberately not executed during this audit) and normalize CRLF↔LF and `declare(strict_types=1)`.
5. Test backlog: T2 document-download denial; T3 extend export suites to the other 3 entities; T4 job-context export test.
6. Cosmetic backlog: D1 remove unused `scopeSearch()` or make it JSON-aware; D2 replace dead `position`/`title` reads with `job_title`/`label`; D3 mark accepted or unify notes types in a future additive migration; D6 defer camelcase noise to a project-wide lint-config decision.

---

## 12. Final closure checklist

**Must be fixed to officially close Business Partners (all cross-module, this module only):**

- [ ] F2 — per-user scoping (or documented elevated-permission policy) on `GET bp/bulk-email/batches[/{id}]`.
- [ ] F1 — reconcile frontend CASL subjects with the seeded permission names (decide module-level vs per-resource) and add the missing regression test.
- [ ] F3 — cap `SendPdfEmailJob::handleListExport()`.
- [ ] D5 — Pint clean for module + `EnsureBpModuleAccess.php`.
- [ ] T1 (+ ideally T2) regression tests for the above.

**Can remain as explicitly identified cross-module / follow-up work (does not block closure):**

- T3 export suites for supplier/SP/organisation (extension of existing suites).
- T4 job-context export test (folds into F3's fix).
- D1–D4, D6, S4–S6, P-1..P-3 cosmetic/perf backlog.
- Verify every consumer of `/bp/<entity>/options` explicitly passes `status=active` (or equivalent) where only active partners should be selectable — single shared contract already verified, per-consumer audit belongs to each module's own closure.
- CSV formula-injection neutralization check in `DocumentRenderService::streamCsvResponse()` (cross-module shared service; verify once, benefits several modules).

---

### Bottom line

Business Partners is a **technically complete, well-integrated, well-secured-at-the-backend module** with a first-class archive-safe design and a healthy regression suite (50 green backend tests, 77 green shared-UI tests, green production build). It is **not yet finishable as "closed"** until the F1/F2/F3 authorization/consistency items and the Pint debt are fixed — those are concrete, small, and localized (one controller, one permission mapping, one job line, one style run). After those fixes, the module can be closed with the remaining items explicitly tracked as the cross-module follow-ups listed in §12 below.
