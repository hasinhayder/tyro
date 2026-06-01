# Permission Resolution Rules

## Why It Matters

Permission resolution in Tyro follows a deterministic three-tier pipeline implemented in the `can()` method of `HasTyroRoles` (`src/Concerns/HasTyroRoles.php:123-132`). The pipeline is: (1) check if the ability is a string matching a privilege slug, (2) check if the ability is a string matching a role slug, (3) fall through to Laravel's `Gate::check()`. Because Tyro is additive-only (no deny model), conflict resolution is straightforward: the first match in this chain wins, and there is no mechanism for explicit denial. The `*` wildcard slug acts as a universal allow in both `hasRole()` and `hasPrivilege()` — if a user has `*` in their role slugs or privilege slugs, all checks pass. This design prioritizes performance (early exit on string match) over flexibility (no deny rules), which is appropriate for a framework targeting mid-to-large Laravel applications.

## Incorrect

```php
// INCORRECT: Expecting deny to override allow
$user->hasRole('admin'); // true
$user->hasPrivilege('super-admin'); // false
// No way to say "admin CANNOT super-admin even if a privilege grants it"
// Because Tyro is additive-only — privilege is checked first
```

```php
// INCORRECT: Checking Gate before Tyro
if (Gate::forUser($user)->check('edit-posts')) {
    // This bypasses Tyro resolution entirely
    // If the Gate check fails, the user might still have the privilege
}
```

```php
// INCORRECT: Using can() with non-string abilities incorrectly
$user->can($post, 'update');
// can($ability, $arguments) — if $ability is not a string,
// only Tier 3 (Gate) runs. Role/privilege checks are skipped.
```

```php
// INCORRECT: Assuming can() checks all arguments for role/privilege matches
$user->can('update', $post);
// $ability is 'update' (string), but it doesn't match any role/privilege slug
// Falls through to Gate::check('update', $post)
// This is correct behavior, but authors should NOT expect role checks on non-slug abilities
```

## Correct

```php
// CORRECT: Understanding the three-tier resolution pipeline
// From HasTyroRoles trait:
public function can($ability, $arguments = []): bool {
    // Tier 1: Direct privilege slug match
    if (is_string($ability) && $this->hasPrivilege($ability)) {
        return true;
    }
    // Tier 2: Direct role slug match
    if (is_string($ability) && $this->hasRole($ability)) {
        return true;
    }
    // Tier 3: Fallback to Laravel Gate
    return Gate::forUser($this)->check($ability, $arguments);
}
```

```php
// CORRECT: Tracing a full resolution example
$user->can('edit-posts');
// Pipeline:
// 1. Is 'edit-posts' a string? Yes.
//    hasPrivilege('edit-posts'):
//      → tyroPrivilegeSlugs() returns ['view-posts', 'review-posts']
//      → in_array('edit-posts', ...) → false
//      → in_array('*', ...) → false
//      → returns false
// 2. Is 'edit-posts' a string? Yes.
//    hasRole('edit-posts'):
//      → tyroRoleSlugs() returns ['editor']
//      → in_array('edit-posts', ...) → false
//      → in_array('*', ...) → false
//      → returns false
// 3. Gate::forUser($user)->check('edit-posts')
//      → Checks Gate policies/abilities registered via AuthServiceProvider
//      → Returns true if Gate allows, false otherwise
// Result: whatever Gate decides
```

```php
// CORRECT: Wildcard '*' resolution
$user->can('anything');
// Pipeline:
// 1. hasPrivilege('anything'):
//      → tyroPrivilegeSlugs() returns ['*', 'edit-posts']
//      → in_array('anything', ...) → false
//      → in_array('*', ...) → TRUE
//      → returns true
// 2. Returns true immediately — Tier 2 and 3 never execute
```

```php
// CORRECT: Middleware resolution path
// EnsureTyroPrivilege resolves privileges in handle():
$ownedPrivileges = $this->resolvePrivilegeSlugs($user);

foreach ($required as $privilege) {
    if (! $ownedPrivileges->contains(fn ($slug) => $slug === $privilege || $slug === '*')) {
        throw new AuthorizationException('ACCESS DENIED.');
    }
}

// EnsureTyroRole does the same for roles:
$ownedRoles = $this->resolveRoleSlugs($request, $user);

foreach ($required as $role) {
    if (! $this->matchesRole($ownedRoles, $role)) {
        throw new AuthorizationException('ACCESS DENIED.');
    }
}

// matchesRole() handles '*' in both directions:
// - required role is '*' → always true
// - user has '*' in owned roles → always true
```

```php
// CORRECT: hasPrivileges (all required) resolution
$user->hasPrivileges(['edit-posts', 'view-posts']);
// 1. Gets $userPrivileges = tyroPrivilegeSlugs()
// 2. Checks in_array('*', $userPrivileges) → if true, return true immediately
// 3. array_diff(['edit-posts', 'view-posts'], $userPrivileges)
// 4. Returns true if diff is empty

// hasAnyPrivilege (any required) — checked via hasPrivilege in middleware:
// Required: ['admin', 'super-admin']
// Owned: ['editor']
// contains() checks each required against owned with slug === $privilege || slug === '*'
```

```php
// CORRECT: Resolution order matters for performance
// Fast path: check privileges first (smaller set in most cases)
// Medium path: check roles (larger set)
// Slow path: Gate fallback (may involve policy resolution, closure execution)

// To optimize, prefer privilege checks for fine-grained access control:
$user->can('edit-posts'); // Fast if privilege exists

// And use Gate policies for instance-level authorization:
$user->can('update', $post); // Non-string ability → direct Gate
```

## Notes

- Because Tyro is additive-only (no deny model), there is no conflict resolution beyond "first match wins" in the pipeline order. If a need for deny arises, use Laravel Gate policies (Tier 3) which support deny via `Gate::before()` or policy `deny()` methods.
- The `*` wildcard must be explicitly stored as a role slug or privilege slug. It is not automatically granted to any role.
- `can()` only checks string abilities against role/privilege slugs. Non-string abilities (objects, arrays) always fall through to Gate.
- The middleware classes (`EnsureTyroRole`, `EnsureTyroPrivilege`, etc.) do NOT call `can()`. They directly resolve slugs via `tyroRoleSlugs()` / `tyroPrivilegeSlugs()` for consistent behavior without Gate interference.
- Blade directives similarly use the underlying trait methods directly, not `can()`.
- For best performance with large user bases, ensure `config('tyro.cache.enabled')` is true and configure an appropriate cache store (Redis recommended).
- Runtime cache versioning (`TyroCache::runtimeVersion()`) is bumped on every cache flush operation via `TyroCache::bumpRuntimeVersion()`. This ensures in-memory cache within the same request stays consistent.
- The `*` check in `hasRole()` and `hasPrivilege()` uses strict `in_array()` — slug values must be exactly `*` (string asterisk).

## Cross References

- [authorization.md](authorization.md) — Authorization entry points and middleware
- [inheritance.md](inheritance.md) — How privileges inherit through roles
- [privileges.md](privileges.md) — Privilege role in resolution
- [roles.md](roles.md) — Role role in resolution
- [caching.md](caching.md) — Caching strategy for resolution performance
- [performance.md](performance.md) — Resolution performance considerations
