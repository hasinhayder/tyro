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
$user->hasRole('admin');                                // check single role
$user->hasRoles(['admin', 'editor']);                  // must have ALL roles
$user->hasAnyRole(['admin', 'editor']);                // must have ANY role
$user->hasPrivilege('reports.run');                     // check single privilege
$user->hasPrivileges(['reports.run', 'billing.view']); // must have ALL privileges
$user->can('reports.run');                             // check privilege, role, or Laravel Gate
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

Route middleware aliases available out of the box:

| Middleware Alias | Description |
| --- | --- |
| `role:admin` | User must possess the specified single role. |
| `roles:admin,editor` | User must possess **any** of the comma-separated roles. |
| `privilege:reports.run` | User must possess the specified single privilege. |
| `privileges:reports.run,billing.view` | User must possess **any** of the comma-separated privileges. |
| `tyro.log` | Log API requests into Tyro's audit trail. |

Example route definitions:

```php
Route::middleware(['auth:sanctum', 'role:admin'])->get('/admin', AdminController::class);
Route::middleware(['auth:sanctum', 'roles:admin,editor'])->get('/dashboard', DashboardController::class);
Route::middleware(['auth:sanctum', 'privilege:reports.run'])->get('/reports', ReportsController::class);
Route::middleware(['auth:sanctum', 'privileges:reports.run,billing.view'])->get('/finance', FinanceController::class);
```

### Role & Privilege Helpers (`HasTyroRoles`)

In addition to basic role/privilege checks (`hasRole`, `hasRoles`, `hasAnyRole`, `hasPrivilege`, `hasPrivileges`, `can`), `HasTyroRoles` includes convenient helper methods:

```php
// Role & privilege checking methods
$user->hasRole('admin');                                // check single role
$user->hasRoles(['admin', 'editor']);                  // check user has ALL roles
$user->hasAnyRole(['admin', 'editor']);                // check user has ANY role
$user->hasPrivilege('reports.run');                     // check single privilege
$user->hasPrivileges(['reports.run', 'billing.view']); // check user has ALL privileges
$user->can('reports.run');                             // check privilege, role, or Laravel Gate

// Quick boolean role checks
$user->isAdmin();        // checks for 'admin' role
$user->isSuperAdmin();   // checks for 'super-admin' role
$user->isEditor();       // checks for 'editor' role
$user->isCustomer();     // checks for 'customer' role
$user->isUser();         // checks for 'user' role

// Inspection helpers
$user->hasNoRoles();     // true if user has 0 roles assigned
$user->rolesCount();     // count of assigned distinct roles (excluding super-admin wildcard)
$user->privileges();     // returns Eloquent Collection of all unique user privileges
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

| Directive | Description |
| --- | --- |
| `@hasRole('admin')` ... `@endhasRole` | Check for a single role |
| `@hasRoles(['admin', 'editor'])` ... `@endhasRoles` | Check if user has ALL specified roles |
| `@hasAnyRole(['admin', 'editor'])` ... `@endhasAnyRole` | Check if user has ANY specified role |
| `@hasPrivilege('reports.run')` ... `@endhasPrivilege` | Check for a single privilege |
| `@hasPrivileges(['reports.run', 'billing.view'])` ... `@endhasPrivileges` | Check if user has ALL specified privileges |
| `@hasAnyPrivilege(['reports.run', 'billing.view'])` ... `@endhasAnyPrivilege` | Check if user has ANY specified privilege |
| `@userCan('reports.run')` ... `@enduserCan` | Check privilege, role, or Laravel Gate |

```blade
@hasRole('admin')
    Welcome, Admin!
@endhasRole

@hasPrivilege('reports.run')
    <a href="/reports">View Reports</a>
@endhasPrivilege

@hasRoles(['admin', 'editor'])
    Full editing permissions active.
@endhasRoles
```

## CLI at a glance

One of Tyro's most powerful features: you can manage **everything** from the CLI (users, roles, privileges, tokens, suspensions, and audit logs) without touching the database directly. Perfect for automation, CI/CD pipelines, and incident response.

### Users

| Command | Description |
| --- | --- |
| `tyro:user-create` | Create a new user and attach Tyro's default role |
| `tyro:user-list` | Display all users tracked by Tyro |
| `tyro:user-list-with-roles` | Display users alongside their Tyro roles |
| `tyro:user-show` | Display a single user's details, roles, privileges, and suspension state |
| `tyro:user-update` | Modify a user's name, email, and password |
| `tyro:user-delete` | Delete a user while respecting the admin guardrails |
| `tyro:user-suspend` | Suspend a user (revokes all tokens) |
| `tyro:user-unsuspend` | Lift the suspension for a user |
| `tyro:user-suspended` | List every user currently suspended |
| `tyro:user-token` | Mint a Sanctum token without prompting for credentials |
| `tyro:user-roles` | Display a user's roles and their attached privileges |
| `tyro:user-privileges` | Display the privileges inherited by a user |

### Roles

| Command | Description |
| --- | --- |
| `tyro:role-create` | Create a new role |
| `tyro:role-list` | Display all Tyro roles |
| `tyro:role-list-with-privileges` | Display each role along with its attached privileges |
| `tyro:role-update` | Modify a role name or slug |
| `tyro:role-delete` | Delete a role (except the protected ones) |
| `tyro:role-attach` | Attach a role to a user |
| `tyro:role-detach` | Detach a role from a user |
| `tyro:role-users` | Display every user assigned to the given role |
| `tyro:role-purge` | Truncate the roles and pivot tables without re-seeding |

### Privileges

| Command | Description |
| --- | --- |
| `tyro:privilege-create` | Create a new Tyro privilege record |
| `tyro:privilege-list` | Display all Tyro privileges and their roles |
| `tyro:privilege-update` | Modify an existing privilege record |
| `tyro:privilege-delete` | Delete a Tyro privilege record |
| `tyro:privilege-attach` | Attach a privilege to a Tyro role |
| `tyro:privilege-detach` | Detach a privilege from a Tyro role |
| `tyro:privilege-purge` | Delete every privilege record and detach them from roles |

### Auth & Tokens

| Command | Description |
| --- | --- |
| `tyro:auth-login` | Mint a Sanctum token for a user via the CLI |
| `tyro:auth-logout` | Delete a single Sanctum token (log out the corresponding session) |
| `tyro:auth-logout-all` | Delete every Sanctum token for a specific user |
| `tyro:auth-logout-all-users` | Revoke every Sanctum token issued for all users |
| `tyro:who` | Inspect which user a given token belongs to |

### Audit & Setup

| Command | Description |
| --- | --- |
| `tyro:audit-list` | Display recent Tyro audit logs |
| `tyro:audit-purge` | Purge old Tyro audit logs |
| `tyro:seed-all` | Seed default roles, privileges, and bootstrap admin user |
| `tyro:seed-roles` | Seed default role definitions |
| `tyro:seed-privileges` | Seed default privilege definitions and role assignments |
| `tyro:user-prepare` | Add `HasApiTokens` and `HasTyroRoles` traits to the default User model |
| `tyro:install` | Bootstrap Tyro: set up Sanctum, run migrations, seed roles/privileges, and prepare your User model |
| `tyro:publish-config` | Publish Tyro's configuration file into your application |
| `tyro:publish-migrations` | Publish Tyro's migration files into your application |
| `tyro:update-config` | Update tyro config with the latest version |
| `tyro:sys-version` | Show the currently installed Tyro version |
| `tyro:sys-about` | Show Tyro's mission, version, and author details |
| `tyro:setup-ai-skill` | Install the Tyro Authorization AI skill for your preferred agent |
| `tyro:doc` | Open the Tyro documentation in your browser |
| `tyro:postman` | Open the Tyro Postman collection in your browser |
| `tyro:star` | Open the Tyro GitHub repository so you can star it |
| `tyro:run-tests` | Run your project's automated tests (Pest by default) |

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

Every option in `config/tyro.php`

**API & routes**

| Env var | Default | Description |
| --- | --- | --- |
| `TYRO_DISABLE_API` | `false` | Skip loading Tyro's REST routes entirely |
| `TYRO_DISABLE_COMMANDS` | `false` | Skip registering Tyro's artisan commands |
| `TYRO_GUARD` | `sanctum` | Guard used by Tyro's protected routes |
| `TYRO_ROUTE_PREFIX` | `api` | Prefix for Tyro's REST routes |
| `TYRO_ROUTE_NAME_PREFIX` | `tyro.` | Prefix for Tyro's route names |

**Users & roles**

| Env var | Default | Description |
| --- | --- | --- |
| `TYRO_USER_MODEL`, `AUTH_MODEL` | `App\Models\User` | User model Tyro operates on |
| `DEFAULT_ROLE_SLUG` | `user` | Role slug attached to newly registered users |
| `TYRO_USERS_TABLE` | `users` | Table name used for suspension columns |

**Passwords**

| Env var | Default | Description |
| --- | --- | --- |
| `TYRO_PASSWORD_MIN_LENGTH` | `8` | Minimum password length |
| `TYRO_PASSWORD_MAX_LENGTH` | `null` | Maximum password length (no limit when `null`) |
| `TYRO_PASSWORD_REQUIRE_UPPERCASE` | `false` | Require at least one uppercase letter |
| `TYRO_PASSWORD_REQUIRE_LOWERCASE` | `false` | Require at least one lowercase letter |
| `TYRO_PASSWORD_REQUIRE_NUMBERS` | `false` | Require at least one number |
| `TYRO_PASSWORD_REQUIRE_SPECIAL_CHARS` | `false` | Require at least one special character |
| `TYRO_PASSWORD_REQUIRE_CONFIRMATION` | `false` | Require a matching `password_confirmation` field |
| `TYRO_PASSWORD_CHECK_COMMON` | `false` | Block common or compromised passwords |
| `TYRO_PASSWORD_DISALLOW_USER_INFO` | `false` | Reject passwords containing the user's email or name |

**Sessions & tokens**

| Env var | Default | Description |
| --- | --- | --- |
| `DELETE_PREVIOUS_ACCESS_TOKENS_ON_LOGIN` | `false` | Revoke all previous tokens on login (single-session mode) |

**Audit trail**

| Env var | Default | Description |
| --- | --- | --- |
| `TYRO_AUDIT_ENABLED` | `true` | Enable the database-backed audit trail |
| `TYRO_AUDIT_RETENTION_DAYS` | `30` | Days audit logs are kept before purging |

**Cache**

| Env var | Default | Description |
| --- | --- | --- |
| `TYRO_CACHE_ENABLED` | `true` | Cache per-user role/privilege lookups |
| `TYRO_CACHE_TTL` | `300` | Seconds role/privilege lookups are cached |
| `TYRO_CACHE_STORE` | `null` | Cache store to use (`null` uses Laravel's default) |

## Related resources

- [**Tyro Labs**](https://tyrolabs.dev/): complete Auth & Admin platform for Laravel.
- [**tyro-login**](https://github.com/hasinhayder/tyro-login): production-ready login UI with OTP, TOTP, magic login, social login, and password reset.
- [**tyro-dashboard**](https://github.com/hasinhayder/tyro-dashboard): admin dashboard for CRUD, roles, privileges, users, and permissions.

## License

Tyro is open-sourced software licensed under the [MIT license](LICENSE).
