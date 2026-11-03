---
name: wave-plus-verification
description: Executes and validates the full WAVE PLUS verification suite across PHP syntax, Laravel backend tests, shared UI tests, non-mutating ESLint checks, Pint code styling, and Vite production builds.
---

# WAVE PLUS Verification and Quality Assurance

Use this skill whenever verifying new features, bug fixes, refactorings, pull requests, or release readiness across the WAVE PLUS repository.

---

## 1. Principles of Verification

1. **Never claim an unexecuted check has passed.** If a command cannot run due to missing services, missing database credentials, or environment issues, state the blocker clearly.
2. **Run targeted checks before full suites.** Fast targeted tests identify regressions in seconds before initiating multi-minute full-suite runs.
3. **Prefer non-mutating checks.** In this repository, `npm run lint` and `composer pint` will rewrite files. Always use non-mutating checks (`npm run lint:check` or targeted `npx eslint <path> -c .eslintrc.cjs`, `composer pint:test`) during verification.
4. **Distinguish syntax/static validation from runtime behavior.** A passing `php -l` does not prove routes or policies work; a passing `npm run build` does not prove user interactions or validations function correctly.

---

## 2. Standard Verification Workflow (Execution Order)

Follow this sequence for comprehensive verification:

### Step 1: PHP Syntax & Lint Check
Check syntax for modified PHP files or all project PHP files:
```powershell
# Targeted check
php -l path/to/ModifiedFile.php

# Batch check across app, routes, config, database, tests
Get-ChildItem -Path app, routes, config, database, tests -Filter *.php -Recurse | ForEach-Object { $r = php -l $_.FullName 2>&1; if ($LASTEXITCODE -ne 0) { Write-Output $r } }
```

### Step 2: PHP Code Style Check
Ensure PHP code adheres to project style without modifying files:
```bash
composer pint:test
# or: php vendor/bin/pint --test
```
For targeted file formatting:
```bash
php vendor/bin/pint path/to/file.php
```

### Step 3: Targeted Backend Tests
Execute the specific test class or method that covers the touched domain:
```bash
php artisan test --filter=TestClassName
# Example:
php artisan test --filter=CatalogVariantsTest
```

### Step 4: Shared UI Unit Tests
Run the Node.js built-in unit tests for frontend shared composables (presets, server errors, dirty state, wizard engine, currency, search/select):
```bash
npm run test:shared-ui
```

### Step 5: Frontend Linting (Non-Mutating)
Run ESLint to check for syntax and linting errors:
```bash
# Targeted check on modified files/folders:
npx eslint resources/js/components/shared -c .eslintrc.cjs
npx eslint resources/js/composables/shared -c .eslintrc.cjs

# Workspace check:
npm run lint:check
```

### Step 6: Frontend Production Build
Compile the application with Vite to verify template syntax, TypeScript types, asset bundling, and rollup packaging:
```bash
npm run build
```

### Step 7: Broader Regression Suite
Run neighboring feature tests or the full suite when resources allow:
```bash
php artisan test
```

---

## 3. Handling Blockers and Environment Limitations

When executing verification in restricted environments, classify blockers accurately:

| Limitation | Correct Reporting |
|---|---|
| **Database Unavailable** | State: "Database service not configured or connection failed. Database-backed integration tests could not be executed." |
| **Chromium / Browsershot Unavailable** | State: "Puppeteer / Chromium not installed or executable; PDF generation tests skipped." |
| **Node / NPM Unavailable** | State: "Node.js runtime not installed in environment; frontend tests and Vite build could not be executed." |
| **Existing Domain Logic Failures** | If preexisting domain failures occur outside your scope, identify them explicitly as existing failures without claiming the entire suite passed. |

---

## 4. Required Completion Report Format

Finish verification reports with this structure:

1. **Verification Summary:** Pass / Fail / Blocked status with scope.
2. **Commands Executed:** Exact command lines run and exit codes.
3. **Test Results:** Number of tests executed, passed, failed, assertions count, and duration.
4. **Static Analysis & Linting:** Exact results of `php -l`, Pint, and ESLint.
5. **Build Output:** Result of `npm run build` with build time or asset output.
6. **Blockers & Unresolved Gaps:** Any test or check that could not run or domain failures outside task scope.
