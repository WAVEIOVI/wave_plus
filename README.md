# WAVE PLUS — Enterprise ERP Platform

WAVE PLUS is an enterprise-grade ERP (Enterprise Resource Planning) platform developed with a modular Laravel 12 backend and a modern Vue 3 (Composition API) single-page application powered by Vuexy and Vuetify 3.

---

## 1. Technology Stack

- **Backend:**
  - PHP 8.2+ (tested up to PHP 8.3+)
  - Laravel 12 Framework
  - Authentication: Laravel Sanctum (`auth:sanctum`)
  - Authorization: `spatie/laravel-permission` (v7+)
  - Multilingual Models: `spatie/laravel-translatable` (v6+)
  - Query Filters: `spatie/laravel-query-builder` (v6+)
  - File & Media Management: `spatie/laravel-medialibrary` (v11+)
  - PDF Generation: `spatie/browsershot` (v5+) with Puppeteer
  - Testing & Code Quality: PHPUnit 11, Larastan 3, Laravel Pint 1.24

- **Frontend:**
  - Vue 3 (`<script setup>` Composition API)
  - UI Framework: Vuexy Admin Template built on Vuetify 3
  - State Management: Pinia 3
  - Routing: Vue Router 4 with auto-routing via `unplugin-vue-router`
  - Bundler: Vite 7 with `laravel-vite-plugin`
  - Internationalization: `vue-i18n` 11
  - Iconography: `@iconify/vue` with Tabler, MDI, and FontAwesome icons

---

## 2. Architecture & Modules

The backend business logic is cleanly partitioned into domain modules under `app/Modules/`:

| Module | Namespace / Path | Responsibilities |
|---|---|---|
| **BusinessPartners** | `App\Modules\BusinessPartners` | Clients, Suppliers, Service Providers, Organizations, Contacts, Addresses |
| **Catalog** | `App\Modules\Catalog` | Items, Variants, Categories, Units of Measure, Equipment Blueprints, Servicing Rules |
| **ClientAssets** | `App\Modules\ClientAssets` | Client Sites, Recursive Areas, Equipment, QR Scanning, Workwear Models |
| **Crm** | `App\Modules\Crm` | Opportunities, Quotations, Contracts, Sales Orders, Feasibility Engine |
| **Finance** | `App\Modules\Finance` | Invoicing, Payments, Financial Centers, Accounting integrations |
| **Hr** | `App\Modules\Hr` | Employee Master, Attendance/Time-clock, Kiosk Sessions, Recruitment, Certifications |
| **Inventory** | `App\Modules\Inventory` | Multi-Warehouse, Bins/Locations, Stock Tracking, Movements, Adjustments |
| **Logistics** | `App\Modules\Logistics` | Delivery Work Orders (DWO), Waves, Proof of Delivery (POD), Fleet & Drivers |
| **OperationalHub** | `App\Modules\OperationalHub` | Workshop Receptions, Inspection Findings, Job Orders, Service Delivery Notes |
| **Procurement** | `App\Modules\Procurement` | Internal Purchase Requests (IPR), RFQs, Purchase Orders (PO), Goods Receipts (GRN) |
| **Shared** | `App\Modules\Shared` | Brand Logo resolution, SVG sanitizer, cross-module validation services |

All module routes are defined in `app/Modules/<Module>/routes/api.php` and explicitly loaded via `routes/api.php`.

Frontend pages reside under `resources/js/pages/` (auto-routed) and feature views under `resources/js/views/`. Shared reusable components (`Wp*`) reside in `resources/js/components/shared/`.

---

## 3. Prerequisites

Ensure your development environment meets the following requirements:
- **PHP:** `^8.2` or higher with `pdo`, `mbstring`, `openssl`, `curl`, `fileinfo`, `gd`/`imagick` extensions
- **Composer:** `^2.5` or higher
- **Node.js:** `^20.0` or `^24.0` (LTS recommended)
- **Package Manager:** `pnpm` (recommended) or `npm`
- **Database:** SQLite (default for development/testing) or PostgreSQL/MySQL

---

## 4. Installation & Setup

### 4.1 Clone and Environment Setup
```bash
# 1. Install backend dependencies
composer install

# 2. Configure environment file
cp .env.example .env
php artisan key:generate

# 3. Create storage symlink
php artisan storage:link
```

### 4.2 Database Initialization
```bash
# Run database migrations
php artisan migrate

# Optional: Seed development/reference data
php artisan db:seed
```

### 4.3 Frontend Setup
```bash
# Install Node dependencies (runs postinstall build:icons and msw:init)
npm install
# or: pnpm install
```

---

## 5. Development Workflow

### Option A: Run Full Stack Concurrently
Use the composer script to run the API server, queue worker, Laravel Pail log stream, and Vite dev server simultaneously:
```bash
composer dev
```

### Option B: Run Services Individually
```bash
# Terminal 1: Backend API server
php artisan serve

# Terminal 2: Vite hot-reload dev server
npm run dev
# or: pnpm dev

# Terminal 3: Background queue worker
php artisan queue:listen --tries=1
```

The application is accessible at `http://localhost:8000` (or the configured Vite dev URL `http://localhost:5173`).

---

## 6. Verification & Quality Assurance

WAVE PLUS enforces a comprehensive verification workflow across backend and frontend layers:

| Layer | Check | Command |
|---|---|---|
| **Backend Tests** | Full Laravel test suite | `php artisan test` |
| **Backend Tests** | Targeted test or filter | `php artisan test --filter=<Name>` |
| **Frontend UI Tests** | Shared composable & UI unit tests | `npm run test:shared-ui` |
| **Frontend Linting** | Non-mutating ESLint check | `npm run lint:check` |
| **Frontend Linting** | Targeted non-mutating check | `npx eslint <path> -c .eslintrc.cjs` |
| **Frontend Build** | Production Vite build | `npm run build` |
| **PHP Style Check** | Laravel Pint code style check | `composer pint:test` |
| **PHP Style Fix** | Laravel Pint auto-format | `composer pint` |
| **PHP Syntax** | PHP linter (`php -l`) | Target files or PowerShell batch |

---

## 7. Key Project Documentation

- `AGENTS.md`: Mandatory engineering rules, security constraints, and invariant principles for all automated and human contributors.
- `docs/agent/WAVE_PLUS_ENGINEERING_REFERENCE.md`: Complete engineering manual covering module boundaries, page families, shared UI components (`Wp*`), composables, and testing matrices.
- `.agents/skills/`: Specialized task workflows:
  - `wave-plus-module-development`: End-to-end ERP module creation and extension.
  - `wave-plus-frontend-engineering`: Vuexy/Vuetify UI development, responsive design, and shared components.
  - `wave-plus-safe-refactor`: Non-destructive debugging, root-cause repair, and security auditing.
  - `wave-plus-verification`: Systematic execution of all test suites, syntax checks, linting, and build validations.
