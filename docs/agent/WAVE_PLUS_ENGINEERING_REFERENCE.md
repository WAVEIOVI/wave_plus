# WAVE PLUS — Engineering Reference Manual

This document provides the authoritative technical reference for the WAVE PLUS enterprise application. All agents and developers working on WAVE PLUS must consult this reference alongside the project invariants in `AGENTS.md`.

---

## 1. System Architecture & Tech Stack

WAVE PLUS is an enterprise ERP platform built on a decoupled modular backend and a single-page Vue 3 frontend.

### 1.1 Backend Stack
- **Framework:** Laravel 12 (PHP 8.2+ runtime; active runtime tested: PHP 8.3+)
- **Authentication:** Laravel Sanctum (Bearer token authentication via `auth:sanctum` middleware)
- **Role & Permission Management:** `spatie/laravel-permission` (v7+)
- **Localization:** `spatie/laravel-translatable` (v6+) for multilingual model attributes (JSON columns)
- **Query Building:** `spatie/laravel-query-builder` (v6+) for standardized API filtering, sorting, and inclusion
- **Media & File Handling:** `spatie/laravel-medialibrary` (v11+) using polymorphic `media` table and custom model `App\Models\Media`
- **Headless Document Rendering:** `spatie/browsershot` (v5+) with Puppeteer for server-side PDF generation
- **Code Quality & Testing:** PHPUnit 11, Larastan 3, Laravel Pint 1.24

### 1.2 Frontend Stack
- **Framework:** Vue 3 (Composition API with `<script setup>`)
- **Theme & UI Kit:** Vuexy Admin Template built on Vuetify 3 (`vuetify: 3.12+`)
- **State Management:** Pinia 3 (`pinia: 3.0+`)
- **Routing:** Vue Router 4 with `unplugin-vue-router` auto-routing generated from `resources/js/pages/`
- **Build Tool:** Vite 7 with `laravel-vite-plugin`
- **Internationalization:** `vue-i18n` 11 with message bundles under `resources/js/plugins/i18n/`
- **Iconography:** `@iconify/vue` with Tabler, MDI, and FontAwesome icon bundles precompiled via `build-icons.js`

---

## 2. Backend Module Structure & Conventions

Business logic is organized into self-contained domain modules located under `app/Modules/`.

### 2.1 Active Modules
| Module | Directory | Route Registration | Core Domain Responsibilities |
|---|---|---|---|
| **BusinessPartners** | `app/Modules/BusinessPartners/` | Required in `routes/api.php` | Clients, Suppliers, Service Providers, Organizations, Contacts, Addresses, BP Documents |
| **Catalog** | `app/Modules/Catalog/` | Required in `routes/api.php` | Catalog Items, Variants, Categories, Units of Measure, Equipment Blueprints, Servicing Rules |
| **ClientAssets** | `app/Modules/ClientAssets/` | Required in `routes/api.php` | Client Sites, Recursive Areas, Equipment, QR Tracking, Workwear Models & Allocations |
| **Crm** | `app/Modules/Crm/` | Required in `routes/api.php` | Leads, Opportunities, Quotations, Contracts, Sales Orders, Feasibility Assessments |
| **Finance** | `app/Modules/Finance/` | Required in `routes/api.php` | Invoices, Payments, Financial Centers, Accounts, General Ledger Integrations |
| **Hr** | `app/Modules/Hr/` | Required in `routes/api.php` | Employees, Attendance/Time-clock, Kiosk sessions, Candidates/Recruitment, Certifications |
| **Inventory** | `app/Modules/Inventory/` | Required in `routes/api.php` | Warehouses, Bins/Locations, Stock Items, Movements, Adjustments, Transfers |
| **Logistics** | `app/Modules/Logistics/` | Required in `routes/api.php` | Delivery Work Orders (DWO), Waves, Proof of Delivery (POD), Fleet & Driver assignments |
| **OperationalHub** | `app/Modules/OperationalHub/` | Required in `routes/api.php` | Workshop Receptions, Inspection Findings, Job Orders, Exit Passes, Service Delivery Notes |
| **Procurement** | `app/Modules/Procurement/` | Required in `routes/api.php` | Internal Purchase Requests (IPR), RFQs, Purchase Orders (PO), Goods Receipt Notes (GRN) |
| **Shared** | `app/Modules/Shared/` | Direct in `routes/api.php` | Brand Logo resolution (Brandfetch/Logo.dev), SVG sanitation, cross-module validation services |

### 2.2 Standard Module Directory Layout
Each module adheres to this structure:
```
app/Modules/<ModuleName>/
├── Controllers/         # Thin HTTP controllers handling requests and returning resources
├── Models/              # Eloquent models, relationships, casts, and media collections
├── Policies/            # Spatie authorization policies mapped to domain permissions
├── Requests/            # FormRequest classes enforcing schema, types, and domain rules
├── Resources/           # API Resource classes formatting client-facing responses
├── Services/            # Core business workflows, state machines, transactions, calculations
└── routes/
    └── api.php          # Module API routes (prefixed and middleware-guarded)
```

### 2.3 Route & Middleware Conventions
- Every module route file MUST be explicitly required in `routes/api.php`.
- Sensitive routes require `auth:sanctum` and Spatie permission or role middleware (e.g. `role:Super Admin|Admin` or `permission:<name>`).
- Public endpoints (such as visitor registration requests) MUST be rate-limited using `throttle` middleware.
- Cross-module operations (e.g. `resource-validation/*`) reside in central controllers guarded by authentication.

### 2.4 Data Persistence & Database Rules
- **Primary Keys:** Models use either auto-incrementing integer IDs or ULIDs depending on the domain. Check existing migrations before writing foreign keys.
- **Transactions:** Any operation mutating more than one record or spanning multiple tables MUST execute inside `DB::transaction()`.
- **Calculations:** Authoritative totals, discounts, taxes, inventory deductions, and status changes MUST be calculated on the backend. Never trust client-calculated totals.
- **Multilingual Fields:** Translatable text columns (e.g. `name`, `description`) use `Spatie\Translatable\HasTranslations` and store localized values as JSON `{"en": "...", "fr": "..."}`.

---

## 3. Frontend Architecture & Conventions

### 3.1 Routing and Page Generation
- Routes are generated automatically by `unplugin-vue-router` from files in `resources/js/pages/`.
- Pages define route metadata using `definePage()`:
```vue
<script setup>
definePage({
  meta: {
    action: 'read',
    subject: 'Catalog',
  },
})
</script>
```
- Complex views are structured in `resources/js/views/<module>/` with the page in `resources/js/pages/` serving as the layout shell.

### 3.2 State Management & HTTP Communication
- Pinia stores live in `resources/js/stores/`.
- Two HTTP client conventions exist in the codebase:
  1. `$api` (`resources/js/utils/api.js`): `ofetch`-based wrapper that automatically attaches the `accessToken` cookie.
  2. `axios` (`resources/js/utils/axios.js`): Axios instance configured with baseURL and auth interceptor.
  *Follow the convention of the closest existing store in your target module; do not introduce a third HTTP client or mix paradigms within the same module.*

### 3.3 Internationalization (i18n)
- All user-facing labels, headings, buttons, notifications, and error messages MUST use `$t('...')` or `t('...')` from `vue-i18n`.
- Localization files live in `resources/js/plugins/i18n/`.
- Use `MultiLangField` (`resources/js/components/shared/data/MultiLangField.vue`) for editing multilingual backend fields.

---

## 4. Standard Page Families & UI Patterns

To maintain consistent UX across ERP modules, use the appropriate page family:

### 4.1 Master / Entity Family
- **List Page:** Breadcrumb, search bar with debounced input, visible filter chips, server-side paginated `WpDataTable`, status chips (`WpStatusChip`), and action buttons (`WpRowActions`).
- **Create / Edit:** Full-page workspace layout (`WpEditPageLayout`), grouped sections (`WpSectionCard`), sticky header/footer with unsaved changes confirmation (`WpUnsavedChangesDialog`).
- **Detail View:** Quick-view modal dialog (`WpDetailDialog`) for fast reading without full page navigation.

### 4.2 Secondary Resource Family
- Child entities (e.g. site areas, employee certifications, partner contacts) are managed within entity tabs (`AppHybridTabs`) using inline cards (`WpPartnerContactsCard`) and modal dialogs (`WpFormDialog`).

### 4.3 Configuration & Lookups Family
- Compact searchable server-side data table with quick create/edit dialog (`WpFormDialog`) and confirmation modal for deletes (`WpDeleteDialog`).

### 4.4 Business Document Family
- Used for Quotations, Contracts, Sales Orders, Purchase Orders, DWOs.
- Structured editor with:
  1. Header section with document metadata, status badge, and workflow action buttons (Approve, Submit, Cancel).
  2. Business partner / address card (`WpAddressSiteCard`, `WpPartnerCard`).
  3. Document lines editor (`WpDocumentLinesCard`) with recalculation triggers.
  4. Summary card (`WpStatusCard`) displaying subtotal, tax, discounts, and net total.
  5. Audit history, attachments (`WpAttachmentField`), and printable PDF preview.

### 4.5 Planning & Execution Family
- Calendars (`@fullcalendar/vue3`), timelines, or operational dispatch boards.
- Mobile-responsive design: on narrow screens (< 960px), data tables switch to responsive cards (`WpMobileCard`, `WpResponsiveList`).

---

## 5. Reusable Shared Component Catalog

All shared presentation components reside in `resources/js/components/shared/` and are re-exported in `index.js`.

### 5.1 Data & Tables
- `WpDataTable`: Server-side paginated, sortable data table wrapping Vuetify's `VDataTableServer`.
- `WpDataTableCard`: Elevated container integrating search, filter toolbar, table, and pagination.
- `WpMobileCard`: Card presentation for entity rows on mobile viewports.
- `WpResponsiveList`: Automatic viewport-based switcher between table (desktop) and card list (mobile).
- `WpPagination`: Standardized pagination controls with page size selector.
- `WpRowActions`: Dropdown or icon-button action menu with permission checks and confirmation triggers.
- `WpBulkActions`: Floating bulk-selection toolbar for batch operations.
- `WpStatusChip`: Semantic status chip utilizing color tokens from `resources/js/utils/wp-status.js`.
- `MultiLangField`: Input tab/switcher for editing multilingual values (EN, AR, FR).

### 5.2 Layout & Structure
- `WpPageHeader`: Standard page title, subtitle, breadcrumb, and primary action slot.
- `WpPageToolbar`: Filter bar, search input, refresh action, and export buttons.
- `WpKpiCard` & `WpKpiGroup`: Metric summary cards with icons, trend indicators, and filter triggers.
- `WpSectionCard`: Structured card with header, optional action slot, and collapsible content.
- `AppHybridTabs`: Responsive horizontal/dropdown navigation tabs for entity workspaces.
- `WpActionFooter`: Sticky bottom action bar with cancel, save, and workflow buttons.
- `WpEditPageLayout`: Full layout shell for multi-section edit workspaces.

### 5.3 Dialogs & Feedback
- `WpDetailDialog`: Read-only entity inspection modal with responsive layout.
- `WpFormDialog`: Generic modal for quick entity creation or editing with submit/cancel state.
- `WpDeleteDialog`: Destructive action confirmation dialog with double-confirmation for critical records.
- `WpConfirmDialog`: General action confirmation modal.
- `WpUnsavedChangesDialog`: Prevents route transitions or modal closing when dirty state is detected.
- `WpLoadingState` / `WpEmptyState` / `WpErrorBanner`: Uniform feedback states for tables and containers.

### 5.4 Complex Interactive Systems
- **Wizard Engine:** `WpWizardDialog`, `WpWizardStepper`, `WpWizardReview` coupled with composable `useWizard` and presets in `wizardPresets.js`. Supports 4 variants (`fast`, `standard`, `developed`, `deeper`), conditional steps, step validation, and review step.
- **Search & Select Engine:** `WpSearchSelectDialog`, `WpSearchSelectResults`, `WpSearchSelectFilters` coupled with `useSearchSelect`. Features debounced remote search, race-condition protection, server pagination, and multi/single select modes.
- **Media & Document Pipeline:** `WpCameraCapture` (webcam capture), `WpQrScannerDialog` (jsQR scanning), `WpDocumentDialog` (document preview/upload), `WpAttachmentField` (file picker), `WpMediaGallery` (Spatie media viewer/carousel), `WpEntityLogo` / `WpLogoDialog` (Brandfetch/Logo.dev integration).

### 5.5 Media, Document & Storage Architecture
- **Document Management Suite (`WpDocumentDialog`, `WpDocumentList`, `WpAttachmentField`):**
  - **`WpDocumentDialog.vue`:** Standard 3-step document workflow dialog.
    - *Step 1 (Mode Selection):* Two distinct cards — "Upload Document" and "Scan Document".
    - *Step 2 (Dedicated Capture/Staging):* Dedicated upload dropzone with MIME/size validation OR interactive multi-page camera/scanner grid with page reordering, rotation, deletion, and client-side multi-page PDF generation via `pdf-lib`.
    - *Step 3 (Metadata & Details):* Required document title/name and collapsible optional advanced fields (`type`, `reference`, `issuer`, `issue_date`, `effective_date`, `expiration_date`, `status`, `notes`).
    - *Presets:* Configured via `preset` prop (`'bp'`, `'fleet'`, `'employee'`, `'site'`, `'catalog'`, `'compact'`, `'full'`) or custom `fields` array via `documentPresets.js`.
    - *Editing & Replacement:* Direct edit mode skips to Step 3. File replacement preserves existing domain metadata unless explicitly updated.
  - **`WpDocumentList.vue`:** Reusable responsive document list with search, status filters, desktop `VDataTable` / mobile cards, expiration badges, and standardized action triggers (Open/Preview, Download, Edit, Replace, Delete).
  - **`WpAttachmentField.vue`:** Lightweight single-file form control with backend-aligned format/size hints, scanner integration, and in-app preview dialog.
- **Image, Avatar, and Gallery Components (`WpAvatarDialog`, `WpImageDialog`, `WpMediaGallery`, `WpMediaViewerDialog`):**
  - **`WpAvatarDialog.vue`:** Dedicated user/employee photo workflow. Supports webcam capture and file upload, responsive circular social-media crop overlay, zoom slider (1x–3x), pan dragging/touch, 90° rotation, and client-side 512x512 JPEG export.
  - **`WpImageDialog.vue`:** Single image upload with cropping, rotation, zoom, and configurable aspect ratios.
  - **`WpMediaViewerDialog.vue`:** Centralized in-app viewer dialog supporting PDFs (embedded iframe), images (interactive zoom, pan, 90° rotation, fit-to-screen), and videos (native HTML5 player with playback controls) with secure download fallback.
  - **`WpMediaGallery.vue`:** Multi-file image and video uploads, live camera capture, in-browser video recording via `MediaRecorder` with 60-second countdown timer and fallback alert to file upload when recording is unsupported or denied, responsive media cards, Swiper carousel, and integrated `WpMediaViewerDialog`.
- **Validation & Sanitation (`MediaValidationService` & `SvgSanitizer`):**
  - All file uploads MUST pass through `MediaValidationService::validateAndProcess($file, $categoryOrCollection)`.
  - Categories: `image` (10MB, raster & sanitized SVG), `document` (20MB, PDF/Word/Excel/CSV/TXT; note: scanned documents must be packaged into `application/pdf` before submission), `certificate` (20MB, PDF/Images), `resume` (10MB, PDF/Word), `design` (50MB, AI/EPS/PSD/PDF/SVG blueprint files), `video` (100MB, MP4/WebM/MOV/AVI).
  - SVG uploads are automatically sanitized using `SvgSanitizer` to strip dangerous elements (`<script>`, `<foreignObject>`, `<iframe>`, `on*` event handlers, `javascript:` or `data:text/xml` URIs, and XXE entities).
  - Magic byte/MIME sniffing ensures actual file contents match the claimed extension, preventing extension spoofing.
- **Storage Visibility & Authorized Downloads (`MediaFormatter`):**
  - **Public Disks (`public`):** Intentionally public visual assets (entity logos, catalog item photos, user avatars, workshop inspection photos).
  - **Private Disks (`local` / `storage/app/private`):** Client-specific confidential files, technical site plans, CAD/embroidery blueprints (`design_files`), employee resumes, certifications, delivery notes (GRN), and legal contracts.
  - Media responses format download links via `MediaFormatter::format($media)`, setting `is_private = true` and `url`/`download_url` to the model's authorized route (e.g. `/api/client-assets/workwear-models/{model}/media/{media}/download`). Spatie's `getUrl()` must NEVER be exposed directly for private media collections.
- **Remote Branding & Logo Resilience (`BrandingSyncService` & `WpEntityLogo`):**
  - Brandfetch URLs with transient session tokens are stabilized using `BrandingSyncService::stabilizeProviderUrl()` to inject the persistent client ID.
  - Logo.dev provider URLs are stabilized to guarantee inclusion of the public API token query parameter, preventing 401s and broken remote logos.
  - `WpEntityLogo.vue` provides multi-stage resilience: attempts domain re-resolution if the remote URL fails, fails over to Logo.dev CDN with client token, and falls back cleanly to stylized initials without broken image badges.


---

## 6. Shared Composables

Located in `resources/js/composables/shared/` and `resources/js/composables/`:
- `useDirtyState(initialData)`: Tracks deep modification of reactive form data; provides `isDirty`, `commit()`, and `reset()`.
- `useServerErrors()`: Normalizes Laravel 422 validation response payloads into step/field-level errors.
- `useSearchSelect(fetchFn, options)`: Encapsulates debouncing, stale request cancellation, pagination, and selection state.
- `useWizard(steps, options)`: Multi-step form management with conditional visibility, validation gates, and recovery.
- `useCurrency()`: Currency and number formatting with locale-aware symbol/code placement.
- `useAppSnackbar()`: Global feedback snackbars (success, error, warning, info).
- `usePagination(options)`: Standard reactive pagination state (`page`, `itemsPerPage`, `sortBy`, `totalItems`).

---

## 7. Verification & Testing Workflow

WAVE PLUS maintains strict verification standards. Never report a verification check as passing unless it was executed in the workspace.

### 7.1 Verification Commands Matrix
| Check | Command | Description |
|---|---|---|
| **PHP Syntax Check** | `pwsh` script using `php -l` | Verifies syntax of all PHP files in `app/`, `routes/`, `config/`, `database/`, `tests/` |
| **Laravel Pint** | `composer pint:test` (or `php vendor/bin/pint --test`) | Checks PHP code styling against project rules without modifying files |
| **Pint Auto-Fix** | `composer pint` (or `php vendor/bin/pint <file>`) | Formats specified PHP files to match project style rules |
| **Laravel Test Suite** | `php artisan test` | Runs PHPUnit feature and unit tests across all backend modules |
| **Targeted Backend Test** | `php artisan test --filter=<TestName>` | Runs specific test suite or test method |
| **Shared UI Unit Tests** | `npm run test:shared-ui` | Runs Node.js native unit tests for frontend shared composables (`tests/js/shared-ui.test.mjs`) |
| **Non-Mutating ESLint** | `npm run lint:check` (or `npx eslint <path> -c .eslintrc.cjs`) | Checks JavaScript/Vue files without modifying code |
| **Mutating ESLint Fix** | `npm run lint` | Runs ESLint with `--fix` (WARNING: run targeted, not repo-wide) |
| **Production Build** | `npm run build` | Validates Vite compilation, Vue template syntax, asset bundling, and minification |

### 7.2 Verification Execution Order
When completing a module feature, bug fix, or refactor:
1. **Targeted Unit / Feature Test:** Verify the specific changed behavior passes.
2. **PHP Syntax / Pint:** Check syntax and formatting on touched backend files.
3. **Frontend Component / Shared UI Tests:** Run `npm run test:shared-ui` if UI logic was modified.
4. **Targeted ESLint Check:** Run `npx eslint <changed-files> -c .eslintrc.cjs` to catch linter defects without scanning legacy files.
5. **Production Build Validation:** Run `npm run build` to ensure no Vite build or template regressions.
6. **Broader Regression Suite:** Run relevant neighboring tests via `php artisan test`.
