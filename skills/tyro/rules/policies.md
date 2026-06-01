# Policy Integration

## Why It Matters

Tyro provides a role-and-privilege authorization layer that must coexist with Laravel's native Gate/Policy system without conflicts. The `HasTyroRoles` trait's `can()` method implements a three-tier resolution chain: privilege check first, then role check, then fallback to Laravel's `Gate::forUser($user)->check()`. This means Tyro does not replace Laravel policies — it augments them. When a user lacks a matching privilege or role slug, the Gate is consulted, allowing traditional Laravel policies (`view`, `create`, `update`, `delete`) to still govern resource access. Poorly designed policy integration leads to duplicated authorization logic, unexpected bypasses, or false denials.

## Incorrect

```php
// Bypassing the Gate fallback — reimplementing policy logic in privilege checks
public function update(Request $request, Post $post) {
    if (! $request->user()->hasPrivilege('update-post')) {
        abort(403);
    }
    // Also calls Gate::authorize('update', $post) somewhere else...
    $this->authorize('update', $post);
    // Now the same check is enforced twice with different semantics
}

// Using can() on the user object when you mean Gate::allows()
// This checks privileges then roles then Gate — unexpected fallback
if ($user->can('view', $post)) {
    // Falls through to Gate::forUser($user)->check('view', $post)
    // But caller may have expected only privilege/role check
}

// Defining privilege slugs that shadow policy ability names
// privilege slug: 'update-post', policy method: 'update'
// Now can('update-post') returns true from privilege check
// while can('update', $post) returns true from Gate
// — confusing and inconsistent
```

## Correct

```php
// Let Tyro handle privilege/role checks inline
public function index(Request $request) {
    // Role-based gating via middleware
    // Route: Route::get('/posts', ...)->middleware('role:admin,editor');
    return Post::all();
}

// Use Gate for resource ownership / instance checks
public function show(Request $request, Post $post) {
    // Gate checks ownership — Tyro delegates here naturally
    $this->authorize('view', $post);
    return $post;
}

// Combine Tyro privileges with Gate policies cleanly
public function destroy(Request $request, Post $post) {
    // Step 1: Tyro privilege for broad authorization
    if (! $request->user()->hasPrivilege('posts.delete')) {
        abort(403);
    }
    // Step 2: Gate for fine-grained instance check
    $this->authorize('delete', $post);
    $post->delete();
}

// Rely on the can() fallback chain when appropriate
if ($user->can('posts.delete')) {
    // Matches privilege slug first
}

if ($user->can('admin')) {
    // Matches role slug second
}

if ($user->can('view', $post)) {
    // Falls through to Gate::forUser($user)->check('view', $post)
}

// Register policies normally — Tyro does not interfere
// In AppServiceProvider:
// Gate::policy(Post::class, PostPolicy::class);
```

## Notes

- The `can()` method in `HasTyroRoles` calls `Gate::forUser($this)->check($ability, $arguments)` only when the string `$ability` does not match a privilege slug or a role slug. If `$arguments` is non-empty but `$ability` is a string matching a privilege, the Gate fallback is never reached.
- Blade directives `@hasprivilege`, `@usercan`, and `@hasrole` operate purely on the user's tyro data — they do not consult the Gate. Use `@can` (Laravel's native directive) for policy checks.
- The middleware aliases (`role`, `roles`, `privilege`, `privileges`) throw `AuthorizationException('ACCESS DENIED.')` and never fall back to the Gate. For Gate-based middleware, use Laravel's `can` middleware.
- Config `abilities.*` maps broad ability names to arrays of role slugs. Sanctum's `CheckAbilities` / `CheckForAnyAbility` middleware uses these for token-based gating in routes.
- The `*` wildcard slug bypasses all privilege and role checks in both the trait methods and the middleware — it does not bypass the Gate.

## Cross References

- api-design.md (route middleware configuration, abilities array)
- security.md (privilege escalation prevention, wildcard behavior)
- caching.md (role/privilege slug caching affects policy checks indirectly)
