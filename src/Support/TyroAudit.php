<?php

namespace HasinHayder\Tyro\Support;

use HasinHayder\Tyro\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class TyroAudit
{
    /**
     * Log an audit event.
     *
     * @param string $event
     * @param Model|null $auditable
     * @param array|null $oldValues
     * @param array|null $newValues
     * @param array $metadata
     * @return AuditLog|null
     */
    public static function log(
        string $event,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $metadata = []
    ): ?AuditLog {
        if (!config('tyro.audit.enabled', true)) {
            return null;
        }

        $userId = Auth::guard(config('tyro.guard'))->id() ?? Auth::id();
        
        $defaultMetadata = [
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'is_console' => app()->runningInConsole(),
        ];

        return AuditLog::create([
            'user_id' => $userId,
            'event' => $event,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable ? $auditable->getKey() : null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => array_merge($defaultMetadata, $metadata),
        ]);
    }
}
