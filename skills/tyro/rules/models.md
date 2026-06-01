# Model Rules

## Why It Matters

Tyro defines 5 Eloquent models (`Role`, `Privilege`, `UserRole`, `RolePrivilege`, `AuditLog`) that form the authorization data layer. All models use configurable table names via `config('tyro.tables.*')` — most implement `getTable()` to resolve the table name at runtime, while `Role` uses a static `$table` property. Two are pivot models (`UserRole`, `RolePrivilege`) extending `Illuminate\Database\Eloquent\Relations\Pivot` with automatic cache invalidation. The `AuditLog` model is append-only with JSON casts for old/new values and metadata. Factory resolution follows the `HasFactory` trait convention. Observer registration is gated by `config('tyro.audit.enabled')` in the ServiceProvider. Model relationships connect users to roles (many-to-many through `UserRole`) and roles to privileges (many-to-many through `RolePrivilege`). Understanding each model's fillable fields, hidden fields, relationship methods, table name resolution, and customization points is essential for extension and debugging.

## Incorrect

```php
// INCORRECT: Hardcoding table names in model relationships
class Role extends Model {
    public function privileges() {
        return $this->belongsToMany(Privilege::class, 'privilege_role');
        // Should use config('tyro.tables.role_privilege', 'privilege_role')
    }
}
```

```php
// INCORRECT: Adding user_id to Role fillable (wrong model)
class Role extends Model {
    protected $fillable = ['name', 'slug', 'user_id'];
    // Roles are global — not user-specific. User_id belongs on UserRole pivot.
}
```

```php
// INCORRECT: Forgetting to extend Pivot for pivot models
class UserRole extends Model {
    // Should extend Illuminate\Database\Eloquent\Relations\Pivot
    // Otherwise belongsToMany(->using(UserRole::class)) breaks
}
```

```php
// INCORRECT: Adding timestamps to AuditLog
class AuditLog extends Model {
    public $timestamps = true;
    // AuditLog explicitly sets $timestamps = false
    // created_at is managed as a regular fillable datetime column
}
```

```php
// INCORRECT: Calling model factory on a model without HasFactory
$auditLog = AuditLog::factory()->create();
// AuditLog does not have a dedicated factory — use direct create() instead
```

## Correct

```php
// CORRECT: Role model — static table property with config-driven relationships
class Role extends Model {
    use HasFactory;

    protected $fillable = ['name', 'slug'];
    protected $hidden = ['pivot', 'created_at', 'updated_at'];
    protected $table = 'roles'; // Static — not config-overridable via getTable()

    public function users() {
        $userClass = config('tyro.models.user', config('auth.providers.users.model', 'App\\Models\\User'));
        return $this->belongsToMany($userClass, config('tyro.tables.pivot', 'user_roles'));
    }

    public function privileges(): BelongsToMany {
        return $this->belongsToMany(
            Privilege::class,
            config('tyro.tables.role_privilege', 'privilege_role')
        )->using(RolePrivilege::class)->withTimestamps();
    }
}
```

```php
// CORRECT: Privilege model — config-driven table via getTable()
class Privilege extends Model {
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description'];
    protected $hidden = ['pivot', 'created_at', 'updated_at'];

    public function getTable() {
        return config('tyro.tables.privileges', parent::getTable());
    }

    public function roles(): BelongsToMany {
        return $this->belongsToMany(
            Role::class,
            config('tyro.tables.role_privilege', 'privilege_role')
        )->using(RolePrivilege::class)->withTimestamps();
    }
}
```

```php
// CORRECT: UserRole pivot model — extends Pivot with cache auto-flush
class UserRole extends Pivot {
    use HasFactory;

    protected $table = 'user_roles'; // Static, overridden by getTable()
    protected $fillable = ['user_id', 'role_id'];
    public $timestamps = true;

    protected static function booted(): void {
        static::saved(function (self $pivot): void {
            TyroCache::forgetUser($pivot->user_id);
        });
        static::deleted(function (self $pivot): void {
            TyroCache::forgetUser($pivot->user_id);
        });
    }

    public function getTable() {
        return config('tyro.tables.pivot', parent::getTable());
    }
}
```

```php
// CORRECT: RolePrivilege pivot model — caches invalidation by role
class RolePrivilege extends Pivot {
    use HasFactory;

    protected $table = 'privilege_role'; // Static, overridden by getTable()
    protected $fillable = ['role_id', 'privilege_id'];
    public $timestamps = true;

    protected static function booted(): void {
        static::saved(function (self $pivot): void {
            TyroCache::forgetUsersByRoleIds([$pivot->role_id]);
        });
        static::deleted(function (self $pivot): void {
            TyroCache::forgetUsersByRoleIds([$pivot->role_id]);
        });
    }

    public function getTable() {
        return config('tyro.tables.role_privilege', parent::getTable());
    }
}
```

```php
// CORRECT: AuditLog model — append-only with JSON casts
class AuditLog extends Model {
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'event', 'auditable_type', 'auditable_id',
        'old_values', 'new_values', 'metadata', 'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function getTable() {
        return config('tyro.tables.audit_logs', 'tyro_audit_logs');
    }

    public function user(): BelongsTo {
        $userClass = config('tyro.models.user', 'App\Models\User');
        return $this->belongsTo($userClass, 'user_id');
    }

    public function auditable(): MorphTo {
        return $this->morphTo();
    }

    public function getSummaryAttribute(): string {
        // Computed summary for human-readable audit display
        // Maps events like 'role.assigned' to readable strings
    }
}
```

```php
// CORRECT: Observer registration in TyroServiceProvider
protected function registerObservers(): void {
    if (config('tyro.audit.enabled', true)) {
        Role::observe(RoleObserver::class);
        Privilege::observe(PrivilegeObserver::class);
    }
}
// Observers are conditional on audit being enabled
```

```php
// CORRECT: RoleObserver — audits CRUD lifecycle
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

// PrivilegeObserver — same pattern for privilege lifecycle
class PrivilegeObserver {
    public function created(Privilege $privilege) {
        TyroAudit::log('privilege.created', $privilege, null, $privilege->toArray());
    }
    public function updated(Privilege $privilege) {
        TyroAudit::log('privilege.updated', $privilege, $privilege->getOriginal(), $privilege->getChanges());
    }
    public function deleted(Privilege $privilege) {
        TyroAudit::log('privilege.deleted', $privilege, $privilege->toArray());
    }
}
```

```php
// CORRECT: Route model bindings registered in ServiceProvider
protected function registerBindings(): void {
    Route::model('role', Role::class);
    Route::model('privilege', Privilege::class);

    Route::bind('user', function ($value) {
        $userClass = config('tyro.models.user', config('auth.providers.users.model'));
        return $userClass::query()->findOrFail($value);
    });
}
// Role and Privilege use Eloquent route model binding
// User uses a custom resolver for the configurable user model
```

## Notes

- `Role` uses `protected $table = 'roles'` (static property), while `Privilege`, `UserRole`, `RolePrivilege`, and `AuditLog` override `getTable()` to return `config('tyro.tables.*')` values. This is an inconsistency — extenders should be aware of both patterns.
- Pivot models extend `Illuminate\Database\Eloquent\Relations\Pivot` — not `Model` directly — which enables the `using()` clause in `belongsToMany()` definitions.
- Both pivot models auto-flush `TyroCache` via `booted()` hooks: `UserRole` calls `TyroCache::forgetUser($pivot->user_id)`, `RolePrivilege` calls `TyroCache::forgetUsersByRoleIds([$pivot->role_id])`.
- `AuditLog` is append-only with no `updated_at` — only `created_at` is stored as a fillable datetime column with a `datetime` cast.
- `Role` fillable: `['name', 'slug']`. `Privilege` fillable: `['name', 'slug', 'description']`. `UserRole` fillable: `['user_id', 'role_id']`. `RolePrivilege` fillable: `['role_id', 'privilege_id']`. `AuditLog` fillable: `['user_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'metadata', 'created_at']`.
- Both `Role` and `Privilege` hide `['pivot', 'created_at', 'updated_at']` from JSON serialization.
- Factory resolution: models use the `HasFactory` trait from Laravel, which resolves factories via convention (`Database\Factories\RoleFactory` for `Role`, etc.). Factories are loaded in `TyroServiceProvider::boot()` via `$this->loadFactoriesFrom(__DIR__.'/../../database/factories')` when running in console. Only `UserFactory` and `PrivilegeFactory` exist — `Role`, `UserRole`, `RolePrivilege`, and `AuditLog` do not have dedicated factories.
- The config section `config('tyro.models.*')` defines the class references for `user`, `role`, `privilege`, `pivot`, and `audit_log` — used in relationships and route bindings.
- The config section `config('tyro.tables.*')` defines: `users`, `roles`, `pivot` (user_roles), `privileges`, `role_privilege` (privilege_role), `audit_logs` (tyro_audit_logs).
- When extending or customizing models, override the config values in `config/tyro.php` — never modify the package source files.

## Cross References

- [caching.md](caching.md) — Pivot model auto-invalidation hooks
- [audit-logs.md](audit-logs.md) — AuditLog model details and summary attribute
- [roles.md](roles.md) — Role model methods and hasPrivilege checks
- [privileges.md](privileges.md) — Privilege model relationships
- [configuration.md](configuration.md) — Table and model configuration options
- [migrations-seeding.md](migrations-seeding.md) — Schema design and seed factories
