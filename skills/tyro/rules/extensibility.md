# Extensibility

## Why It Matters

Tyro is designed as a reusable framework, not a fixed drop-in. Every major component — models, tables, middleware, commands, Blade directives, observer hooks — is configurable or replaceable. This allows third-party packages, SaaS platforms, and enterprise deployments to extend Tyro without modifying vendor code. The `HasTyroRoles` trait composition pattern means any authenticatable model becomes a Tyro user.

## Incorrect

Forking the package to change behavior:

```php
// Do NOT fork the package for custom table names
// Instead, configure them via config/tyro.php
```

Hardcoding base classes instead of using config resolution:

```php
// Do NOT extend Tyro models directly for customization
class CustomRole extends HasinHayder\Tyro\Models\Role {
    // Overriding this way breaks config('tyro.models.role')
}
```

Duplicating middleware logic instead of composing existing ones:

```php
// Do NOT rewrite role checking from scratch in your own middleware
class MyCustomRoleMiddleware {
    public function handle($request, $next, $role) {
        // Re-implementing EnsureTyroRole logic here...
    }
}
```

## Correct

Use configurable model classes to provide custom models (config/tyro.php):

```php
'models' => [
    'user' => env('TYRO_USER_MODEL', 'App\\Models\\User'),
    'role' => \HasinHayder\Tyro\Models\Role::class,
    'privilege' => \HasinHayder\Tyro\Models\Privilege::class,
    'pivot' => \HasinHayder\Tyro\Models\UserRole::class,
    'audit_log' => \HasinHayder\Tyro\Models\AuditLog::class,
],
```

Use configurable table names for multi-tenant or legacy database setups:

```php
'tables' => [
    'users' => env('TYRO_USERS_TABLE', 'users'),
    'roles' => 'tenant_roles',
    'pivot' => 'tenant_user_roles',
    'privileges' => 'tenant_privileges',
    'role_privilege' => 'tenant_privilege_role',
    'audit_logs' => 'tenant_tyro_audit_logs',
],
```

Add custom Commands by extending `BaseTyroCommand` (src/Console/Commands/BaseTyroCommand.php):

```php
use HasinHayder\Tyro\Console\Commands\BaseTyroCommand;

class AuditReportCommand extends BaseTyroCommand {
    protected $signature = 'tyro:audit-report {--days=30}';
    protected $description = 'Generate an audit report for the last N days';

    public function handle(): int {
        $auditClass = config('tyro.models.audit_log');
        $logs = $auditClass::where(
            'created_at', '>=', now()->subDays($this->option('days'))
        )->get();

        $this->table(['Event', 'User ID', 'Created'], $logs->map(fn ($log) => [
            $log->event,
            $log->user_id,
            $log->created_at,
        ]));

        return self::SUCCESS;
    }
}
```

Register custom Blade directives alongside Tyro's built-in ones (in your service provider):

```php
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {
    public function boot(): void {
        Blade::if('hassuperadmin', function () {
            $user = auth()->user();
            return $user && method_exists($user, 'hasRole')
                ? $user->hasRole('super-admin')
                : false;
        });
    }
}
```

Register custom middleware aliases (in your service provider's boot):

```php
$router = $this->app['router'];
$router->aliasMiddleware('tyro.audit', \App\Http\Middleware\CustomTyroAudit::class);
```

Override observer behavior by disabling Tyro's default observer and registering your own:

```php
// In AppServiceProvider::boot()
\HasinHayder\Tyro\Models\Role::flushEventListeners();
\HasinHayder\Tyro\Models\Role::observe(\App\Observers\CustomRoleObserver::class);
```

Add custom cache driver behavior by extending TyroCache's key resolution:

```php
use HasinHayder\Tyro\Support\TyroCache;

// Custom keys for multi-tenant per-organization caching
class TenantAwareTyroCache extends TyroCache {
    protected static function rolesKey($userId): string {
        $tenantId = tenant()->id;  // hypothetical tenant context
        return sprintf('tyro:tenant-%s:user-%s:roles', $tenantId, $userId);
    }
}
```

## Notes

- The `HasTyroRoles` trait is the ONLY requirement for a user model. It uses method existence checks (`method_exists`) internally, so any Authenticatable with the trait works.
- Custom route bindings for `role`, `privilege`, and `user` are registered in `TyroServiceProvider::registerBindings()` — see `Route::model('role', Role::class)` and the custom `user` binding.
- Middleware aliases registered: `tyro.log`, `privilege`, `privileges`, `role`, `roles`, `ability`, `abilities`.
- Blade directives registered: `@hasrole`, `@hasRole`, `@hasanyrole`, `@hasanyRole`, `@hasroles`, `@hasprivilege`, `@hasanyprivilege`, `@hasprivileges`, `@usercan`.
- To add new Artisan commands with Tyro conventions, extend `BaseTyroCommand` to inherit `userClass()`, `newUserQuery()`, `findUser()`, `findRole()`, `findPrivilege()`, `defaultRole()`, `openUrl()`, and `abilitiesForUser()`.
- Third-party packages can depend on `hasinhayder/tyro` as a Composer dependency and use all public APIs documented in `backward-compatibility.md`.

## Cross References

- [configuration.md](configuration.md) — model/table config as extension mechanism
- [backward-compatibility.md](backward-compatibility.md) — public API contract for extenders
- [testing.md](testing.md) — testing custom extensions
- [artisan-commands.md](artisan-commands.md) — BaseTyroCommand extension pattern
- [architecture.md](architecture.md) — service provider registration flow
