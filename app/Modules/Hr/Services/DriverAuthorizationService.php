<?php

namespace App\Modules\Hr\Services;

use App\Modules\Hr\Models\ApprovedDriver;
use App\Modules\Hr\Models\DriverActivityLog;
use App\Modules\Hr\Models\DriverIncident;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Driver Authorization Service
 *
 * Handles driver authorization lifecycle, scoring, and incidents
 */
class DriverAuthorizationService
{
    /**
     * Approve a driver
     */
    public function approveDriver(
        string $employeeId,
        array $authorizationData,
        ?string $approvedBy = null
    ): ApprovedDriver {
        return DB::transaction(function () use ($employeeId, $authorizationData, $approvedBy) {
            $driver = ApprovedDriver::create([
                'employee_id' => $employeeId,
                'authorization_status' => 'approved',
                'authorization_date' => $authorizationData['authorization_date'] ?? now(),
                'expiry_date' => $authorizationData['expiry_date'] ?? null,
                'review_date' => $authorizationData['review_date'] ?? null,
                'license_number' => $authorizationData['license_number'],
                'license_category' => $authorizationData['license_category'] ?? null,
                'license_categories' => $authorizationData['license_categories'] ?? [],
                'license_issue_date' => $authorizationData['license_issue_date'] ?? null,
                'license_expiry_date' => $authorizationData['license_expiry_date'] ?? null,
                'license_issuer' => $authorizationData['license_issuer'] ?? null,
                'permitted_vehicle_types' => $authorizationData['permitted_vehicle_types'] ?? [],
                'permitted_vehicle_classes' => $authorizationData['permitted_vehicle_classes'] ?? [],
                'can_drive_rental' => $authorizationData['can_drive_rental'] ?? true,
                'can_drive_client_vehicles' => $authorizationData['can_drive_client_vehicles'] ?? false,
                'restrictions' => $authorizationData['restrictions'] ?? [],
                'max_passengers' => $authorizationData['max_passengers'] ?? null,
                'max_cargo_kg' => $authorizationData['max_cargo_kg'] ?? null,
                'restriction_notes' => $authorizationData['restriction_notes'] ?? null,
                'driver_level' => $authorizationData['driver_level'] ?? 'standard',
                'driver_score' => 100.0, // Start with perfect score
                'notes' => $authorizationData['notes'] ?? null,
                'documents' => $authorizationData['documents'] ?? [],
                'approved_by' => $approvedBy,
                'approved_at' => now(),
                'created_by' => $approvedBy,
            ]);

            // Log activity
            $this->logDriverActivity($driver->id, [
                'event_type' => 'authorization_granted',
                'event_title' => 'Driver Authorization Granted',
                'event_description' => 'Driver has been approved for vehicle operation',
                'event_date' => $driver->authorization_date,
                'impact' => 'positive',
                'recorded_by' => $approvedBy,
            ]);

            return $driver;
        });
    }

    /**
     * Suspend driver authorization
     */
    public function suspendDriver(
        string $driverId,
        Carbon $suspendedUntil,
        string $reason,
        ?string $suspendedBy = null
    ): ApprovedDriver {
        return DB::transaction(function () use ($driverId, $suspendedUntil, $reason, $suspendedBy) {
            $driver = ApprovedDriver::findOrFail($driverId);

            $driver->update([
                'authorization_status' => 'suspended',
                'suspended_from' => now(),
                'suspended_until' => $suspendedUntil,
                'suspension_reason' => $reason,
                'updated_by' => $suspendedBy,
            ]);

            // Log activity
            $this->logDriverActivity($driverId, [
                'event_type' => 'authorization_suspended',
                'event_title' => 'Driver Authorization Suspended',
                'event_description' => "Suspended until {$suspendedUntil->format('Y-m-d')}: {$reason}",
                'event_date' => now(),
                'impact' => 'negative',
                'recorded_by' => $suspendedBy,
                'metadata' => [
                    'suspended_until' => $suspendedUntil->format('Y-m-d'),
                    'reason' => $reason,
                ],
            ]);

            return $driver->fresh();
        });
    }

    /**
     * Reinstate driver authorization
     */
    public function reinstateDriver(
        string $driverId,
        ?string $notes = null,
        ?string $reinstatedBy = null
    ): ApprovedDriver {
        return DB::transaction(function () use ($driverId, $notes, $reinstatedBy) {
            $driver = ApprovedDriver::findOrFail($driverId);

            $driver->update([
                'authorization_status' => 'approved',
                'suspended_from' => null,
                'suspended_until' => null,
                'suspension_reason' => null,
                'reviewed_by' => $reinstatedBy,
                'reviewed_at' => now(),
                'updated_by' => $reinstatedBy,
            ]);

            // Log activity
            $this->logDriverActivity($driverId, [
                'event_type' => 'authorization_reinstated',
                'event_title' => 'Driver Authorization Reinstated',
                'event_description' => $notes ?? 'Driver authorization has been reinstated',
                'event_date' => now(),
                'impact' => 'positive',
                'recorded_by' => $reinstatedBy,
            ]);

            return $driver->fresh();
        });
    }

    /** Permanently revoke an authorization while retaining its safety history. */
    public function revokeDriver(string $driverId, string $reason, ?string $revokedBy = null): ApprovedDriver
    {
        return DB::transaction(function () use ($driverId, $reason, $revokedBy) {
            $driver = ApprovedDriver::findOrFail($driverId);
            $driver->update([
                'authorization_status' => 'revoked',
                'suspension_reason' => $reason,
                'reviewed_by' => $revokedBy,
                'reviewed_at' => now(),
                'updated_by' => $revokedBy,
            ]);
            $this->logDriverActivity($driverId, [
                'event_type' => 'authorization_revoked',
                'event_title' => 'Driver Authorization Revoked',
                'event_description' => $reason,
                'event_date' => now(),
                'impact' => 'negative',
                'recorded_by' => $revokedBy,
            ]);

            return $driver->fresh();
        });
    }

    /**
     * Renew driver authorization
     */
    public function renewAuthorization(
        string $driverId,
        array $renewalData,
        ?string $renewedBy = null
    ): ApprovedDriver {
        return DB::transaction(function () use ($driverId, $renewalData, $renewedBy) {
            $driver = ApprovedDriver::findOrFail($driverId);

            $driver->update([
                'authorization_status' => 'approved',
                'authorization_date' => now(),
                'expiry_date' => $renewalData['expiry_date'] ?? null,
                'review_date' => $renewalData['review_date'] ?? null,
                'license_expiry_date' => $renewalData['license_expiry_date'] ?? $driver->license_expiry_date,
                'reviewed_by' => $renewedBy,
                'reviewed_at' => now(),
                'updated_by' => $renewedBy,
            ]);

            // Log activity
            $this->logDriverActivity($driverId, [
                'event_type' => 'authorization_renewed',
                'event_title' => 'Driver Authorization Renewed',
                'event_description' => 'Driver authorization has been renewed',
                'event_date' => now(),
                'impact' => 'positive',
                'recorded_by' => $renewedBy,
            ]);

            return $driver->fresh();
        });
    }

    /**
     * Record driver incident
     */
    public function recordIncident(
        string $driverId,
        array $incidentData,
        ?string $reportedBy = null
    ): DriverIncident {
        return DB::transaction(function () use ($driverId, $incidentData, $reportedBy) {
            $driver = ApprovedDriver::findOrFail($driverId);

            // Create incident
            $incident = DriverIncident::create(array_merge($incidentData, [
                'driver_id' => $driverId,
                'employee_id' => $driver->employee_id,
                'status' => 'reported',
                'reported_by' => $reportedBy,
                'created_by' => $reportedBy,
            ]));

            // Update driver incident count and last incident date
            $driver->increment('incident_count');
            $driver->update([
                'last_incident_date' => $incident->incident_date,
            ]);

            // Calculate score impact
            $scoreImpact = $this->calculateIncidentScoreImpact($incident);
            if ($scoreImpact > 0) {
                $this->adjustDriverScore($driverId, -$scoreImpact, $reportedBy);
            }

            // Auto-suspend if critical incident
            if ($incident->severity === 'critical' || $incident->personal_injury) {
                $this->suspendDriver(
                    $driverId,
                    now()->addDays(30),
                    "Auto-suspended due to critical incident: {$incident->incident_number}",
                    $reportedBy
                );
            }

            // Log activity
            $this->logDriverActivity($driverId, [
                'event_type' => 'incident_reported',
                'event_title' => 'Incident Reported',
                'event_description' => "{$incident->incident_type}: {$incident->title}",
                'event_date' => $incident->incident_date,
                'impact' => 'negative',
                'score_change' => -$scoreImpact,
                'recorded_by' => $reportedBy,
                'related_id' => $incident->id,
                'related_type' => DriverIncident::class,
            ]);

            return $incident;
        });
    }

    /**
     * Record violation
     */
    public function recordViolation(
        string $driverId,
        array $violationData,
        ?string $reportedBy = null
    ): DriverIncident {
        $violationData['incident_type'] = $violationData['incident_type'] ?? 'traffic_violation';

        return DB::transaction(function () use ($driverId, $violationData, $reportedBy) {
            $driver = ApprovedDriver::findOrFail($driverId);

            $incident = $this->recordIncident($driverId, $violationData, $reportedBy);

            $driver->increment('violation_count');

            return $incident;
        });
    }

    /**
     * Calculate driver score based on history
     */
    public function calculateDriverScore(string $driverId): float
    {
        $driver = ApprovedDriver::findOrFail($driverId);

        $baseScore = 100.0;

        // Deduct for incidents (last 12 months)
        $recentIncidents = DriverIncident::byDriver($driverId)
            ->where('incident_date', '>=', now()->subMonths(12))
            ->get();

        foreach ($recentIncidents as $incident) {
            $baseScore -= $this->calculateIncidentScoreImpact($incident);
        }

        // Bonus for clean record
        if ($recentIncidents->isEmpty() && $driver->authorization_date->diffInMonths(now()) >= 6) {
            $baseScore += 5.0;
        }

        // Bonus for certifications
        $certificationCount = count($driver->certifications ?? []);
        $baseScore += min($certificationCount * 2, 10); // Max 10 points

        // Cap score between 0 and 100
        return max(0, min(100, round($baseScore, 2)));
    }

    /**
     * Calculate incident score impact (returns positive penalty amount to be subtracted from base score)
     */
    protected function calculateIncidentScoreImpact(DriverIncident $incident): float
    {
        $impact = 0;

        // Base impact by severity
        $severityImpact = [
            'minor' => 2,
            'moderate' => 5,
            'major' => 10,
            'critical' => 20,
        ];
        $impact += $severityImpact[$incident->severity] ?? 0;

        // Additional impact for damages
        if ($incident->personal_injury) {
            $impact += 15;
        }
        if ($incident->property_damage) {
            $impact += 5;
        }
        if ($incident->vehicle_damage) {
            $impact += 5;
        }

        // Driver at fault
        if ($incident->driver_at_fault === 'yes') {
            $impact *= 1.5;
        } elseif ($incident->driver_at_fault === 'no') {
            $impact *= 0.5;
        }

        return round($impact, 2);
    }

    /**
     * Adjust driver score
     */
    public function adjustDriverScore(
        string $driverId,
        float $scoreChange,
        ?string $adjustedBy = null
    ): ApprovedDriver {
        $driver = ApprovedDriver::findOrFail($driverId);

        $newScore = max(0, min(100, $driver->driver_score + $scoreChange));

        $driver->update([
            'driver_score' => $newScore,
            'updated_by' => $adjustedBy,
        ]);

        // Update driver level based on score
        $this->updateDriverLevel($driverId);

        // Log activity
        $this->logDriverActivity($driverId, [
            'event_type' => 'score_updated',
            'event_title' => 'Driver Score Updated',
            'event_description' => 'Score adjusted by '.($scoreChange >= 0 ? '+' : '').$scoreChange,
            'event_date' => now(),
            'old_value' => (string) $driver->driver_score,
            'new_value' => (string) $newScore,
            'impact' => $scoreChange >= 0 ? 'positive' : 'negative',
            'score_change' => $scoreChange,
            'recorded_by' => $adjustedBy,
        ]);

        return $driver->fresh();
    }

    /**
     * Update driver level based on score
     */
    protected function updateDriverLevel(string $driverId): void
    {
        $driver = ApprovedDriver::findOrFail($driverId);

        $score = $driver->driver_score;

        $newLevel = match (true) {
            $score >= 95 => 'expert',
            $score >= 85 => 'senior',
            $score >= 70 => 'standard',
            $score >= 50 => 'junior',
            default => 'trainee',
        };

        if ($driver->driver_level !== $newLevel) {
            $oldLevel = $driver->driver_level;
            $driver->update(['driver_level' => $newLevel]);

            $this->logDriverActivity($driverId, [
                'event_type' => 'other',
                'event_title' => 'Driver Level Changed',
                'event_description' => "Driver level changed from {$oldLevel} to {$newLevel}",
                'event_date' => now(),
                'old_value' => $oldLevel,
                'new_value' => $newLevel,
                'impact' => $newLevel > $oldLevel ? 'positive' : 'negative',
            ]);
        }
    }

    /**
     * Log driver activity
     */
    public function logDriverActivity(string $driverId, array $activityData): DriverActivityLog
    {
        $driver = ApprovedDriver::findOrFail($driverId);

        return DriverActivityLog::create([
            'driver_id' => $driverId,
            'employee_id' => $driver->employee_id,
            'event_type' => $activityData['event_type'],
            'event_title' => $activityData['event_title'],
            'event_description' => $activityData['event_description'] ?? null,
            'event_date' => $activityData['event_date'] ?? now(),
            'old_value' => $activityData['old_value'] ?? null,
            'new_value' => $activityData['new_value'] ?? null,
            'metadata' => $activityData['metadata'] ?? null,
            'vehicle_id' => $activityData['vehicle_id'] ?? null,
            'related_id' => $activityData['related_id'] ?? null,
            'related_type' => $activityData['related_type'] ?? null,
            'impact' => $activityData['impact'] ?? null,
            'score_change' => $activityData['score_change'] ?? null,
            'recorded_by' => $activityData['recorded_by'] ?? null,
            'created_by' => $activityData['recorded_by'] ?? null,
            'created_at' => now(),
        ]);
    }

    /**
     * Get drivers requiring license renewal
     */
    public function getDriversRequiringLicenseRenewal(int $daysAhead = 30): \Illuminate\Database\Eloquent\Collection
    {
        return ApprovedDriver::active()
            ->where('license_expiry_date', '<=', now()->addDays($daysAhead))
            ->where('license_expiry_date', '>=', now())
            ->with('employee')
            ->get();
    }

    /**
     * Get drivers requiring authorization review
     */
    public function getDriversRequiringReview(int $daysAhead = 30): \Illuminate\Database\Eloquent\Collection
    {
        return ApprovedDriver::active()
            ->where(function ($query) use ($daysAhead) {
                $query->where('review_date', '<=', now()->addDays($daysAhead))
                    ->orWhere('expiry_date', '<=', now()->addDays($daysAhead));
            })
            ->with('employee')
            ->get();
    }
}
