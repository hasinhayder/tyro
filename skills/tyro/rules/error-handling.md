# Error Handling Rules

## Why It Matters

Tyro's error handling follows strict conventions across API controllers, middleware, Artisan commands, and internal support classes. API responses use a consistent JSON envelope `{error: int, message: string}` where `error: 1` indicates failure and `error: 0` indicates success. HTTP status codes are chosen deliberately: 401 for invalid credentials, 403 for authorization failures (thrown as `AuthorizationException`), 422 for validation/business rule violations, 423 for suspended users, 409 for conflicts (duplicate resources, last-admin protection), and 404 for not-found. Middleware uses a generic `AuthorizationException('ACCESS DENIED.')` to avoid leaking information about required roles or privileges. Artisan commands return `self::SUCCESS` (0) or `self::FAILURE` (1) exit codes with actionable error messages via `$this->error()`. Understanding how errors flow through the authorization stack — from middleware to trait to support class — is critical for debugging and for building consistent consuming applications.

## Incorrect

```php
// INCORRECT: Inconsistent API response shapes
return response()->json(['success' => false, 'msg' => 'error'], 400);
return response()->json(['error' => 'role not found'], 404);
return response()->json(['status' => 'error', 'details' => '...'], 500);
// Always use {error: 1|0, message: "..."}
```

```php
// INCORRECT: Revealing the specific missing role/privilege in middleware
throw new AuthorizationException('Missing role: admin');
// Information leakage — attacker learns which role gates the endpoint
```

```php
// INCORRECT: Using wrong HTTP status codes
return response(['error' => 1, 'message' => 'invalid credentials'], 403);
// Invalid credentials = 401 Unauthorized, not 403 Forbidden
```

```php
// INCORRECT: Abort helper without JSON response
abort(409, 'role already exists');
// In an API context, abort() may return HTML error pages
// Always return response()->json() explicitly
```

```php
// INCORRECT: Silent failures in commands with no error output
public function handle(): int {
    $role = $this->findRole($identifier);
    if (! $role) {
        return self::FAILURE; // No $this->error() message — operator is confused
    }
}
```

```php
// INCORRECT: Exposing internal implementation details in error messages
return response(['error' => 1, 'message' => 'User model does not have HasTyroRoles trait'], 422);
// Internal implementation details should not leak to API consumers
```

## Correct

```php
// CORRECT: Consistent JSON error envelope across all API responses
// Success:
['error' => 0, 'message' => 'role has been deleted']
// Failure:
['error' => 1, 'message' => 'role already exists']
// With data:
['error' => 0, 'message' => '...', 'data' => [...]]
```

```php
// CORRECT: HTTP status code conventions

// 401 — Invalid credentials (login failure)
return response(['error' => 1, 'message' => 'invalid credentials'], 401);

// 403 (AuthorizationException) — Generic access denied from middleware
throw new AuthorizationException('ACCESS DENIED.');
// Laravel converts this to a 403 response automatically

// 422 — Business rule violations (not validation errors)
return response(['error' => 1, 'message' => 'you cannot delete this role'], 422);

// 423 — User is suspended
return response(['error' => 1, 'message' => 'user is suspended'], 423);

// 409 — Resource conflicts
return response(['error' => 1, 'message' => 'role already exists'], 409);
return response(['error' => 1, 'message' => 'user already exists'], 409);
return response(['error' => 1, 'message' => 'Create another admin before deleting this only admin user'], 409);

// 404 — Resource not found
return response(['error' => 1, 'message' => 'role doesn\'t exist'], 404);
```

```php
// CORRECT: Middleware throws generic AuthorizationException for all failures
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
// The commented-out code shows the intentional choice:
// throw new AuthorizationException('Missing Tyro role: '.$role); // NEVER THIS
// throw new AuthorizationException('ACCESS DENIED.'); // ALWAYS THIS
```

```php
// CORRECT: Controller error response patterns

// Duplicate resource check
public function store(Request $request) {
    $data = $request->validate(['name' => 'required|string', 'slug' => 'required|string']);
    $existing = Role::where('slug', $data['slug'])->first();
    if ($existing) {
        return response(['error' => 1, 'message' => 'role already exists'], 409);
    }
    return Role::create($data);
}

// Protected resource deletion blocked
public function destroy(Role $role) {
    $protected = config('tyro.protected_role_slugs', ['admin', 'super-admin']);
    if (in_array($role->slug, $protected, true)) {
        return response(['error' => 1, 'message' => 'you cannot delete this role'], 422);
    }
    TyroCache::forgetUsersByRole($role);
    $role->delete();
    return response(['error' => 0, 'message' => 'role has been deleted']);
}

// Last admin protection
public function destroy($user) {
    $user = $this->resolveUser($user);
    $adminRole = Role::where('slug', 'admin')->first();
    if ($adminRole && $user->roles->contains($adminRole)) {
        $adminCount = $adminRole->users()->count();
        if ($adminCount === 1) {
            return response(['error' => 1, 'message' => 'Create another admin before deleting this only admin user'], 409);
        }
    }
    $user->delete();
    return response(['error' => 0, 'message' => 'user deleted']);
}

// Suspension check on login
public function login(Request $request) {
    $creds = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);
    $user = $this->resolveUser($creds['email'] ?? '');
    if (! $user || ! Hash::check($request->password, $user->password)) {
        return response(['error' => 1, 'message' => 'invalid credentials'], 401);
    }
    if ($this->userIsSuspended($user)) {
        return response(['error' => 1, 'message' => 'user is suspended'], 423);
    }
    // ... create token ...
    return response(['error' => 0, 'id' => $user->id, 'name' => $user->name, 'token' => $token], 200);
}

// Resource not found
public function update(Request $request, ?Role $role = null) {
    if (! $role) {
        return response(['error' => 1, 'message' => 'role doesn\'t exist'], 404);
    }
    // ... update ...
}
```

```php
// CORRECT: Command error handling — return codes + actionable messages
public function handle(): int {
    $userIdentifier = $this->option('user') ?? $this->ask('User ID or email');
    $roleIdentifier = $this->option('role') ?? $this->ask('Role ID or slug');

    $user = $this->findUser($userIdentifier);
    if (! $user) {
        $this->error('User not found.');
        return self::FAILURE;
    }

    if (! method_exists($user, 'roles')) {
        $this->error('The configured user model does not use the HasTyroRoles trait.');
        return self::FAILURE;
    }

    $role = $this->findRole($roleIdentifier);
    if (! $role) {
        $this->error('Role not found.');
        return self::FAILURE;
    }

    $user->assignRole($role);
    $this->info(sprintf('Role "%s" assigned to %s.', $role->slug, $user->email));
    return self::SUCCESS;
}
```

```php
// CORRECT: Protected role enforcement in commands
public function handle(): int {
    $identifier = $this->option('role') ?? $this->ask('Role ID or slug');
    $role = $this->findRole($identifier);

    if (! $role) {
        $this->error('Role not found.');
        return self::FAILURE;
    }

    $protected = config('tyro.protected_role_slugs', ['admin', 'super-admin']);
    if (in_array($role->slug, $protected, true)) {
        $this->error('This role is protected and cannot be deleted.');
        return self::FAILURE;
    }

    if (! $this->option('force') && ! $this->confirm(...)) {
        $this->warn('Operation cancelled.');
        return self::SUCCESS; // Not a failure — user intentionally cancelled
    }

    // ...delete...
    $this->info(sprintf('Role "%s" deleted.', $role->slug));
    return self::SUCCESS;
}
```

```php
// CORRECT: Error flow through the full authorization stack
// 1. Route middleware (EnsureTyroRole):
//    AuthorizationException('ACCESS DENIED.') → 403
// 2. HasTyroRoles trait methods:
//    hasRole(), hasPrivilege() → boolean (no exceptions)
// 3. TyroCache:
//    Returns empty array on miss → trait returns false → middleware denies
// 4. Controllers:
//    Return json responses with error: 1/0 pattern
// 5. Commands:
//    Return self::FAILURE (1) or self::SUCCESS (0)
// Each layer catches errors from the layer below and translates them
```

## Notes

- The `error: 1` / `error: 0` envelope is used consistently across all API controllers. `error: 1` always accompanies a non-2xx status code. `error: 0` accompanies a 2xx status code.
- `AuthorizationException` (from `Illuminate\Auth\Access\AuthorizationException`) thrown in middleware is caught by Laravel's exception handler and rendered as a 403 response. In API contexts it becomes JSON.
- HTTP 422 is used for business rule violations (e.g., deleting protected roles) — not for Laravel validation errors (which also use 422 but return a different structure via `ValidationException`).
- HTTP 401 is reserved for authentication failures only (invalid credentials). Authorization failures use 403.
- HTTP 423 (Locked) signals suspension — a deliberate non-standard use that clearly distinguishes suspension from other access denials.
- HTTP 409 (Conflict) is used for duplicate resources and the last-admin protection — scenarios where the request conflicts with current state.
- The generic `'ACCESS DENIED.'` message in middleware is intentional. Never customize it to include specific role or privilege names.
- Command exit codes: `self::SUCCESS` = 0, `self::FAILURE` = 1. Use `$this->error()` for failure messages (red text), `$this->warn()` for cancellations (yellow text), `$this->info()` for success messages (green text).
- Protected role enforcement in commands mirrors the controller logic — check `config('tyro.protected_role_slugs')` before delete/update operations.
- The `MissingAbilityException` from Sanctum is thrown in `UserController::update()` for authorization failures — this is a Sanctum-level error, not a Tyro middleware error.
- When in `APP_DEBUG=false` mode (production), the `AuthorizationException` message is still `'ACCESS DENIED.'` — no additional detail is exposed.
- Suspension errors return both the error indicator and a reason field when available: `['error' => 1, 'message' => 'user is suspended', 'reason' => $user->suspension_reason]`.

## Cross References

- [security.md](security.md) — Information leakage prevention, protected role enforcement
- [api-design.md](api-design.md) — API response conventions and route structure
- [middleware.md](middleware.md) — AuthorizationException in middleware context
- [artisan-commands.md](artisan-commands.md) — Command exit codes and error patterns
- [suspensions.md](suspensions.md) — Suspension error codes (423)
