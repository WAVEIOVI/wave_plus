---
name: wave-plus-frontend-engineering
description: Creates or improves WAVE PLUS Vue 3, Vuexy, Vuetify pages, reusable shared components, composables, forms, lists, dialogs, responsive layouts, and frontend state while following established UI and API standards.
---

# WAVE PLUS Frontend and Shared UI Engineering

Use for page/component creation, UI/UX improvement, component extraction, responsive fixes, shared dialogs, reusable list/form patterns, and frontend refactors. Always follow root `AGENTS.md` and the existing app conventions.

## 1. Inspect before designing

1. Inspect the target page, related views, shared components, composables, Pinia store, translations, routing/navigation, and API contract.
2. Search `resources/js/components/shared/`, `resources/js/composables/`, `resources/js/views/`, and sibling pages for existing implementations before creating new ones. Refer to `docs/agent/WAVE_PLUS_ENGINEERING_REFERENCE.md` for the catalog of shared components (`Wp*`) and composables.
3. Inspect the real API/prop/emits contract of components you intend to reuse; component names and documentation alone do not prove an implementation is suitable.
4. Use the existing WAVE PLUS/Vuexy visual language and theme tokens. Generic UI skill recommendations must not override Vuexy/Vuetify architecture or project requirements.

## 2. Page-family decision

Use the UI shape appropriate to the business object:

- **Master/entity:** list page, create/edit page/workspace, and a read-only detail dialog when it improves speed without duplicating confusing layouts.
- **Secondary resources:** manage in entity tabs/sections when it preserves context; use a child page only when the resource is substantial or needs its own navigation.
- **Configuration:** compact searchable server-side list and small add/edit dialog, with confirmation for destructive actions.
- **Business documents:** document list plus a persistent workspace/editor with header, status/actions, main information, lines, totals, related records, attachments/notes/history, and preview/output when relevant.
- **Planning/execution:** calendar, planner, timeline, board, or work-order workspace according to the job; support field/mobile use for operational screens.

Do not apply one layout to every business concept. Consistency means consistent interaction rules and visual language, not identical screen structure.

## 3. Standard list pages

A standard list should include applicable parts of:
- Clear breadcrumb/page header, title/description, primary action, and contextual actions.
- Useful KPI/summary cards only where meaningful. If a KPI looks clickable, it must actually apply the stated filter/navigation and give feedback.
- Debounced search, visible filter controls, advanced filters where needed, removable active-filter chips, and a reliable reset action.
- Server-side pagination, page size, sorting, and filtering where supported by the API. Whitelist supported sort fields and map UI fields to the backend contract; do not pass arbitrary field names.
- Responsive table on suitable large screens and cards/list on narrow screens, without losing the main actions or key status information.
- Row actions with clear labels/tooltips and proper permission checks; destructive actions require a confirmation pattern.
- Loading skeleton, empty state, no-search-results state, API error/retry, and success/error feedback.
- Selection/bulk actions only when the backend safely supports them.

Avoid client-side fetching of all records for filtering, decorative inert KPIs, too many simultaneously visible filters, overloaded tables, and horizontal scrolling as the default mobile solution.

## 4. Forms, edit pages, and wizards

- Group fields by user intent; use progressive disclosure for advanced/rare fields. Prefer searchable autocomplete for large or remote option sets, with debounce, server pagination where needed, clear selection, and loading/error states.
- Use correct input types, labels, help text, validation feedback, and defaults based on business rules. Do not remove necessary fields solely to make a form look short.
- Separate editable inputs from derived/read-only values. Keep domain calculations and authoritative validation on the backend.
- Use existing form validation and async-action patterns. Disable repeated submission, preserve entered data after validation/server errors, map field-level API errors correctly, and show a safe summary for general errors.
- Edit workspaces should use the existing page header/breadcrumb/tab conventions, a clearly bounded scrollable content area, and discoverable save/cancel actions. Do not create multiple competing scroll containers or trap mobile scrolling.
- Wizards must prevent invalid progression, preserve state when moving backward, support conditional steps where justified, clearly signal progress and errors, and confirm discarding dirty work. Do not turn a simple two-field form into a wizard.
- Detail/view dialogs should use consistent grouping and hierarchy from the edit experience but must be read-only; do not copy the entire edit form with disabled fields if a better details layout is available.

## 5. Shared component and composable design

- Give shared UI components `Wp` names when that matches the existing convention and use the existing folders under `resources/js/components/shared/`.
- Keep shared components presentation-focused and entity-agnostic. Accept data/configuration via props and expose intentional events/slots; avoid hardcoded endpoints, hidden store mutations, permissions/business rules, or calculations that belong to a domain module.
- Prefer a small composable for reusable behavior (list state, filter state, wizard flow, async action, dirty state, formatting) only when at least two real use cases share the same semantics.
- Avoid one giant component with dozens of flags and deeply nested conditions. Use a focused base component plus a few understandable variants/slots when the behavior is genuinely shared.
- Avoid near-duplicate `New`, `V2`, or `Advanced` components unless there is a documented migration need. Improve the existing implementation compatibly where reasonable.
- Use explicit prop defaults, predictable emitted events, stable keys, lifecycle cleanup, and stale-request/race protection for async lookups.
- Don't overuse dialogs: avoid stacking dialogs behind other dialogs. For contextual secondary tasks, consider a well-integrated single dialog, drawer, or route based on project standards.

## 6. Responsive and accessibility baseline

- Design mobile-first and verify narrow layouts, not only desktop. Reduce unnecessary visible controls; use compact actions, full-screen dialogs where suitable, sticky actions where helpful, and touch-friendly targets.
- Ensure no unintended page-level horizontal overflow, clipped controls, hidden required actions, hover-only interactions, or unreadable dense text.
- Keep focus visible, labels associated, icon-only buttons labelled, keyboard interaction predictable, dialogs focus-managed, and contrast compatible with the current theme.
- Respect reduced-motion preferences and avoid distracting animation. Use the project's existing icons/iconify conventions rather than emoji as interface icons.
- Test light/dark themes if supported by the app; do not hardcode colors that ignore tokens or theme variables.

## 7. State, API, permissions, and localization

- Follow the closest working Pinia store and existing API client/response contract. Do not add another HTTP client or scatter raw requests inside components.
- Keep display state separate from domain state; handle loading, errors, empty values, cancellation/race conditions, and reset-on-close behavior deliberately.
- Backend authorization is authoritative. Frontend helpers may hide or disable actions for UX, but all API endpoints must enforce the action independently.
- Add all visible strings to the existing i18n/localization structure and follow its key organization. Do not hardcode only the English text or invent a separate translation system.
- Do not change CASL/auth architecture while improving UI unless the task explicitly includes that migration. Preserve current route metadata and access guard semantics.

## 8. Verification before delivery

- Verify the actual rendered behavior in a browser or screenshot workflow when available: desktop, mobile/narrow width, loading, empty, validation errors, and long content/scrolling.
- Run relevant frontend unit tests (including `npm run test:shared-ui` where applicable) and `npm run build` when dependencies permit. Use targeted lint checks (`npm run lint:check` or `npx eslint <target> -c .eslintrc.cjs`) and avoid repository-wide `--fix` unless explicitly scoped and reviewed.
- Check console/runtime errors, form submission payloads, API response shape, navigation, permissions, and whether each visible control works.
- Report what was actually tested. If no browser/device verification occurred, do not claim the result is fully visually validated.
