# Blade Directive Rules

## Why It Matters

Tyro provides 7 Blade directives that allow authorization checks directly in Blade templates without controller or model references. Each directive class lives in `src/View/Directives/`, has a single `static register()` method that calls `Blade::if()` twice — once with a kebab-case name and once with a camelCase alias — to maintain backward compatibility and support diverse coding styles. The directives resolve the currently authenticated user via `auth()->user()` and delegate to methods on the `HasTyroRoles` trait (`hasRole`, `hasRoles`, `hasPrivilege`, `hasPrivileges`, `can`). All 7 directives are registered in `TyroServiceProvider::registerBladeDirectives()`. The dual-alias pattern is important: early adopters may use `@hasrole` while newer code prefers `@hasRole` — both must work identically. Directives return `false` when no user is authenticated, preventing access to guarded template sections.

## Incorrect

```php
// INCORRECT: Only registering a single alias
class UserHasRoleDirective {
    public static function register(): void {
        Blade::if('hasRole', function (string $role) {
            // Only camelCase — kebab users get undefined directive errors
        });
    }
}
```

```php
// INCORRECT: Inline anonymous function without extracting to a variable
Blade::if('hasrole', function (string $role) {
    $user = auth()->user();
    if (! $user) return false;
    return $user->hasRole($role);
});
Blade::if('hasRole', function (string $role) {
    $user = auth()->user();
    if (! $user) return false;
    return $user->hasRole($role); // Duplicated closure — violates DRY
});
// Both closures are identical — assign to a variable
```

```php
// INCORRECT: Not checking if the user's model has the trait method
Blade::if('hasrole', function (string $role) {
    $user = auth()->user();
    return $user->hasRole($role); // Fatal error if user model lacks HasTyroRoles
});
```

```php
// INCORRECT: Directives that throw exceptions instead of returning boolean
Blade::if('hasrole', function (string $role) {
    $user = auth()->user();
    if (! $user->hasRole($role)) {
        abort(403); // Breaks Blade::if() contract — must return bool
    }
    return true;
});
```

```php
// INCORRECT: Registering directives outside the ServiceProvider
// Don't do this in AppServiceProvider or boot():
Blade::if('hasrole', ...);
// All Tyro directives are registered by TyroServiceProvider
```

## Correct

```php
// CORRECT: Shared handler with dual alias registration
class UserHasRoleDirective {
    public static function register(): void {
        $handler = function (string $role) {
            $user = auth()->user();
            if (! $user) {
                return false;
            }
            return method_exists($user, 'hasRole') ? $user->hasRole($role) : false;
        };

        Blade::if('hasrole', $handler);
        Blade::if('hasRole', $handler);
    }
}
```

```php
// CORRECT: All 7 directive registrations in TyroServiceProvider
protected function registerBladeDirectives(): void {
    UserCanDirective::register();
    UserHasRoleDirective::register();
    UserHasAnyRoleDirective::register();
    UserHasRolesDirective::register();
    UserHasPrivilegeDirective::register();
    UserHasAnyPrivilegeDirective::register();
    UserHasPrivilegesDirective::register();
}
```

```php
// CORRECT: @hasanyrole / @hasAnyRole — checks ANY of the provided roles
class UserHasAnyRoleDirective {
    public static function register(): void {
        $handler = function (...$roles) {
            $user = auth()->user();
            if (! $user || ! method_exists($user, 'hasRole')) {
                return false;
            }
            foreach ($roles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
            return false;
        };

        Blade::if('hasanyrole', $handler);
        Blade::if('hasAnyRole', $handler);
    }
}
```

```php
// CORRECT: @hasroles / @hasAllRoles — checks ALL provided roles
class UserHasRolesDirective {
    public static function register(): void {
        $handler = function (...$roles) {
            $user = auth()->user();
            if (! $user || ! method_exists($user, 'hasRoles')) {
                return false;
            }
            return $user->hasRoles($roles);
        };

        Blade::if('hasroles', $handler);
        Blade::if('hasAllRoles', $handler);
    }
}
```

```php
// CORRECT: @hasprivilege / @hasPrivilege — single privilege check
class UserHasPrivilegeDirective {
    public static function register(): void {
        $handler = function (string $privilege) {
            $user = auth()->user();
            if (! $user) {
                return false;
            }
            return method_exists($user, 'hasPrivilege') ? $user->hasPrivilege($privilege) : false;
        };

        Blade::if('hasprivilege', $handler);
        Blade::if('hasPrivilege', $handler);
    }
}
```

```php
// CORRECT: @hasanyprivilege / @hasAnyPrivilege — checks ANY privilege
class UserHasAnyPrivilegeDirective {
    public static function register(): void {
        $handler = function (...$privileges) {
            $user = auth()->user();
            if (! $user || ! method_exists($user, 'hasPrivilege')) {
                return false;
            }
            foreach ($privileges as $privilege) {
                if ($user->hasPrivilege($privilege)) {
                    return true;
                }
            }
            return false;
        };

        Blade::if('hasanyprivilege', $handler);
        Blade::if('hasAnyPrivilege', $handler);
    }
}
```

```php
// CORRECT: @hasprivileges / @hasAllPrivileges — checks ALL privileges
class UserHasPrivilegesDirective {
    public static function register(): void {
        $handler = function (...$privileges) {
            $user = auth()->user();
            if (! $user || ! method_exists($user, 'hasPrivileges')) {
                return false;
            }
            return $user->hasPrivileges($privileges);
        };

        Blade::if('hasprivileges', $handler);
        Blade::if('hasAllPrivileges', $handler);
    }
}
```

```php
// CORRECT: @usercan / @userCan — checks ability through three-tier resolution
class UserCanDirective {
    public static function register(): void {
        $handler = function (string $ability) {
            $user = auth()->user();
            if (! $user) {
                return false;
            }
            return method_exists($user, 'can') ? $user->can($ability) : false;
        };

        Blade::if('usercan', $handler);
        Blade::if('userCan', $handler);
    }
}
```

```php
// CORRECT: Usage in Blade templates — all directives as opening/closing tags
@hasrole('admin')
    <p>Only admins see this.</p>
@endhasrole

@hasRole('admin')
    <p>Same check, camelCase alias.</p>
@endhasRole

@hasanyrole('admin,editor')
    <p>Users with admin OR editor role.</p>
@endhasanyrole

@hasroles('admin,editor,super-admin')
    <p>Users with ALL three roles.</p>
@endhasroles

@hasprivilege('edit-posts')
    <p>Users with edit-posts privilege.</p>
@endhasprivilege

@hasanyprivilege('edit-posts,delete-posts')
    <p>Users with edit-posts OR delete-posts.</p>
@endhasanyprivilege

@hasprivileges('edit-posts,delete-posts')
    <p>Users with BOTH privileges.</p>
@endhasprivileges

@usercan('edit-posts')
    <p>Users who can edit-posts (privilege, role, or Gate).</p>
@endusercan
```

```php
// CORRECT: @else works with Blade::if() directives
@hasrole('admin')
    <p>Admin panel</p>
@else
    <p>User dashboard</p>
@endhasrole

// Nested directives are also supported
@hasrole('admin')
    @hasprivilege('manage-users')
        <p>User management controls</p>
    @endhasprivilege
@endhasrole
```

## Notes

- All 7 directive classes use the exact same pattern: a `static register()` method, a shared `$handler` closure, and two `Blade::if()` calls (kebab + camelCase).
- The kebab-to-camelCase mapping is: `hasrole`/`hasRole`, `hasanyrole`/`hasAnyRole`, `hasroles`/`hasAllRoles`, `hasprivilege`/`hasPrivilege`, `hasanyprivilege`/`hasAnyPrivilege`, `hasprivileges`/`hasAllPrivileges`, `usercan`/`userCan`.
- Closing tags match the kebab variant automatically: `@endhasrole`, `@endhasanyrole`, `@endhasroles`, `@endhasprivilege`, `@endhasanyprivilege`, `@endhasprivileges`, `@endusercan`.
- All directives return `false` when `auth()->user()` is null — unauthenticated users never see guarded content.
- All directives check `method_exists($user, ...)` before calling the trait method, falling back to `false` if the trait is not used on the user model.
- Directives that take variadic arguments (`hasanyrole`, `hasroles`, `hasanyprivilege`, `hasprivileges`) accept both `@hasrole('admin,editor')` (comma string) and `@hasrole('admin', 'editor')` (separate arguments).
- The `@usercan` / `@userCan` directive calls `$user->can($ability)` which follows the three-tier resolution: privilege check → role check → Laravel Gate fallback.
- All directives are registered in `TyroServiceProvider::registerBladeDirectives()` — never register them manually.
- Blade directives are registered during `boot()`, not `register()`, because they depend on the Blade compiler being available.
- The directive names intentionally start with `@has` or `@user` prefix to avoid conflicts with potential future Laravel built-in directives.
- `Blade::if()` was introduced in Laravel 5.6 — these directives require at minimum that version.
- The `@hasAllRoles` camelCase alias uses "AllRoles" instead of "Roles" to distinguish from the kebab `@hasroles` which already implies plurality.

## Cross References

- [architecture.md](architecture.md) — ServiceProvider boot method order
- [authorization.md](authorization.md) — Trait methods called by directives
- [naming-conventions.md](naming-conventions.md) — Directive naming patterns
- [permissions.md](permissions.md) — Privilege checking in templates
- [roles.md](roles.md) — Role checking in templates
