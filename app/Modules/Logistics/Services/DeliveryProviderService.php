<?php

namespace App\Modules\Logistics\Services;

use App\Modules\Logistics\Models\DeliveryProviderActivityLog;
use App\Modules\Logistics\Models\DeliveryServiceProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Delivery Provider Service
 *
 * Manages delivery service provider operations and performance tracking
 */
class DeliveryProviderService
{
    /**
     * Update provider performance metrics
     */
    public function updatePerformanceMetrics(string $providerId): DeliveryServiceProvider
    {
        $provider = DeliveryServiceProvider::findOrFail($providerId);

        // Calculate success rate
        $totalDeliveries = $provider->total_deliveries;
        if ($totalDeliveries > 0) {
            $successRate = round(($provider->successful_deliveries / $totalDeliveries) * 100, 2);
            $onTimeRate = round((($totalDeliveries - $provider->delayed_deliveries) / $totalDeliveries) * 100, 2);

            $provider->update([
                'success_rate' => $successRate,
                'on_time_rate' => $onTimeRate,
            ]);
        }

        // Calculate performance score
        $this->calculatePerformanceScore($providerId);

        return $provider->fresh();
    }

    /**
     * Calculate provider performance score
     */
    public function calculatePerformanceScore(string $providerId): float
    {
        $provider = DeliveryServiceProvider::findOrFail($providerId);

        $score = 0;

        // Success rate (40 points)
        $score += ($provider->success_rate ?? 0) * 0.4;

        // On-time rate (40 points)
        $score += ($provider->on_time_rate ?? 0) * 0.4;

        // Incident rate (20 points - deduct for incidents)
        $recentIncidents = DeliveryProviderActivityLog::byProvider($providerId)
            ->byEventType('incident_reported')
            ->where('event_date', '>=', now()->subMonths(3))
            ->count();

        $incidentPenalty = min($recentIncidents * 5, 20); // Max 20 point penalty
        $score = max(0, $score - $incidentPenalty);

        // Update provider
        $provider->update([
            'performance_score' => round($score, 2),
        ]);

        return $score;
    }

    /**
     * Record delivery completion
     */
    public function recordDeliveryCompletion(
        string $providerId,
        string $dwoId,
        bool $successful,
        bool $onTime,
        ?string $recordedBy = null
    ): void {
        DB::transaction(function () use ($providerId, $dwoId, $successful, $onTime, $recordedBy) {
            $provider = DeliveryServiceProvider::findOrFail($providerId);

            // Update counters
            $provider->increment('total_deliveries');

            if ($successful) {
                $provider->increment('successful_deliveries');
            } else {
                $provider->increment('failed_deliveries');
            }

            if (! $onTime) {
                $provider->increment('delayed_deliveries');
            }

            // Decrement current load
            if ($provider->current_load > 0) {
                $provider->decrement('current_load');
            }

            // Log activity
            $this->logProviderActivity($providerId, [
                'event_type' => $successful ? 'delivery_completed' : 'delivery_failed',
                'event_title' => $successful ? 'Delivery Completed' : 'Delivery Failed',
                'event_description' => $onTime ? 'Delivered on time' : 'Delayed delivery',
                'event_date' => now(),
                'dwo_id' => $dwoId,
                'impact' => $successful && $onTime ? 'positive' : 'negative',
                'recorded_by' => $recordedBy,
            ]);

            // Update metrics
            $this->updatePerformanceMetrics($providerId);
        });
    }

    /**
     * Record delivery assignment
     */
    public function recordDeliveryAssignment(
        string $providerId,
        string $dwoId,
        ?string $recordedBy = null
    ): void {
        DB::transaction(function () use ($providerId, $dwoId, $recordedBy) {
            $provider = DeliveryServiceProvider::findOrFail($providerId);

            // Increment current load
            $provider->increment('current_load');

            // Check if at capacity
            if ($provider->daily_capacity && $provider->current_load >= $provider->daily_capacity) {
                $provider->update(['accepts_new_orders' => false]);
            }

            // Log activity
            $this->logProviderActivity($providerId, [
                'event_type' => 'delivery_assigned',
                'event_title' => 'Delivery Assigned',
                'event_description' => 'New delivery work order assigned',
                'event_date' => now(),
                'dwo_id' => $dwoId,
                'impact' => 'neutral',
                'recorded_by' => $recordedBy,
            ]);
        });
    }

    /**
     * Update operational status
     */
    public function updateOperationalStatus(
        string $providerId,
        string $newStatus,
        ?string $reason = null,
        ?string $updatedBy = null
    ): DeliveryServiceProvider {
        return DB::transaction(function () use ($providerId, $newStatus, $reason, $updatedBy) {
            $provider = DeliveryServiceProvider::findOrFail($providerId);

            $oldStatus = $provider->operational_status;

            $provider->update([
                'operational_status' => $newStatus,
                'updated_by' => $updatedBy,
            ]);

            // If becoming inactive, stop accepting orders
            if (! in_array($newStatus, ['active', 'probation'])) {
                $provider->update(['accepts_new_orders' => false]);
            }

            // Log activity
            $this->logProviderActivity($providerId, [
                'event_type' => 'status_changed',
                'event_title' => 'Operational Status Changed',
                'event_description' => $reason ?? "Status changed from {$oldStatus} to {$newStatus}",
                'event_date' => now(),
                'old_value' => $oldStatus,
                'new_value' => $newStatus,
                'impact' => $newStatus === 'active' ? 'positive' : 'negative',
                'recorded_by' => $updatedBy,
            ]);

            return $provider->fresh();
        });
    }

    /**
     * Update capacity settings
     */
    public function updateCapacity(
        string $providerId,
        int $dailyCapacity,
        bool $acceptsNewOrders,
        ?string $updatedBy = null
    ): DeliveryServiceProvider {
        $provider = DeliveryServiceProvider::findOrFail($providerId);

        $provider->update([
            'daily_capacity' => $dailyCapacity,
            'accepts_new_orders' => $acceptsNewOrders,
            'updated_by' => $updatedBy,
        ]);

        // Log activity
        $this->logProviderActivity($providerId, [
            'event_type' => 'capacity_changed',
            'event_title' => 'Capacity Updated',
            'event_description' => "Daily capacity set to {$dailyCapacity}",
            'event_date' => now(),
            'new_value' => (string) $dailyCapacity,
            'impact' => 'neutral',
            'recorded_by' => $updatedBy,
        ]);

        return $provider->fresh();
    }

    /**
     * Reset daily load (typically run at end of day)
     */
    public function resetDailyLoad(string $providerId, ?string $resetBy = null): DeliveryServiceProvider
    {
        $provider = DeliveryServiceProvider::findOrFail($providerId);

        $oldLoad = $provider->current_load;

        $provider->update([
            'current_load' => 0,
            'accepts_new_orders' => true,
            'updated_by' => $resetBy,
        ]);

        // Log activity
        $this->logProviderActivity($providerId, [
            'event_type' => 'other',
            'event_title' => 'Daily Load Reset',
            'event_description' => "Load reset from {$oldLoad} to 0",
            'event_date' => now(),
            'impact' => 'neutral',
            'recorded_by' => $resetBy,
        ]);

        return $provider->fresh();
    }

    /**
     * Record incident
     */
    public function recordIncident(
        string $providerId,
        string $incidentDescription,
        ?string $dwoId = null,
        ?string $recordedBy = null
    ): DeliveryProviderActivityLog {
        return DB::transaction(function () use ($providerId, $incidentDescription, $dwoId, $recordedBy) {
            // Log incident
            $activity = $this->logProviderActivity($providerId, [
                'event_type' => 'incident_reported',
                'event_title' => 'Incident Reported',
                'event_description' => $incidentDescription,
                'event_date' => now(),
                'dwo_id' => $dwoId,
                'impact' => 'negative',
                'score_change' => -5.0,
                'recorded_by' => $recordedBy,
            ]);

            // Recalculate performance score
            $this->calculatePerformanceScore($providerId);

            return $activity;
        });
    }

    /**
     * Log provider activity
     */
    public function logProviderActivity(string $providerId, array $activityData): DeliveryProviderActivityLog
    {
        return DeliveryProviderActivityLog::create([
            'delivery_provider_id' => $providerId,
            'event_type' => $activityData['event_type'],
            'event_title' => $activityData['event_title'],
            'event_description' => $activityData['event_description'] ?? null,
            'event_date' => $activityData['event_date'] ?? now(),
            'old_value' => $activityData['old_value'] ?? null,
            'new_value' => $activityData['new_value'] ?? null,
            'metadata' => $activityData['metadata'] ?? null,
            'dwo_id' => $activityData['dwo_id'] ?? null,
            'impact' => $activityData['impact'] ?? null,
            'score_change' => $activityData['score_change'] ?? null,
            'notes' => $activityData['notes'] ?? null,
            'recorded_by' => $activityData['recorded_by'] ?? null,
            'created_by' => $activityData['recorded_by'] ?? null,
            'created_at' => now(),
        ]);
    }

    /**
     * Get available providers for delivery
     */
    public function getAvailableProviders(
        string $city,
        ?float $weight = null,
        ?float $volume = null,
        array $requirements = []
    ): \Illuminate\Database\Eloquent\Collection {
        return DeliveryServiceProvider::acceptingOrders()
            ->where(function ($query) use ($city) {
                $query->whereJsonContains('service_areas', $city)
                    ->orWhereNull('service_areas');
            })
            ->when($weight, function ($query) use ($weight) {
                $query->where(function ($q) use ($weight) {
                    $q->where('max_weight_kg', '>=', $weight)
                        ->orWhereNull('max_weight_kg');
                });
            })
            ->when($volume, function ($query) use ($volume) {
                $query->where(function ($q) use ($volume) {
                    $q->where('max_volume_m3', '>=', $volume)
                        ->orWhereNull('max_volume_m3');
                });
            })
            ->when($requirements['fragile'] ?? false, function ($query) {
                $query->where('can_handle_fragile', true);
            })
            ->when($requirements['hazmat'] ?? false, function ($query) {
                $query->where('can_handle_hazmat', true);
            })
            ->when($requirements['refrigerated'] ?? false, function ($query) {
                $query->where('can_handle_refrigerated', true);
            })
            ->with('serviceProvider')
            ->orderByDesc('performance_score')
            ->get();
    }

    /**
     * Get provider performance report
     */
    public function getPerformanceReport(string $providerId, Carbon $startDate, Carbon $endDate): array
    {
        $provider = DeliveryServiceProvider::with('serviceProvider')->findOrFail($providerId);

        $activities = DeliveryProviderActivityLog::byProvider($providerId)
            ->inPeriod($startDate, $endDate)
            ->get();

        $completedDeliveries = $activities->where('event_type', 'delivery_completed')->count();
        $failedDeliveries = $activities->where('event_type', 'delivery_failed')->count();
        $delayedDeliveries = $activities->where('event_type', 'delivery_completed')
            ->where('event_description', 'like', '%delayed%')
            ->count();
        $incidents = $activities->where('event_type', 'incident_reported')->count();

        $totalDeliveries = $completedDeliveries + $failedDeliveries;

        return [
            'provider_id' => $providerId,
            'provider_name' => $provider->serviceProvider->legal_name,
            'period_start' => $startDate->format('Y-m-d'),
            'period_end' => $endDate->format('Y-m-d'),
            'total_deliveries' => $totalDeliveries,
            'successful_deliveries' => $completedDeliveries,
            'failed_deliveries' => $failedDeliveries,
            'delayed_deliveries' => $delayedDeliveries,
            'incidents' => $incidents,
            'success_rate' => $totalDeliveries > 0 ? round(($completedDeliveries / $totalDeliveries) * 100, 2) : 0,
            'on_time_rate' => $totalDeliveries > 0 ? round((($totalDeliveries - $delayedDeliveries) / $totalDeliveries) * 100, 2) : 0,
            'current_performance_score' => $provider->performance_score,
        ];
    }
}
