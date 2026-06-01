# Backward Compatibility

## Why It Matters

Tyro is a framework consumed by multiple downstream projects (Tyro Login, Tyro Dashboard, Tyro SaaS, and custom applications). Every public API — trait methods, static support methods, Artisan command signatures, middleware aliases, Blade directives, config keys, route names, model classes — is a contract. Breaking changes force all consumers to update simultaneously. Tyro uses semantic versioning and treats deprecation as a multi-release process.

## Incorrect

Removing a public method without a deprecation cycle:

```php
// Do NOT remove or rename public methods without deprecation
// Old: $user->hasPrivilege('reports.generate')
// Removing this breaks all consumers
```

Changing Artisan command signatures without aliases:

```php
// Do NOT change a command name without keeping the old name as an alias
// Old: tyro:user-list
// New: tyro:user-list-all (removing old breaks scripts and muscle memory)
```

Altering config key names without a migration path:

```php
// Do NOT rename config keys — consumers reference them in their config files
// Old: 'models.role'
// New: 'role_model'  // Breaks all config('tyro.models.role') calls
```

Changing method signatures (adding required parameters):

```php
// Do NOT add required parameters to public methods
// Old: public function assignRole(Role $role)
// New: public function assignRole(Role $role, bool $notify = true)
// Adding $notify as required would break all callers
```

## Correct

Deprecate by keeping the old method working and adding an alias chain. Pattern from the existing codebase:

```php
// Adding new method while keeping old one working
// In HasTyroRoles trait:

/**
 * Check if the user has a specific privilege.
 * @deprecated Use hasPrivilege() instead. Will be removed in v2.0.
 */
public function can(string $ability): bool {
    // Still works — forwards to the canonical method
    return $this->hasPrivilege($ability);
}

/**
 * Canonical method for privilege checking.
 * @since 1.5.0
 */
public function hasPrivilege(string $ability): bool {
    // Actual implementation
}
```

Command aliasing pattern (from existing commands like DocCommand and AboutCommand):

```php
// In the command class, use protected $aliases
class DocCommand extends BaseTyroCommand {
    protected $signature = 'tyro:sys-doc {--no-open : Only print the docs URL}';
    protected $aliases = ['tyro:doc'];  // Old alias still works

    // ... handle() implementation
}

class AboutCommand extends BaseTyroCommand {
    protected $signature = 'tyro:sys-about';
    protected $aliases = ['tyro:about'];
}
```

Add new features behind a config flag without changing the default behavior:

```php
// New feature: token deletion on login (added in v1.x)
// Default: false — existing apps see no behavior change
'delete_previous_access_tokens_on_login' => env('DELETE_PREVIOUS_ACCESS_TOKENS_ON_LOGIN', false),
```

Complete list of stable public APIs that must maintain backward compatibility:

```php
// HasTyroRoles trait (src/Concerns/HasTyroRoles.php)
assignRole(Role $role): void
removeRole(Role $role): void
hasRole(string $role): bool
hasAnyRole(array $roles): bool
hasRoles(array $roles): bool
privileges(): Collection
hasPrivileges(array $privileges): bool
can($ability, $arguments = []): bool
hasPrivilege(string $ability): bool
tyroRoleSlugs(): array
tyroPrivilegeSlugs(): array
suspend(?string $reason = null): int
unsuspend(): void
isSuspended(): bool
getSuspensionReason(): ?string

// TyroCache (src/Support/TyroCache.php)
rememberRoleSlugs($userId, callable $resolver): array
rememberPrivilegeSlugs($userId, callable $resolver): array
forgetUser($user): void
forgetUsers(iterable $userIds): void
forgetUsersByRole(Role $role): void
forgetUsersByPrivilege(Privilege $privilege): void
forgetUsersByRoleIds(iterable $roleIds): void
forgetAllUsersWithRoles(): void

// TyroAudit (src/Support/TyroAudit.php)
log(string $event, ?Model $auditable, ?array $oldValues, ?array $newValues, array $metadata): ?AuditLog

// BaseTyroCommand (src/Console/Commands/BaseTyroCommand.php)
userClass(): string
newUserQuery()
findUser(?string $identifier): ?Model
findRole(?string $identifier): ?Role
findPrivilege(?string $identifier): ?Privilege
defaultRole(): ?Role
abilitiesForUser(Model $user): array

// Middleware aliases (registered in TyroServiceProvider)
'tyro.log'
'privilege'
'privileges'
'role'
'roles'
'ability'
'abilities'

// Blade directives
@hasrole / @hasRole
@hasanyrole / @hasanyRole
@hasroles
@hasprivilege
@hasanyprivilege
@hasprivileges
@usercan

// Config keys (config/tyro.php)
All keys listed in configuration.md

// Route names
'tyro.info'
'tyro.version'
'tyro.login'
'tyro.me'
'tyro.users.store'
'tyro.users.update'
'tyro.users.suspend'
'tyro.users.unsuspend'
'tyro.audit-logs.index'
'tyro.users.index', 'tyro.users.show', 'tyro.users.destroy'
'tyro.roles.index', 'tyro.roles.store', 'tyro.roles.show', 'tyro.roles.update', 'tyro.roles.destroy'
'tyro.privileges.index', 'tyro.privileges.store', 'tyro.privileges.show', 'tyro.privileges.update', 'tyro.privileges.destroy'
'tyro.users.roles.index', 'tyro.users.roles.store', 'tyro.users.roles.destroy'
'tyro.roles.privileges.index', 'tyro.roles.privileges.store', 'tyro.roles.privileges.destroy'
```

## Notes

- Version tracked in `composer.json` branch-alias: `"dev-main": "1.5.x-dev"`. The actual version is `1.6.0`.
- Prefix ALL new features with `@since 1.x.0` in docblocks.
- Mark ALL deprecations with `@deprecated Use X instead. Will be removed in v2.0.` in docblocks.
- Use `protected $aliases` on commands to maintain old command names when renaming.
- Add new config keys with `env()` fallbacks and document them in the changelog.
- Never remove a public method in a minor version. Deprecate in N.x, remove in (N+1).0.
- When adding new parameters to public methods, always make them optional with sensible defaults (e.g., `?string $reason = null`).
- All test files must continue to pass after any change — the test suite is the backward compatibility gate.

## Cross References

- [configuration.md](configuration.md) — stable config key contract
- [documentation.md](documentation.md) — documenting deprecations and upgrade paths
- [testing.md](testing.md) — regression testing requirements
- [api-design.md](api-design.md) — API versioning strategy
