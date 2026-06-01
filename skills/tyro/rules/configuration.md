# Configuration

## Why It Matters

All Tyro behavior — tables, models, caching, auditing, passwords, abilities, routes, guards — is controlled through a single `config/tyro.php` file. This allows consuming applications to override every aspect of the framework without modifying vendor code. Environment variable overrides enable per-environment tuning (e.g., different cache stores, disabling audits in testing).

## Incorrect

Hardcoding table names or model classes:

```php
// Do NOT hardcode table names or model references
$roles = DB::table('roles')->get();  // Hardcoded
$user = User::find($id);             // Assumes default User class
```

Making assumptions about the guard or authentication driver:

```php
// Do NOT assume a specific guard
$user = auth()->user();  // May use wrong guard
$userId = Auth::id();    // No guard specified
```

Omitting environment variable overrides for sensitive settings:

```php
// Do NOT expose sensitive settings without env override
'cache' => [
    'store' => 'redis',   // Should be env('TYRO_CACHE_STORE')
    'ttl' => 300,
],
```

## Correct

Always resolve table names from config in model `getTable()` (src/Models/Privilege.php):

```php
class Privilege extends Model {
    public function getTable() {
        return config('tyro.tables.privileges', parent::getTable());
    }
}
```

Resolve model classes from config with fallback chain (src/Models/Role.php):

```php
public function users() {
    $userClass = config(
        'tyro.models.user',
        config('auth.providers.users.model', 'App\\Models\\User')
    );
    return $this->belongsToMany($userClass, config('tyro.tables.pivot', 'user_roles'));
}
```

Use config for guard in audit and auth contexts (src/Support/TyroAudit.php):

```php
$userId = Auth::guard(config('tyro.guard'))->id() ?? Auth::id();
```

Set environment variable overrides in `config/tyro.php`:

```php
// config/tyro.php
return [
    'disable_commands' => env('TYRO_DISABLE_COMMANDS', false),
    'guard' => env('TYRO_GUARD', 'sanctum'),
    'route_prefix' => env('TYRO_ROUTE_PREFIX', 'api'),

    'cache' => [
        'enabled' => env('TYRO_CACHE_ENABLED', true),
        'store' => env('TYRO_CACHE_STORE'),
        'ttl' => env('TYRO_CACHE_TTL', 300),
    ],

    'models' => [
        'user' => env('TYRO_USER_MODEL', env('AUTH_MODEL', 'App\\Models\\User')),
    ],

    'tables' => [
        'users' => env('TYRO_USERS_TABLE', 'users'),
    ],

    'audit' => [
        'enabled' => env('TYRO_AUDIT_ENABLED', true),
        'retention_days' => env('TYRO_AUDIT_RETENTION_DAYS', 30),
    ],
];
```

All configurable path keys:

```php
// Full config/tyro.php structure:
'disable_commands'              // bool — disable all artisan commands
'guard'                         // string — auth guard (sanctum, web, api)
'route_prefix'                  // string — API route prefix
'route_name_prefix'             // string — route name prefix
'route_middleware'               // array — middleware applied to all Tyro routes
'load_default_routes'           // bool — register built-in API routes
'disable_api'                   // bool — disable all API routes
'models.user'                   // string — user model class
'models.role'                   // string — role model class
'models.privilege'              // string — privilege model class
'models.pivot'                  // string — user_role pivot model
'models.audit_log'              // string — audit log model class
'tables.users'                  // string — users table name
'tables.roles'                  // string — roles table name
'tables.pivot'                  // string — user_roles pivot table
'tables.privileges'             // string — privileges table name
'tables.role_privilege'         // string — privilege_role pivot table
'tables.audit_logs'             // string — tyro_audit_logs table name
'audit.enabled'                 // bool — enable audit logging
'audit.retention_days'          // int — days to keep audit logs
'default_user_role_slug'        // string — role assigned to new users by default
'protected_role_slugs'          // array — roles protected from deletion
'delete_previous_access_tokens_on_login' // bool
'cache.enabled'                 // bool — enable caching layer
'cache.store'                   // string|null — cache store name
'cache.ttl'                     // int|null — cache TTL in seconds
'password.min_length'           // int
'password.max_length'           // int|null
'password.require_confirmation' // bool
'password.complexity.*'         // bool — uppercase, lowercase, numbers, special_chars
'password.check_common_passwords' // bool
'password.disallow_user_info'   // bool
'abilities.admin'               // array — slugs for admin ability
'abilities.user_update'         // array — slugs for user update ability
```

## Notes

- Always use `config('tyro.*')` accessor — never access `$this->app['config']` directly outside service providers.
- Always provide a sensible default as the second argument to `config()` in case the key is missing.
- Environment variables use the `TYRO_` prefix convention.
- The `route_middleware` array is NOT overridable via env — it is a code-time decision.
- The `models.*` config keys accept FQCN strings, not instances.
- The `tables.*` config keys accept raw table names; migrations use these too via `Schema::hasTable()` and `DB::table()`.
- When publishing config via `php artisan vendor:publish --tag=tyro-config`, the published file is immutable for that project — changes should go through the published copy.

## Cross References

- [extensibility.md](extensibility.md) — model/table configuration as extension points
- [performance.md](performance.md) — cache configuration effects
- [migrations-seeding.md](migrations-seeding.md) — table name config in migrations
- [backward-compatibility.md](backward-compatibility.md) — config key stability as public API
