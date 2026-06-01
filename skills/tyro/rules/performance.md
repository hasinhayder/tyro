# Performance

## Why It Matters

Authorization checks happen on nearly every authenticated request. Without optimization, each check can trigger multiple database queries (load user, load roles, load privileges for each role, check suspension). Tyro uses a two-tier caching system — runtime versioning in memory plus configurable cache store — to resolve role and privilege slugs in a single bulk query with no N+1 issues. Middleware caches resolved slugs on the request object to avoid re-resolution within the same request lifecycle.

## Incorrect

Loading roles and privileges separately per check:

```php
// Do NOT — this triggers N+1 queries on every authorization call
public function canAccess(string $resource): bool {
    $roles = $this->roles()->get();              // 1 query
    foreach ($roles as $role) {
        $privileges = $role->privileges()->get(); // N queries
        foreach ($privileges as $privilege) {
            if ($privilege->slug === $resource) {
                return true;
            }
        }
    }
    return false;
}
```

Calling `fresh()` repeatedly inside authorization checks:

```php
// Do NOT — fresh() bypasses all caching
public function hasRole(string $slug): bool {
    return $this->fresh()->roles->contains('slug', $slug);
}
```

Manually resolving slugs on every request without caching:

```php
// Do NOT — slugs are fetched from DB every check
$user = auth()->user();
$roleSlugs = $user->roles()->pluck('slug')->all();
$privilegeSlugs = $user->roles()->with('privileges:id,slug')
    ->get()
    ->flatMap(fn ($r) => $r->privileges)
    ->pluck('slug')
    ->all();
```

## Correct

Use `tyroRoleSlugs()` and `tyroPrivilegeSlugs()` which leverage two-tier caching (src/Concerns/HasTyroRoles.php):

```php
// Runtime-level caching (within the same request)
public function tyroRoleSlugs(): array {
    $userId = $this->getKey();
    if ($userId === null) {
        return [];
    }

    $runtimeVersion = TyroCache::runtimeVersion($userId);
    if ($this->tyroRoleSlugsCache !== null && $this->tyroRoleSlugsVersion === $runtimeVersion) {
        return $this->tyroRoleSlugsCache;  // Zero queries — in-memory hit
    }

    $slugs = $this->getTyroSlugsData($userId, 'roles');

    $this->tyroRoleSlugsCache = $slugs;
    $this->tyroRoleSlugsVersion = $runtimeVersion;

    return $slugs;
}
```

The `getTyroSlugsData()` method uses bulk queries and the TyroCache store (src/Concerns/HasTyroRoles.php):

```php
protected function getTyroSlugsData(int $userId, string $type): array {
    if ($type === 'roles') {
        if ($this->relationLoaded('roles')) {
            $slugs = $this->roles->pluck('slug')->all();                // Already loaded
        } else {
            $slugs = TyroCache::rememberRoleSlugs($userId, function () {
                return $this->roles()->pluck('slug')->all();            // 1 bulk query
            });
        }
    } else {
        if ($this->relationLoaded('roles') && $this->roles->every(fn ($role) => $role->relationLoaded('privileges'))) {
            $slugs = $this->roles                                   // Already loaded
                ->flatMap(fn (Role $role) => $role->privileges)
                ->pluck('slug')
                ->all();
        } else {
            $slugs = TyroCache::rememberPrivilegeSlugs($userId, function () {
                return $this->roles()
                    ->with('privileges:id,slug')                    // 2 queries total
                    ->get()
                    ->flatMap(fn (Role $role) => $role->privileges)
                    ->pluck('slug')
                    ->all();
            });
        }
    }
    return array_values(array_unique(array_filter($slugs)));
}
```

Middleware caches resolved slugs on the request object (src/Http/Middleware/EnsureTyroRole.php):

```php
private function resolveRoleSlugs(Request $request, $user): Collection {
    if ($request->attributes->has('tyro.role_slugs')) {
        return $request->attributes->get('tyro.role_slugs');  // Already cached in this request
    }

    if (method_exists($user, 'tyroRoleSlugs')) {
        $slugs = collect($user->tyroRoleSlugs());
        $request->attributes->set('tyro.role_slugs', $slugs);  // Cache for later middleware
        return $slugs;
    }
    // ... fallback patterns
}
```

TyroCache two-tier system ensures stale reads are avoided (src/Support/TyroCache.php):

```php
// Tier 1: Cache store (Redis, Memcached, database, etc.)
// Tier 2: Runtime in-memory versioning

protected static array $runtimeVersions = [];

public static function rememberRoleSlugs($userId, callable $resolver): array {
    if (! static::enabled() || ! $userId) {
        return $resolver();
    }
    return static::remember(static::rolesKey($userId), $resolver);
}

// Cache invalidation bumps runtime version to force re-read
protected static function bumpRuntimeVersion($user): void {
    $key = $user instanceof Authenticatable ? $user->getAuthIdentifier() : $user;
    static::$runtimeVersions[$key] = (static::$runtimeVersions[$key] ?? 0) + 1;
}
```

Key cache configuration options for large-scale deployments:

```php
// config/tyro.php
'cache' => [
    'enabled' => true,
    'store' => env('TYRO_CACHE_STORE', 'redis'),  // Use Redis for multi-server deployments
    'ttl' => env('TYRO_CACHE_TTL', 300),          // 5 minutes default TTL
],
```

## Notes

- The two-tier system ensures: (1) within a single request, slugs are resolved at most once; (2) across requests, slugs are cached per the configured TTL; (3) on any role/privilege assignment change, the runtime version is bumped and the cache store is flushed for that user.
- N+1 prevention in `privileges()` method (src/Concerns/HasTyroRoles.php:91): if roles are already loaded but privileges are not, it lazy-loads privileges in a single query via `$roles->load('privileges')`.
- The wildcard slug `'*'` is short-circuit evaluated — if a user has `'*'` as a role or privilege slug, all checks return true without iterating (see `hasRole`, `hasAnyRole`, `hasRoles`, `hasPrivileges`).
- In `EnsureAnyTyroPrivilege` and `EnsureTyroPrivilege` middleware, privilege slugs are resolved via `tyroPrivilegeSlugs()` which uses caching.
- Cache invalidation is granular: `forgetUser()` flushes a single user, `forgetUsersByRole()` flushes all users with a given role, `forgetUsersByPrivilege()` flushes all users who have a role that includes the privilege.
- For large-scale deployments, use a shared cache store (Redis) and ensure `TYRO_CACHE_STORE` is configured on all web servers.

## Cross References

- [caching.md](caching.md) — detailed cache invalidation strategy
- [configuration.md](configuration.md) — cache config keys and environment overrides
- [testing.md](testing.md) — cache invalidation test patterns
- [architecture.md](architecture.md) — TyroCache dependency injection
