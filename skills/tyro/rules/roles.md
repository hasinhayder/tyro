# Role Rules

## Why It Matters

Roles are the fundamental grouping mechanism in Tyro's RBAC model. The `Role` model (`src/Models/Role.php`) bridges users and privileges: users are assigned to roles, and privileges are attached to roles. The `UserRole` pivot model (`src/Models/UserRole.php`) auto-invalidates `TyroCache` on any assignment or removal. The `RoleObserver` (`src/Models/Observers/RoleObserver.php`) audits all role lifecycle events (`role.created`, `role.updated`, `role.deleted`). Two slugs are protected by convention: `admin` and `super-admin`, as defined in `config('tyro.protected_role_slugs')`. These protected slugs should not be deleted or reassigned through normal operations. Role users are managed through the `roles()` BelongsToMany relationship on the user model (added by the `HasTyroRoles` trait) and through `Role::users()` which dynamically resolves the user model class from config.

## Incorrect

```php
// INCORRECT: Directly manipulating the pivot table
DB::table('user_roles')->insert([
    'user_id' => 1,
    'role_id' => 2,
]);
// Bypasses cache invalidation and audit logging!
```

```php
// INCORRECT: Modifying or deleting protected roles
$adminRole = Role::where('slug', 'admin')->first();
$adminRole->delete(); // Should not be allowed for protected roles

$adminRole->update(['slug' => 'super-duper-admin']); // Breaks convention
```

```php
// INCORRECT: Creating roles without checking for duplicates
Role::create(['name' => 'Admin', 'slug' => 'admin']);
Role::create(['name' => 'Admin Two', 'slug' => 'admin']); // Duplicate slug acceptable by DB
```

```php
// INCORRECT: Assigning the same role multiple times
$user->assignRole($role);
$user->assignRole($role); // syncWithoutDetaching prevents duplicates, but still called twice
```

## Correct

```php
// CORRECT: Using the HasTyroRoles trait for role assignment
$user = User::find(1);
$role = Role::where('slug', 'editor')->first();

$user->assignRole($role);
// 1. Attaches via syncWithoutDetaching
// 2. Flushes TyroCache for this user
// 3. Flushes runtime in-memory cache
// 4. Logs 'role.assigned' audit event
```

```php
// CORRECT: Using the trait for role removal
$user->removeRole($role);
// 1. Detaches the role
// 2. Flushes TyroCache for this user
// 3. Flushes runtime in-memory cache
// 4. Logs 'role.removed' audit event
```

```php
// CORRECT: Checking role membership
$user->hasRole('admin');         // Single role
$user->hasAnyRole(['admin', 'super-admin']); // Any of these roles
$user->hasRoles(['editor', 'reviewer']);     // All of these roles
```

```php
// CORRECT: Getting all role slugs
$slugs = $user->tyroRoleSlugs();
// Returns: ['admin', 'editor']
// Uses two-tier caching: runtime in-memory + configurable cache store
```

```php
// CORRECT: Using Artisan commands for role management
// Create:  php artisan tyro:role-create
// List:    php artisan tyro:role-list
// Update:  php artisan tyro:role-update
// Delete:  php artisan tyro:role-delete (handle protected slugs)
// Assign:  php artisan tyro:role-assign
// Users:   php artisan tyro:role-users
```

```php
// CORRECT: Checking protected role slugs before deletion
$protectedSlugs = config('tyro.protected_role_slugs', ['admin', 'super-admin']);
if (in_array($role->slug, $protectedSlugs)) {
    throw new \RuntimeException("Cannot delete protected role: {$role->slug}");
}
$role->delete();
```

## Notes

- The `Role` model has `fillable: ['name', 'slug']` and `hidden: ['pivot', 'created_at', 'updated_at']`.
- Table name is configurable via `config('tyro.tables.roles')`, currently hardcoded to `'roles'` in the model.
- Pivot table `config('tyro.tables.pivot')` defaults to `'user_roles'`.
- The `UserRole` pivot has `fillable: ['user_id', 'role_id']` and timestamps enabled.
- `Role::users()` dynamically resolves the user model from `config('tyro.models.user')` with fallback to `config('auth.providers.users.model')` and then `'App\\Models\\User'`.
- `Role::privileges()` is a BelongsToMany relationship through `config('tyro.tables.role_privilege')` using `RolePrivilege` pivot.
- Protected slugs (`admin`, `super-admin`) are defined in `config('tyro.protected_role_slugs')` — the system should check this list before delete/rename operations.
- When a role is deleted, pivot records in `user_roles` and `privilege_role` are cascade-deleted (or should be handled by application logic).
- The `RoleObserver` logs `role.created`, `role.updated`, `role.deleted` audit events.
- `Role::hasPrivilege(string $slug)` checks if the role has a specific privilege, using eager-loaded data if available to avoid N+1 queries.

## Cross References

- [permissions.md](permissions.md) — How privileges attach to roles
- [privileges.md](privileges.md) — System-level privileges on roles
- [authorization.md](authorization.md) — Role-based authorization checks
- [inheritance.md](inheritance.md) — Privilege inheritance through role hierarchy
- [architecture.md](architecture.md) — Model architecture and pivot conventions
