# Architecture Rules

## Why It Matters

Tyro is a reusable Laravel authorization package under the `HasinHayder\Tyro` namespace, autoloaded via PSR-4 and installed through Composer as `hasinhayder/tyro`. Its architecture follows strict dependency direction: consuming applications depend on Tyro, never the reverse. All binding between Tyro and the host application goes through Laravel's service container, config files, and published migrations — never through hard-coded application class references. The ServiceProvider (`src/Providers/TyroServiceProvider.php`) is the single entry point that registers middleware aliases, route model bindings, Artisan commands, Blade directives, model observers, routes, and publishable assets. Internal support classes (`TyroCache`, `TyroAudit`, `PasswordRules`) must never be called directly by consuming code; all public interaction happens through the `HasTyroRoles` trait and the exposed model classes.

## Incorrect

```php
// INCORRECT: Hard-coding the user model class name
class Role extends Model {
    public function users() {
        return $this->belongsToMany('App\\Models\\User', 'user_roles');
    }
}
```

```php
// INCORRECT: Calling internal support classes directly in application code
$slugs = TyroCache::rememberRoleSlugs($userId, fn() => []);
TyroAudit::log('custom.event', $something);

// Application code should only use the public HasTyroRoles trait
```

```php
// INCORRECT: Tyro importing from application code
use App\Models\User;

class SomeTyroClass {
    public function getDefaultUser() {
        return new User();
    }
}
```

```php
// INCORRECT: Bypassing the ServiceProvider to register middleware
// Don't register Tyro middleware manually in app/Http/Kernel.php
// This is the ServiceProvider's responsibility
```

## Correct

```php
// CORRECT: Using config-driven model resolution
class Role extends Model {
    public function users() {
        $userClass = config('tyro.models.user', config('auth.providers.users.model', 'App\\Models\\User'));

        return $this->belongsToMany($userClass, config('tyro.tables.pivot', 'user_roles'));
    }
}
```

```php
// CORRECT: Public APIs go through the HasTyroRoles trait
$user->assignRole($role);
$user->hasRole('admin');
$user->can('edit-posts');
$user->isSuspended();
```

```php
// CORRECT: Internal support classes used only within Tyro internals
// TyroCache is called by HasTyroRoles trait, pivot models, and Role model
// Never directly by application code

// In HasTyroRoles trait:
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

```php
// CORRECT: ServiceProvider as the single entry point
class TyroServiceProvider extends ServiceProvider {
    public function boot(): void {
        $this->registerPublishing();   // Config, migrations, assets
        $this->registerRoutes();       // API routes under configurable prefix
        $this->registerMiddleware();   // Alias middleware (role, roles, privilege, etc.)
        $this->registerBindings();     // Route model bindings
        $this->registerCommands();     // Artisan commands
        $this->registerBladeDirectives(); // @userHasRole etc.
        $this->registerObservers();    // RoleObserver, PrivilegeObserver
    }
}
```

## Notes

- The `HasTyroRoles` trait is the primary public API surface. It adds `roles()`, `assignRole()`, `removeRole()`, `hasRole()`, `hasAnyRole()`, `hasRoles()`, `privileges()`, `hasPrivilege()`, `hasPrivileges()`, `can()`, `tyroRoleSlugs()`, `tyroPrivilegeSlugs()`, `suspend()`, `unsuspend()`, `isSuspended()`, and `getSuspensionReason()` to any authenticatable model.
- Models (`Role`, `Privilege`, `UserRole`, `RolePrivilege`, `AuditLog`) use configurable table names via `config('tyro.tables.*')`.
- All pivot models (`UserRole`, `RolePrivilege`) extend `Illuminate\Database\Eloquent\Relations\Pivot` and auto-flush `TyroCache` on save/delete.
- Blade directives (`@userHasRole`, `@userCan`, etc.) are registered in `TyroServiceProvider::registerBladeDirectives()`.
- Middleware aliases (`role`, `roles`, `privilege`, `privileges`, `ability`, `abilities`) are registered in `TyroServiceProvider::registerMiddleware()`.
- Observer registration (`RoleObserver`, `PrivilegeObserver`) is gated by `config('tyro.audit.enabled')`.
- Routes are loaded from `routes/api.php` with configurable prefix, name prefix, and middleware group.

## Cross References

- [authorization.md](authorization.md) — Public API of the authorization layer
- [roles.md](roles.md) — Role model and registration
- [privileges.md](privileges.md) — Privilege model and system-level access
- [permissions.md](permissions.md) — Permission modeling as privileges
- [configuration.md](configuration.md) — Config-driven architecture
