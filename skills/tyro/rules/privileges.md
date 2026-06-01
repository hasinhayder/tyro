# Privilege Rules

## Why It Matters

Privileges in Tyro are the atomic unit of authorization — they represent discrete, indivisible access rights. Unlike regular permissions (which are also modeled as Privileges), system-level privileges grant elevated or break-glass access that bypasses normal authorization boundaries. Examples include `super-admin`, `bypass-tenant-restrictions`, `impersonate-users`, `purge-audit-logs`, and `manage-system-config`. These privileges should be carefully controlled, always audited, and never granted to regular user roles. The `Privilege` model (`src/Models/Privilege.php`) is intentionally simple — it is a slim model with `name`, `slug`, and `description` fields. All authorization logic resides in `HasTyroRoles` trait methods (`hasPrivilege`, `hasPrivileges`) and the middleware classes. The `PrivilegeObserver` (`src/Models/Observers/PrivilegeObserver.php`) ensures every privilege lifecycle event is immutable-audited.

## Incorrect

```php
// INCORRECT: Granting system-level privileges to broad roles
$viewerRole->attachPrivilege($superAdminPrivilege);
// Now every viewer user has super-admin access!
```

```php
// INCORRECT: Using privilege slugs without documenting their system impact
Privilege::create(['slug' => 'super-admin', 'name' => 'Super Admin']);
// No description, no review process — this grants complete system access
```

```php
// INCORRECT: Checking privileges with string concatenation or dynamic slugs
$user->hasPrivilege('tenant-' . $tenantId . '-access'); // Unpredictable slugs
```

```php
// INCORRECT: Creating application-level permissions as Privileges when they should be Gates
Privilege::create(['slug' => 'post-belongs-to-user-42', 'name' => 'Post 42']);
// Privileges should be role-based, not instance-based
```

## Correct

```php
// CORRECT: Defining system-level privileges with clear documentation
Privilege::create([
    'name' => 'Super Administrator',
    'slug' => 'super-admin',
    'description' => 'Grants unrestricted system-wide access. Only assign to system administrators.',
]);

Privilege::create([
    'name' => 'Bypass Tenant Restrictions',
    'slug' => 'bypass-tenant-restrictions',
    'description' => 'Allows cross-tenant data access. For support engineers only.',
]);

Privilege::create([
    'name' => 'Impersonate Users',
    'slug' => 'impersonate-users',
    'description' => 'Allows logging in as any user. Strictly for debugging.',
]);
```

```php
// CORRECT: Restricting system-level privileges to admin-only roles
$superAdminRole = Role::where('slug', 'super-admin')->first();
$superAdminRole->attachPrivilege(Privilege::where('slug', 'super-admin')->first());
$superAdminRole->attachPrivilege(Privilege::where('slug', 'bypass-tenant-restrictions')->first());

$supportRole = Role::where('slug', 'support')->first();
$supportRole->attachPrivilege(Privilege::where('slug', 'bypass-tenant-restrictions')->first());
// Support role gets bypass-tenant-restrictions but NOT super-admin
```

```php
// CORRECT: Checking system-level privileges
if ($user->hasPrivilege('super-admin')) {
    // Full system access
}

if ($user->hasPrivilege('bypass-tenant-restrictions')) {
    // Cross-tenant operations
}

// Using the three-tier can() method for privilege checks
if ($user->can('super-admin')) {
    // Resolved through can()->hasPrivilege()->in_array('*') fallback
}

// Using middleware
Route::middleware('privilege:super-admin')->group(function () {
    Route::get('/system/config', [SystemConfigController::class, 'index']);
});
```

```php
// CORRECT: Auditing privilege usage
// Automatic audit via PrivilegeObserver:
// - 'privilege.created' — when a privilege is created
// - 'privilege.updated' — when a privilege name/slug/description changes
// - 'privilege.deleted' — when a privilege is removed

// Automatic audit via TyroAudit in Role model:
// - 'privilege.attached' — when attached to a role (via Role::attachPrivilege)
// - 'privilege.detached' — when detached from a role (via Role::detachPrivilege)
```

```php
// CORRECT: Using wildcard '*' for break-glass access
// If a user has '*' in their privilege slugs, all privilege checks pass
$user->hasPrivilege('anything-at-all'); // true if '*' is present
```

## Notes

- `hasPrivilege()` checks for an exact slug match OR the wildcard `'*'`. The `*` wildcard grants all privileges and should only be used for super-admin scenarios.
- Privilege slugs are resolved through `tyroPrivilegeSlugs()`, which flattens privileges from all user roles, deduplicates, and caches results with two-tier caching (runtime in-memory versioning + configurable cache store with 300s default TTL).
- The `Privilege` model's table is configurable via `config('tyro.tables.privileges')`, defaulting to `'privileges'`.
- The `RolePrivilege` pivot table is configurable via `config('tyro.tables.role_privilege')`, defaulting to `'privilege_role'`.
- The `Role::hasPrivilege()` method checks if a specific role holds a privilege — useful before attaching to avoid duplicates.
- Break-glass access patterns: store a `*` privilege slug in a special role (e.g., `super-admin`) that grants universal access when the user is assigned that role.
- When a `Privilege` is deleted, the `PrivilegeObserver` logs the event but does NOT cascade to roles. The `RolePrivilege` pivot model handles cache invalidation automatically through its `deleted` hook.

## Cross References

- [permissions.md](permissions.md) — General permission modeling as privileges
- [roles.md](roles.md) — Role-privilege attachment via Role model
- [inheritance.md](inheritance.md) — How privileges inherit through roles
- [permission-resolution.md](permission-resolution.md) — Resolution pipeline and wildcard handling
- [security.md](security.md) — Privilege escalation prevention
