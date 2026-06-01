# Caching

## Why It Matters

Tyro's authorization checks (role and privilege lookups) run on nearly every request. Without caching, each check queries the database through multiple joins (user → roles → privileges). Tyro implements a two-tier caching system: in-memory runtime versioning for per-request performance, plus a configurable cache store (Redis, file, database) for cross-request persistence. Cache invalidation is reactive — any role/privilege assignment change must invalidate affected users' cached data. Stale permissions lead to security bypasses (user retains a removed role) or false denials (user lacks a newly assigned role). Cache stampedes from broad invalidations degrade performance.

## Incorrect

```php
// Bypassing cache — always querying the database
public function hasPrivilege(string $ability): bool {
    $privileges = DB::table('privileges')
        ->join('privilege_role', ...)
        ->join('user_roles', ...)
        ->where('user_roles.user_id', $this->id)
        ->pluck('slug');
    return in_array($ability, $privileges->toArray());
    // Every call hits the DB — no caching at all
}

// Caching without invalidation
Cache::rememberForever('user:roles:'.$userId, function () {
    return $user->roles()->pluck('slug');
});
// When the role changes, the cache is never cleared

// Over-invalidation — flushing all users on every minor change
public function assignRole(Role $role): void {
    $this->roles()->syncWithoutDetaching($role);
    TyroCache::forgetAllUsersWithRoles(); // Stampede!
}

// Ignoring runtime version staleness
public function hasRole(string $role): bool {
    return in_array($role, $this->tyroRoleSlugsCache);
    // May return stale data if runtime version was bumped
}
```

## Correct

```php
// Two-tier: runtime version check in the trait
public function tyroRoleSlugs(): array {
    $userId = $this->getKey();
    if ($userId === null) {
        return [];
    }
    $runtimeVersion = TyroCache::runtimeVersion($userId);
    if ($this->tyroRoleSlugsCache !== null && $this->tyroRoleSlugsVersion === $runtimeVersion) {
        return $this->tyroRoleSlugsCache;
    }
    $slugs = $this->getTyroSlugsData($userId, 'roles');
    $this->tyroRoleSlugsCache = $slugs;
    $this->tyroRoleSlugsVersion = $runtimeVersion;
    return $slugs;
}

// Cache store methods with TTL and store config
public static function rememberRoleSlugs($userId, callable $resolver): array {
    if (! static::enabled() || ! $userId) {
        return $resolver();
    }
    return static::remember(static::rolesKey($userId), $resolver);
}

// Precise invalidation — bust only affected users
public function assignRole(Role $role): void {
    $this->roles()->syncWithoutDetaching($role);
    TyroCache::forgetUser($this->getKey()); // Only this user
    $this->flushTyroRuntimeCache();
}

// Cascade invalidation by role — all users with this role
public function detachPrivilege(Privilege $privilege): void {
    $this->privileges()->detach($privilege);
    TyroCache::forgetUsersByRole($this);
}

// Cascade invalidation by privilege — all users who have roles with this privilege
public static function forgetUsersByPrivilege(Privilege $privilege): void {
    $roleIds = DB::table(config('tyro.tables.role_privilege', 'privilege_role'))
        ->where('privilege_id', $privilege->getKey())
        ->pluck('role_id');
    static::forgetUsersByRoleIds($roleIds);
}

// Pivot model auto-invalidation
class UserRole extends Pivot {
    protected static function booted(): void {
        static::saved(function (self $pivot): void {
            TyroCache::forgetUser($pivot->user_id);
        });
        static::deleted(function (self $pivot): void {
            TyroCache::forgetUser($pivot->user_id);
        });
    }
}

// Runtime version bumping — forces re-fetch on next request
protected static function bumpRuntimeVersion($user): void {
    $key = $user instanceof Authenticatable ? $user->getAuthIdentifier() : $user;
    static::$runtimeVersions[$key] = (static::$runtimeVersions[$key] ?? 0) + 1;
}

// Config-driven cache behavior
// config/tyro.php:
'cache' => [
    'enabled' => env('TYRO_CACHE_ENABLED', true),
    'store' => env('TYRO_CACHE_STORE'),     // null = default store
    'ttl' => env('TYRO_CACHE_TTL', 300),   // seconds, null = forever
],
```

## Notes

- Cache key patterns: `tyro:user-{userId}:roles` and `tyro:user-{userId}:privileges` — defined in `TyroCache::rolesKey()` and `TyroCache::privilegesKey()`.
- Runtime versioning: an in-memory `$runtimeVersions` array (per-request) is incremented each time `forgetUser()` is called. The trait checks this version against its cached version before returning cached slugs.
- `TyroCache::forgetUser($user)` is the primary invalidation call — it deletes both role and privilege cache entries for that user and bumps the runtime version.
- `TyroCache::forgetUsersByRole(Role $role)` finds all users with that role and forgets each one.
- `TyroCache::forgetUsersByPrivilege(Privilege $privilege)` finds all roles with that privilege, then all users with those roles, then forgets each user.
- `TyroCache::forgetAllUsersWithRoles()` is used sparingly (seed, flush, purge commands) — it queries all user IDs from the pivot table and forgets each one.
- Pivot model `booted()` methods (`UserRole`, `RolePrivilege`) automatically fire cache invalidation on `saved` and `deleted`.
- When cache is disabled (`config('tyro.cache.enabled')` = false), `rememberRoleSlugs` and `rememberPrivilegeSlugs` skip the cache store and call the resolver directly. Runtime versioning still works for per-request deduplication.
- Cache TTL of `null` means `rememberForever()` — entries never expire. A positive integer sets seconds. Zero or negative falls back to forever.
- The `store` config selects a specific cache driver. If null, the default Laravel cache store is used.
- Observers (`RoleObserver`, `PrivilegeObserver`) do not handle cache invalidation — that is done at the pivot level and in the model methods directly.

## Cross References

- policies.md (cache affects role/privilege check speed)
- multi-tenancy.md (extending cache keys for tenant isolation)
- naming-conventions.md (cache key patterns)
