<?php

declare(strict_types=1);

namespace App\Modules\Hr\Services;

use App\Modules\Hr\Models\HrAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class HrAuditService
{
    /**
     * Record an audit event for any HR entity.
     */
    public function log(
        Model $model,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?string $actorId = null
    ): HrAuditLog {
        return HrAuditLog::create([
            'auditable_type' => get_class($model),
            'auditable_id' => (string) $model->getKey(),
            'actor_id' => $actorId ?? auth()->id(),
            'action' => strtoupper($action),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'ip_address' => Request::ip(),
        ]);
    }
}
