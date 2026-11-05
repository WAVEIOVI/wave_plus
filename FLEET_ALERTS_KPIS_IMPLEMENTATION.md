# Fleet Alerts, KPIs & Operational Reports — Implementation Report

**Date:** November 5, 2026  
**Module:** People & Skills → Fleet Management  
**Status:** ✅ COMPLETE — All tests passing (24/24)

---

## Executive Summary

Implemented comprehensive Fleet alerts and KPI reporting system covering vehicle maintenance, document expiry, driver authorizations, cost tracking, utilization calculations, and driver statistics. The system uses server-side aggregated statistics, handles overlapping periods correctly, and integrates with the existing WAVE PLUS dashboard infrastructure.

### Key Achievements
- ✅ **FleetAlertsService** — Detects upcoming and overdue alerts for vehicles and drivers
- ✅ **FleetKpiService** — Computes operational KPIs, costs, and utilization metrics
- ✅ **Enhanced HrDashboardService** — Integrated with existing dashboard
- ✅ **Comprehensive Tests** — 24 test scenarios with 76 assertions (100% passing)
- ✅ **No GPS Dependencies** — Focused on operational data only
- ✅ **Reuses Existing Infrastructure** — No separate notification system

---

## Implementation Details

### 1. FleetAlertsService

**Location:** `app/Modules/Hr/Services/FleetAlertsService.php`

**Purpose:** Centralized alert detection for vehicles and drivers

#### Vehicle Alerts

**Maintenance Alerts (`getMaintenanceAlerts`)**
- Date-based: Checks `next_service_date` and `next_inspection_date` on vehicles
- Categorizes as overdue (before today) or upcoming (within threshold)
- Excludes retired/sold vehicles
- Provides days overdue/until due and detailed reason text

**Insurance Alerts (`getInsuranceAlerts`)**
- Reads from `vehicle_documents` table where `document_type = 'insurance'`
- Checks `expiry_date` against today and future threshold
- Returns expired and expiring insurance with vehicle details
- Excludes archived documents and retired vehicles

**Registration Alerts (`getRegistrationAlerts`)**
- Reads from `vehicle_documents` table where `document_type = 'registration'`
- Checks `expiry_date` for compliance deadlines
- Returns expired and expiring registrations
- Includes document number, issuer, and expiry dates

**Other Document Alerts (`getVehicleDocumentAlerts`)**
- Handles remaining document types: technical_inspection, rental_contract, client_authorization, maintenance_record, purchase_invoice, lease_agreement, tax_certificate, other
- Same expiry logic as insurance/registration
- Provides document type and title for identification

#### Driver Alerts

**License Expiry Alerts (`getDriverLicenseAlerts`)**
- Checks `license_expiry_date` on `hr_approved_drivers`
- Excludes revoked drivers
- Returns expired and expiring licenses with employee names
- Includes license numbers and driver authorization numbers

**Authorization Expiry Alerts (`getDriverAuthorizationAlerts`)**
- Checks `expiry_date` for approved drivers
- Tracks authorization expiration separate from license
- Returns overdue and upcoming authorization renewals

**Review Alerts (`getDriverReviewAlerts`)**
- Checks `review_date` for periodic driver reviews
- Only for approved drivers
- Returns overdue and upcoming review dates

**Suspended Drivers (`getSuspendedDriverAlerts`)**
- Detects drivers with `authorization_status = 'suspended'`
- Also checks `suspended_until` date for future-dated suspensions
- Includes suspension reason and indefinite vs time-bound flag
- Operationally relevant for dispatch planning

**Revoked Drivers (`getRevokedDriverAlerts`)**
- Detects drivers with `authorization_status = 'revoked'`
- Critical alert for compliance

#### Alert Aggregation (`getAllAlerts`)

Returns structured summary:
```php
[
    'summary' => [
        'total_alerts' => int,
        'overdue' => int,
        'upcoming' => int,
        'critical' => int,
    ],
    'vehicle_alerts' => [...],
    'driver_alerts' => [...],
]
```

**Critical Definition:**
- Overdue maintenance
- Expired insurance
- Expired registration
- Expired driver licenses
- Suspended/revoked drivers

---

### 2. FleetKpiService

**Location:** `app/Modules/Hr/Services/FleetKpiService.php`

**Purpose:** Compute operational KPIs and statistics using server-side aggregation

#### Vehicle KPIs (`getVehicleKpis`)

**Counts:**
- Total vehicles
- Operational vehicles (available + in_use)
- Vehicles under maintenance
- Vehicles unavailable (out_of_service + retired + sold)
- Operational percentage

**Breakdowns:**
- Status breakdown (count by status)
- Ownership breakdown (company, rental, client)
- Type breakdown (count by vehicle type)

**Implementation:**
```php
Vehicle::select('status', DB::raw('count(*) as count'))
    ->groupBy('status')
    ->pluck('count', 'status')
```

#### Cost KPIs (`getCostKpis`)

**Maintenance Costs (`getMaintenanceCosts`)**
- Aggregates from `vehicle_maintenance_records`
- Filters by `completed_date` in period and `status = 'completed'`
- Currency-aware (filters by specified currency)
- Breakdown by maintenance type (preventive, corrective, inspection, etc.)
- Returns:
  - Total cost
  - Record count
  - Breakdown by type with counts and average costs

**Fuel Costs (`getFuelCosts`)**
- Aggregates from `vehicle_fuel_records`
- Filters by `fill_date` in period
- Currency-aware
- Breakdown by fuel type (petrol, diesel, electric, hybrid, etc.)
- Returns:
  - Total cost
  - Total quantity consumed
  - Record count
  - Breakdown by fuel type with quantities and average unit prices

**Cost Per Kilometer (`getCostPerKilometer`)**
- **Only includes vehicles with:**
  - Odometer tracking enabled (`odometer_km` not null)
  - Cost data available (maintenance + fuel costs in period)
  - Sufficient odometer delta (≥100 km to be statistically meaningful)

- **Calculation Method:**
  1. Get odometer reading at start of period (from fuel/maintenance records)
  2. Get odometer reading at end of period
  3. Calculate km delta
  4. Sum all costs (maintenance + fuel) in period
  5. Cost per km = Total Cost / Total KM

- **Odometer Reading Logic (`getOdometerAtDate`):**
  - First tries fuel records (most frequent updates)
  - Falls back to maintenance records
  - Falls back to vehicle's current odometer if date is today/future
  - Returns null if no reliable reading available

- **Returns:**
  - `average_cost_per_km` — Rounded to 3 decimals
  - `total_km_tracked` — Sum of km deltas across all vehicles
  - `vehicles_with_cost_data` — Count of vehicles included in calculation

#### Utilization KPIs (`getUtilizationKpis`)

**Purpose:** Calculate vehicle utilization percentage without double-counting overlapping periods

**Calculation:**
```
Utilization % = (Total Utilized Days) / (Total Vehicles × Period Days) × 100
```

**Utilization Sources:**
1. **Company Assignments** — From `vehicle_assignments` table
2. **Rental Periods** — From `vehicles.rental_start_date` / `rental_end_date` when `rental_status = 'active'`
3. **Client Usage Periods** — From `vehicles.client_usage_start` / `client_usage_end` when `client_vehicle_status` in ['active', 'pending_return']

**Overlap Handling (`mergeOverlappingPeriods`):**
- Sorts all periods by start date
- Merges overlapping or adjacent periods
- Returns non-overlapping periods only
- **Example:**
  - Assignment: Days 1-5
  - Rental: Days 3-8
  - Merged Result: Days 1-8 (8 unique days, not 11)

**Per-Vehicle Calculation (`calculateVehicleUtilizedDays`):**
- Collects all utilization periods for the vehicle
- Merges overlapping periods
- Counts unique days
- Handles period boundaries (clips to reporting period)

**Returns:**
- `utilization_percentage` — Rounded to 1 decimal
- `total_utilized_days` — Sum across all vehicles
- `max_possible_days` — Total vehicles × period days
- `period_days` — Length of reporting period
- `vehicles_tracked` — Count of non-retired vehicles

#### Driver KPIs (`getDriverKpis`)

**Counts:**
- Total drivers
- Active drivers (authorization_status = 'approved')
- Suspended drivers
- Expired drivers
- Revoked drivers
- Pending drivers

**Renewal & Review:**
- Drivers requiring renewal (within 30 days)
- Drivers requiring review (within 30 days)

**Incident Statistics (Last 30 Days):**
- Recent incident count
- Recent violation count (incident_type = 'traffic_violation')
- Incidents by severity breakdown (minor, moderate, major, critical)

**Safety Score Distribution (`getSafetyScoreDistribution`):**
- Groups approved drivers by `driver_level`:
  - Expert: 95-100
  - Senior: 85-94
  - Standard: 70-84
  - Junior: 50-69
  - Trainee: 0-49
- Returns count and percentage for each level
- Calculates average safety score across all drivers

**Returns:**
```php
[
    'total_drivers' => int,
    'active_drivers' => int,
    'suspended_drivers' => int,
    ... status counts ...,
    'drivers_requiring_renewal' => int,
    'drivers_requiring_review' => int,
    'status_breakdown' => array,
    'recent_incidents' => int,
    'recent_violations' => int,
    'incidents_by_severity' => array,
    'safety_score_distribution' => [
        'distribution' => array, // Per-level breakdown
        'average_score' => float,
        'total_scored_drivers' => int,
    ],
]
```

#### All KPIs Aggregation (`getAllKpis`)

Returns comprehensive report:
```php
[
    'period' => [
        'start_date' => 'Y-m-d',
        'end_date' => 'Y-m-d',
        'days' => int,
        'currency' => string,
    ],
    'vehicle_kpis' => [...],
    'cost_kpis' => [...],
    'utilization_kpis' => [...],
    'driver_kpis' => [...],
]
```

---

### 3. Enhanced HrDashboardService

**Location:** `app/Modules/Hr/Services/HrDashboardService.php`

**Changes:** Updated `fleetStatistics()` method to use new services

**Before:**
- Manual counting of vehicles by status
- Simple service due count
- Driver renewal count

**After:**
- Calls `FleetAlertsService::getAllAlerts(30)`
- Calls `FleetKpiService::getVehicleKpis()`
- Calls `FleetKpiService::getDriverKpis()`
- Returns comprehensive alert summary and KPI breakdowns

**Returns:**
```php
[
    // Counts
    'total_vehicles' => int,
    'operational_vehicles' => int,
    'vehicles_under_maintenance' => int,
    'vehicles_unavailable' => int,
    
    // Driver counts
    'total_drivers' => int,
    'approved_drivers' => int,
    
    // Alerts
    'total_alerts' => int,
    'critical_alerts' => int,
    'overdue_alerts' => int,
    'upcoming_alerts' => int,
    'service_due_count' => int,
    'driver_renewals_due' => int,
    
    // Breakdowns
    'vehicle_status' => array,
    'driver_status' => array,
]
```

---

## Test Coverage

**Location:** `tests/Feature/Hr/FleetAlertsAndKpisTest.php`

**Status:** ✅ 24/24 tests passing, 76 assertions

### Vehicle Alert Tests (6 tests)
1. ✅ Detects overdue maintenance alerts
2. ✅ Detects upcoming maintenance alerts
3. ✅ Detects expired insurance from documents
4. ✅ Detects expiring registration from documents
5. ✅ Detects other expiring documents
6. ✅ Aggregated alerts summary

### Driver Alert Tests (5 tests)
7. ✅ Detects expiring driver license
8. ✅ Detects expired driver authorization
9. ✅ Detects upcoming driver review
10. ✅ Detects suspended drivers
11. ✅ Detects revoked drivers

### Vehicle KPI Tests (2 tests)
12. ✅ Vehicle KPIs counts
13. ✅ Vehicle KPIs status breakdown

### Cost KPI Tests (4 tests)
14. ✅ Maintenance costs calculation
15. ✅ Fuel costs calculation
16. ✅ Total costs aggregation
17. ✅ Cost per kilometer calculation

### Utilization KPI Tests (3 tests)
18. ✅ Utilization calculation simple assignment
19. ✅ Utilization handles overlapping periods without double-counting
20. ✅ Utilization handles rental and client periods

### Driver KPI Tests (3 tests)
21. ✅ Driver KPIs counts
22. ✅ Driver KPIs incident statistics
23. ✅ Safety score distribution

### Integration Test (1 test)
24. ✅ All KPIs aggregation

---

## Business Rules Preserved

### Alert Thresholds
- **Default upcoming threshold:** 30 days
- **Configurable:** `getAllAlerts($daysAhead)` parameter

### Cost Calculations
- **Currency-aware:** All cost queries filter by specified currency
- **Default currency:** TND (Tunisian Dinar)
- **Minimum km delta for cost/km:** 100 km (prevents statistically insignificant results)

### Utilization Calculations
- **Excludes:** Retired and sold vehicles from utilization base
- **Includes:** Company, rental, and client periods
- **Merge Logic:** Overlapping periods counted only once

### Safety Score Levels
- **Expert:** 95-100
- **Senior:** 85-94
- **Standard:** 70-84
- **Junior:** 50-69
- **Trainee:** 0-49

### Incident Tracking Window
- **Recent incidents:** Last 30 days
- **Violation counting:** Only `incident_type = 'traffic_violation'`

---

## Data Sources

### Vehicle Alerts
- `vehicles` table: next_service_date, next_inspection_date, status
- `vehicle_documents` table: document_type, expiry_date, status

### Driver Alerts
- `hr_approved_drivers` table: authorization_status, expiry_date, license_expiry_date, review_date, suspended_from, suspended_until

### Cost KPIs
- `vehicle_maintenance_records` table: completed_date, total_cost, currency, status, odometer_km
- `vehicle_fuel_records` table: fill_date, total_cost, currency, odometer_km

### Utilization KPIs
- `vehicle_assignments` table: start_date, end_date, is_current
- `vehicles` table: rental_start_date, rental_end_date, rental_status, client_usage_start, client_usage_end, client_vehicle_status

### Driver KPIs
- `hr_approved_drivers` table: authorization_status, driver_level, driver_score
- `driver_incidents` table: incident_date, incident_type, severity

---

## API / Service Usage Examples

### Get All Alerts

```php
$alertsService = app(FleetAlertsService::class);
$alerts = $alertsService->getAllAlerts(30); // 30 days ahead

// Access summary
$totalAlerts = $alerts['summary']['total_alerts'];
$overdueAlerts = $alerts['summary']['overdue'];
$criticalAlerts = $alerts['summary']['critical'];

// Access vehicle alerts
$overdueMaintenanceCount = $alerts['vehicle_alerts']['maintenance']['overdue']->count();
$expiredInsurance = $alerts['vehicle_alerts']['insurance']['overdue'];

// Access driver alerts
$expiringLicenses = $alerts['driver_alerts']['licenses']['upcoming'];
$suspendedDrivers = $alerts['driver_alerts']['suspended'];
```

### Get Vehicle KPIs

```php
$kpiService = app(FleetKpiService::class);
$vehicleKpis = $kpiService->getVehicleKpis();

echo "Total Vehicles: {$vehicleKpis['total_vehicles']}";
echo "Operational: {$vehicleKpis['operational_vehicles']} ({$vehicleKpis['operational_percentage']}%)";
echo "Under Maintenance: {$vehicleKpis['vehicles_under_maintenance']}";

// Status breakdown
foreach ($vehicleKpis['status_breakdown'] as $status => $count) {
    echo "{$status}: {$count}";
}
```

### Get Cost KPIs

```php
$startDate = Carbon::today()->subDays(30);
$endDate = Carbon::today();

$costKpis = $kpiService->getCostKpis($startDate, $endDate, 'TND');

echo "Total Maintenance Cost: {$costKpis['total_maintenance_cost']} TND";
echo "Total Fuel Cost: {$costKpis['total_fuel_cost']} TND";
echo "Total Cost: {$costKpis['total_cost']} TND";

if ($costKpis['average_cost_per_km']) {
    echo "Average Cost/km: {$costKpis['average_cost_per_km']} TND/km";
    echo "Based on {$costKpis['total_km_tracked']} km tracked across {$costKpis['vehicles_with_cost_data']} vehicles";
}
```

### Get Utilization KPIs

```php
$startDate = Carbon::today()->subDays(30);
$endDate = Carbon::today();

$utilizationKpis = $kpiService->getUtilizationKpis($startDate, $endDate);

echo "Utilization: {$utilizationKpis['utilization_percentage']}%";
echo "Utilized Days: {$utilizationKpis['total_utilized_days']} / {$utilizationKpis['max_possible_days']}";
echo "Period: {$utilizationKpis['period_days']} days";
echo "Vehicles Tracked: {$utilizationKpis['vehicles_tracked']}";
```

### Get Driver KPIs

```php
$driverKpis = $kpiService->getDriverKpis();

echo "Total Drivers: {$driverKpis['total_drivers']}";
echo "Active: {$driverKpis['active_drivers']}";
echo "Requiring Renewal: {$driverKpis['drivers_requiring_renewal']}";
echo "Requiring Review: {$driverKpis['drivers_requiring_review']}";

echo "Recent Incidents (30d): {$driverKpis['recent_incidents']}";
echo "Recent Violations (30d): {$driverKpis['recent_violations']}";

// Safety score distribution
$distribution = $driverKpis['safety_score_distribution'];
echo "Average Safety Score: {$distribution['average_score']}";
foreach ($distribution['distribution'] as $level => $data) {
    echo "{$level}: {$data['count']} ({$data['percentage']}%)";
}
```

### Get All KPIs

```php
$startDate = Carbon::today()->subDays(30);
$endDate = Carbon::today();

$allKpis = $kpiService->getAllKpis($startDate, $endDate, 'TND');

// Returns comprehensive report with all KPIs
// Structure: ['period', 'vehicle_kpis', 'cost_kpis', 'utilization_kpis', 'driver_kpis']
```

---

## Files Created/Modified

### Services Created
1. `app/Modules/Hr/Services/FleetAlertsService.php` (700+ lines)
2. `app/Modules/Hr/Services/FleetKpiService.php` (650+ lines)

### Services Modified
3. `app/Modules/Hr/Services/HrDashboardService.php` — Enhanced `fleetStatistics()` method

### Tests Created
4. `tests/Feature/Hr/FleetAlertsAndKpisTest.php` (24 tests, 76 assertions)

### Documentation Created
5. `FLEET_ALERTS_KPIS_IMPLEMENTATION.md` (this document)

---

## Integration Points

### Dashboard Integration
- `HrDashboardService::statistics()` already calls `fleetStatistics()`
- Dashboard consumers automatically get enhanced fleet alerts and KPIs
- No API changes required — existing endpoints enriched

### Future Frontend Integration
When building Fleet dashboards/reports, consume these endpoints:

**Example Controller Method:**
```php
public function getFleetAlerts(Request $request)
{
    $daysAhead = $request->integer('days_ahead', 30);
    $alertsService = app(FleetAlertsService::class);
    
    return response()->json([
        'success' => true,
        'data' => $alertsService->getAllAlerts($daysAhead),
    ]);
}

public function getFleetKpis(Request $request)
{
    $startDate = Carbon::parse($request->input('start_date', now()->subDays(30)));
    $endDate = Carbon::parse($request->input('end_date', now()));
    $currency = $request->input('currency', 'TND');
    
    $kpiService = app(FleetKpiService::class);
    
    return response()->json([
        'success' => true,
        'data' => $kpiService->getAllKpis($startDate, $endDate, $currency),
    ]);
}
```

---

## Performance Considerations

### Optimized Queries
- Uses `DB::raw('count(*) as count')` for aggregations
- Groups by single columns to leverage indexes
- Filters on indexed columns (`status`, `expiry_date`, `fill_date`, `completed_date`)
- Eager loads relationships where needed (`with('vehicle')`, `with('employee.user')`)

### Scalability
- All calculations use server-side aggregation (no full table scans in PHP)
- Utilization merging is per-vehicle (not global), scales linearly
- Cost per km only processes vehicles with cost data (not all vehicles)

### Caching Opportunities (Future)
- Alert counts rarely change hourly — cache for 15-30 minutes
- KPIs for historical periods (last month) — cache indefinitely
- Safety score distribution — cache for 1 hour

---

## Limitations & Future Enhancements

### Current Limitations
1. **Odometer-based maintenance:** Not yet implemented (requires `next_service_km` field and comparison logic)
2. **GPS tracking:** Explicitly excluded per requirements
3. **Predictive alerts:** No machine learning for failure prediction
4. **Multi-currency conversion:** Reports in single currency only (no exchange rates)
5. **Historical trends:** No time-series analysis of KPIs

### Future Enhancements
1. **Odometer-based alerts:** Add maintenance alerts when `current_odometer >= next_service_km`
2. **Email/SMS notifications:** Integrate with WAVE PLUS notification system
3. **Alert scheduling:** Daily/weekly alert digest emails
4. **KPI trends:** Month-over-month and year-over-year comparisons
5. **Predictive maintenance:** ML model for predicting failures based on usage patterns
6. **Cost benchmarking:** Compare cost per km against industry standards
7. **Driver performance trends:** Track safety score changes over time
8. **Export reports:** PDF/Excel export of KPIs and alerts

---

## Compliance & Business Rules

### Alert Priority Classification
- **Critical (P1):** Expired insurance, expired registration, expired driver license, suspended/revoked drivers, overdue safety-critical maintenance
- **High (P2):** Expiring (within 7 days) insurance/registration/licenses, overdue non-critical maintenance
- **Medium (P3):** Expiring (8-30 days) documents, upcoming maintenance
- **Low (P4):** Informational alerts, review reminders

### Regulatory Compliance
- Insurance expiry alerts ensure legal compliance
- Registration expiry alerts prevent fines and operational disruptions
- Driver license expiry alerts prevent unauthorized driving
- Technical inspection alerts ensure roadworthiness

### Operational Compliance
- Maintenance alerts prevent breakdowns and downtime
- Driver review alerts ensure periodic assessment
- Suspended driver alerts prevent unauthorized dispatch
- Cost tracking supports budget management and audit trails

---

## Testing Strategy

### Unit Testing
- Service methods tested in isolation
- Mock data for various scenarios
- Edge cases covered (no data, overlapping periods, retired vehicles)

### Integration Testing
- Full workflow tests with database
- Multiple entities and relationships
- Real date calculations with Carbon

### Test Scenarios Covered
- **Alert Detection:** Overdue, upcoming, expired, expiring
- **Cost Calculations:** Maintenance, fuel, total, cost per km
- **Utilization:** Simple assignments, overlapping periods, rental/client periods
- **Driver Stats:** Counts, incidents, safety scores, distribution

### Test Data Quality
- Uses factories where available
- Manual creation with all required fields
- Realistic data ranges and values
- Proper relationships and foreign keys

---

## Verification Commands

```powershell
# Run all Fleet alerts and KPIs tests
php artisan test --filter=FleetAlertsAndKpisTest

# Run specific test category
php artisan test --filter="FleetAlertsAndKpisTest::test_detects_overdue_maintenance"

# Run with detailed output
php artisan test --filter=FleetAlertsAndKpisTest --testdox

# Check code style
composer pint:test app/Modules/Hr/Services/FleetAlertsService.php
composer pint:test app/Modules/Hr/Services/FleetKpiService.php
```

---

## Summary

✅ **Implementation Complete**
- All requirements met
- 24/24 tests passing
- Server-side aggregation
- Correct utilization without double-counting
- Currency and measurement unit consistency
- Integrated with existing WAVE PLUS infrastructure
- No GPS dependencies
- No separate notification system

✅ **Ready for Production**
- Comprehensive test coverage
- Performance optimized
- Business rules preserved
- Documentation complete
- Integration points defined

✅ **Next Steps**
- Create API controllers for frontend consumption
- Add alert notification scheduling
- Implement odometer-based maintenance alerts
- Build dashboard UI components

---

**Report End**
