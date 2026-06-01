# Permission Rules

## Why It Matters

In Tyro, "permissions" are modeled as **Privileges**. The `Privilege` model (`src/Models/Privilege.php`) is the sole entity representing a discrete action or access right. Privileges are never assigned directly to users — they are attached to Roles through the `RolePrivilege` pivot table (`privilege_role`), and users inherit them through their role assignments. This means permission management is always indirect: you manage what privileges a role has, and then assign users to roles. The `PrivilegeObserver` (`src/Models/Observers/PrivilegeObserver.php`) audits all privilege lifecycle events (`privilege.created`, `privilege.updated`, `privilege.deleted`). The `RolePrivilege` pivot auto-invalidates the `TyroCache` for all affected users whenever a privilege is attached to or detached from a role.

## Incorrect

```php
// INCORRECT: Creating privileges without a clear naming convention
Privilege::create(['name' => 'p1', 'slug' => 'abc123']);
Privilege::create(['name' => 'something', 'slug' => 'some_weird_slug']);
```

```php
// INCORRECT: Assigning privileges directly to users (no support for this)
$user->privileges()->attach($privilege); // Privilege model has no user relationship!
```

```php
// INCORRECT: Deleting a privilege that is attached to roles
$privilege = Privilege::findBySlug('edit-posts');
$privilege->delete(); // Orphaned pivot records! Use detach first.
```

```php
// INCORRECT: Duplicating privilege names
Privilege::create(['name' => 'Edit Posts', 'slug' => 'edit-posts']);
Privilege::create(['name' => 'Edit Posts', 'slug' => 'edit-posts-v2']); // Ambiguous
```

## Correct

```php
// CORRECT: Creating privileges with a consistent naming convention
Privilege::create([
    'name' => 'Edit Posts',
    'slug' => 'edit-posts',
    'description' => 'Allows the user to edit any post',
]);

Privilege::create([
    'name' => 'Delete Users',
    'slug' => 'delete-users',
    'description' => 'Allows the user to permanently delete user accounts',
]);

Privilege::create([
    'name' => 'Bypass Tenant Restrictions',
    'slug' => 'bypass-tenant-restrictions',
    'description' => 'Allows cross-tenant data access',
]);
```

```php
// CORRECT: Attaching privileges to roles through the Role model
$role = Role::where('slug', 'editor')->first();
$privilege = Privilege::where('slug', 'edit-posts')->first();

$role->attachPrivilege($privilege);
// Automatically:
// 1. Creates pivot record in privilege_role table
// 2. Flushes TyroCache for all users with this role
// 3. Logs 'privilege.attached' audit event
```

```php
// CORRECT: Detaching privileges and cleaning up
$role = Role::where('slug', 'editor')->first();
$privilege = Privilege::where('slug', 'edit-posts')->first();

// First detach from all roles
$role->detachPrivilege($privilege);

// Then safely delete
$privilege->delete();
// Audit: 'privilege.deleted' logged by PrivilegeObserver
```

```php
// CORRECT: Checking privileges through user
$user->hasPrivilege('edit-posts');     // Single privilege
$user->hasPrivileges(['edit-posts', 'delete-posts']); // All required
$user->can('edit-posts');               // Through three-tier resolution
```

```php
// CORRECT: Using Artisan commands for privilege management
// Create: php artisan tyro:privilege-create edit-posts "Edit Posts"
// Attach: php artisan tyro:privilege-attach editor edit-posts
// List:   php artisan tyro:privilege-list
// Purge orphaned: php artisan tyro:privilege-purge
```

## Notes

- Privilege slugs should use kebab-case (e.g., `edit-posts`, `manage-users`, `view-reports`).
- Privilege names should be human-readable (e.g., "Edit Posts", "Manage Users").
- The `description` field is optional but recommended for documenting what access the privilege grants.
- The `Privilege` model has `fillable: ['name', 'slug', 'description']` and `hidden: ['pivot', 'created_at', 'updated_at']`.
- Table name is configurable via `config('tyro.tables.privileges')`, defaulting to `'privileges'`.
- The pivot table `config('tyro.tables.role_privilege')` defaults to `'privilege_role'`.
- Orphaned privileges (not attached to any role) are inert — they grant nothing. Use `php artisan tyro:privilege-purge` to remove them.
- The `PrivilegeObserver` creates audit events (`privilege.created`, `privilege.updated`, `privilege.deleted`) only when `config('tyro.audit.enabled')` is true.
- The `RolePrivilege` pivot's `booted()` method registers `saved` and `deleted` hooks that call `TyroCache::forgetUsersByRoleIds()`.

## Cross References

- [privileges.md](privileges.md) — System-level and elevated privileges
- [roles.md](roles.md) — Role management and privilege attachment
- [inheritance.md](inheritance.md) — How privileges inherit through roles
- [permission-resolution.md](permission-resolution.md) — Resolution pipeline
- [naming-conventions.md](naming-conventions.md) — Slug and naming conventions
