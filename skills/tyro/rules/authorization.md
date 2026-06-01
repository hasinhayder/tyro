# Authorization Rules

## Why It Matters

Authorization in Tyro flows through a three-tier resolution pipeline: privilege check, role check, then Laravel Gate fallback. The `can()` method in `HasTyroRoles` (`src/Concerns/HasTyroRoles.php:123-132`) is the central authorization entry point. Middleware classes (`EnsureTyroRole`, `EnsureAnyTyroRole`, `EnsureTyroPrivilege`, `EnsureAnyTyroPrivilege`) provide HTTP-layer authorization guards. Blade directives (`@userCan`, `@userHasRole`, `@userHasPrivilege`, etc.) provide view-layer authorization. All paths converge on the same underlying slug-resolution and matching logic to guarantee consistent authorization decisions regardless of the entry point.

## Incorrect

```php
// INCORRECT: Manually checking roles bypassing the can() chain
if (in_array('admin', $user->roles->pluck('slug')->toArray())) {
    // grant access
}
```

```php
// INCORRECT: Using Gate::check() directly instead of can()
// This bypasses Tyro's privilege and role resolution
if (Gate::forUser($user)->check('edit-posts')) {
    // grant access — misses privilege checks!
}
```

```php
// INCORRECT: Hard-coding authorization logic in controllers
public function destroy(Post $post) {
    if ($post->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
        abort(403);
    }
}
```

```php
// INCORRECT: Inconsistent middleware naming
Route::get('/admin', fn() => ...)->middleware('check.role:admin');
```

## Correct

```php
// CORRECT: Using the can() method — three-tier resolution
public function can($ability, $arguments = []): bool {
    // Tier 1: Check privileges (direct slug match)
    if (is_string($ability) && $this->hasPrivilege($ability)) {
        return true;
    }
    // Tier 2: Check roles (direct slug match)
    if (is_string($ability) && $this->hasRole($ability)) {
        return true;
    }
    // Tier 3: Fall back to Laravel Gate
    return Gate::forUser($this)->check($ability, $arguments);
}
```

```php
// CORRECT: Using middleware with registered aliases
Route::middleware('role:admin')->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
});

Route::middleware('roles:admin,super-admin')->group(function () {
    Route::get('/admin/users', [UserController::class, 'index']);
});

Route::middleware('privilege:bypass-tenant-restrictions')->group(function () {
    Route::get('/admin/tenants', [TenantController::class, 'index']);
});
```

```php
// CORRECT: Using Blade directives in views
@userCan('edit-posts')
    <button>Edit Post</button>
@enduserCan

@userHasRole('admin')
    <div class="admin-panel">...</div>
@enduserHasRole

@userHasPrivilege('super-admin')
    <div class="system-config">...</div>
@enduserHasPrivilege

@userHasAnyRole(['editor', 'admin'])
    <a href="/admin">Admin Panel</a>
@enduserHasAnyRole
```

```php
// CORRECT: Using trait methods cleanly in controllers
public function destroy(Post $post) {
    $this->authorize('delete', $post); // Uses Gate, which can be registered in AuthServiceProvider

    // OR for Tyro-specific checks:
    if (!request()->user()->can('delete-posts')) {
        abort(403);
    }

    $post->delete();
}
```

```php
// CORRECT: Middleware handles wildcard '*' access uniformly
// EnsureTyroRole matchesRole method:
private function matchesRole(Collection $ownedRoles, string $requiredRole): bool {
    if ($requiredRole === '*') {
        return true;
    }
    return $ownedRoles->contains(function ($slug) use ($requiredRole) {
        return $slug === $requiredRole || $slug === '*';
    });
}

// EnsureTyroPrivilege also checks slug === '*' pattern
```

## Notes

- The `can()` method in `HasTyroRoles` overrides Laravel's `Authorizable` trait's `can()` method. This means `$this->authorize()` in controllers will still use Gate (not Tyro), because `authorize()` calls `Gate::check()` directly, not `$user->can()`. To use Tyro authorization in controllers, call `$request->user()->can('privilege-slug')` explicitly.
- Middleware aliases are registered in `TyroServiceProvider::registerMiddleware()`:
  - `role` → `EnsureTyroRole` (user must have ALL specified roles)
  - `roles` → `EnsureAnyTyroRole` (user must have ANY of the specified roles)
  - `privilege` → `EnsureTyroPrivilege` (user must have ALL specified privileges)
  - `privileges` → `EnsureAnyTyroPrivilege` (user must have ANY of the specified privileges)
  - `ability` → Sanctum's `CheckForAnyAbility`
  - `abilities` → Sanctum's `CheckAbilities`
- The `normalize()` method in all middleware splits comma-separated strings so `role:admin,super-admin` is equivalent to `role:admin,role:super-admin`.
- Middleware caches resolved slugs on the request attributes (`tyro.role_slugs`) for request-lifecycle reuse.

## Cross References

- [architecture.md](architecture.md) — Package boundaries and public API surface
- [permission-resolution.md](permission-resolution.md) — Three-tier resolution pipeline
- [permissions.md](permissions.md) — Permission modeling as privileges
- [roles.md](roles.md) — Role-based authorization
- [privileges.md](privileges.md) — Privilege-based system-level authorization
