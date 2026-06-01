# Suspension Rules

## Why It Matters

Suspensions in Tyro are modeled as columns on the users table (`suspended_at`, `suspension_reason`), managed through the `HasTyroRoles` trait. Unlike role-based access control, suspensions are an override mechanism: a suspended user is denied access regardless of their role or privilege assignments. This provides a hard kill-switch for individual user accounts without modifying their role structure. When a user is suspended, all active Sanctum tokens are revoked. The `suspend()`, `unsuspend()`, and `isSuspended()` methods are provided by the `HasTyroRoles` trait (`src/Concerns/HasTyroRoles.php:240-292`). Suspension audit events use the dot-notation `user.suspended` and `user.unsuspended`. Artisan commands `tyro:suspend-user` and `tyro:unsuspend-user` provide CLI management.

## Incorrect

```php
// INCORRECT: Manually setting suspension columns without audit or token revocation
$user->suspended_at = now();
$user->suspension_reason = 'Violated terms';
$user->save();
// Missing: TyroAudit::log('user.suspended', ...)
// Missing: $user->tokens()->delete()
```

```php
// INCORRECT: Deleting the user instead of suspending
$user->delete();
// Destructive — cannot be easily reversed like unsuspend()
```

```php
// INCORRECT: Removing all roles instead of suspending
$user->roles()->detach();
// Destructive to the role structure, requires re-assignment on unsuspend
```

```php
// INCORRECT: Suspending without a reason
$user->suspend(null); // No audit trail of WHY
```

```php
// INCORRECT: Checking roles without checking suspension status
if ($user->hasRole('admin')) {
    // Suspended admin should NOT have access!
}
```

## Correct

```php
// CORRECT: Using the suspend() method from HasTyroRoles trait
$user->suspend('Violated terms of service - Section 4.2');

// Internally:
// 1. Sets suspended_at = now()
// 2. Sets suspension_reason = $reason
// 3. Saves the model
// 4. Logs 'user.suspended' audit event with old/new values
// 5. Revokes all Sanctum tokens: $this->tokens()->delete()
// Returns: number of revoked tokens
```

```php
// CORRECT: Unsuspending a user
$user->unsuspend();

// Internally:
// 1. Sets suspended_at = null
// 2. Sets suspension_reason = null
// 3. Saves the model
// 4. Logs 'user.unsuspended' audit event with old/new values
```

```php
// CORRECT: Checking suspension status — must be done before authorization
public function login(Request $request) {
    $user = User::where('email', $request->email)->first();

    if ($user && $user->isSuspended()) {
        $reason = $user->getSuspensionReason();
        throw new AuthorizationException("Account suspended: {$reason}");
    }

    // Proceed with login...
}
```

```php
// CORRECT: Authorization checks should account for suspension
if ($user->isSuspended()) {
    abort(403, 'Account suspended: ' . $user->getSuspensionReason());
}

// Then proceed with role/privilege checks
if ($user->can('edit-posts')) {
    // ...
}
```

```php
// CORRECT: Using Artisan commands for suspension management
// php artisan tyro:suspend-user 5 --reason="Spam account"
// php artisan tyro:unsuspend-user 5
// php artisan tyro:suspended-users  (list all suspended users)
```

```php
// CORRECT: Suspension with audit trail
// Audit log entries contain old and new values:
// TyroAudit::log('user.suspended', $user, [
//     'suspended_at' => null,
//     'suspension_reason' => null,
// ], [
//     'suspended_at' => '2026-06-01T10:00:00Z',
//     'suspension_reason' => 'Violated terms of service',
// ]);
```

```php
// CORRECT: Middleware-level suspension check (recommended pattern)
class CheckUserSuspension {
    public function handle(Request $request, Closure $next) {
        if ($request->user() && $request->user()->isSuspended()) {
            auth()->guard(config('tyro.guard'))->logout();
            throw new AuthorizationException('ACCESS DENIED.');
        }
        return $next($request);
    }
}
```

## Notes

- `isSuspended()` simply checks `(bool) ($this->suspended_at ?? false)` — if `suspended_at` is non-null, the user is considered suspended.
- `getSuspensionReason()` returns the stored reason string or `null`.
- Token revocation uses `$this->tokens()->delete()`, which requires the user model to use Laravel Sanctum's `HasApiTokens` trait.
- The `suspended_at` and `suspension_reason` columns should be added to the users table via the published migration. Check `database/migrations/` for the relevant migration file.
- Suspensions are a user-level concept in current implementation. There is no role-level, privilege-level, or tenant-level suspension — only user-level suspension.
- When a user is unsuspended, tokens are NOT automatically recreated. The user must log in again to obtain new tokens.
- The `suspend()` method returns the count of revoked tokens, which can be logged or displayed.
- The `SuspendUserCommand` and `UnsuspendUserCommand` commands handle CLI interaction, prompting for reason on suspend.
- `SuspendedUsersCommand` lists all users where `suspended_at IS NOT NULL`.
- The `UserSuspensionController` provides REST API endpoints for suspension management.

## Cross References

- [authorization.md](authorization.md) — Authorization flow and suspension check placement
- [permission-resolution.md](permission-resolution.md) — How suspension interacts with resolution pipeline
- [audit-logs.md](audit-logs.md) — Suspension audit events format
- [artisan-commands.md](artisan-commands.md) — Suspension command reference
- [security.md](security.md) — Suspension as a security control
