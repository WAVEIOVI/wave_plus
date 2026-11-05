<?php

declare(strict_types=1);

namespace App\Modules\Hr\Services;

use App\Modules\Hr\Models\ApprovedDriver;
use App\Modules\Hr\Models\DriverIncident;
use App\Modules\Hr\Models\Vehicle;
use App\Modules\Hr\Models\VehicleAssignment;
use App\Modules\Hr\Models\VehicleFuelRecord;
use App\Modules\Hr\Models\VehicleMaintenanceRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fleet KPI Service
 *
 * Computes operational KPIs and statistics for fleet management:
 * - Vehicle KPIs (counts, operational status, utilization)
 * - Cost KPIs (maintenance, fuel, cost per km)
 * - Driver KPIs (active, renewal, incidents, safety scores)
 *
 * Uses server-side aggregated statistics where appropriate.
 */
class FleetKpiService
{
    /**
     * Get all fleet KPIs and statistics.
     *
     * @param  Carbon|null  $startDate  Period start (default: 30 days ago)
     * @param  Carbon|null  $endDate  Period end (default: today)
     * @param  string  $currency  Reporting currency (default: TND)
     * @return array Comprehensive fleet KPIs
     */
    public function getAllKpis(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        string $currency = 'TND'
    ): array {
        $startDate = $startDate ?? Carbon::today()->subDays(30);
        $endDate = $endDate ?? Carbon::today();

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'days' => $startDate->diffInDays($endDate) + 1,
                'currency' => $currency,
            ],
            'vehicle_kpis' => $this->getVehicleKpis(),
            'cost_kpis' => $this->getCostKpis($startDate, $endDate, $currency),
            'utilization_kpis' => $this->getUtilizationKpis($startDate, $endDate),
            'driver_kpis' => $this->getDriverKpis(),
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  Vehicle KPIs
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get vehicle counts and operational status KPIs.
     *
     * @return array Vehicle KPIs with counts by status
     */
    public function getVehicleKpis(): array
    {
        $total = Vehicle::count();

        // Status breakdown
        $statusCounts = Vehicle::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Operational = available + in_use
        $operational = ($statusCounts['available'] ?? 0) + ($statusCounts['in_use'] ?? 0);

        // Under maintenance
        $underMaintenance = $statusCounts['maintenance'] ?? 0;

        // Unavailable = out_of_service + retired + sold
        $unavailable = ($statusCounts['out_of_service'] ?? 0)
            + ($statusCounts['retired'] ?? 0)
            + ($statusCounts['sold'] ?? 0);

        // Ownership breakdown
        $ownershipCounts = Vehicle::select('ownership_type', DB::raw('count(*) as count'))
            ->groupBy('ownership_type')
            ->pluck('count', 'ownership_type')
            ->toArray();

        // Type breakdown
        $typeCounts = Vehicle::select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        // Operational percentage
        $operationalPercentage = $total > 0 ? round(($operational / $total) * 100, 1) : 0;

        return [
            'total_vehicles' => $total,
            'operational_vehicles' => $operational,
            'operational_percentage' => $operationalPercentage,
            'vehicles_under_maintenance' => $underMaintenance,
            'vehicles_unavailable' => $unavailable,
            'status_breakdown' => $statusCounts,
            'ownership_breakdown' => $ownershipCounts,
            'type_breakdown' => $typeCounts,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  Cost KPIs
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get cost KPIs for the specified period.
     *
     * @param  Carbon  $startDate  Period start
     * @param  Carbon  $endDate  Period end
     * @param  string  $currency  Reporting currency
     * @return array Cost KPIs with maintenance, fuel, and cost per km
     */
    public function getCostKpis(Carbon $startDate, Carbon $endDate, string $currency = 'TND'): array
    {
        // Maintenance costs
        $maintenanceCosts = $this->getMaintenanceCosts($startDate, $endDate, $currency);

        // Fuel/energy costs
        $fuelCosts = $this->getFuelCosts($startDate, $endDate, $currency);

        // Total costs
        $totalCost = $maintenanceCosts['total_cost'] + $fuelCosts['total_cost'];

        // Cost per kilometer
        $costPerKm = $this->getCostPerKilometer($startDate, $endDate, $currency);

        return [
            'total_maintenance_cost' => $maintenanceCosts['total_cost'],
            'total_fuel_cost' => $fuelCosts['total_cost'],
            'total_cost' => $totalCost,
            'maintenance_breakdown' => $maintenanceCosts['breakdown'],
            'fuel_breakdown' => $fuelCosts['breakdown'],
            'average_cost_per_km' => $costPerKm['average_cost_per_km'],
            'total_km_tracked' => $costPerKm['total_km_tracked'],
            'vehicles_with_cost_data' => $costPerKm['vehicles_with_cost_data'],
            'currency' => $currency,
        ];
    }

    /**
     * Get maintenance costs for the period.
     *
     * @param  Carbon  $startDate  Period start
     * @param  Carbon  $endDate  Period end
     * @param  string  $currency  Reporting currency
     * @return array Maintenance costs and breakdown
     */
    private function getMaintenanceCosts(Carbon $startDate, Carbon $endDate, string $currency): array
    {
        $records = VehicleMaintenanceRecord::whereBetween('completed_date', [$startDate, $endDate])
            ->where('status', 'completed')
            ->where('currency', $currency)
            ->get();

        $totalCost = $records->sum('total_cost');

        $breakdown = $records->groupBy('maintenance_type')->map(function ($group, $type) {
            return [
                'type' => $type,
                'count' => $group->count(),
                'total_cost' => $group->sum('total_cost'),
                'average_cost' => $group->avg('total_cost'),
            ];
        })->values()->toArray();

        return [
            'total_cost' => (float) $totalCost,
            'record_count' => $records->count(),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Get fuel/energy costs for the period.
     *
     * @param  Carbon  $startDate  Period start
     * @param  Carbon  $endDate  Period end
     * @param  string  $currency  Reporting currency
     * @return array Fuel costs and breakdown
     */
    private function getFuelCosts(Carbon $startDate, Carbon $endDate, string $currency): array
    {
        $records = VehicleFuelRecord::whereBetween('fill_date', [$startDate, $endDate])
            ->where('currency', $currency)
            ->whereNotNull('total_cost')
            ->get();

        $totalCost = $records->sum('total_cost');
        $totalQuantity = $records->sum('quantity');

        $breakdown = $records->groupBy('fuel_type')->map(function ($group, $type) {
            return [
                'fuel_type' => $type,
                'count' => $group->count(),
                'total_quantity' => $group->sum('quantity'),
                'total_cost' => $group->sum('total_cost'),
                'average_unit_price' => $group->avg('unit_price'),
            ];
        })->values()->toArray();

        return [
            'total_cost' => (float) $totalCost,
            'total_quantity' => (float) $totalQuantity,
            'record_count' => $records->count(),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Calculate average cost per kilometer where data supports it.
     *
     * Only includes vehicles with:
     * - Odometer readings tracked
     * - Cost data available (maintenance + fuel)
     * - Sufficient odometer delta (at least 100km to be meaningful)
     *
     * @param  Carbon  $startDate  Period start
     * @param  Carbon  $endDate  Period end
     * @param  string  $currency  Reporting currency
     * @return array Cost per km statistics
     */
    private function getCostPerKilometer(Carbon $startDate, Carbon $endDate, string $currency): array
    {
        // Get vehicles with odometer tracking
        $vehiclesWithData = Vehicle::whereNotNull('odometer_km')
            ->get();

        $totalKm = 0;
        $totalCost = 0.0;
        $vehicleCount = 0;

        foreach ($vehiclesWithData as $vehicle) {
            // Get odometer delta for the period
            $startOdometer = $this->getOdometerAtDate($vehicle->id, $startDate);
            $endOdometer = $this->getOdometerAtDate($vehicle->id, $endDate);

            if (! $startOdometer || ! $endOdometer) {
                continue;
            }

            $kmDelta = $endOdometer - $startOdometer;

            // Require at least 100km delta to be meaningful
            if ($kmDelta < 100) {
                continue;
            }

            // Get costs for this vehicle in the period
            $maintenanceCost = VehicleMaintenanceRecord::where('vehicle_id', $vehicle->id)
                ->whereBetween('completed_date', [$startDate, $endDate])
                ->where('status', 'completed')
                ->where('currency', $currency)
                ->sum('total_cost');

            $fuelCost = VehicleFuelRecord::where('vehicle_id', $vehicle->id)
                ->whereBetween('fill_date', [$startDate, $endDate])
                ->where('currency', $currency)
                ->sum('total_cost');

            $vehicleTotalCost = $maintenanceCost + $fuelCost;

            if ($vehicleTotalCost > 0) {
                $totalKm += $kmDelta;
                $totalCost += $vehicleTotalCost;
                $vehicleCount++;
            }
        }

        $averageCostPerKm = $totalKm > 0 ? round($totalCost / $totalKm, 3) : null;

        return [
            'average_cost_per_km' => $averageCostPerKm,
            'total_km_tracked' => $totalKm,
            'vehicles_with_cost_data' => $vehicleCount,
        ];
    }

    /**
     * Get odometer reading at a specific date.
     *
     * @param  string  $vehicleId  Vehicle ULID
     * @param  Carbon  $date  Target date
     * @return int|null Odometer reading or null if not available
     */
    private function getOdometerAtDate(string $vehicleId, Carbon $date): ?int
    {
        // Try to get from fuel records first (most frequent updates)
        $fuelRecord = VehicleFuelRecord::where('vehicle_id', $vehicleId)
            ->where('fill_date', '<=', $date)
            ->whereNotNull('odometer_km')
            ->orderByDesc('fill_date')
            ->first();

        if ($fuelRecord) {
            return $fuelRecord->odometer_km;
        }

        // Try maintenance records
        $maintenanceRecord = VehicleMaintenanceRecord::where('vehicle_id', $vehicleId)
            ->where('completed_date', '<=', $date)
            ->whereNotNull('odometer_km')
            ->orderByDesc('completed_date')
            ->first();

        if ($maintenanceRecord) {
            return $maintenanceRecord->odometer_km;
        }

        // Fall back to vehicle's current odometer if date is today or future
        $vehicle = Vehicle::find($vehicleId);
        if ($vehicle && ($date->gte(Carbon::today()))) {
            return $vehicle->odometer_km;
        }

        return null;
    }

    // ═══════════════════════════════════════════════════════════════
    //  Utilization KPIs
    // ═══════════════════════════════════════════════════════════════

    /**
     * Calculate vehicle utilization for the period.
     *
     * Utilization = total days vehicles were assigned / (total vehicles × period days)
     *
     * Handles overlapping periods correctly:
     * - Company assignments
     * - Rental periods
     * - Client usage periods
     *
     * @param  Carbon  $startDate  Period start
     * @param  Carbon  $endDate  Period end
     * @return array Utilization KPIs
     */
    public function getUtilizationKpis(Carbon $startDate, Carbon $endDate): array
    {
        $periodDays = $startDate->diffInDays($endDate) + 1;
        $vehicles = Vehicle::whereNotIn('status', ['retired', 'sold'])->get();
        $totalVehicles = $vehicles->count();

        $totalUtilizedDays = 0;

        foreach ($vehicles as $vehicle) {
            $utilizedDays = $this->calculateVehicleUtilizedDays($vehicle->id, $startDate, $endDate);
            $totalUtilizedDays += $utilizedDays;
        }

        $maxPossibleDays = $totalVehicles * $periodDays;
        $utilizationPercentage = $maxPossibleDays > 0
            ? round(($totalUtilizedDays / $maxPossibleDays) * 100, 1)
            : 0;

        return [
            'utilization_percentage' => $utilizationPercentage,
            'total_utilized_days' => $totalUtilizedDays,
            'max_possible_days' => $maxPossibleDays,
            'period_days' => $periodDays,
            'vehicles_tracked' => $totalVehicles,
        ];
    }

    /**
     * Calculate utilized days for a single vehicle in the period.
     *
     * Handles overlapping assignments without double-counting.
     *
     * @param  string  $vehicleId  Vehicle ULID
     * @param  Carbon  $startDate  Period start
     * @param  Carbon  $endDate  Period end
     * @return int Number of days vehicle was utilized
     */
    private function calculateVehicleUtilizedDays(string $vehicleId, Carbon $startDate, Carbon $endDate): int
    {
        // Collect all utilization periods for this vehicle
        $periods = collect();

        // 1. Company assignments (from vehicle_assignments)
        $assignments = VehicleAssignment::where('vehicle_id', $vehicleId)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($inner) use ($startDate, $endDate) {
                    $inner->where('start_date', '<=', $endDate)
                        ->where(function ($end) use ($startDate) {
                            $end->whereNull('end_date')
                                ->orWhere('end_date', '>=', $startDate);
                        });
                });
            })
            ->get();

        foreach ($assignments as $assignment) {
            $assignStart = Carbon::parse($assignment->start_date)->max($startDate);
            $assignEnd = $assignment->end_date
                ? Carbon::parse($assignment->end_date)->min($endDate)
                : $endDate;

            $periods->push([
                'type' => 'assignment',
                'start' => $assignStart,
                'end' => $assignEnd,
            ]);
        }

        // 2. Rental periods (from vehicle record)
        $vehicle = Vehicle::find($vehicleId);
        if ($vehicle && $vehicle->rental_start_date && $vehicle->rental_status === 'active') {
            $rentalStart = Carbon::parse($vehicle->rental_start_date)->max($startDate);
            $rentalEnd = $vehicle->rental_end_date
                ? Carbon::parse($vehicle->rental_end_date)->min($endDate)
                : $endDate;

            if ($rentalStart->lte($rentalEnd)) {
                $periods->push([
                    'type' => 'rental',
                    'start' => $rentalStart,
                    'end' => $rentalEnd,
                ]);
            }
        }

        // 3. Client usage periods (from vehicle record)
        if ($vehicle && $vehicle->client_usage_start && in_array($vehicle->client_vehicle_status, ['active', 'pending_return'])) {
            $clientStart = Carbon::parse($vehicle->client_usage_start)->max($startDate);
            $clientEnd = $vehicle->client_usage_end
                ? Carbon::parse($vehicle->client_usage_end)->min($endDate)
                : $endDate;

            if ($clientStart->lte($clientEnd)) {
                $periods->push([
                    'type' => 'client',
                    'start' => $clientStart,
                    'end' => $clientEnd,
                ]);
            }
        }

        // Merge overlapping periods to avoid double-counting
        $mergedPeriods = $this->mergeOverlappingPeriods($periods);

        // Count total unique days
        $totalDays = 0;
        foreach ($mergedPeriods as $period) {
            $totalDays += (int) ($period['start']->diffInDays($period['end']) + 1);
        }

        return $totalDays;
    }

    /**
     * Merge overlapping date periods to avoid double-counting.
     *
     * @param  \Illuminate\Support\Collection  $periods  Collection of ['start' => Carbon, 'end' => Carbon]
     * @return array Merged non-overlapping periods
     */
    private function mergeOverlappingPeriods($periods): array
    {
        if ($periods->isEmpty()) {
            return [];
        }

        // Sort by start date
        $sorted = $periods->sortBy('start')->values();

        $merged = [];
        $current = $sorted->first();

        for ($i = 1; $i < $sorted->count(); $i++) {
            $next = $sorted[$i];

            // If current and next overlap or are adjacent, merge them
            if ($next['start']->lte($current['end']->addDay())) {
                $current['end'] = $current['end']->max($next['end']);
            } else {
                // No overlap, save current and move to next
                $merged[] = $current;
                $current = $next;
            }
        }

        // Add the last period
        $merged[] = $current;

        return $merged;
    }

    // ═══════════════════════════════════════════════════════════════
    //  Driver KPIs
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get driver KPIs and statistics.
     *
     * @return array Driver KPIs with counts, incidents, and safety scores
     */
    public function getDriverKpis(): array
    {
        $total = ApprovedDriver::count();

        // Status breakdown
        $statusCounts = ApprovedDriver::select('authorization_status', DB::raw('count(*) as count'))
            ->groupBy('authorization_status')
            ->pluck('count', 'authorization_status')
            ->toArray();

        $approved = $statusCounts['approved'] ?? 0;
        $suspended = $statusCounts['suspended'] ?? 0;
        $expired = $statusCounts['expired'] ?? 0;
        $revoked = $statusCounts['revoked'] ?? 0;
        $pending = $statusCounts['pending'] ?? 0;

        // Renewals and reviews due (30 days)
        $today = Carbon::today();
        $in30Days = $today->copy()->addDays(30);

        $renewalsDue = ApprovedDriver::where('authorization_status', 'approved')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $in30Days)
            ->count();

        $reviewsDue = ApprovedDriver::where('authorization_status', 'approved')
            ->whereNotNull('review_date')
            ->where('review_date', '<=', $in30Days)
            ->count();

        // Incident statistics (last 30 days)
        $last30Days = $today->copy()->subDays(30);
        $recentIncidents = DriverIncident::where('incident_date', '>=', $last30Days)->count();

        $incidentsBySeverity = DriverIncident::where('incident_date', '>=', $last30Days)
            ->select('severity', DB::raw('count(*) as count'))
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        // Violation statistics (last 30 days)
        $recentViolations = DriverIncident::where('incident_date', '>=', $last30Days)
            ->where('incident_type', 'traffic_violation')
            ->count();

        // Safety score distribution
        $safetyScoreDistribution = $this->getSafetyScoreDistribution();

        return [
            'total_drivers' => $total,
            'active_drivers' => $approved,
            'suspended_drivers' => $suspended,
            'expired_drivers' => $expired,
            'revoked_drivers' => $revoked,
            'pending_drivers' => $pending,
            'drivers_requiring_renewal' => $renewalsDue,
            'drivers_requiring_review' => $reviewsDue,
            'status_breakdown' => $statusCounts,
            'recent_incidents' => $recentIncidents,
            'recent_violations' => $recentViolations,
            'incidents_by_severity' => $incidentsBySeverity,
            'safety_score_distribution' => $safetyScoreDistribution,
        ];
    }

    /**
     * Get safety score distribution.
     *
     * @return array Score distribution by range
     */
    private function getSafetyScoreDistribution(): array
    {
        $drivers = ApprovedDriver::where('authorization_status', 'approved')
            ->whereNotNull('driver_score')
            ->select('driver_score', 'driver_level')
            ->get();

        $distribution = [
            'expert' => ['range' => '95-100', 'count' => 0, 'percentage' => 0],
            'senior' => ['range' => '85-94', 'count' => 0, 'percentage' => 0],
            'standard' => ['range' => '70-84', 'count' => 0, 'percentage' => 0],
            'junior' => ['range' => '50-69', 'count' => 0, 'percentage' => 0],
            'trainee' => ['range' => '0-49', 'count' => 0, 'percentage' => 0],
        ];

        $total = $drivers->count();

        if ($total === 0) {
            return $distribution;
        }

        foreach ($drivers as $driver) {
            $level = $driver->driver_level ?? 'standard';
            if (isset($distribution[$level])) {
                $distribution[$level]['count']++;
            }
        }

        // Calculate percentages
        foreach ($distribution as $level => $data) {
            $distribution[$level]['percentage'] = round(($data['count'] / $total) * 100, 1);
        }

        $averageScore = $drivers->avg('driver_score');

        return [
            'distribution' => $distribution,
            'average_score' => round($averageScore, 2),
            'total_scored_drivers' => $total,
        ];
    }
}
