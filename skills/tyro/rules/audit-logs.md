# Audit Logs

## Why It Matters

Tyro's audit system records every authorization-relevant state change: role and privilege CRUD, user-role assignments, privilege attachments, user suspensions, token creation/revocation, and system-level events (install, seed). Audit logs provide an immutable (append-only) trail for compliance, debugging, and security incident response. The `AuditLog` model stores the event name, actor (user), auditable entity (polymorphic), old/new values, and rich metadata (IP, user agent, console flag). Without a disciplined audit strategy, accountability is lost, and regulatory requirements (SOC 2, GDPR, HIPAA data access logs) cannot be satisfied.

## Incorrect

```php
// Skipping audit for important state changes
public function destroy(Role $role) {
    TyroCache::forgetUsersByRole($role);
    $role->delete();
    // No TyroAudit::log() call — who deleted this role?
}

// Logging without old values — can't reconstruct state
TyroAudit::log('role.updated', $role, null, $role->toArray());
// Now we have no idea what changed

// Logging with mutable data — later DB changes corrupt the audit
TyroAudit::log('user.created', $user, null, $user->toArray());
// $user is later modified — the audit record still references live data

// Mutating audit logs after creation
$log = AuditLog::create([...]);
$log->update(['event' => 'different.event']); // Breaks immutability

// Logging without actor context
AuditLog::create([
    'event' => 'role.created',
    // user_id is null — who did it?
]);
```

## Correct

```php
// Audit via TyroAudit::log() — captures actor automatically
TyroAudit::log('role.created', $role, null, $role->toArray());

// Old values from model state before mutation
$oldValues = [
    'name' => $role->getOriginal('name'),
    'slug' => $role->getOriginal('slug'),
];
$role->update(['name' => 'New Name', 'slug' => 'new-slug']);
TyroAudit::log('role.updated', $role, $oldValues, $role->getChanges());

// Role observer handles created/updated/deleted automatically
class RoleObserver {
    public function created(Role $role) {
        TyroAudit::log('role.created', $role, null, $role->toArray());
    }
    public function updated(Role $role) {
        TyroAudit::log('role.updated', $role, $role->getOriginal(), $role->getChanges());
    }
    public function deleted(Role $role) {
        TyroAudit::log('role.deleted', $role, $role->toArray());
    }
}

// Assignment audits in the trait
public function assignRole(Role $role): void {
    $this->roles()->syncWithoutDetaching($role);
    TyroCache::forgetUser($this->getKey());
    $this->flushTyroRuntimeCache();
    TyroAudit::log('role.assigned', $this, null, [
        'role_id' => $role->id,
        'role_slug' => $role->slug,
    ]);
}

// Suspension audit in the trait
public function suspend(?string $reason = null): int {
    $oldValues = [
        'suspended_at' => $this->suspended_at,
        'suspension_reason' => $this->suspension_reason,
    ];
    $this->suspended_at = now();
    $this->suspension_reason = $reason;
    $this->save();
    TyroAudit::log('user.suspended', $this, $oldValues, [
        'suspended_at' => $this->suspended_at,
        'suspension_reason' => $this->suspension_reason,
    ]);
    return (int) $this->tokens()->delete();
}

// Audit log with correlation — add request ID to metadata
TyroAudit::log('user.deleted', $user, $user->only(['name', 'email', 'id']), null, [
    'correlation_id' => request()->header('X-Correlation-ID'),
]);

// Query logs with filters
AuditLog::query()
    ->where('event', 'like', 'role.%')
    ->where('user_id', $actorId)
    ->whereDate('created_at', '>=', $from)
    ->paginate(20);

// Read the computed summary attribute
$log->summary; // "Assigned role \"editor\" to user #42"
```

## Notes

- `AuditLog` model uses `$timestamps = false` and stores `created_at` explicitly as a fillable datetime column.
- `old_values` and `new_values` are JSON `array` casts. `metadata` is also a JSON `array` cast.
- The `auditable` relationship is a `morphTo()` — any Eloquent model can be the subject.
- Default metadata includes `ip`, `user_agent`, and `is_console` — appended automatically by `TyroAudit::log()`.
- If audit is disabled (`config('tyro.audit.enabled')` = false), `TyroAudit::log()` returns null and no record is created.
- Retention is managed via `config('tyro.audit.retention_days')` (default 30). The `PurgeAuditLogsCommand` deletes older records.
- Observer registration is conditional on `config('tyro.audit.enabled')` in the service provider.
- All audit event strings use dot notation: `role.created`, `privilege.attached`, `user.suspended`, `system.installed`.
- Audit logs should never be updated or deleted in application code — they are append-only. The `PurgeAuditLogsCommand` is the sole exception for lifecycle management.
- `AuditLogController::index()` exposes filtering by `event`, `user_id`, `from`, `to`, and pagination.

## Cross References

- artisan-commands.md (PurgeAuditLogsCommand, ListAuditLogsCommand)
- security.md (immutable audit trails, actor tracking)
- naming-conventions.md (audit event naming patterns)
- api-design.md (AuditLogController API)
