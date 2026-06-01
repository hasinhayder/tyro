# Events

## Why It Matters

Tyro uses a string-based audit event system (e.g., `'role.created'`, `'user.suspended'`) instead of dedicated Laravel Event classes. This design keeps the surface area small, avoids class proliferation, and makes audit trail filtering trivial. Observers (RoleObserver, PrivilegeObserver) fire these events automatically during model lifecycle. Cache invalidation is triggered inline rather than via events to prevent stale reads during request lifetimes.

## Incorrect

Creating dedicated Event classes for every audit event:

```php
// Do NOT create classes like this for every event
namespace HasinHayder\Tyro\Events;

class RoleCreated extends \Illuminate\Foundation\Events\Dispatchable
{
    public function __construct(
        public Role $role,
        public ?User $actor = null,
    ) {}
}

// And another...
class RoleUpdated extends \Illuminate\Foundation\Events\Dispatchable {}
class RoleDeleted extends \Illuminate\Foundation\Events\Dispatchable {}
// ...this would add 40+ event classes
```

Firing audit events without context or actor identification:

```php
// Do NOT — missing old_values, new_values, and actor context
\Illuminate\Support\Facades\Log::info('role assigned to user');
```

Mixing cache invalidation with audit logging in the same method call:

```php
// Do NOT — cache should be invalidated independently of audit
$user->roles()->attach($role);
Cache::forget('roles:'.$user->id);
TyroAudit::log('role.assigned', $user, null, ['role_slug' => $role->slug]);
```

## Correct

Use `TyroAudit::log()` with consistent dot-notation event names:

```php
use HasinHayder\Tyro\Support\TyroAudit;

// Log a role assignment with full context
$user->assignRole($role);
// Internally calls:
// TyroAudit::log('role.assigned', $user, null, [
//     'role_id' => $role->id,
//     'role_slug' => $role->slug,
// ]);
```

Observer pattern for model lifecycle events (src/Models/Observers/RoleObserver.php):

```php
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
```

Cache invalidation happens inline before audit logging (src/Concerns/HasTyroRoles.php):

```php
public function assignRole(Role $role): void {
    $this->roles()->syncWithoutDetaching($role);
    TyroCache::forgetUser($this->getKey());
    $this->flushTyroRuntimeCache();

    TyroAudit::log('role.assigned', $this, null, [
        'role_id' => $role->id,
        'role_slug' => $role->slug,
    ]);
}
```

Available audit event strings (from src/Models/AuditLog.php `getSummaryAttribute`):

```php
// Role events
'role.created'
'role.updated'
'role.deleted'
'role.assigned'
'role.removed'

// Privilege events
'privilege.created'
'privilege.updated'
'privilege.deleted'
'privilege.attached'
'privilege.detached'

// User events
'user.created'
'user.updated'
'user.deleted'
'user.suspended'
'user.unsuspended'
'user.token_created'
'user.token_revoked'
'user.tokens_revoked'
'user.login'
'user.logout'
'user.email_changed'

// System events
'system.installed'
'system.seeded'
'system.tokens_purged'

// Batch operations
'roles.flushed'
'privileges.purged'
```

## Notes

- If you must add Laravel Event classes, fire them AFTER audit logging and cache invalidation to maintain ordering guarantees.
- Use `TyroAudit::log()` rather than direct `AuditLog::create()` to get automatic actor ID, IP, user agent, and console detection.
- All event strings use `{entity}.{action}` dot notation — never invent a different format.
- Audit event strings are human-readable in the `getSummaryAttribute()` method of `AuditLog`.
- Retention is configurable via `config('tyro.audit.retention_days')` — the `tyro:audit-purge` command enforces this.
- If `config('tyro.audit.enabled')` is false, `TyroAudit::log()` returns null without persisting.

## Cross References

- [configuration.md](configuration.md) — audit.enabled and audit.retention_days config
- [architecture.md](architecture.md) — observer registration in TyroServiceProvider
- [caching.md](caching.md) — cache invalidation dependency on audit events
- [audit-logs.md](audit-logs.md) — audit log schema, retention, and querying
- [backward-compatibility.md](backward-compatibility.md) — event string stability as public API
