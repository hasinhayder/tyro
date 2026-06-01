# Middleware Rules

## Why It Matters

Tyro ships 5 middleware classes that enforce authorization at the route level. The 4 auth middleware — `EnsureTyroRole` (alias `role`), `EnsureAnyTyroRole` (alias `roles`), `EnsureTyroPrivilege` (alias `privilege`), and `EnsureAnyTyroPrivilege` (alias `privileges`) — guard routes by checking the authenticated user's roles or privileges against a set of required slugs. The 5th middleware, `TyroLog` (alias `tyro.log`), is a debug-only request/response logger. All 5 are registered in `TyroServiceProvider::registerMiddleware()` using the Router's `aliasMiddleware()` method. The auth middleware throw a generic `AuthorizationException('ACCESS DENIED.')` to prevent information leaking about which specific role or privilege was missing. They support comma-separated values through the `normalize()` method and wildcard matching via `*` in `matchesRole()`. Role and privilege slugs are resolved once per request and cached in `$request->attributes` to avoid redundant database queries when multiple middleware are stacked.

## Incorrect

```php
// INCORRECT: Revealing which specific role/privilege was missing
throw new AuthorizationException('Missing role: admin');
// Information leakage — attacker learns the required role
```

```php
// INCORRECT: Registering middleware outside the ServiceProvider
// Don't do this in app/Http/Kernel.php:
protected $routeMiddleware = [
    'role' => \HasinHayder\Tyro\Http\Middleware\EnsureTyroRole::class,
];
// The ServiceProvider registers these automatically
```

```php
// INCORRECT: Skipping the user authentication check in middleware
public function handle(Request $request, Closure $next, string ...$roles) {
    // Missing: if (! $request->user()) throw ...
    $required = $this->normalize($roles);
    // Proceeds even when unauthenticated — throws confusing errors
}
```

```php
// INCORRECT: Re-resolving role slugs on every middleware in a stack
// Route::group(['middleware' => ['role:admin,editor', 'privilege:edit-posts']]);
// Each middleware independently queries the DB for role slugs
// Instead they should use request attributes as a shared cache
```

```php
// INCORRECT: Using the wrong "all required" vs "any required" middleware
// Route using role:admin,editor requires BOTH admin AND editor
// If you meant "admin OR editor", use roles:admin,editor instead
Route::get('/admin', fn() => 'ok')->middleware('role:admin,editor');
```

## Correct

```php
// CORRECT: Generic error message for all auth middleware
throw new AuthorizationException('ACCESS DENIED.');
// No information about which role/privilege was required or missing
```

```php
// CORRECT: Registration pattern in TyroServiceProvider
protected function registerMiddleware(): void {
    $router = $this->app['router'];
    $router->aliasMiddleware('tyro.log', TyroLog::class);
    $router->aliasMiddleware('privilege', EnsureTyroPrivilege::class);
    $router->aliasMiddleware('privileges', EnsureAnyTyroPrivilege::class);
    $router->aliasMiddleware('role', EnsureTyroRole::class);
    $router->aliasMiddleware('roles', EnsureAnyTyroRole::class);
}
```

```php
// CORRECT: All-required role middleware — user must have EVERY role
class EnsureTyroRole {
    public function handle(Request $request, Closure $next, string ...$roles) {
        $user = $request->user();
        if (! $user) {
            throw new AuthorizationException('ACCESS DENIED.');
        }
        $required = $this->normalize($roles);
        if ($required->isEmpty()) {
            return $next($request);
        }
        $ownedRoles = $this->resolveRoleSlugs($request, $user);
        foreach ($required as $role) {
            if (! $this->matchesRole($ownedRoles, $role)) {
                throw new AuthorizationException('ACCESS DENIED.');
            }
        }
        return $next($request);
    }
}
```

```php
// CORRECT: Any-required role middleware — user needs at least ONE
class EnsureAnyTyroRole {
    public function handle(Request $request, Closure $next, string ...$roles) {
        $user = $request->user();
        if (! $user) {
            throw new AuthorizationException('ACCESS DENIED.');
        }
        $required = $this->normalize($roles);
        if ($required->isEmpty()) {
            return $next($request);
        }
        $ownedRoles = $this->resolveRoleSlugs($request, $user);
        $hasRole = $required->contains(fn ($role) => $this->matchesRole($ownedRoles, $role));
        if (! $hasRole) {
            throw new AuthorizationException('ACCESS DENIED.');
        }
        return $next($request);
    }
}
```

```php
// CORRECT: Request-attribute caching avoids redundant DB queries
private function resolveRoleSlugs(Request $request, $user): Collection {
    if ($request->attributes->has('tyro.role_slugs')) {
        return $request->attributes->get('tyro.role_slugs');
    }
    // trait method, eager-loaded relation, fallback query
    if (method_exists($user, 'tyroRoleSlugs')) {
        $slugs = collect($user->tyroRoleSlugs());
        $request->attributes->set('tyro.role_slugs', $slugs);
        return $slugs;
    }
    if ($user->relationLoaded('roles')) {
        $slugs = $user->roles->pluck('slug')->filter()->unique()->values();
        $request->attributes->set('tyro.role_slugs', $slugs);
        return $slugs;
    }
    if (method_exists($user, 'roles')) {
        $slugs = $user->roles()->select('slug')->pluck('slug')->filter()->unique()->values();
        $request->attributes->set('tyro.role_slugs', $slugs);
        return $slugs;
    }
    $empty = collect();
    $request->attributes->set('tyro.role_slugs', $empty);
    return $empty;
}
```

```php
// CORRECT: Privlege resolution with request caching
// EnsureTyroPrivilege and EnsureAnyTyroPrivilege use resolvePrivilegeSlugs()
private function resolvePrivilegeSlugs($user): Collection {
    if (method_exists($user, 'tyroPrivilegeSlugs')) {
        return collect($user->tyroPrivilegeSlugs());
    }
    if (method_exists($user, 'privileges')) {
        $privileges = $user->privileges();
        if ($privileges instanceof Collection) {
            return $privileges->pluck('slug')->filter()->unique()->values();
        }
    }
    return collect();
}
```

```php
// CORRECT: The normalize() method handles comma-separated values
private function normalize(array $roles): Collection {
    return collect($roles)
        ->flatMap(function ($chunk) {
            $parts = is_string($chunk) ? explode(',', $chunk) : (array) $chunk;
            return collect($parts)->map(fn ($part) => trim((string) $part));
        })
        ->filter()
        ->unique()
        ->values();
}
// This allows: 'role:admin,editor' to be equivalent to 'role:admin,role:editor'
```

```php
// CORRECT: Wildcard matching in matchesRole()
private function matchesRole(Collection $ownedRoles, string $requiredRole): bool {
    if ($requiredRole === '*') {
        return true; // Middleware explicitly allows all
    }
    return $ownedRoles->contains(function ($slug) use ($requiredRole) {
        return $slug === $requiredRole || $slug === '*';
    });
}
// The wildcard checks both directions:
// 1. requiredRole === '*' — middleware allows everyone
// 2. ownedRoles contains '*' — user has blanket access
```

```php
// CORRECT: TyroLog middleware for debug request/response logging
class TyroLog {
    public function handle(Request $request, Closure $next) {
        return $next($request);
    }

    public function terminate($request, $response): void {
        if (! config('app.debug', false)) {
            return; // Only logs when APP_DEBUG=true
        }
        Log::info(str_repeat('=', 80));
        Log::debug('tyro.route', ['route' => optional($request->route())->uri()]);
        Log::debug('tyro.headers', $request->headers->all());
        Log::debug('tyro.request', $request->all());
        Log::debug('tyro.response', ['status' => $response->getStatusCode()]);
        Log::info(str_repeat('=', 80));
    }
}
// Applied via: Route::middleware('tyro.log')
// Logs route, headers, request payload, and response status
```

```php
// CORRECT: Middleware applied to routes
// Group applies BOTH admin AND super-admin role
Route::middleware('role:admin,super-admin')->group(function () {
    Route::get('sensitive-data', ...);
});

// User needs admin OR editor OR super-admin
Route::middleware('roles:admin,editor,super-admin')->group(function () {
    Route::get('moderate', ...);
});

// User needs ALL listed privileges
Route::middleware('privilege:edit-posts,delete-posts')->group(function () {
    Route::post('bulk-actions', ...);
});

// User needs ANY of the listed privileges
Route::middleware('privileges:view-reports,manage-users')->group(function () {
    Route::get('dashboard', ...);
});
```

## Notes

- All 4 auth middleware follow the same pattern: authenticate, normalize, resolve slugs, match, throw or pass.
- `normalize()` splits comma-separated strings so middleware parameters like `role:admin,editor` work the same as separate arguments.
- `resolveRoleSlugs()` checks request attributes first, then `tyroRoleSlugs()` trait method, then eager-loaded relation, then a fallback query, then empty collection. This ensures request-scoped caching across stacked middleware.
- `resolvePrivilegeSlugs()` checks `tyroPrivilegeSlugs()` trait method first, then the `privileges()` relation, then returns empty.
- The `*` wildcard in middleware context works bidirectionally: if the required argument is `*`, everyone passes. If the user has a `*` role/privilege slug, all checks pass.
- `AuthorizationException` from Illuminate\Auth\Access is caught by Laravel's exception handler and converts to a 403 HTTP response by default.
- `TyroLog` only logs when `APP_DEBUG=true` — production requests are never logged. It uses `terminate()` to log after the response is sent.
- Santern's `CheckForAnyAbility` and `CheckAbilities` are also aliased (`ability` and `abilities`) unless already registered by the application.
- The middleware aliases are: `role` (all required), `roles` (any required), `privilege` (all required), `privileges` (any required), `tyro.log` (debug logging).
- Middleware never queries the database more than once per request for role/privilege slugs thanks to request-attribute caching.
- Always pair `role`/`roles`/`privilege`/`privileges` middleware with an authentication middleware (`auth:sanctum` or similar) — the middleware itself only checks the authenticated user on the request.

## Cross References

- [security.md](security.md) — Generic error messages, information leakage prevention
- [authorization.md](authorization.md) — How middleware interacts with HasTyroRoles trait
- [permission-resolution.md](permission-resolution.md) — Slug resolution pipeline
- [caching.md](caching.md) — Request-attribute caching vs cache store
- [architecture.md](architecture.md) — ServiceProvider middleware registration
