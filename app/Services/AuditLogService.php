<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogService
{
    /**
     * Persist a governance audit log entry.
     *
     * @param  array<string, mixed>  $details
     */
    public function log(
        ?User $actor,
        string $moduleName,
        string $actionType,
        string $referenceType,
        int $referenceId,
        array $details = [],
        string $logDomain = 'TECHNICAL',
        ?int $officeId = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $actor?->id,
            'office_id' => $officeId ?? $actor?->office_id,
            'log_domain' => strtoupper($logDomain),
            'module_name' => $moduleName,
            'action_type' => $actionType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'details' => $details,
        ]);
    }
}
