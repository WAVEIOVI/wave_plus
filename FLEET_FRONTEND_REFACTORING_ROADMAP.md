# Fleet Frontend Standardization — Implementation Roadmap

**Date:** November 5, 2026  
**Priority:** High (User-designated "particularly important")  
**Estimated Effort:** 7–8 weeks full implementation  
**Status:** Audit complete, roadmap defined

---

## Executive Summary

A comprehensive **45-page audit document** (`FLEET_FRONTEND_BP_STANDARDIZATION.md`) has been created detailing all discrepancies between Fleet and Business Partners UI/UX patterns. This document provides an **actionable roadmap** for implementation.

### Scope of Work

**Pages to Refactor:**
- People & Skills → Fleet Vehicles (list + detail + dialogs)
- People & Skills → Approved Drivers (list + detail + dialogs)
- Logistics → Fleet Vehicles (integration points)

**Key Changes Required:**
1. Replace custom list management with `useListPage()` composable
2. Replace custom dialogs with `WpFormDialog`, `WpDetailDialog`, `WpDeleteDialog`
3. Replace VDataTable with `WpResponsiveList` for mobile adaptation
4. Complete 350+ missing translation keys (EN/AR/FR)
5. Standardize status colors, theme variables, and responsive behavior
6. Fix permission visibility and CASL integration

---

## Critical Decision Point

**Full refactoring (7–8 weeks) is currently in BACKLOG per the Fleet Module Status Report.**

### Option A: Proceed with Full Implementation Now
- **Effort:** 7–8 weeks (37 working days)
- **Impact:** Complete UI/UX standardization across Fleet
- **Risk:** Delays progression to next module
- **Recommendation:** Only if Fleet UI/UX consistency is release-blocking

### Option B: Defer to Planned Sprint (Recommended)
- **Effort:** 2–4 hours now (documentation + critical fixes only)
- **Impact:** Fleet remains functional, BP standardization scheduled post-module-completion
- **Risk:** Minimal — current Fleet UI works, just not perfectly aligned with BP
- **Recommendation:** Complete Resource Validation integration (3–4 days), then schedule dedicated Frontend Sprint

### Option C: Hybrid — High-Impact Quick Wins Now
- **Effort:** 1–2 days
- **Impact:** 60–70% visual consistency with minimal effort
- **Risk:** Low — focused on low-risk cosmetic improvements
- **Targets:**
  1. Complete missing translations (4 hours)
  2. Standardize status colors and theme variables (2 hours)
  3. Fix mobile responsiveness with WpResponsiveList (4 hours)
  4. Fix hardcoded English labels in driver dialogs (2 hours)
  5. Add permission-based visibility where missing (2 hours)

---

## Recommended Action: Option B + Planning

**Immediate (Now):**
1. ✅ Comprehensive audit complete (45 pages)
2. ✅ Implementation plan documented (7 phases, 37 days)
3. ⏭️ Resource Validation integration (3–4 days) — **higher priority blocker**

**Next Sprint (After Module Sign-off):**
- Dedicated 8-week Frontend Standardization Sprint
- Team allocation: 1 frontend developer full-time
- Deliverables: All Fleet pages match BP patterns

---

## Implementation Phases (When Approved)

### Phase 1: Foundation (Week 1 — 5 days)
**Goal:** Create shared composables and update stores

**Files to Create:**
- `resources/js/composables/fleet/useFleetVehicleListPage.js`
- `resources/js/composables/fleet/useApprovedDriverListPage.js`
- `resources/js/composables/fleet/useVehicleForm.js`
- `resources/js/composables/fleet/useDriverForm.js`

**Files to Modify:**
- `resources/js/stores/useVehicleStore.js` (align with BP pattern)
- `resources/js/stores/useApprovedDriverStore.js` (align with BP pattern)

**Acceptance Criteria:**
- Composables follow BP patterns (useClientList reference)
- Stores use consistent method naming (fetch*/create*/update*/delete*)
- No breaking changes to existing pages (backward compatible)

**Estimated:** 5 days

---

### Phase 2: Vehicle List Page (Week 1–2 — 5 days)
**Goal:** Refactor FleetVehiclesListPage.vue to match BP ClientsListPage.vue

**File:** `resources/js/pages/people-skills/fleet/vehicles/list/index.vue`

**Changes:**
1. Replace custom list logic with `useFleetVehicleListPage()`
2. Replace custom table with `WpDataTableCard` + `WpResponsiveList`
3. Replace custom filters with `WpFilterDialog` + `WpActiveFilters`
4. Replace custom row actions with `WpRowActions`
5. Add selection mode and bulk actions (WpBulkActions)
6. Standardize KPI cards with `WpKpiGroup` + `WpKpiCard`
7. Add mobile FAB for quick actions (`AppFabSpeedDial`)

**Before (Current):**
```vue
<script setup>
const page = useListPage({ fetchFn: params => store.fetchList(params), ... })
// ~300 lines of custom logic
</script>
```

**After (Target):**
```vue
<script setup>
const {
  vehicles,
  loading,
  error,
  filters,
  activeFilters,
  selectedVehicles,
  kpiCards,
  ...
} = useFleetVehicleListPage()
// ~150 lines (50% reduction)
</script>
```

**Acceptance Criteria:**
- List page matches BP visual structure
- Desktop: WpDataTableCard with sorting, filtering, pagination
- Mobile: WpResponsiveList auto-switches to cards
- KPIs show correct counts
- Selection mode works for bulk operations
- All translations in place
- Dark/light themes work correctly

**Estimated:** 5 days

---

### Phase 3: Vehicle Detail Dialog (Week 2–3 — 6 days)
**Goal:** Refactor VehicleDetailDialog.vue to match BP ViewClientDialog.vue

**File:** `resources/js/views/people-skills/fleet/vehicles/VehicleDetailDialog.vue`

**Changes:**
1. Replace custom dialog with `WpDetailDialog`
2. Standardize header with status chip, actions menu
3. Implement tab navigation using VTabs + VWindow
4. Create dedicated tab components:
   - `VehicleOverviewTab.vue` (new)
   - `VehicleAssignmentsTab.vue` (refactored)
   - `VehicleMaintenanceTab.vue` (refactored)
   - `VehicleFuelTab.vue` (refactored)
   - `VehicleDocumentsTab.vue` (refactored)
   - `VehicleChecklistsTab.vue` (refactored)
5. Add breadcrumb navigation
6. Implement responsive tab behavior (dropdown on mobile)
7. Add quick action buttons in header
8. Standardize loading/error states

**Acceptance Criteria:**
- Detail dialog matches BP visual structure
- 6 tabs: Overview, Assignments, Maintenance, Fuel, Documents, Checklists
- Header shows: fleet_code, plate_number, status chip, action menu
- Tabs lazy-load content
- Mobile: Tabs become dropdown selector
- Permissions control visible actions
- Deep-linking to specific tabs works

**Estimated:** 6 days

---

### Phase 4: Driver Pages Refactoring (Week 3–4 — 8 days)
**Goal:** Refactor all driver pages/dialogs to match BP patterns

**Files:**
- `resources/js/pages/people-skills/fleet/drivers/index.vue` (list)
- `resources/js/views/people-skills/fleet/drivers/DriverDetailDialog.vue` (detail)
- `resources/js/views/people-skills/fleet/drivers/DriverAuthorizationDialog.vue` (create/edit)
- `resources/js/views/people-skills/fleet/drivers/DriverIncidentDialog.vue` (incidents)
- `resources/js/views/people-skills/fleet/drivers/DriverSuspendDialog.vue` (suspend)
- `resources/js/views/people-skills/fleet/drivers/DriverRenewDialog.vue` (renew)
- `resources/js/views/people-skills/fleet/drivers/DriverRevokeDialog.vue` (revoke)

**Changes:**
1. **List page:** Use `useApprovedDriverListPage()` composable
2. **Detail dialog:** Use `WpDetailDialog` with 6 tabs (Basic Info, Permissions, Restrictions, Training/Certs, Documents, Incidents)
3. **Form dialogs:** Use `WpFormDialog` with `useDriverForm()`
4. **Workflow dialogs:** Use `WpConfirmDialog` for suspend/revoke/renew
5. **Remove ALL hardcoded English labels** — use i18n
6. **Add mobile responsiveness** — WpResponsiveList for incident history
7. **Standardize status colors** — approved=success, suspended=warning, expired/revoked=error
8. **Add unsaved changes protection** — WpUnsavedChangesDialog

**Acceptance Criteria:**
- Driver list page matches vehicle list page structure
- Detail dialog has 6 tabs with lazy loading
- All dialogs use WpFormDialog/WpConfirmDialog
- Zero hardcoded English text (100% i18n coverage)
- Mobile: Cards instead of tables, stacked forms
- Dark/light themes consistent
- Permission checks control visibility

**Estimated:** 8 days

---

### Phase 5: Internationalization (Week 4–5 — 5 days)
**Goal:** Complete all missing translations (EN/AR/FR)

**Files:**
- `resources/locales/en.json` (add ~200 fleet keys)
- `resources/locales/ar.json` (add ~200 fleet keys)
- `resources/locales/fr.json` (add ~200 fleet keys)

**Translation Keys Structure:**
```json
{
  "fleet": {
    "vehicles": {
      "title": "Fleet Vehicles",
      "subtitle": "Manage company vehicles, assignments, and maintenance",
      "addVehicle": "Add Vehicle",
      "editVehicle": "Edit Vehicle",
      "deleteVehicle": "Delete Vehicle",
      "viewVehicle": "View Vehicle Details",
      "filters": {
        "status": "Status",
        "type": "Vehicle Type",
        "ownership": "Ownership Type",
        "class": "Vehicle Class",
        "location": "Current Location"
      },
      "tabs": {
        "overview": "Overview",
        "assignments": "Assignments",
        "maintenance": "Maintenance",
        "fuel": "Fuel Records",
        "documents": "Documents",
        "checklists": "Checklists"
      },
      "fields": {
        "fleetCode": "Fleet Code",
        "plateNumber": "Plate Number",
        "registrationNumber": "Registration Number",
        "vin": "VIN",
        "make": "Make",
        "model": "Model",
        "year": "Year",
        "color": "Color",
        "type": "Vehicle Type",
        "vehicleClass": "Vehicle Class",
        "fuelType": "Fuel Type",
        "ownership": "Ownership Type",
        "status": "Status",
        "currentLocation": "Current Location",
        "odometer": "Odometer (km)",
        "insuranceExpiry": "Insurance Expiry",
        "registrationExpiry": "Registration Expiry",
        "lastService": "Last Service",
        "nextService": "Next Service"
      },
      "status": {
        "available": "Available",
        "in_use": "In Use",
        "maintenance": "Under Maintenance",
        "out_of_service": "Out of Service",
        "rental_active": "Rental Active",
        "client_use": "Client Use",
        "retired": "Retired",
        "sold": "Sold"
      },
      "ownership": {
        "company": "Company Owned",
        "rental": "Rental",
        "client": "Client Owned"
      },
      "types": {
        "sedan": "Sedan",
        "suv": "SUV",
        "van": "Van",
        "truck": "Truck",
        "pickup": "Pickup",
        "bus": "Bus",
        "motorcycle": "Motorcycle"
      },
      "messages": {
        "saveSuccess": "Vehicle saved successfully",
        "saveError": "Failed to save vehicle",
        "deleteSuccess": "Vehicle deleted successfully",
        "deleteError": "Failed to delete vehicle",
        "assignSuccess": "Vehicle assigned successfully",
        "returnSuccess": "Vehicle returned successfully"
      }
    },
    "drivers": {
      "title": "Approved Drivers",
      "subtitle": "Manage driver authorizations, licenses, and safety records",
      "addDriver": "Approve Driver",
      "editDriver": "Edit Driver Authorization",
      "suspendDriver": "Suspend Driver",
      "reinstateDriver": "Reinstate Driver",
      "revokeDriver": "Revoke Authorization",
      "renewDriver": "Renew Authorization",
      "viewDriver": "View Driver Details",
      "fields": {
        "authorizationNumber": "Authorization Number",
        "employee": "Employee",
        "authorizationStatus": "Authorization Status",
        "authorizationDate": "Authorization Date",
        "expiryDate": "Expiry Date",
        "reviewDate": "Review Date",
        "licenseNumber": "License Number",
        "licenseCategory": "License Category",
        "licenseExpiry": "License Expiry",
        "licenseIssuer": "License Issuer",
        "driverLevel": "Driver Level",
        "driverScore": "Safety Score",
        "incidentCount": "Incidents",
        "violationCount": "Violations",
        "lastIncident": "Last Incident",
        "suspendedUntil": "Suspended Until",
        "suspensionReason": "Suspension Reason",
        "permittedTypes": "Permitted Vehicle Types",
        "permittedClasses": "Permitted Vehicle Classes",
        "maxCargo": "Max Cargo (kg)",
        "maxPassengers": "Max Passengers"
      },
      "status": {
        "pending": "Pending",
        "approved": "Approved",
        "suspended": "Suspended",
        "expired": "Expired",
        "revoked": "Revoked",
        "under_review": "Under Review"
      },
      "levels": {
        "trainee": "Trainee",
        "junior": "Junior",
        "standard": "Standard",
        "senior": "Senior",
        "expert": "Expert"
      },
      "incidents": {
        "title": "Driver Incidents",
        "addIncident": "Record Incident",
        "viewIncident": "View Incident Details",
        "updateIncident": "Update Incident",
        "resolveIncident": "Resolve Incident",
        "closeIncident": "Close Incident",
        "types": {
          "accident": "Accident",
          "traffic_violation": "Traffic Violation",
          "parking_violation": "Parking Violation",
          "speeding": "Speeding",
          "equipment_damage": "Equipment Damage",
          "safety_violation": "Safety Violation",
          "policy_violation": "Policy Violation",
          "near_miss": "Near Miss",
          "customer_complaint": "Customer Complaint",
          "other": "Other"
        },
        "severity": {
          "minor": "Minor",
          "moderate": "Moderate",
          "major": "Major",
          "critical": "Critical"
        },
        "fields": {
          "incidentType": "Incident Type",
          "severity": "Severity",
          "incidentDate": "Incident Date",
          "title": "Title",
          "description": "Description",
          "location": "Location",
          "propertyDamage": "Property Damage",
          "vehicleDamage": "Vehicle Damage",
          "personalInjury": "Personal Injury",
          "policeReportFiled": "Police Report Filed",
          "status": "Status",
          "assignedTo": "Assigned To",
          "resolution": "Resolution"
        }
      },
      "messages": {
        "approveSuccess": "Driver authorization approved",
        "suspendSuccess": "Driver suspended successfully",
        "reinstateSuccess": "Driver reinstated successfully",
        "revokeSuccess": "Driver authorization revoked",
        "renewSuccess": "Driver authorization renewed",
        "incidentSuccess": "Incident recorded successfully",
        "deleteSuccess": "Driver authorization deleted"
      }
    },
    "common": {
      "actions": "Actions",
      "edit": "Edit",
      "delete": "Delete",
      "view": "View",
      "save": "Save",
      "cancel": "Cancel",
      "close": "Close",
      "confirm": "Confirm",
      "reset": "Reset",
      "apply": "Apply",
      "export": "Export",
      "import": "Import",
      "search": "Search",
      "filter": "Filter",
      "sort": "Sort",
      "refresh": "Refresh",
      "loading": "Loading...",
      "noData": "No data available",
      "error": "An error occurred"
    }
  }
}
```

**Process:**
1. Extract all hardcoded strings from Fleet components
2. Create translation keys following BP naming conventions
3. Translate to Arabic (use professional translator or existing BP patterns)
4. Translate to French (use professional translator or existing BP patterns)
5. Update all components to use `$t()` or `t()` from useI18n
6. Test language switching (EN ↔ AR ↔ FR)
7. Verify RTL layout works correctly for Arabic

**Acceptance Criteria:**
- Zero hardcoded strings in Fleet components
- All EN keys have AR and FR equivalents
- Translation keys follow BP naming conventions
- RTL layout correct for Arabic
- Date/number formatting respects locale

**Estimated:** 5 days (1 day EN audit, 2 days AR translation, 1 day FR translation, 1 day integration/testing)

---

### Phase 6: Responsive & Theme Fixes (Week 5 — 5 days)
**Goal:** Fix mobile responsiveness and dark/light theme consistency

**Changes:**

**6.1 Mobile Responsiveness:**
- Replace all VDataTable with WpResponsiveList
- Add mobile-specific layouts for detail dialogs
- Fix form field stacking on small screens
- Implement bottom sheet dialogs for mobile workflows
- Add AppFabSpeedDial for quick actions
- Test on iPhone SE, iPhone 14, iPad, Android tablet

**6.2 Theme Consistency:**
- Replace hardcoded colors with theme variables
- Standardize status colors (success/info/warning/error)
- Fix dark mode artifacts (white backgrounds, wrong text colors)
- Use consistent elevation values (1, 2, 4, 8, 12)
- Use consistent border radius (4px, 8px, 12px)
- Test both light and dark themes thoroughly

**Files to Modify:**
- All Fleet .vue files (replace hardcoded colors)
- Fleet SCSS files (use theme variables)
- Status chip usage (use WpStatusChip everywhere)

**Acceptance Criteria:**
- Mobile: No horizontal scrolling, cards instead of tables
- Tablet: Hybrid layout (tables with responsive columns)
- Desktop: Full tables with all columns
- Dark mode: No visual artifacts, proper contrast
- Status colors: Consistent with BP (approved=success, suspended=warning, etc.)
- Elevation/shadows: Match BP pattern
- Border radius: Consistent across all cards/dialogs

**Estimated:** 5 days

---

### Phase 7: Testing & QA (Week 6 — 5 days)
**Goal:** Comprehensive testing across devices, languages, themes, and permissions

**Test Matrix:**

**Devices:**
- Desktop (1920×1080, 1366×768)
- Tablet (iPad Air, Surface Pro)
- Mobile (iPhone 14, Samsung S23)

**Languages:**
- English (EN)
- Arabic (AR) — RTL layout
- French (FR)

**Themes:**
- Light mode
- Dark mode

**Permissions:**
- Super Admin (full access)
- Fleet Manager (read + write)
- Fleet Viewer (read only)
- No access (redirect/hide module)

**Test Scenarios:**

1. **Vehicle List Page:**
   - Load with 0, 10, 100, 1000 vehicles
   - Apply each filter type
   - Sort by each column
   - Select multiple vehicles
   - Bulk delete
   - Export to CSV/PDF
   - Test empty state
   - Test error state
   - Test loading state

2. **Vehicle Detail Dialog:**
   - Open each tab
   - Verify lazy loading
   - Edit vehicle
   - Delete vehicle
   - Assign vehicle
   - Return vehicle
   - Record maintenance
   - Upload document

3. **Driver List Page:**
   - Load with 0, 10, 100 drivers
   - Apply filters
   - Sort by score, level, status
   - View driver details
   - Approve new driver
   - Suspend driver
   - Reinstate driver
   - Revoke driver
   - Renew authorization
   - Record incident

4. **Cross-Device Testing:**
   - Mobile: Swipe actions work
   - Mobile: Bottom sheet dialogs
   - Mobile: FAB speed dial
   - Tablet: Hybrid layout
   - Desktop: Full features

5. **RTL Testing (Arabic):**
   - Text direction correct
   - Icons mirrored correctly
   - Dialogs positioned correctly
   - Tables read right-to-left

6. **Theme Testing:**
   - Light → Dark switch
   - Dark → Light switch
   - No visual artifacts
   - Proper contrast ratios
   - Status colors visible in both themes

7. **Permission Testing:**
   - Create button hidden for read-only
   - Edit button hidden for read-only
   - Delete button hidden for read-only
   - Bulk actions hidden for read-only
   - Admin sees all features

**Bug Tracking:**
- Use project issue tracker
- P0: Blocker (prevents use)
- P1: Critical (major functionality broken)
- P2: Important (minor functionality broken)
- P3: Nice-to-have (cosmetic issues)

**Acceptance Criteria:**
- Zero P0 bugs
- Zero P1 bugs
- < 5 P2 bugs (documented)
- QA sign-off from product owner
- Passes accessibility audit (WCAG 2.1 AA)

**Estimated:** 5 days

---

## Effort Breakdown

| Phase | Description | Days | Cumulative |
|-------|-------------|------|------------|
| 1 | Foundation (Composables + Stores) | 5 | 5 |
| 2 | Vehicle List Page | 5 | 10 |
| 3 | Vehicle Detail Dialog | 6 | 16 |
| 4 | Driver Pages | 8 | 24 |
| 5 | Internationalization (EN/AR/FR) | 5 | 29 |
| 6 | Responsive + Theme | 5 | 34 |
| 7 | Testing & QA | 5 | 39 |
| **Total** | | **39 days** | **~8 weeks** |

---

## Success Criteria

### Visual Consistency
- ✅ Fleet pages visually indistinguishable from BP pages
- ✅ Status colors match BP standards
- ✅ Typography, spacing, elevation consistent
- ✅ Dark/light themes work perfectly

### Functional Consistency
- ✅ All pages use WpDataTableCard + WpResponsiveList
- ✅ All dialogs use WpFormDialog/WpDetailDialog/WpDeleteDialog
- ✅ All forms use useFormDialog composable
- ✅ All lists use useListPage composable
- ✅ All stores follow BP method naming

### Mobile Responsiveness
- ✅ Tables become cards on mobile
- ✅ Dialogs adapt to small screens
- ✅ Forms stack vertically
- ✅ No horizontal scrolling
- ✅ Touch targets ≥44px

### Internationalization
- ✅ Zero hardcoded strings
- ✅ 100% translation coverage (EN/AR/FR)
- ✅ RTL layout works correctly
- ✅ Date/number formatting respects locale

### Accessibility
- ✅ WCAG 2.1 AA compliance
- ✅ Keyboard navigation works
- ✅ Screen reader compatible
- ✅ Sufficient color contrast

### Performance
- ✅ List page loads in <2s with 100 items
- ✅ Detail dialog opens in <500ms
- ✅ No layout shift on load
- ✅ Smooth scrolling on mobile

---

## Risk Mitigation

### Risk 1: Breaking Existing Functionality
**Mitigation:**
- Implement behind feature flag
- Maintain backward compatibility during transition
- Comprehensive E2E testing before deployment
- Rollback plan in place

### Risk 2: Translation Accuracy
**Mitigation:**
- Use professional translators (not Google Translate)
- Review with native speakers
- Compare with existing BP translations for consistency
- User testing with Arabic/French speakers

### Risk 3: Timeline Slippage
**Mitigation:**
- Buffer time in each phase (5 days → estimate 6 days)
- Daily standups to track progress
- Weekly reviews with product owner
- Clear definition of done for each phase

### Risk 4: Scope Creep
**Mitigation:**
- Strict adherence to BP patterns (no "improvements")
- Focus on standardization, not new features
- Document all "nice-to-haves" for future sprint
- Product owner approval required for any deviation

---

## Post-Implementation

### Documentation
- Update developer onboarding guide
- Document Fleet composables (JSDocs)
- Update component library documentation
- Create video walkthrough for team

### Knowledge Transfer
- Code review sessions
- Pair programming during transition
- Architecture decision record (ADR)
- Brown bag session for team

### Maintenance
- Assign code owner for Fleet frontend
- Establish review process for Fleet PRs
- Monitor user feedback for 2 weeks post-launch
- Create backlog for follow-up improvements

---

## Decision Required

**What should we do NOW?**

### Option A: Implement Full Refactoring (8 weeks)
- **Pros:** Complete standardization, best user experience
- **Cons:** Delays next module, resource-intensive
- **Recommendation:** Only if Fleet UI is release-blocking

### Option B: Defer to Planned Sprint (Recommended)
- **Pros:** Allows Resource Validation completion (higher priority)
- **Cons:** Fleet remains inconsistent temporarily
- **Recommendation:** ✅ **Complete Resource Validation (3–4 days), then dedicate 8-week sprint to frontend**

### Option C: Quick Wins Only (1–2 days)
- **Pros:** 60–70% visual improvement with minimal effort
- **Cons:** Incomplete, technical debt remains
- **Quick wins:**
  1. Complete translations (4h)
  2. Fix theme colors (2h)
  3. Add WpResponsiveList (4h)
  4. Remove hardcoded English (2h)
  5. Fix permissions visibility (2h)

---

## Next Steps

1. **User confirms chosen option** (A, B, or C)
2. If Option B (recommended): Complete Resource Validation integration first
3. Schedule 8-week Frontend Sprint with dedicated frontend developer
4. Assign product owner for QA/acceptance
5. Set up feature flag for gradual rollout

---

**Prepared by:** Kiro AI  
**Document:** FLEET_FRONTEND_REFACTORING_ROADMAP.md  
**Related Documents:**
- `FLEET_FRONTEND_BP_STANDARDIZATION.md` (45-page audit)
- `FLEET_MODULE_STATUS_REPORT.md` (module readiness assessment)
- `APPROVED_DRIVERS_COMPLETION_PLAN.md` (driver management backend)
