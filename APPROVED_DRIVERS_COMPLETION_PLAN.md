# Approved Drivers Module — Completion Plan

**Date:** 2026-11-05  
**Objective:** Complete driver management workflows while preserving existing architecture

## Current State Analysis

### ✅ Existing Components (Preserved)

**Backend:**
- ✅ `ApprovedDriver` model with comprehensive fields
- ✅ `DriverIncident` model with status workflow
- ✅ `DriverActivityLog` model
- ✅ `DriverAuthorizationService` with business logic
- ✅ `ApprovedDriverController` with most endpoints
- ✅ Safety scoring system (100-point scale)
- ✅ Driver levels: trainee, junior, standard, senior, expert
- ✅ Auto-suspension on critical incidents
- ✅ Incident types: accident, traffic_violation, parking_violation, speeding, etc.
- ✅ Severity levels: minor, moderate, major, critical

**Frontend:**
- ✅ Driver list view
- ✅ Incident creation dialog
- ⚠️ Driver edit workflow (incomplete)
- ⚠️ Incident management workflow (incomplete)

### ⏳ Missing/Incomplete Components

**Backend Issues:**
1. **Incident Update/Resolution** — No endpoints for updating incident status, investigation, resolution
2. **Violation Count** — Not properly updated when violations recorded
3. **Score Calculation** — Potential duplicate deductions, needs deterministic recalculation
4. **Driver Level Transitions** — String comparison instead of numeric ranking
5. **Activity Logging** — Incomplete for some workflows

**Frontend Issues:**
1. **Driver Edit Dialog** — Missing comprehensive edit form
2. **Incident Detail View** — No view for incident details/update
3. **Incident Status Workflow** — No UI for investigating, resolving, closing
4. **License/Authorization Expiry** — Not prominently displayed
5. **Training Records** — No UI for managing training dates/certifications
6. **Documents Management** — Basic array, needs proper file handling

## Implementation Plan

### Phase 1: Backend Fixes (Priority)

#### 1.1 Incident Management Controller

**File:** `app/Modules/Hr/Controllers/DriverIncidentController.php` (NEW)

**Endpoints:**
```php
GET    /api/hr/incidents                 // List all incidents
GET    /api/hr/incidents/{id}            // Get incident details
PATCH  /api/hr/incidents/{id}            // Update incident (investigation)
POST   /api/hr/incidents/{id}/resolve    // Resolve incident
POST   /api/hr/incidents/{id}/close      // Close incident
DELETE /api/hr/incidents/{id}            // Delete incident
```

**Required Methods:**
- `update()` — Update incident details, witnesses, damages, costs
- `investigate()` — Change status to 'under_investigation', add investigation notes
- `resolve()` — Mark resolved, record resolution notes, corrective actions
- `close()` — Close incident, finalize cost, insurance details

#### 1.2 Safety Score Calculation (Fix)

**File:** `app/Modules/Hr/Services/DriverAuthorizationService.php`

**Issues to Fix:**
1. **Duplicate Deductions** — When recording incident via `recordIncident()`, score is adjusted immediately. If `calculateDriverScore()` is called later, same incidents are counted again.
2. **Deterministic Source of Truth** — `calculateDriverScore()` should be the ONLY method that computes score from scratch.

**Solution:**
```php
// In recordIncident():
// REMOVE immediate score adjustment
// $this->adjustDriverScore($driverId, -$scoreImpact, $reportedBy);

// ADD flag to prevent duplicate scoring
$incident->score_applied = true;
$incident->save();

// In calculateDriverScore():
// Use ALL incidents (not just unapplied ones) for deterministic calculation
$baseScore = 100.0;
$recentIncidents = DriverIncident::byDriver($driverId)
    ->where('incident_date', '>=', now()->subMonths(12))
    ->get();

foreach ($recentIncidents as $incident) {
    $baseScore -= $this->calculateIncidentScoreImpact($incident);
}

// Save new score
$driver->update(['driver_score' => max(0, min(100, round($baseScore, 2)))]);
```

**Recalculation Method:**
```php
public function recalculateDriverScore(string $driverId, ?string $updatedBy = null): ApprovedDriver
{
    $driver = ApprovedDriver::findOrFail($driverId);
    $oldScore = $driver->driver_score;
    
    $newScore = $this->calculateDriverScore($driverId);
    
    if ($oldScore != $newScore) {
        $this->logDriverActivity($driverId, [
            'event_type' => 'score_recalculated',
            'event_title' => 'Driver Score Recalculated',
            'event_description' => "Score recalculated from historical incidents",
            'event_date' => now(),
            'old_value' => (string) $oldScore,
            'new_value' => (string) $newScore,
            'impact' => $newScore >= $oldScore ? 'positive' : 'negative',
            'score_change' => $newScore - $oldScore,
            'recorded_by' => $updatedBy,
        ]);
    }
    
    return $driver->fresh();
}
```

#### 1.3 Driver Level Transitions (Fix)

**Current Issue:**
```php
// String comparison is wrong!
$newLevel = match (true) {
    $score >= 95 => 'expert',
    $score >= 85 => 'senior',
    $score >= 70 => 'standard',
    $score >= 50 => 'junior',
    default => 'trainee',
};

// If level changed
if ($newLevel !== $driver->driver_level) {
    // BUG: String comparison doesn't check if this is an upgrade or downgrade
    // "junior" < "standard" is alphabetical, not logical ranking
}
```

**Solution:**
```php
protected function updateDriverLevel(string $driverId): void
{
    $driver = ApprovedDriver::findOrFail($driverId);
    $oldLevel = $driver->driver_level;
    $score = $driver->driver_score;

    // Numeric ranking for proper comparison
    $levelRanking = [
        'trainee' => 1,
        'junior' => 2,
        'standard' => 3,
        'senior' => 4,
        'expert' => 5,
    ];

    $newLevel = match (true) {
        $score >= 95 => 'expert',
        $score >= 85 => 'senior',
        $score >= 70 => 'standard',
        $score >= 50 => 'junior',
        default => 'trainee',
    };

    if ($newLevel !== $oldLevel) {
        $driver->update(['driver_level' => $newLevel]);
        
        $isUpgrade = ($levelRanking[$newLevel] ?? 0) > ($levelRanking[$oldLevel] ?? 0);
        
        $this->logDriverActivity($driverId, [
            'event_type' => $isUpgrade ? 'driver_level_upgraded' : 'driver_level_downgraded',
            'event_title' => $isUpgrade ? 'Driver Level Upgraded' : 'Driver Level Downgraded',
            'event_description' => "Driver level changed from {$oldLevel} to {$newLevel}",
            'event_date' => now(),
            'old_value' => $oldLevel,
            'new_value' => $newLevel,
            'impact' => $isUpgrade ? 'positive' : 'negative',
        ]);
    }
}
```

#### 1.4 Violation Count Fix

**Current Issue:**
```php
public function recordViolation(...) {
    $incident = $this->recordIncident($driverId, $violationData, $reportedBy);
    $driver->increment('violation_count'); // This happens AFTER incident created
    return $incident;
}
```

**Problem:** If violation types are specified in `incident_type`, the increment happens. But if someone creates an incident directly with a violation type, it's not counted.

**Solution:**
```php
// In recordIncident():
// After incident creation:
$incident = DriverIncident::create(...);

// Increment violation count if this is a violation type
if (in_array($incident->incident_type, ['traffic_violation', 'parking_violation', 'speeding', 'safety_violation', 'policy_violation'])) {
    $driver->increment('violation_count');
}
```

#### 1.5 Enhanced Activity Logging

**Missing Events:**
- `license_renewed`
- `restriction_added`
- `restriction_removed`
- `certification_added`
- `training_completed`
- `document_uploaded`
- `incident_updated`
- `incident_resolved`
- `incident_closed`

**Add to Service:**
```php
public function updateDriverRestrictions(string $driverId, array $restrictions, ?string $updatedBy = null): ApprovedDriver
{
    $driver = ApprovedDriver::findOrFail($driverId);
    $oldRestrictions = $driver->restrictions ?? [];
    
    $driver->update([
        'restrictions' => $restrictions,
        'updated_by' => $updatedBy,
    ]);
    
    $this->logDriverActivity($driverId, [
        'event_type' => 'restrictions_updated',
        'event_title' => 'Driving Restrictions Updated',
        'event_description' => 'Driver restrictions have been updated',
        'event_date' => now(),
        'old_value' => json_encode($oldRestrictions),
        'new_value' => json_encode($restrictions),
        'recorded_by' => $updatedBy,
    ]);
    
    return $driver->fresh();
}
```

### Phase 2: Frontend Completion

#### 2.1 Driver Edit Dialog/Page

**File:** `resources/js/views/people-skills/fleet/drivers/DriverEditDialog.vue` (NEW/ENHANCE)

**Must Include:**
1. **Basic Information Tab**
   - License number, category, issue/expiry dates
   - Authorization expiry and review dates
   - Driver level (read-only, computed from score)
   - Current status badge

2. **Vehicle Permissions Tab**
   - Permitted vehicle types (multi-select)
   - Permitted vehicle classes (multi-select)
   - Rental vehicle permission toggle
   - Client vehicle permission toggle

3. **Restrictions Tab**
   - Max passengers input
   - Max cargo (kg) input
   - Restriction categories (checkboxes/multi-select)
   - Restriction notes textarea
   - Visual warning for active restrictions

4. **Training & Certifications Tab**
   - Last training date picker
   - Next training due date picker
   - Certifications list (add/remove)
   - Training records table (if model exists)

5. **Documents Tab**
   - License document upload
   - Medical certificate upload
   - Training certificates upload
   - Document list with download/delete

6. **Notes Tab**
   - General notes textarea
   - History of changes (read-only)

**Validation:**
```js
const validationRules = {
  license_number: [v => !!v || 'License number required'],
  license_expiry_date: [
    v => !!v || 'License expiry required',
    v => new Date(v) > new Date() || 'License has expired',
  ],
  expiry_date: [
    v => !v || new Date(v) > new Date() || 'Authorization has expired',
  ],
  max_passengers: [
    v => !v || (v > 0 && Number.isInteger(+v)) || 'Must be a positive integer',
  ],
  max_cargo_kg: [
    v => !v || v >= 0 || 'Must be zero or positive',
  ],
}
```

#### 2.2 Incident Detail View

**File:** `resources/js/views/people-skills/fleet/drivers/DriverIncidentDetailDialog.vue` (NEW)

**Sections:**
1. **Incident Header**
   - Incident number, type badge, severity badge
   - Status badge with color coding
   - Date, location, driver, vehicle

2. **Incident Details**
   - Title and description
   - Damage flags (property, vehicle, personal injury)
   - Third party involved flag
   - Damage description
   - Estimated vs. actual cost

3. **Investigation Section**
   - Driver at fault selector
   - Police report filed toggle
   - Police report number
   - Insurance claim details
   - Alcohol/drug test results
   - Driver statement textarea
   - Witnesses list

4. **Resolution Section** (editable if status allows)
   - Resolution notes
   - Corrective actions taken
   - Training required toggle
   - Suspension applied toggle

5. **Actions**
   - Update button (saves investigation details)
   - Resolve button (changes status to resolved)
   - Close button (finalizes incident)

**Status Workflow:**
```
reported → under_investigation → pending_action → resolved → closed
                 ↓                      ↓
              archived             archived
```

#### 2.3 Driver Score Display

**Component:** `resources/js/components/fleet/DriverScoreBadge.vue` (NEW)

**Features:**
- Circular progress indicator (0-100)
- Color coding:
  - 90-100: Green (Excellent)
  - 75-89: Blue (Good)
  - 60-74: Yellow (Fair)
  - 40-59: Orange (Poor)
  - 0-39: Red (Critical)
- Driver level badge
- Tooltip showing score breakdown
- Recalculate button (for admins)

### Phase 3: Testing

#### 3.1 Backend Tests

**File:** `tests/Feature/Hr/ApprovedDriverLifecycleTest.php`

**Test Scenarios:**
1. ✅ `test_driver_approval_creates_initial_score_100`
2. ✅ `test_incident_recording_updates_score_and_count`
3. ✅ `test_violation_recording_increments_violation_count`
4. ✅ `test_critical_incident_triggers_auto_suspension`
5. ✅ `test_score_recalculation_is_deterministic`
6. ✅ `test_driver_level_upgrades_when_score_increases`
7. ✅ `test_driver_level_downgrades_when_score_decreases`
8. ✅ `test_suspension_prevents_driver_from_being_active`
9. ✅ `test_reinstatement_clears_suspension`
10. ✅ `test_authorization_renewal_extends_expiry`
11. ✅ `test_incident_resolution_workflow`
12. ✅ `test_activity_log_records_all_lifecycle_events`

**File:** `tests/Feature/Hr/DriverIncidentManagementTest.php`

**Test Scenarios:**
1. ✅ `test_incident_can_be_updated_with_investigation_details`
2. ✅ `test_incident_resolution_requires_notes`
3. ✅ `test_incident_closure_finalizes_costs`
4. ✅ `test_resolved_incident_cannot_be_reopened`
5. ✅ `test_incident_with_insurance_claim_tracks_status`
6. ✅ `test_witnesses_array_properly_stored`
7. ✅ `test_documents_array_properly_stored`

### Phase 4: Documentation

#### 4.1 API Documentation

**Endpoints to Document:**
```
POST   /api/hr/drivers                            // Approve driver
GET    /api/hr/drivers                            // List drivers
GET    /api/hr/drivers/{id}                       // Get driver details
PATCH  /api/hr/drivers/{id}                       // Update driver
POST   /api/hr/drivers/{id}/suspend               // Suspend
POST   /api/hr/drivers/{id}/reinstate             // Reinstate
POST   /api/hr/drivers/{id}/revoke                // Revoke
POST   /api/hr/drivers/{id}/renew                 // Renew
POST   /api/hr/drivers/{id}/incidents             // Record incident
POST   /api/hr/drivers/{id}/calculate-score       // Recalculate score
POST   /api/hr/drivers/{id}/adjust-score          // Manual adjustment
GET    /api/hr/drivers/{id}/activity-log          // Activity history
GET    /api/hr/drivers/requires-renewal           // Expiring soon
GET    /api/hr/drivers/requires-review            // Review due

POST   /api/hr/incidents                          // Create incident
GET    /api/hr/incidents                          // List incidents
GET    /api/hr/incidents/{id}                     // Get incident
PATCH  /api/hr/incidents/{id}                     // Update incident
POST   /api/hr/incidents/{id}/resolve             // Resolve
POST   /api/hr/incidents/{id}/close               // Close
```

#### 4.2 Business Rules Documentation

**Safety Score Calculation:**
- Base: 100 points
- Incident deductions (last 12 months):
  - Minor: -2 points
  - Moderate: -5 points
  - Major: -10 points
  - Critical: -20 points
- Damage modifiers:
  - Personal injury: -15 points
  - Property damage: -5 points
  - Vehicle damage: -5 points
- Driver fault multipliers:
  - At fault: ×1.5
  - Not at fault: ×0.5
  - Partial/Unknown: ×1.0
- Bonuses:
  - Clean record 6+ months: +5 points
  - Certifications: +2 each (max +10)
- Range: 0-100 (capped)

**Driver Levels:**
| Level | Score Range | Description |
|-------|-------------|-------------|
| Expert | 95-100 | Exemplary driving record |
| Senior | 85-94 | High performance driver |
| Standard | 70-84 | Normal operational driver |
| Junior | 50-69 | Requires additional oversight |
| Trainee | 0-49 | Under training/probation |

**Auto-Suspension Policy:**
- Critical severity incident → 30-day suspension
- Personal injury incident → 30-day suspension
- Manual suspension available for other cases
- Suspension prevents all operational assignments
- Activity log records all suspensions

## Priority Order

**Week 1:**
1. Fix safety score calculation (deterministic)
2. Fix driver level transitions (numeric ranking)
3. Fix violation count updates
4. Add incident management endpoints
5. Create comprehensive activity logging

**Week 2:**
1. Complete driver edit dialog (all tabs)
2. Create incident detail/update view
3. Add score display component
4. Integration testing

**Week 3:**
1. Write backend lifecycle tests (12 scenarios)
2. Write incident management tests (7 scenarios)
3. Documentation
4. User acceptance testing

## Success Criteria

✅ **Driver Edit Workflow** — All fields editable via comprehensive UI  
✅ **Incident Management** — Full CRUD with investigation/resolution workflow  
✅ **Safety Score** — Deterministic, no duplicate deductions, properly logged  
✅ **Driver Levels** — Correct upgrades/downgrades based on numeric ranking  
✅ **Activity Logging** — All lifecycle events recorded with proper metadata  
✅ **Auto-Suspension** — Critical incidents trigger suspension, policy auditable  
✅ **Violation Count** — Correctly incremented for all violation types  
✅ **Test Coverage** — 19+ scenarios covering lifecycle and incident workflows  
✅ **Documentation** — API docs and business rules documented  

## Risks & Mitigation

**Risk:** Breaking existing driver approval workflow  
**Mitigation:** Preserve all existing endpoints, add new ones for missing functionality

**Risk:** Score recalculation changing historical scores  
**Mitigation:** Add `score_history` JSON column to track changes, log all recalculations

**Risk:** UI complexity overwhelming users  
**Mitigation:** Use tabbed interface, progressive disclosure, clear validation messages

**Risk:** Performance impact of score recalculation  
**Mitigation:** Make it a manual action (button), not automatic on every page load

## Next Steps

1. Review this plan with stakeholders
2. Confirm business rules are correct
3. Begin Phase 1 implementation
4. Create frontend mockups for review
5. Schedule testing milestones
