# Inheritance Rules

## Why It Matters

Tyro implements a two-level inheritance model: privileges inherit through roles, and there is no role-to-role hierarchy. A user's effective authorization set is the union of all privileges from all roles they are assigned. The `RolePrivilege` pivot (`privilege_role`) is the sole mechanism for privilege inheritance — when a privilege is attached to a role, every user assigned to that role automatically inherits that privilege. The `hasPrivilege()` method in `HasTyroRoles` flattens all privileges from all user roles, deduplicates them, and checks for the requested slug. Because there is no deny model, all privilege assignments are additive — there is no way to exclude a specific privilege from a user who has a role that includes it. The `can()` method resolves through a three-tier chain: privilege → role → Gate fallback.

## Incorrect

```php
// INCORRECT: Assuming role hierarchy (Tyro has none)
if ($user->hasRole('super-admin')) {
    // Super-admin inherits from admin — NOT TRUE in Tyro base
    // Each role has explicitly attached privileges
}
```

```php
// INCORRECT: Expecting privilege denial/exclusion
$role = Role::where('slug', 'editor')->first();
$role->attachPrivilege($editPosts);
$role->detachPrivilege($deletePosts); // Removes it from role, not user-level deny
// No way to say "user has editor role but CANNOT delete posts"
```

```php
// INCORRECT: Manually merging privilege arrays
$userPrivileges = [];
foreach ($user->roles as $role) {
    $userPrivileges = array_merge($userPrivileges, $role->privileges->pluck('slug')->all());
}
// Use $user->tyroPrivilegeSlugs() instead — handles caching, dedup, and relation loading
```

```php
// INCORRECT: Assuming privilege changes propagate instantly without cache flush
$role->attachPrivilege($privilege);
// $user->hasPrivilege(...) may return stale data until cache invalidates
// TyroCache::forgetUsersByRole() handles this in attachPrivilege()
```

## Correct

```php
// CORRECT: Privilege inheritance through role membership
$editorRole = Role::create(['name' => 'Editor', 'slug' => 'editor']);
$editorRole->attachPrivilege(Privilege::where('slug', 'edit-posts')->first());
$editorRole->attachPrivilege(Privilege::where('slug', 'view-posts')->first());

$reviewerRole = Role::create(['name' => 'Reviewer', 'slug' => 'reviewer']);
$reviewerRole->attachPrivilege(Privilege::where('slug', 'review-posts')->first());
$reviewerRole->attachPrivilege(Privilege::where('slug', 'view-posts')->first());

$user->assignRole($editorRole);
$user->assignRole($reviewerRole);

// User's effective privilege set (union of both roles):
// - edit-posts   (from editor)
// - view-posts   (from both — deduplicated)
// - review-posts (from reviewer)

$user->hasPrivilege('edit-posts');   // true
$user->hasPrivilege('review-posts'); // true
$user->hasPrivilege('view-posts');   // true
$user->hasPrivilege('delete-posts'); // false
```

```php
// CORRECT: Three-tier resolution in can()
// Tier 1 — privilege check:
$user->hasPrivilege('edit-posts'); // Checks flattened privilege slugs

// Tier 2 — role check (only if string ability):
$user->hasRole('editor'); // Checks flattened role slugs

// Tier 3 — Gate fallback (if not a string or no match):
Gate::forUser($user)->check('edit-posts', $post);
```

```php
// CORRECT: Understanding the tyroPrivilegeSlugs() flattening logic
// From HasTyroRoles trait:
public function tyroPrivilegeSlugs(): array {
    // Two-tier cache check (runtime version + cache store)
    // Then resolves through getTyroSlugsData()
    // Which loads roles->privileges and plucks slugs
    // Returns: array_values(array_unique(array_filter($slugs)))
}
```

```php
// CORRECT: Cache invalidation on privilege inheritance changes
// When a privilege is attached to a role:
$role->attachPrivilege($privilege);
// Calls TyroCache::forgetUsersByRole($this)
// Which finds all users with this role and invalidates their cache

// When a privilege is detached from a role:
$role->detachPrivilege($privilege);
// Same cache invalidation pattern

// When a pivot record is saved/deleted (RolePrivilege booted):
static::saved(function (self $pivot): void {
    TyroCache::forgetUsersByRoleIds([$pivot->role_id]);
});
```

```php
// CORRECT: Resolving privilege inheritance programmatically
// The Role model provides helper methods:
$role->hasPrivilege('edit-posts');          // Single privilege check on role
$role->hasPrivileges(['edit-posts', 'view-posts']); // All privileges check on role

// The User model (via HasTyroRoles) provides:
$user->privileges();    // Collection of Privilege models (unique, deduplicated)
$user->tyroPrivilegeSlugs(); // Flat array of all privilege slugs (cached)
```

## Notes

- Tyro has no role hierarchy — roles are flat. There is no concept of role A inheriting from role B.
- All privilege inheritance is additive through role membership. There is no deny/exclude mechanism.
- The `*` wildcard slug acts as a universal grant: if a user has `*` in their role slugs or privilege slugs, any check passes.
- Runtime in-memory caching uses versioning (`TyroCache::runtimeVersion()`) so that within a single request, checks return fresh data after mutations.
- Cache store (configurable via `config('tyro.cache.store')`) persists between requests with a configurable TTL (default 300s).
- When building features that depend on inheritance, always use `$user->hasPrivilege()` or `$user->can()` rather than manually checking role assignments.
- For N+1 prevention, `tyroPrivilegeSlugs()` eagerly loads `roles.privileges` when the relation is not already loaded.

## Cross References

- [permission-resolution.md](permission-resolution.md) — Resolution pipeline details
- [roles.md](roles.md) — Role model and attachment
- [privileges.md](privileges.md) — Privilege model and system-level access
- [permissions.md](permissions.md) — Permission modeling
- [caching.md](caching.md) — Cache invalidation on inheritance changes
