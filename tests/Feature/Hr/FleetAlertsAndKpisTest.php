<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Modules\Hr\Models\ApprovedDriver;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\DriverIncident;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\Hr\Models\VehicleAssignment;
use App\Modules\Hr\Models\VehicleDocument;
use App\Modules\Hr\Models\VehicleFuelRecord;
use App\Modules\Hr\Models\VehicleMaintenanceRecord;
use App\Modules\Hr\Services\FleetAlertsService;
use App\Modules\Hr\Services\FleetKpiService;
use App\Modules\Inventory\Models\WarehouseSite;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test Fleet Alerts and KPI calculations.
 *
 * Covers:
 * - Vehicle maintenance alerts (date-based)
 * - Insurance/registration expiry from vehicle_documents
 * - Driver license/authorization expiry
 * - Cost KPIs (maintenance, fuel, cost per km)
 * - Utilization KPIs (without double-counting overlaps)
 * - Driver KPIs (counts, incidents, safety scores)
 */
class FleetAlertsAndKpisTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Employee $employee;

    private WarehouseSite $site;

    private Vehicle $vehicle1;

    private Vehicle $vehicle2;

    private ApprovedDriver $driver1;

    private FleetAlertsService $alertsService;

    private FleetKpiService $kpiService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        \App\Models\Role::findOrCreate('Super Admin', 'web');
        $this->admin->assignRole('Super Admin');

        // Create department and employee
        $dept = Department::create(['name' => 'Fleet', 'code' => 'FLEET']);

        $empUser = User::factory()->create(['name' => 'Driver User']);

        $this->employee = Employee::create([
            'first_name' => 'John',
            'last_name' => 'Driver',
            'work_email' => 'john.driver@test.local',
            'hire_date' => now()->subYear()->format('Y-m-d'),
            'department_id' => $dept->id,
            'status' => 'active',
            'user_id' => $empUser->id,
        ]);

        $this->site = WarehouseSite::create([
            'code' => 'WH-ALERT-TEST',
            'name' => ['en' => 'Alert Test Warehouse'],
            'status' => 'active',
        ]);

        // Create test vehicles
        $this->vehicle1 = Vehicle::create([
            'fleet_code' => 'VEH-ALERT-01',
            'plate_number' => '100-ALT-001',
            'label' => 'Alert Test Vehicle 1',
            'type' => 'van',
            'ownership_type' => 'company',
            'status' => 'available',
            'odometer_km' => 50000,
            'next_service_date' => now()->addDays(15)->format('Y-m-d'),
            'next_inspection_date' => now()->addDays(20)->format('Y-m-d'),
        ]);

        $this->vehicle2 = Vehicle::create([
            'fleet_code' => 'VEH-ALERT-02',
            'plate_number' => '100-ALT-002',
            'label' => 'Alert Test Vehicle 2',
            'type' => 'truck',
            'ownership_type' => 'company',
            'status' => 'in_use',
            'odometer_km' => 80000,
        ]);

        // Create driver
        $this->driver1 = ApprovedDriver::create([
            'driver_authorization_number' => 'DRV-ALERT-01',
            'employee_id' => $this->employee->id,
            'authorization_status' => 'approved',
            'authorization_date' => now()->subMonths(6)->format('Y-m-d'),
            'expiry_date' => now()->addMonths(6)->format('Y-m-d'),
            'license_number' => 'LIC-ALERT-001',
            'license_expiry_date' => now()->addMonths(3)->format('Y-m-d'),
            'review_date' => now()->addMonths(1)->format('Y-m-d'),
            'permitted_vehicle_types' => ['van', 'truck'],
            'permitted_vehicle_classes' => ['light', 'medium'],
            'can_drive_rental' => true,
            'can_drive_client_vehicles' => true,
            'max_cargo_kg' => 5000,
            'max_passengers' => 10,
            'driver_score' => 88.00,
            'driver_level' => 'senior',
            'incident_count' => 1,
            'violation_count' => 0,
        ]);

        $this->alertsService = app(FleetAlertsService::class);
        $this->kpiService = app(FleetKpiService::class);
    }

    // ═══════════════════════════════════════════════════════════════
    //  Vehicle Alert Tests
    // ═══════════════════════════════════════════════════════════════

    public function test_detects_overdue_maintenance_alerts(): void
    {
        $this->vehicle1->update([
            'next_service_date' => now()->subDays(5)->format('Y-m-d'),
        ]);

        $today = Carbon::today();
        $futureDate = $today->copy()->addDays(30);

        $alerts = $this->alertsService->getMaintenanceAlerts($today, $futureDate);

        $this->assertGreaterThan(0, $alerts['overdue']->count());
        $this->assertNotEmpty($alerts['overdue']->firstWhere('fleet_code', 'VEH-ALERT-01'));
    }

    public function test_detects_upcoming_maintenance_alerts(): void
    {
        $today = Carbon::today();
        $futureDate = $today->copy()->addDays(30);

        $alerts = $this->alertsService->getMaintenanceAlerts($today, $futureDate);

        $this->assertGreaterThan(0, $alerts['upcoming']->count());
        $upcoming = $alerts['upcoming']->firstWhere('fleet_code', 'VEH-ALERT-01');
        $this->assertNotNull($upcoming);
        $this->assertEquals('maintenance_upcoming', $upcoming['alert_type']);
    }

    public function test_detects_expired_insurance_from_documents(): void
    {
        VehicleDocument::create([
            'vehicle_id' => $this->vehicle1->id,
            'document_type' => 'insurance',
            'title' => 'Comprehensive Insurance',
            'document_number' => 'INS-2024-001',
            'issuer' => 'Insurance Company',
            'issue_date' => now()->subYear()->format('Y-m-d'),
            'expiry_date' => now()->subDays(10)->format('Y-m-d'),
            'status' => 'expired',
        ]);

        $today = Carbon::today();
        $futureDate = $today->copy()->addDays(30);

        $alerts = $this->alertsService->getInsuranceAlerts($today, $futureDate);

        $this->assertEquals(1, $alerts['overdue']->count());
        $alert = $alerts['overdue']->first();
        $this->assertEquals('insurance_expired', $alert['alert_type']);
        $this->assertEquals($this->vehicle1->id, $alert['vehicle_id']);
    }

    public function test_detects_expiring_registration_from_documents(): void
    {
        VehicleDocument::create([
            'vehicle_id' => $this->vehicle1->id,
            'document_type' => 'registration',
            'title' => 'Vehicle Registration',
            'document_number' => 'REG-2024-001',
            'issuer' => 'Ministry of Transport',
            'issue_date' => now()->subYear()->format('Y-m-d'),
            'expiry_date' => now()->addDays(15)->format('Y-m-d'),
            'status' => 'active',
        ]);

        $today = Carbon::today();
        $futureDate = $today->copy()->addDays(30);

        $alerts = $this->alertsService->getRegistrationAlerts($today, $futureDate);

        $this->assertEquals(1, $alerts['upcoming']->count());
        $alert = $alerts['upcoming']->first();
        $this->assertEquals('registration_expiring', $alert['alert_type']);
        $this->assertEquals($this->vehicle1->id, $alert['vehicle_id']);
    }

    public function test_detects_other_expiring_documents(): void
    {
        VehicleDocument::create([
            'vehicle_id' => $this->vehicle1->id,
            'document_type' => 'technical_inspection',
            'title' => 'Annual Technical Inspection',
            'document_number' => 'TECH-2024-001',
            'expiry_date' => now()->addDays(10)->format('Y-m-d'),
            'status' => 'active',
        ]);

        $today = Carbon::today();
        $futureDate = $today->copy()->addDays(30);

        $alerts = $this->alertsService->getVehicleDocumentAlerts($today, $futureDate);

        $this->assertEquals(1, $alerts['upcoming']->count());
        $alert = $alerts['upcoming']->first();
        $this->assertEquals('document_expiring', $alert['alert_type']);
        $this->assertEquals('technical_inspection', $alert['document_type']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  Driver Alert Tests
    // ═══════════════════════════════════════════════════════════════

    public function test_detects_expiring_driver_license(): void
    {
        $this->driver1->update([
            'license_expiry_date' => now()->addDays(20)->format('Y-m-d'),
        ]);

        $today = Carbon::today();
        $futureDate = $today->copy()->addDays(30);

        $alerts = $this->alertsService->getDriverLicenseAlerts($today, $futureDate);

        $this->assertEquals(1, $alerts['upcoming']->count());
        $alert = $alerts['upcoming']->first();
        $this->assertEquals('license_expiring', $alert['alert_type']);
        $this->assertEquals($this->driver1->id, $alert['driver_id']);
    }

    public function test_detects_expired_driver_authorization(): void
    {
        $this->driver1->update([
            'expiry_date' => now()->subDays(5)->format('Y-m-d'),
        ]);

        $today = Carbon::today();
        $futureDate = $today->copy()->addDays(30);

        $alerts = $this->alertsService->getDriverAuthorizationAlerts($today, $futureDate);

        $this->assertEquals(1, $alerts['overdue']->count());
        $alert = $alerts['overdue']->first();
        $this->assertEquals('authorization_expired', $alert['alert_type']);
    }

    public function test_detects_upcoming_driver_review(): void
    {
        $today = Carbon::today();
        $futureDate = $today->copy()->addDays(30);

        $alerts = $this->alertsService->getDriverReviewAlerts($today, $futureDate);

        $this->assertGreaterThan(0, $alerts['upcoming']->count());
        $alert = $alerts['upcoming']->first();
        $this->assertEquals('review_upcoming', $alert['alert_type']);
    }

    public function test_detects_suspended_drivers(): void
    {
        $this->driver1->update([
            'authorization_status' => 'suspended',
            'suspended_from' => now()->subDays(5)->format('Y-m-d'),
            'suspended_until' => now()->addDays(25)->format('Y-m-d'),
            'suspension_reason' => 'Critical incident',
        ]);

        $alerts = $this->alertsService->getSuspendedDriverAlerts();

        $this->assertEquals(1, $alerts->count());
        $alert = $alerts->first();
        $this->assertEquals('driver_suspended', $alert['alert_type']);
        $this->assertEquals($this->driver1->id, $alert['driver_id']);
        $this->assertFalse($alert['is_indefinite']);
    }

    public function test_detects_revoked_drivers(): void
    {
        $this->driver1->update([
            'authorization_status' => 'revoked',
        ]);

        $alerts = $this->alertsService->getRevokedDriverAlerts();

        $this->assertEquals(1, $alerts->count());
        $alert = $alerts->first();
        $this->assertEquals('driver_revoked', $alert['alert_type']);
        $this->assertEquals($this->driver1->id, $alert['driver_id']);
    }

    public function test_aggregated_alerts_summary(): void
    {
        // Create overdue maintenance
        $this->vehicle1->update(['next_service_date' => now()->subDays(5)->format('Y-m-d')]);

        // Create expired insurance
        VehicleDocument::create([
            'vehicle_id' => $this->vehicle1->id,
            'document_type' => 'insurance',
            'title' => 'Insurance',
            'expiry_date' => now()->subDays(3)->format('Y-m-d'),
            'status' => 'expired',
        ]);

        $alerts = $this->alertsService->getAllAlerts(30);

        $this->assertArrayHasKey('summary', $alerts);
        $this->assertGreaterThanOrEqual(2, $alerts['summary']['overdue']);
        $this->assertGreaterThan(0, $alerts['summary']['total_alerts']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  Vehicle KPI Tests
    // ═══════════════════════════════════════════════════════════════

    public function test_vehicle_kpis_counts(): void
    {
        $kpis = $this->kpiService->getVehicleKpis();

        $this->assertArrayHasKey('total_vehicles', $kpis);
        $this->assertArrayHasKey('operational_vehicles', $kpis);
        $this->assertArrayHasKey('operational_percentage', $kpis);
        $this->assertEquals(2, $kpis['total_vehicles']);
        $this->assertEquals(2, $kpis['operational_vehicles']); // available + in_use
        $this->assertEquals(100.0, $kpis['operational_percentage']);
    }

    public function test_vehicle_kpis_status_breakdown(): void
    {
        // Add retired vehicle
        Vehicle::create([
            'fleet_code' => 'VEH-RETIRED',
            'plate_number' => '999-RET-999',
            'label' => 'Retired Vehicle',
            'type' => 'van',
            'ownership_type' => 'company',
            'status' => 'retired',
        ]);

        $kpis = $this->kpiService->getVehicleKpis();

        $this->assertArrayHasKey('status_breakdown', $kpis);
        $this->assertEquals(1, $kpis['status_breakdown']['retired']);
        $this->assertEquals(1, $kpis['vehicles_unavailable']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  Cost KPI Tests
    // ═══════════════════════════════════════════════════════════════

    public function test_maintenance_costs_calculation(): void
    {
        VehicleMaintenanceRecord::create([
            'maintenance_number' => 'MAINT-TEST-001',
            'vehicle_id' => $this->vehicle1->id,
            'maintenance_type' => 'preventive',
            'priority' => 'normal',
            'scheduled_date' => now()->subDays(5)->format('Y-m-d'),
            'completed_date' => now()->subDays(3)->format('Y-m-d'),
            'title' => 'Oil Change',
            'labor_cost' => 50.000,
            'parts_cost' => 150.000,
            'total_cost' => 200.000,
            'currency' => 'TND',
            'status' => 'completed',
        ]);

        VehicleMaintenanceRecord::create([
            'maintenance_number' => 'MAINT-TEST-002',
            'vehicle_id' => $this->vehicle1->id,
            'maintenance_type' => 'corrective',
            'priority' => 'high',
            'scheduled_date' => now()->subDays(10)->format('Y-m-d'),
            'completed_date' => now()->subDays(8)->format('Y-m-d'),
            'title' => 'Brake Repair',
            'labor_cost' => 100.000,
            'parts_cost' => 400.000,
            'total_cost' => 500.000,
            'currency' => 'TND',
            'status' => 'completed',
        ]);

        $startDate = Carbon::today()->subDays(15);
        $endDate = Carbon::today();

        $costKpis = $this->kpiService->getCostKpis($startDate, $endDate, 'TND');

        $this->assertEquals(700.0, $costKpis['total_maintenance_cost']);
        $this->assertEquals(2, count($costKpis['maintenance_breakdown']));
    }

    public function test_fuel_costs_calculation(): void
    {
        VehicleFuelRecord::create([
            'vehicle_id' => $this->vehicle1->id,
            'fill_date' => now()->subDays(5)->format('Y-m-d'),
            'fuel_type' => 'diesel',
            'quantity' => 50.000,
            'unit' => 'L',
            'unit_price' => 2.500,
            'total_cost' => 125.000,
            'currency' => 'TND',
            'odometer_km' => 50500,
        ]);

        VehicleFuelRecord::create([
            'vehicle_id' => $this->vehicle2->id,
            'fill_date' => now()->subDays(3)->format('Y-m-d'),
            'fuel_type' => 'diesel',
            'quantity' => 80.000,
            'unit' => 'L',
            'unit_price' => 2.500,
            'total_cost' => 200.000,
            'currency' => 'TND',
            'odometer_km' => 80500,
        ]);

        $startDate = Carbon::today()->subDays(10);
        $endDate = Carbon::today();

        $costKpis = $this->kpiService->getCostKpis($startDate, $endDate, 'TND');

        $this->assertEquals(325.0, $costKpis['total_fuel_cost']);
        $this->assertEquals(130.0, $costKpis['fuel_breakdown'][0]['total_quantity']);
    }

    public function test_total_costs_aggregation(): void
    {
        // Add maintenance cost
        VehicleMaintenanceRecord::create([
            'maintenance_number' => 'MAINT-COST-001',
            'vehicle_id' => $this->vehicle1->id,
            'maintenance_type' => 'preventive',
            'priority' => 'normal',
            'completed_date' => now()->subDays(5)->format('Y-m-d'),
            'title' => 'Service',
            'total_cost' => 300.000,
            'currency' => 'TND',
            'status' => 'completed',
        ]);

        // Add fuel cost
        VehicleFuelRecord::create([
            'vehicle_id' => $this->vehicle1->id,
            'fill_date' => now()->subDays(3)->format('Y-m-d'),
            'fuel_type' => 'diesel',
            'quantity' => 50.000,
            'total_cost' => 125.000,
            'currency' => 'TND',
        ]);

        $startDate = Carbon::today()->subDays(10);
        $endDate = Carbon::today();

        $costKpis = $this->kpiService->getCostKpis($startDate, $endDate, 'TND');

        $this->assertEquals(425.0, $costKpis['total_cost']);
    }

    public function test_cost_per_kilometer_calculation(): void
    {
        // Create odometer-tracked fuel records showing km delta
        VehicleFuelRecord::create([
            'vehicle_id' => $this->vehicle1->id,
            'fill_date' => now()->subDays(10)->format('Y-m-d'),
            'fuel_type' => 'diesel',
            'quantity' => 50.000,
            'total_cost' => 125.000,
            'currency' => 'TND',
            'odometer_km' => 50000,
        ]);

        VehicleFuelRecord::create([
            'vehicle_id' => $this->vehicle1->id,
            'fill_date' => now()->subDays(1)->format('Y-m-d'),
            'fuel_type' => 'diesel',
            'quantity' => 50.000,
            'total_cost' => 125.000,
            'currency' => 'TND',
            'odometer_km' => 51000, // 1000 km delta
        ]);

        // Add maintenance in the same period
        VehicleMaintenanceRecord::create([
            'maintenance_number' => 'MAINT-KM-001',
            'vehicle_id' => $this->vehicle1->id,
            'maintenance_type' => 'preventive',
            'priority' => 'normal',
            'completed_date' => now()->subDays(5)->format('Y-m-d'),
            'odometer_km' => 50500,
            'title' => 'Service',
            'total_cost' => 250.000,
            'currency' => 'TND',
            'status' => 'completed',
        ]);

        $startDate = Carbon::today()->subDays(10); // Aligned with first fuel record
        $endDate = Carbon::today();

        $costKpis = $this->kpiService->getCostKpis($startDate, $endDate, 'TND');

        // Total cost = 125 + 125 + 250 = 500 TND
        // Total km = 1000 km
        // Cost per km = 0.500 TND/km
        $this->assertNotNull($costKpis['average_cost_per_km']);
        $this->assertEquals(0.500, $costKpis['average_cost_per_km']);
        $this->assertEquals(1000, $costKpis['total_km_tracked']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  Utilization KPI Tests
    // ═══════════════════════════════════════════════════════════════

    public function test_utilization_calculation_simple_assignment(): void
    {
        $startDate = Carbon::today()->subDays(10);
        $endDate = Carbon::today();

        // Assign vehicle1 for 5 days
        VehicleAssignment::create([
            'vehicle_id' => $this->vehicle1->id,
            'custody_type' => 'employee_home',
            'responsible_employee_id' => $this->employee->user_id,
            'start_date' => $startDate->copy()->addDays(2)->format('Y-m-d H:i:s'),
            'end_date' => $startDate->copy()->addDays(6)->format('Y-m-d H:i:s'),
            'is_current' => false,
        ]);

        $utilizationKpis = $this->kpiService->getUtilizationKpis($startDate, $endDate);

        // Period = 11 days (subDays(10) + today = 11 days inclusive)
        // Vehicle1 used 5 days, vehicle2 not used
        // Total possible = 2 vehicles × 11 days = 22 days
        // Total utilized = 5 days
        // Utilization = 5/22 = 22.7%

        $this->assertEquals(11, $utilizationKpis['period_days']);
        $this->assertEquals(5, $utilizationKpis['total_utilized_days']);
        $this->assertEquals(22, $utilizationKpis['max_possible_days']);
        $this->assertEquals(22.7, $utilizationKpis['utilization_percentage']);
    }

    public function test_utilization_handles_overlapping_periods_without_double_counting(): void
    {
        $startDate = Carbon::today()->subDays(10);
        $endDate = Carbon::today();

        // Create overlapping assignment and rental
        VehicleAssignment::create([
            'vehicle_id' => $this->vehicle1->id,
            'custody_type' => 'company_site',
            'site_id' => $this->site->id,
            'start_date' => $startDate->copy()->addDays(1)->format('Y-m-d H:i:s'),
            'end_date' => $startDate->copy()->addDays(5)->format('Y-m-d H:i:s'),
            'is_current' => false,
        ]);

        // Overlap rental period
        $this->vehicle1->update([
            'rental_start_date' => $startDate->copy()->addDays(3)->format('Y-m-d'),
            'rental_end_date' => $startDate->copy()->addDays(8)->format('Y-m-d'),
            'rental_status' => 'active',
        ]);

        $utilizationKpis = $this->kpiService->getUtilizationKpis($startDate, $endDate);

        // Period = 11 days
        // Assignment: days 1-5 (5 days)
        // Rental: days 3-8 (6 days)
        // Overlap: days 3-5 (3 days)
        // Merged: days 1-8 (8 unique days, not 11)

        $this->assertEquals(8, $utilizationKpis['total_utilized_days']);
    }

    public function test_utilization_handles_rental_and_client_periods(): void
    {
        $startDate = Carbon::today()->subDays(10);
        $endDate = Carbon::today();

        // Rental period
        $this->vehicle1->update([
            'rental_start_date' => $startDate->copy()->addDays(1)->format('Y-m-d'),
            'rental_end_date' => $startDate->copy()->addDays(5)->format('Y-m-d'),
            'rental_status' => 'active',
        ]);

        // Client usage period (no overlap)
        $this->vehicle2->update([
            'client_usage_start' => $startDate->copy()->addDays(2)->format('Y-m-d'),
            'client_usage_end' => $startDate->copy()->addDays(7)->format('Y-m-d'),
            'client_vehicle_status' => 'active',
        ]);

        $utilizationKpis = $this->kpiService->getUtilizationKpis($startDate, $endDate);

        // Vehicle1: 5 days (rental)
        // Vehicle2: 6 days (client)
        // Total: 11 days utilized

        $this->assertEquals(11, $utilizationKpis['total_utilized_days']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  Driver KPI Tests
    // ═══════════════════════════════════════════════════════════════

    public function test_driver_kpis_counts(): void
    {
        $kpis = $this->kpiService->getDriverKpis();

        $this->assertArrayHasKey('total_drivers', $kpis);
        $this->assertArrayHasKey('active_drivers', $kpis);
        $this->assertEquals(1, $kpis['total_drivers']);
        $this->assertEquals(1, $kpis['active_drivers']);
    }

    public function test_driver_kpis_incident_statistics(): void
    {
        DriverIncident::create([
            'incident_number' => 'INC-TEST-001',
            'driver_id' => $this->driver1->id,
            'employee_id' => $this->employee->id,
            'vehicle_id' => $this->vehicle1->id,
            'incident_type' => 'accident',
            'severity' => 'minor',
            'incident_date' => now()->subDays(5),
            'title' => 'Minor Fender Bender',
            'description' => 'Minor collision',
            'status' => 'reported',
        ]);

        DriverIncident::create([
            'incident_number' => 'INC-TEST-002',
            'driver_id' => $this->driver1->id,
            'employee_id' => $this->employee->id,
            'vehicle_id' => $this->vehicle1->id,
            'incident_type' => 'traffic_violation',
            'severity' => 'minor',
            'incident_date' => now()->subDays(3),
            'title' => 'Speeding Ticket',
            'description' => '10km over limit',
            'status' => 'reported',
        ]);

        $kpis = $this->kpiService->getDriverKpis();

        $this->assertEquals(2, $kpis['recent_incidents']);
        $this->assertEquals(1, $kpis['recent_violations']);
        $this->assertArrayHasKey('incidents_by_severity', $kpis);
        $this->assertEquals(2, $kpis['incidents_by_severity']['minor']);
    }

    public function test_safety_score_distribution(): void
    {
        // Create additional drivers with different score levels
        $dept = Department::first();

        $empUser2 = User::factory()->create(['name' => 'Expert Driver']);
        $emp2 = Employee::create([
            'first_name' => 'Expert',
            'last_name' => 'Driver',
            'work_email' => 'expert@test.local',
            'hire_date' => now()->subYear()->format('Y-m-d'),
            'department_id' => $dept->id,
            'status' => 'active',
            'user_id' => $empUser2->id,
        ]);

        ApprovedDriver::create([
            'driver_authorization_number' => 'DRV-EXPERT',
            'employee_id' => $emp2->id,
            'authorization_status' => 'approved',
            'authorization_date' => now()->subYear()->format('Y-m-d'),
            'license_number' => 'LIC-EXPERT',
            'permitted_vehicle_types' => ['van'],
            'permitted_vehicle_classes' => ['light'],
            'driver_score' => 98.00,
            'driver_level' => 'expert',
        ]);

        $empUser3 = User::factory()->create(['name' => 'Junior Driver']);
        $emp3 = Employee::create([
            'first_name' => 'Junior',
            'last_name' => 'Driver',
            'work_email' => 'junior@test.local',
            'hire_date' => now()->subMonths(6)->format('Y-m-d'),
            'department_id' => $dept->id,
            'status' => 'active',
            'user_id' => $empUser3->id,
        ]);

        ApprovedDriver::create([
            'driver_authorization_number' => 'DRV-JUNIOR',
            'employee_id' => $emp3->id,
            'authorization_status' => 'approved',
            'authorization_date' => now()->subMonths(6)->format('Y-m-d'),
            'license_number' => 'LIC-JUNIOR',
            'permitted_vehicle_types' => ['van'],
            'permitted_vehicle_classes' => ['light'],
            'driver_score' => 65.00,
            'driver_level' => 'junior',
        ]);

        $kpis = $this->kpiService->getDriverKpis();

        $this->assertArrayHasKey('safety_score_distribution', $kpis);
        $distribution = $kpis['safety_score_distribution'];

        $this->assertEquals(3, $distribution['total_scored_drivers']);
        $this->assertEquals(1, $distribution['distribution']['expert']['count']);
        $this->assertEquals(1, $distribution['distribution']['senior']['count']);
        $this->assertEquals(1, $distribution['distribution']['junior']['count']);

        // Average: (98 + 88 + 65) / 3 = 83.67
        $this->assertEqualsWithDelta(83.67, $distribution['average_score'], 0.1);
    }

    public function test_all_kpis_aggregation(): void
    {
        $startDate = Carbon::today()->subDays(30);
        $endDate = Carbon::today();

        $allKpis = $this->kpiService->getAllKpis($startDate, $endDate, 'TND');

        $this->assertArrayHasKey('period', $allKpis);
        $this->assertArrayHasKey('vehicle_kpis', $allKpis);
        $this->assertArrayHasKey('cost_kpis', $allKpis);
        $this->assertArrayHasKey('utilization_kpis', $allKpis);
        $this->assertArrayHasKey('driver_kpis', $allKpis);

        $this->assertEquals(31, $allKpis['period']['days']);
        $this->assertEquals('TND', $allKpis['period']['currency']);
    }
}

