# Security

## Why It Matters

Tyro manages authentication tokens (Sanctum), authorization (roles/privileges), user suspension, and password policies. Security failures in an authorization framework are catastrophic: privilege escalation lets users gain admin access, authorization bypass lets unauthenticated users read protected data, and suspension bypass lets banned users continue operating. Every code path that checks permissions, assigns roles, creates users, or revokes tokens must be hardened against edge cases, race conditions, and bypass attempts.

## Incorrect

```php
// Privilege escalation — allowing slug changes on protected roles
public function update(Request $request, Role $role) {
    $role->slug = $request->slug; // No check for protected slugs
    $role->save();
    // Now 'super-admin' slug could be changed, making the role unreachable
}

// Authorization bypass — missing middleware on admin endpoints
// Route not behind admin middleware:
Route::post('roles', [RoleController::class, 'store']);

// Suspension bypass — not checking suspension status
public function login(Request $request) {
    $user = User::where('email', $request->email)->first();
    if (! $user || ! Hash::check($request->password, $user->password)) {
        return response(['error' => 1, 'message' => 'invalid credentials'], 401);
    }
    // Missing: check if user is suspended before creating token
    $token = $user->createToken('api-token')->plainTextToken;
    // Suspended user now has an active token!
}

// Wildcard abuse — permitting all access too broadly
if (in_array('*', $userRoles)) {
    // true — grants every permission check
    // If assigned unintentionally, user has unrestricted access
}

// Weak passwords — no complexity requirements
'password' => 'required|min:4', // Far too short, no complexity

// Deleting the last admin — system becomes unmanageable
public function destroy($user) {
    $user->delete();
    // No check: is this the last admin user?
}

// Mutable audit logs — destroying accountability
AuditLog::where('event', 'user.deleted')->delete();
```

## Correct

```php
// Protected role slug enforcement
public function update(Request $request, ?Role $role = null) {
    // ...
    if ($request->slug) {
        $protected = config('tyro.protected_role_slugs', ['admin', 'super-admin']);
        if (! in_array($role->slug, $protected, true)) {
            $role->slug = $request->slug;
        }
        // Protected roles keep their slug — prevents privilege escalation
    }
    // ...
}

public function destroy(Role $role) {
    $protected = config('tyro.protected_role_slugs', ['admin', 'super-admin']);
    if (in_array($role->slug, $protected, true)) {
        return response(['error' => 1, 'message' => 'you cannot delete this role'], 422);
    }
    // ...
}

// Suspension check before token creation
public function login(Request $request) {
    // ...
    if ($this->userIsSuspended($user)) {
        return response(['error' => 1, 'message' => 'user is suspended'], 423);
    }
    // Token creation only after suspension check passes
    $token = $user->createToken('tyro-api-token', $roles)->plainTextToken;
}

// Token revocation on suspension (in the HasTyroRoles trait)
public function suspend(?string $reason = null): int {
    // ...save suspension fields...
    return (int) $this->tokens()->delete(); // Revoke ALL tokens
}

// Last-admin protection
public function destroy($user) {
    $adminRole = Role::where('slug', 'admin')->first();
    if ($adminRole && $user->roles->contains($adminRole)) {
        $adminCount = $adminRole->users()->count();
        if ($adminCount === 1) {
            return response(['error' => 1, 'message' => 'Create another admin before deleting this only admin user'], 409);
        }
    }
    // ...delete user...
}

// Middleware throws AuthorizationException for all failures
class EnsureTyroRole {
    public function handle(Request $request, Closure $next, string ...$roles) {
        $user = $request->user();
        if (! $user) {
            throw new AuthorizationException('ACCESS DENIED.');
        }
        // ...check roles...
        throw new AuthorizationException('ACCESS DENIED.');
    }
}

// Wildcard grants all access — use intentionally and sparingly
public function hasRole(string $role): bool {
    $userRoles = $this->tyroRoleSlugs();
    return in_array($role, $userRoles, true) || in_array('*', $userRoles, true);
}

// Password complexity enforcement
public static function get(array $userData = []): array {
    $passwordRule = Password::min(config('tyro.password.min_length', 8));
    if (config('tyro.password.complexity.require_numbers', false)) {
        $passwordRule->numbers();
    }
    if (config('tyro.password.complexity.require_special_chars', false)) {
        $passwordRule->symbols();
    }
    if (config('tyro.password.check_common_passwords', false)) {
        $passwordRule->uncompromised();
    }
    if (config('tyro.password.disallow_user_info', false) && ! empty($userData)) {
        $rules[] = function ($attribute, $value, $fail) use ($userData) {
            // Check password does not contain email or name parts
        };
    }
    return $rules;
}

// Immutable audit trail — no updates, deletes only via purge
class AuditLog extends Model {
    public $timestamps = false;
    // No fillable for 'updated_at' — records are append-only
    // In application code, never AuditLog::update() or AuditLog::delete()
}

// Defense in depth: middleware + controller + model checks
// Route level: admin middleware (ability:admin,super-admin)
// Controller level: protected slug check
// Model level: cascade cache invalidation on pivot
```

## Notes

- Protected role slugs (`admin`, `super-admin`) are defined in `config('tyro.protected_role_slugs')`. These roles cannot be deleted and their slugs cannot be changed via the API or commands.
- The `*` wildcard slug grants all access in `hasRole()`, `hasAnyRole()`, `hasRoles()`, `hasPrivilege()`, `hasPrivileges()`, and all middleware checks. It should only be assigned via `BaseTyroCommand::abilitiesForUser()` which returns `['*']` only when the user has no roles.
- Suspension triggers token revocation via `$this->tokens()->delete()` in `HasTyroRoles::suspend()`. All active Sanctum tokens are invalidated.
- The login endpoint checks suspension before token creation and returns HTTP 423 if suspended.
- Deleting the last admin user is blocked at the controller level — at least one admin must remain.
- All authorization middleware (`role`, `roles`, `privilege`, `privileges`) throws a generic `AuthorizationException('ACCESS DENIED.')` — the specific missing role/privilege is not disclosed to prevent information leaking.
- Route-level authorization uses Sanctum's `CheckForAnyAbility` (aliased as `ability` middleware) combined with the `abilities` config map. This is an additional layer beyond Tyro's own middleware.
- Commands check protected slugs for delete and update operations — failing gracefully with an error message.
- The `PasswordRules` class builds configurable password validation rules. All complexity checks are opt-in via config.
- Audit logs are effectively append-only — no update methods are exposed in application code, and delete is reserved for the `PurgeAuditLogsCommand` (which respects retention policy).
- Cache invalidation on pivot changes (UserRole, RolePrivilege) ensures stale permissions are never served.

## Cross References

- policies.md (wildcard behavior, Gate fallback)
- audit-logs.md (immutable audit trails, actor tracking)
- caching.md (cache invalidation preventing stale permissions)
- multi-tenancy.md (tenant boundary enforcement)
