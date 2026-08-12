# Tyro

[![Packagist](https://img.shields.io/packagist/v/hasinhayder/tyro?style=for-the-badge&logo=packagist&logoColor=white&label=Packagist)](https://packagist.org/packages/hasinhayder/tyro) [![Downloads](https://img.shields.io/packagist/dt/hasinhayder/tyro?style=for-the-badge&logo=packagist&logoColor=white&label=Downloads)](https://packagist.org/packages/hasinhayder/tyro/stats) [![Tests](https://img.shields.io/github/actions/workflow/status/hasinhayder/tyro/tests.yml?style=for-the-badge&label=Tests)](https://github.com/hasinhayder/tyro/actions/workflows/tests.yml) [![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com) [![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com) [![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php)](https://php.net) [![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)](LICENSE) [![Documentation](https://img.shields.io/badge/Documentation-Visit-4B32C3?style=for-the-badge&logo=readthedocs&logoColor=white)](https://hasinhayder.github.io/tyro/doc.html) [![CLI Ready](https://img.shields.io/badge/CLI-Ready-2EA44F?style=for-the-badge&logo=terminal&logoColor=white)](https://github.com/hasinhayder/tyro)

**Tyro** is the complete authentication, roles & privileges package for Laravel 12 & 13: a modern RBAC/ACL solution built on Laravel Sanctum, with a fantastic CLI that manages everything for you.

Set up in under a minute. No complicated configuration. No boilerplate.

📖 [**Full documentation**](https://hasinhayder.github.io/tyro/doc.html)

- **Instant install**: one command bootstraps Sanctum, runs migrations, seeds roles & privileges, and prepares your User model.
- **RBAC & ACL**: unlimited roles with granular privileges; check access in controllers, middleware, Blade, or anywhere with `$user->can()`, `hasRole()`, `hasPrivileges()`, and more.
- **40+ Artisan commands**: manage users, roles, privileges, tokens, suspensions, and audit logs from the CLI, perfect for automation and CI/CD.
- **Ready for APIs**: Sanctum token auth, REST endpoints for auth, user, role & privilege management, and a ready-made Postman collection.
- **Middleware & Blade directives**: `role:`, `privilege:`, `ability:` middleware plus 7 directives like `@hasRole` and `@hasPrivilege`.
- **User suspension**: freeze accounts instantly with an optional reason; all active tokens are revoked automatically, and users can be restored anytime.
- **Audit trail**: every role/privilege assignment, user suspension, and admin action is logged, with CLI commands to review and purge history.
- **Security hardened**: token abilities mirror roles & privileges, suspension instantly revokes tokens, and protected roles can't be deleted.
- **Zero lock-in**: publish config, migrations, and factories; disable API routes or CLI commands per environment.

## Requirements

- PHP ^8.2
- Laravel 12 or 13
- Laravel Sanctum ^4.0

## Installation (about a minute)

```bash
composer require hasinhayder/tyro
php artisan tyro:install
```

That's it. `tyro:install` sets up Sanctum, prepares your User model, runs migrations, and seeds the default roles & privileges, including a ready-to-use `admin@tyro.project` superuser (password: `tyro`).

> Want the ready-made API client? Run `php artisan tyro:postman` to open the official Postman collection.

## Quick tour

### Checking roles & privileges

```php
$user->hasRole('admin');                 // single role
$user->hasRoles(['admin', 'editor']);    // must have ALL roles
$user->hasAnyRole(['admin', 'editor']);  // must have ANY role
$user->can('reports.run');               // privilege or Laravel Gate
$user->hasPrivileges(['reports.run', 'billing.view']);
```

### Managing roles & privileges

```php
use HasinHayder\Tyro\Models\Role;
use HasinHayder\Tyro\Models\Privilege;

$user->assignRole($adminRole);                  // attach a role
$user->removeRole($editorRole);                 // detach a role
$role->privileges()->attach($privilege->id);    // grant a privilege
$role->privileges()->detach($privilege->id);    // revoke a privilege
```

### Protecting routes

```php
Route::middleware(['auth:sanctum', 'role:admin'])->get('/admin', AdminController::class);
Route::middleware(['auth:sanctum', 'privilege:reports.run'])->get('/reports', ReportsController::class);
```

### Suspending users

```php
$user->suspend('Pending account review');  // instantly revokes all tokens
$user->isSuspended();                      // true/false
$user->getSuspensionReason();
$user->unsuspend();
```

### Auditing admin actions

Every role assignment, privilege change, suspension, and admin action is recorded automatically:

```bash
php artisan tyro:audit-list              # recent activity
php artisan tyro:audit-list --event=role.assigned
php artisan tyro:audit-purge             # purge logs per retention policy
```

### Blade directives

```blade
@hasRole('admin')
    Welcome, Admin!
@endhasRole

@hasPrivilege('reports.run')
    <a href="/reports">View Reports</a>
@endhasPrivilege
```

Also available: `@userCan`, `@hasAnyRole`, `@hasAllRoles`, `@hasAnyPrivilege`, `@hasAllPrivileges`.

## CLI at a glance

One of Tyro's most powerful features: you can manage **everything** from the CLI (users, roles, privileges, tokens, suspensions, and audit logs) without touching the database directly. Perfect for automation, CI/CD pipelines, and incident response.

| Category | Commands |
| --- | --- |
| Users | `tyro:user-create`, `tyro:user-list`, `tyro:user-list-with-roles`, `tyro:user-update`, `tyro:user-delete`, `tyro:user-show`, `tyro:user-suspend`, `tyro:user-unsuspend`, `tyro:user-suspended`, `tyro:user-token` |
| Roles | `tyro:role-create`, `tyro:role-update`, `tyro:role-delete`, `tyro:role-list`, `tyro:role-list-with-privileges`, `tyro:role-attach`, `tyro:role-detach`, `tyro:role-users`, `tyro:role-purge` |
| Privileges | `tyro:privilege-create`, `tyro:privilege-update`, `tyro:privilege-delete`, `tyro:privilege-list`, `tyro:privilege-attach`, `tyro:privilege-detach`, `tyro:privilege-purge`, `tyro:user-privileges` |
| Auth & tokens | `tyro:auth-login`, `tyro:auth-logout`, `tyro:auth-logout-all`, `tyro:auth-logout-all-users`, `tyro:who`, `tyro:user-roles` |
| Audit & setup | `tyro:audit-list`, `tyro:audit-purge`, `tyro:seed-all`, `tyro:seed-roles`, `tyro:seed-privileges`, `tyro:user-prepare`, `tyro:install`, `tyro:publish-config`, `tyro:publish-migrations`, `tyro:update-config`, `tyro:sys-version`, `tyro:sys-about`, `tyro:setup-ai-skill`, `tyro:doc`, `tyro:postman`, `tyro:star`, `tyro:run-tests` |

Run `php artisan list tyro` to see every available command. Most commands also have shorter aliases (for example, `tyro:login`, `tyro:users`, `tyro:roles`, `tyro:me`, `tyro:audit`).

## Optional: REST API

Tyro ships REST endpoints for auth, user management, and role/privilege CRUD (enabled by default, prefix `api`). Disable them whenever you want:

```env
TYRO_DISABLE_API=true
```

## Configuration

Publish and customize everything:

```bash
php artisan vendor:publish --tag=tyro-config
php artisan vendor:publish --tag=tyro-migrations
```

Every option in `config/tyro.php`:

| Env var | Default | Description |
| --- | --- | --- |
| `TYRO_DISABLE_API` | `false` | Skip loading Tyro's REST routes entirely |
| `TYRO_DISABLE_COMMANDS` | `false` | Skip registering Tyro's artisan commands |
| `TYRO_USER_MODEL`, `AUTH_MODEL` | `App\Models\User` | User model Tyro operates on |
| `DEFAULT_ROLE_SLUG` | `user` | Role slug attached to newly registered users |
| `TYRO_GUARD` | `sanctum` | Guard used by Tyro's protected routes |
| `DELETE_PREVIOUS_ACCESS_TOKENS_ON_LOGIN` | `false` | Revoke all previous tokens on login (single-session mode) |
| `TYRO_ROUTE_PREFIX` | `api` | Prefix for Tyro's REST routes |
| `TYRO_ROUTE_NAME_PREFIX` | `tyro.` | Prefix for Tyro's route names |
| `TYRO_PASSWORD_MIN_LENGTH` | `8` | Minimum password length |
| `TYRO_PASSWORD_MAX_LENGTH` | `null` | Maximum password length (no limit when `null`) |
| `TYRO_PASSWORD_REQUIRE_UPPERCASE` | `false` | Require at least one uppercase letter |
| `TYRO_PASSWORD_REQUIRE_LOWERCASE` | `false` | Require at least one lowercase letter |
| `TYRO_PASSWORD_REQUIRE_NUMBERS` | `false` | Require at least one number |
| `TYRO_PASSWORD_REQUIRE_SPECIAL_CHARS` | `false` | Require at least one special character |
| `TYRO_PASSWORD_REQUIRE_CONFIRMATION` | `false` | Require a matching `password_confirmation` field |
| `TYRO_PASSWORD_CHECK_COMMON` | `false` | Block common or compromised passwords |
| `TYRO_PASSWORD_DISALLOW_USER_INFO` | `false` | Reject passwords containing the user's email or name |
| `TYRO_AUDIT_ENABLED` | `true` | Enable the database-backed audit trail |
| `TYRO_AUDIT_RETENTION_DAYS` | `30` | Days audit logs are kept before purging |
| `TYRO_CACHE_ENABLED` | `true` | Cache per-user role/privilege lookups |
| `TYRO_CACHE_TTL` | `300` | Seconds role/privilege lookups are cached |
| `TYRO_CACHE_STORE` | `null` | Cache store to use (`null` uses Laravel's default) |
| `TYRO_USERS_TABLE` | `users` | Users table used for suspension columns |

## Related resources

- [**Tyro Labs**](https://tyrolabs.dev/): complete Auth & Admin platform for Laravel.
- [**tyro-login**](https://github.com/hasinhayder/tyro-login): production-ready login UI with OTP, TOTP, magic login, social login, and password reset.
- [**tyro-dashboard**](https://github.com/hasinhayder/tyro-dashboard): admin dashboard for CRUD, roles, privileges, users, and permissions.

## License

Tyro is open-sourced software licensed under the [MIT license](LICENSE).
