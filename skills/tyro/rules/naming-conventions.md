# Naming Conventions

## Why It Matters

Consistent naming across Tyro's codebase reduces cognitive load, enables tooling (autocomplete, search, code generation), and prevents subtle bugs from mismatched identifiers. Every layer — commands, audit events, cache keys, config, Blade directives, middleware aliases, route names, and database columns — follows a specific convention. Violating these patterns creates friction for developers extending Tyro and breaks discoverability.

## Incorrect

```php
// Inconsistent command naming — mixing delimiters
protected $signature = 'tyro:role-add';       // wrong order
protected $signature = 'tyro:deletePrivilege'; // camelCase
protected $signature = 'tyro:add_new_role';    // snake_case

// Inconsistent audit event format
TyroAudit::log('RoleCreated', $role);               // PascalCase
TyroAudit::log('role_created', $role);              // snake_case
TyroAudit::log('role-created', $role);              // kebab-case

// Inconsistent cache key pattern
'tyro:user_'.$userId.'_roles'          // wrong separator
'tyro:roles:user:'.$userId             // wrong order
'user-'.$userId.'-role-slugs'          // missing tyro: prefix

// Inconsistent Blade directive casing
@hasrole('admin')       // lowercase
@hasRole('admin')       // camelCase — this is the alias, both are registered
// @hasROLE('admin')    // wrong — not registered

// Inconsistent config key styles
'tyro.protected_role_slugs'  // snake_case — correct
'tyro.protectedRoleSlugs'    // camelCase — wrong
'tyro.protected-role-slugs'  // kebab-case — wrong
```

## Correct

```php
// Commands: tyro:{entity}-{action} with kebab-case
protected $signature = 'tyro:add-role';
protected $signature = 'tyro:delete-role';
protected $signature = 'tyro:assign-role';
protected $signature = 'tyro:attach-privilege';
protected $signature = 'tyro:create-user';
protected $signature = 'tyro:sys-install';
protected $signature = 'tyro:auth-login';
protected $aliases = ['tyro:seed']; // alias for tyro:seed-all

// Audit events: {entity}.{action} dot notation
'role.created'
'role.updated'
'role.deleted'
'privilege.attached'
'privilege.detached'
'user.suspended'
'user.unsuspended'
'user.token_created'
'system.installed'
'system.seeded'
'roles.flushed'
'privileges.purged'

// Cache keys: tyro:user-{userId}:{type}
'tyro:user-42:roles'
'tyro:user-42:privileges'

// Config keys: snake_case in config/tyro.php
'protected_role_slugs'
'default_user_role_slug'
'delete_previous_access_tokens_on_login'
'route_prefix'
'route_name_prefix'
'cache.enabled'
'cache.store'
'cache.ttl'
'password.complexity.require_uppercase'

// PHP classes: PascalCase
HasinHayder\Tyro\Models\Role
HasinHayder\Tyro\Models\Privilege
HasinHayder\Tyro\Models\AuditLog
HasinHayder\Tyro\Support\TyroCache
HasinHayder\Tyro\Support\TyroAudit
HasinHayder\Tyro\Concerns\HasTyroRoles
HasinHayder\Tyro\Http\Middleware\EnsureTyroRole
HasinHayder\Tyro\Http\Middleware\EnsureAnyTyroPrivilege
HasinHayder\Tyro\Console\Commands\AddRoleCommand

// Blade directives: lowercase + camelCase alias
@hasrole('admin')       // kebab-case original
@hasRole('admin')       // camelCase alias
@hasprivilege('posts.create')
@hasPrivilege('posts.create')
@hasanyrole(['admin', 'editor'])
@hasAnyRole(['admin', 'editor'])
@hasroles(['admin', 'editor'])
@hasRoles(['admin', 'editor'])
@usercan('admin')
@userCan('admin')

// Middleware aliases: lowercase single-word
'role'       // EnsureTyroRole
'roles'      // EnsureAnyTyroRole
'privilege'  // EnsureTyroPrivilege
'privileges' // EnsureAnyTyroPrivilege
'tyro.log'   // TyroLog (debug logging)

// Database columns: snake_case
'suspended_at'
'suspension_reason'
'auditable_type'
'auditable_id'
'old_values'
'new_values'

// Route names: tyro.{resource}.{action}
'tyro.users.store'
'tyro.users.update'
'tyro.roles.index'
'tyro.roles.store'
'tyro.privileges.show'
'tyro.users.suspend'
'tyro.audit-logs.index'
```

## Notes

- Command signature `protected $signature = 'tyro:{verb}-{noun}'` where verb is `add`, `delete`, `update`, `assign`, `attach`, `detach`, `create`, `list`, `flush`, `purge`, `seed`, `sys-install`, `auth-login`.
- All 45 command classes follow `{Action}{Entity}Command` PascalCase, e.g., `AddRoleCommand`, `DeletePrivilegeCommand`, `AssignRoleCommand`.
- Audit events follow `{entity}.{action}` with past-tense action: `created`, `updated`, `deleted`, `attached`, `detached`, `assigned`, `removed`, `suspended`, `unsuspended`.
- Cache keys always start with `tyro:` prefix for namespace isolation.
- Config keys in `config/tyro.php` use dot notation for nesting: `cache.enabled`, `password.complexity.require_numbers`.
- Blade directives register two variants each: kebab-case (`@hasrole`) for Laravel convention and camelCase (`@hasRole`) for PHP developers.
- Middleware aliases are single-word lowercase (`role`, `roles`, `privilege`, `privileges`) with the exception of `tyro.log`.
- Database tables: `roles`, `privileges`, `user_roles`, `privilege_role`, `tyro_audit_logs`. The `tyro_` prefix distinguishes Tyro's audit table from application tables.
- Controller classes follow `{Entity}Controller` naming with `UserSuspensionController` as the outlier (handles two actions: suspend + unsuspend).

## Cross References

- artisan-commands.md (command naming patterns)
- audit-logs.md (audit event naming)
- caching.md (cache key patterns)
- api-design.md (route naming, route name prefix)
