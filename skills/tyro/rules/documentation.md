# Documentation

## Why It Matters

Documentation is a framework contract. Every public API — trait methods, static methods, Artisan command signatures, middleware aliases, Blade directives, config keys — must be documented so consumers can use Tyro correctly. The `tyro:sys-doc` command opens the GitHub documentation, `tyro:sys-about` prints a project summary, and `tyro:postman` opens the Postman collection. README.md serves as the primary entry point for new users, while CONTRIBUTING.md guides contributors.

## Incorrect

Documenting only the "happy path" without error scenarios:

```php
// Do NOT only show the success case in docs
// @hasrole('admin') — "This shows content for admins"
// Missing: what happens when user isn't authenticated? What happens on method missing?
```

Omitting config key documentation:

```php
// Do NOT add new config keys without adding them to the README and config file docblocks
// If 'tyro.new_feature' is added but undocumented, consumers won't know it exists
```

Writing documentation that doesn't match actual code behavior:

```php
// Do NOT document a method signature that differs from the implementation
// Doc says: hasRole(string $role): bool
// But if someone changes it to: hasRole(string ...$roles): bool
// This breaks callers who read the docs
```

## Correct

Maintain README.md with the following sections:
1. Introduction — what Tyro provides
2. Installation — composer require, service provider, publish commands
3. Configuration — all config keys with environment variable overrides
4. Usage — trait methods, middleware, Blade directives, Artisan commands
5. REST API — endpoint reference with request/response examples
6. Testing — how to run the test suite
7. Changelog — version history with breaking changes highlighted
8. Contributing — link to CONTRIBUTING.md

Document every Artisan command with its signature, aliases, arguments, options, and examples (from the codebase):

```php
// From DocCommand — opens documentation
protected $signature = 'tyro:sys-doc {--no-open : Only print the docs URL}';
protected $aliases = ['tyro:doc'];
protected $description = 'Open the Tyro documentation in your browser';

// From AboutCommand — prints project information
protected $signature = 'tyro:sys-about';
protected $aliases = ['tyro:about'];
protected $description = "Show Tyro's mission, version, and author details";
```

Document the Postman collection path (from PostmanCollectionCommand):

```php
// The Postman collection is available at:
// Tyro.postman_collection.json (project root)
// tyro:postman command opens it in the browser
// Or use --no-open to just print the path
```

Document Blade directives with usage examples matching the actual directive classes:

```php
// @hasrole('admin')
//   You are an admin!
// @endhasrole

// @hasanyrole('admin,editor')
//   You are an admin or editor!
// @endhasanyrole

// @hasroles('admin,editor,user')
//   You have ALL of these roles!
// @endhasroles

// @hasprivilege('report.generate')
//   You can generate reports!
// @endhasprivilege

// @hasanyprivilege('report.generate,users.manage')
//   You have at least one of these privileges!
// @endhasanyprivilege

// @hasprivileges('report.generate,users.manage')
//   You have ALL of these privileges!
// @endhasprivileges

// @usercan('report.generate')
//   You can generate reports (via can())!
// @endusercan
```

Document middleware usage with route examples:

```php
// Route-level middleware:
Route::middleware('role:admin')->group(function () {
    // Only users with 'admin' role can access
});

Route::middleware('roles:admin,editor')->group(function () {
    // Users with 'admin' OR 'editor' role can access
});

Route::middleware('privilege:report.generate')->group(function () {
    // Only users with 'report.generate' privilege can access
});

Route::middleware('privileges:report.generate,users.manage')->group(function () {
    // Users with 'report.generate' OR 'users.manage' privilege can access
});

// Controller-level with custom middleware:
Route::get('/admin/reports', [ReportController::class, 'index'])
    ->middleware('role:super-admin');
```

Document the TyroAudit event system for consumers:

```php
// Filter audit logs by event type:
use HasinHayder\Tyro\Models\AuditLog;

$suspensions = AuditLog::where('event', 'user.suspended')->get();

$roleAssignments = AuditLog::whereIn('event', [
    'role.assigned',
    'role.removed',
])->get();

// Use the summary accessor for human-readable output:
foreach ($auditLogs as $log) {
    echo $log->summary; // "Assigned role \"admin\" to user #42"
}
```

Document the TyroCache exposed methods for consumers:

```php
use HasinHayder\Tyro\Support\TyroCache;

// Cache invalidation for specific user
TyroCache::forgetUser($userId);

// Cache invalidation for all users of a role
$role = Role::where('slug', 'editor')->first();
TyroCache::forgetUsersByRole($role);

// Cache invalidation for all users with a privilege
$privilege = Privilege::where('slug', 'report.generate')->first();
TyroCache::forgetUsersByPrivilege($privilege);
```

Document migration publishing:

```bash
# Publish all migrations to database/migrations/
php artisan vendor:publish --tag=tyro-migrations

# Publish configuration to config/tyro.php
php artisan vendor:publish --tag=tyro-config

# Publish seeders and factories
php artisan vendor:publish --tag=tyro-database

# Publish resource assets (views, etc.)
php artisan vendor:publish --tag=tyro-assets
```

Document CONTRIBUTING.md guidelines:
1. Run `vendor/bin/pest` before submitting changes
2. Run `vendor/bin/pint --test` for code style
3. Follow the naming conventions in naming-conventions.md
4. Write tests for all new features (see testing.md)
5. Maintain backward compatibility (see backward-compatibility.md)
6. Document all new public APIs in README.md

## Notes

- The `tyro:sys-about` command reads `config('tyro.version')` — the version should be set in `config/tyro.php` when releasing.
- PHPDoc blocks on all public methods should include `@param`, `@return`, and `@throws` annotations.
- New features should include `@since 1.x.0` in their docblocks.
- Deprecated methods should include `@deprecated Use X instead. Will be removed in v2.0.`.
- The Postman collection (`Tyro.postman_collection.json`) must be updated when API endpoints change.
- Config file docblocks in `config/tyro.php` explain each key's purpose and environment variable override.
- When adding a new Artisan command, register it in `TyroServiceProvider::registerCommands()` and document it in README.md. The `tyro:sys-about` output should be updated if the feature is significant.

## Cross References

- [backward-compatibility.md](backward-compatibility.md) — documenting deprecations
- [configuration.md](configuration.md) — config key documentation
- [testing.md](testing.md) — documentation testing requirements
- [extensibility.md](extensibility.md) — documenting extension points
