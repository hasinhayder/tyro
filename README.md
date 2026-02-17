# Tyro

**Tyro** is a very powerful Authentication, Authorization, and Role & Privilege Management solution for Laravel 12. Think of it as a Swiss Army knife that handles everything from user authentication and role-based access control to user suspension workflows—whether you're building an API, a traditional web application, or both. With Sanctum integration, 40+ powerful CLI commands, 7 Blade directives, and ready-made middleware, Tyro saves you weeks of development time.

### Related Resources

- [**Tyro Labs**](https://tyrolabs.dev/) – Complete Auth & Admin Platform for Laravel.
- [**tyro-login**](https://github.com/hasinhayder/tyro-login) – A sleek, production-ready login UI component powered by Tyro's authentication system with OTP, TOTP, Multiple Layouts, Magic Login, Authenticator, Social Login, Password Reset, Nice Email Templates.
- [**tyro-dashboard**](https://github.com/hasinhayder/tyro-dashboard) – A comprehensive admin dashboard (admin/user) for instant crud, managing roles, privileges, users, and permissions with ease.

## Why Tyro?

Tyro is the complete auth and access control toolkit that works everywhere in your Laravel application:

-   **Complete Authentication & Authorization.** Out-of-the-box user authentication with Sanctum, role-based access control, fine-grained privilege management, and Laravel Gate integration. Works seamlessly for APIs, web apps, and hybrid applications.
-   **Powerful Role & Privilege System.** Create unlimited roles with granular privileges. Check permissions in controllers, middleware, Blade templates, or anywhere in your code with intuitive helpers like `$user->hasRole()`, `$user->can()`, and `$user->hasPrivileges()`.
-   **40+ Artisan Commands.** Manage users, roles, privileges, and tokens entirely from the CLI. Seed data, suspend users, rotate tokens, audit permissions—all without touching the database directly. Perfect for automation, CI/CD, and incident response.
-   **Blade Directives for Views.** Use `@userCan`, `@hasRole`, `@hasPrivilege`, `@hasAnyRole`, `@hasAllRoles`, `@hasAnyPrivilege`, and `@hasAllPrivileges` to conditionally render content based on user permissions. Clean, readable templates without PHP logic clutter.
-   **User Suspension Workflows.** Freeze accounts instantly with optional reasons, automatically revoke all active tokens, and manage suspensions via CLI or REST endpoints.
-   **Comprehensive Audit Trail.** Track every administrative action—who assigned which role, who suspended a user, and what changed in a role's privileges. View logs via CLI or a dedicated API endpoint.
-   **Optional API Surface.** Need REST endpoints? Tyro ships production-ready routes for login, registration, user management, role CRUD, and privilege management. Don't need them? Disable with one config flag.
-   **Security Hardened.** Sanctum tokens automatically include role and privilege abilities, suspension workflows revoke tokens instantly, and protected role slugs prevent accidental deletion.
-   **Zero Lock-in.** Publish config, migrations, and factories to customize everything. Disable CLI commands or API routes per environment. Tyro adapts to your architecture, not the other way around.

## Requirements

-   PHP ^8.2
-   Laravel ^12.0
-   Laravel Sanctum ^4.0

## Quick start (TL;DR)

1. `composer require hasinhayder/tyro`
2. `php artisan tyro:sys-install` (sets up Sanctum, runs migrations, seeds roles/privileges, and prepares your User model)

That's it! You now have a complete authentication and authorization system. The rest of this document shows how to use Tyro's features in your application.

## Step-by-step installation

### 1. Install the package

```bash
composer require hasinhayder/tyro
```

Tyro's service provider is auto-discovered. Publish its assets if you want to customize them:

```bash
php artisan vendor:publish --tag=tyro-config
php artisan vendor:publish --tag=tyro-migrations
php artisan vendor:publish --tag=tyro-database
php artisan tyro:publish-config --force
php artisan tyro:publish-migrations --force
```

Need the ready-made API client collection? Run `php artisan tyro:sys-postman --no-open` to print the GitHub URL for the official Postman collection, or omit `--no-open` to open it directly.

### 2. Run `tyro:sys-install` (recommended)

```bash
php artisan tyro:sys-install
```

`tyro:sys-install` is the one command you need to bootstrap Tyro on a fresh project. Under the hood it:

1. Calls Laravel 12's `install:api` so Sanctum's config, migration, and middleware stack are registered.
2. Runs `php artisan migrate` (respecting `--force` when you provide it) to apply both Laravel's and Tyro's database tables.
3. Prompts to execute `tyro:seed-all --force`, inserting the default role/privilege catalog plus the bootstrap admin account.
4. Offers to run `tyro:user-prepare` immediately if you skip seeding so the correct traits and imports land on your user model.

Skipping `tyro:sys-install` means you must run each of those commands manually (`install:api`, `migrate`, `tyro:seed-all`, `tyro:user-prepare`). Most teams never need to—`tyro:sys-install` keeps the happy path automated and idempotent.

### 3. Run Tyro's migrations & seeders manually (optional)

```bash
php artisan migrate
# or, interactively
php artisan tyro:seed-all
```

> ℹ️ Seeding is technically optional, but highly recommended the first time you install Tyro. `TyroSeeder` inserts the default role catalogue (Administrator, User, Customer, Editor, All, Super Admin) and creates a ready-to-use `admin@tyro.project` superuser (password `tyro`). Skipping the seeder means you'll need to create equivalent roles and an admin account manually before any ability-gated routes will authorize.

### 4. Prepare your user model

Tyro augments whatever model you mark as `tyro.models.user` (defaults to `App\Models\User`). Run the following command to add the required traits:

```bash
php artisan tyro:user-prepare
```

The command above injects the required imports and trait usage automatically. Prefer editing manually? Here is what the class should look like:

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use HasinHayder\Tyro\Concerns\HasTyroRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasTyroRoles;
}
```

That is the only code change you need. The `HasTyroRoles` trait gives your User model powerful methods for checking roles, privileges, and managing suspensions. Tyro will automatically attach the default role (slug `user`) to future registrations.

## Using Tyro in Your Application

Tyro works everywhere in your Laravel application—controllers, middleware, Blade templates, jobs, policies, and more. Here's how to leverage its features:

### Checking Roles in Code

```php
// In a controller, service, or anywhere you have the user
$user = auth()->user();

// Check single role
if ($user->hasRole('admin')) {
    // User is an admin
}

// Check multiple roles (user must have ALL)
if ($user->hasRoles(['admin', 'super-admin'])) {
    // User has both roles
}

// Get all role slugs
$roles = $user->tyroRoleSlugs(); // ['admin', 'editor']
```

### Checking Privileges in Code

```php
$user = auth()->user();

// Check single privilege (uses Laravel's can() method)
if ($user->can('reports.run')) {
    // User has the reports.run privilege
}

// Check multiple privileges (user must have ALL)
if ($user->hasPrivileges(['reports.run', 'billing.view'])) {
    // User has both privileges
}

// Get all privilege slugs
$privileges = $user->tyroPrivilegeSlugs(); // ['reports.run', 'billing.view']
```

### Managing Roles Programmatically

```php
use HasinHayder\Tyro\Models\Role;

$user = User::find(1);

// Assign a role
$editorRole = Role::where('slug', 'editor')->first();
$user->assignRole($editorRole);

// Remove a role
$user->removeRole($editorRole);

// Get all roles as Eloquent models
$roles = $user->roles;
```

### Managing Privileges Programmatically

```php
use HasinHayder\Tyro\Models\Role;
use HasinHayder\Tyro\Models\Privilege;

$role = Role::where('slug', 'editor')->first();

// Attach privileges to a role
$privilege = Privilege::where('slug', 'reports.run')->first();
$role->privileges()->attach($privilege->id);

// Detach privileges
$role->privileges()->detach($privilege->id);

// Check if role has a privilege
if ($role->hasPrivilege('reports.run')) {
    // Role has this privilege
}
```

### User Suspension

```php
$user = User::find(1);

// Suspend user (revokes all tokens automatically)
$user->suspend('Pending account review');

// Check if suspended
if ($user->isSuspended()) {
    $reason = $user->getSuspensionReason();
}

// Unsuspend user
$user->unsuspend();
```

## Seeding (optional but recommended)

Tyro's `TyroSeeder` keeps every environment aligned by inserting the default roles, privileges, and bootstrap admin account. Trigger it manually or rerun it with `--force` any time you need to refresh local data:

```bash
php artisan tyro:seed-all --force
```

Running the seeder will:

-   Insert the Administrator, User, Customer, Editor, All, and Super Admin roles along with their mapped privileges.
-   Create the `admin@tyro.project` superuser (password `tyro`) so you always have a ready account.
-   Reapply protected role/privilege relationships.

Need something narrower? Use `tyro:seed-roles` or `tyro:seed-privileges` to refresh a single catalog without touching users.

#### HasTyroRoles Trait Reference

The `HasTyroRoles` trait gives your User model a complete API for roles, privileges, and suspensions. These methods are the same ones used by Tyro's routes and CLI commands, so your code stays consistent:

| Method                                   | Category   | Description                                                                                           |
| ---------------------------------------- | ---------- | ----------------------------------------------------------------------------------------------------- |
| `roles(): BelongsToMany`                 | Roles      | Returns the eager-loadable relationship for roles. Chain additional constraints as needed.            |
| `assignRole(Role $role): void`           | Roles      | Attaches a role without detaching existing ones.                                                      |
| `removeRole(Role $role): void`           | Roles      | Detaches the given role from the user.                                                                |
| `hasRole(string $role): bool`            | Roles      | Checks if the user has the specified role slug (supports wildcard `*`).                               |
| `hasRoles(array $roles): bool`           | Roles      | Returns `true` only if the user holds every role in the array.                                        |
| `tyroRoleSlugs(): array`                 | Roles      | Returns an array of all role slugs for the user (cached for performance).                             |
| `privileges(): Collection`               | Privileges | Returns all unique privileges inherited through the user's roles.                                     |
| `hasPrivileges(array $privileges): bool` | Privileges | Returns `true` only if the user has all specified privileges.                                         |
| `hasPrivilege(string $privilege): bool`  | Privileges | Checks if the user has a specific privilege.                                                          |
| `tyroPrivilegeSlugs(): array`            | Privileges | Returns an array of all privilege slugs for the user (cached for performance).                        |
| `can($ability, $arguments = []): bool`   | Gate       | Checks privilege, then role, then falls back to Laravel Gate. Use this for unified permission checks. |
| `suspend(?string $reason = null): void`  | Suspension | Suspends the user, stores optional reason, and revokes all Sanctum tokens.                            |
| `unsuspend(): void`                      | Suspension | Clears suspension without touching roles or privileges.                                               |
| `isSuspended(): bool`                    | Suspension | Returns `true` if the user is currently suspended.                                                    |
| `getSuspensionReason(): ?string`         | Suspension | Returns the stored suspension reason (or `null`).                                                     |

Tyro caches role and privilege slugs per user so authorization checks never hit the database on every request. The cache respects your `config/tyro.php` settings and is automatically invalidated when you modify roles, privileges, or assignments.

## Updating Tyro

When updating Tyro from an older version using `composer update`, you **must run migrations** to apply any new database changes (such as audit trail tables):

```bash
# Update the package
composer update hasinhayder/tyro

# Run migrations to apply new database changes
php artisan migrate

# Or, run migrations for Tyro specifically:
php artisan migrate --path=vendor/hasinhayder/tyro/database/migrations
```

> ⚠️ **Important:** Always run migrations after updating Tyro. Skipping this step may result in missing database tables and unexpected behavior.

### 5. Optional configuration

Override defaults in `config/tyro.php` to align with your app:

| Option                                   | Description                                                                                                   |
| ---------------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| `version`                                | Value returned by `/api/tyro/version`.                                                                        |
| `disable_commands`                       | When `true` (or `TYRO_DISABLE_COMMANDS=true`) Tyro skips registering its artisan commands.                    |
| `guard`                                  | Guard middleware used for protected routes (default `sanctum`).                                               |
| `route_prefix`                           | Route prefix (default `api`).                                                                                 |
| `disable_api`                            | When `true` (or `TYRO_DISABLE_API=true`) Tyro skips loading its built-in routes.                              |
| `route_middleware`                       | Global middleware stack for package routes.                                                                   |
| `models.user`                            | Fully qualified class name of your user model.                                                                |
| `models.privilege`                       | Fully qualified class name of the privilege model (defaults to Tyro\Models\Privilege).                        |
| `tables.roles/pivot`                     | Override the role table (default `roles`) or user-role pivot (default `user_roles`).                          |
| `tables.users`                           | Table name Tyro targets when publishing its suspension columns (default `users`).                             |
| `tables.privileges/role_privilege`       | Override the privilege table (default `privileges`) or the role-privilege pivot (default `privilege_role`).   |
| `default_user_role_slug`                 | Role attached to new users (`user` by default).                                                               |
| `protected_role_slugs`                   | Role slugs that cannot be mutated or deleted.                                                                 |
| `delete_previous_access_tokens_on_login` | Enforce single-session logins when `true`.                                                                    |
| `cache.enabled`                          | Toggle Tyro's per-user role/privilege cache (enabled by default).                                             |
| `cache.store`                            | Choose which cache store to use for the helper cache (`null` falls back to Laravel's default store).          |
| `cache.ttl`                              | Seconds to cache role/privilege slugs. `null` (or `<= 0`) caches indefinitely until Tyro invalidates entries. |
| `abilities.*`                            | Ability arrays checked by the middleware groups.                                                              |

Set `load_default_routes` to `false` if you prefer to include `routes/api.php` manually and merge Tyro endpoints into your own files.

### Disable Tyro commands or API via `.env`

Tyro registers a sizable CLI toolbox. If you would rather keep production shells lean (or limit what teammates can run), drop the following snippet into `.env` on the environments you wish to lock down:

```
TYRO_DISABLE_COMMANDS=true
```

With the variable set to `true`, Tyro skips registering every `tyro:*` artisan command while continuing to expose routes, middleware, and config overrides as usual. Remove the line (or set it to `false`) locally to regain the commands for development.

Need to turn off the bundled API endpoints entirely? Set:

```
TYRO_DISABLE_API=true
```

When `TYRO_DISABLE_API` is `true`, Tyro skips loading its `routes/api.php` file so you can provide a fully custom HTTP surface (or disable it in worker contexts).

Need an emergency token rotation? Run `php artisan tyro:auth-logout-all-users --force` to revoke every Sanctum token the package has issued.

### Password Security

Tyro includes robust password validation that you can configure via your `.env` file. These settings apply to user registration, password updates, and the `tyro:user-create` command.

| Environment Variable                  | Default | Description                                                                               |
| ------------------------------------- | ------- | ----------------------------------------------------------------------------------------- |
| `TYRO_PASSWORD_MIN_LENGTH`            | `8`     | Minimum number of characters required.                                                    |
| `TYRO_PASSWORD_MAX_LENGTH`            | `null`  | Maximum number of characters allowed (optional).                                          |
| `TYRO_PASSWORD_REQUIRE_NUMBERS`       | `false` | When `true`, passwords must contain at least one number.                                  |
| `TYRO_PASSWORD_REQUIRE_UPPERCASE`     | `false` | When `true`, passwords must contain at least one uppercase letter.                        |
| `TYRO_PASSWORD_REQUIRE_LOWERCASE`     | `false` | When `true`, passwords must contain at least one lowercase letter.                        |
| `TYRO_PASSWORD_REQUIRE_SPECIAL_CHARS` | `false` | When `true`, passwords must contain at least one special character (symbol).              |
| `TYRO_PASSWORD_REQUIRE_CONFIRMATION`  | `false` | When `true`, requires a matching `password_confirmation` field.                           |
| `TYRO_PASSWORD_CHECK_COMMON`          | `false` | When `true`, prevents users from using common/compromised passwords (via standard lists). |
| `TYRO_PASSWORD_DISALLOW_USER_INFO`    | `false` | When `true`, prevents passwords from containing the user's email or parts of their name.  |

Example `.env` configuration for high security:

```dotenv
TYRO_PASSWORD_MIN_LENGTH=12
TYRO_PASSWORD_REQUIRE_NUMBERS=true
TYRO_PASSWORD_REQUIRE_SPECIAL_CHARS=true
TYRO_PASSWORD_CHECK_COMMON=true
TYRO_PASSWORD_DISALLOW_USER_INFO=true
```

## Blade Directives

Tyro provides custom Blade directives for checking user roles and privileges directly in your views. All directives automatically return `false` if no user is authenticated.

### @userCan

Checks if the current user has a specific role or privilege (uses the `can()` method):

```blade
@userCan('admin')
    <div class="admin-panel">
        <h2>Admin Dashboard</h2>
        <p>Welcome to the admin area!</p>
    </div>
@enduserCan

@userCan('edit-posts')
    <button class="btn btn-primary">Edit Post</button>
@enduserCan
```

### @hasRole

Checks if the current user has a specific role:

```blade
@hasRole('admin')
    <p>Welcome, Admin!</p>
@endhasRole

@hasRole('editor')
    <a href="/dashboard/editor" class="nav-link">Editor Dashboard</a>
@endhasRole
```

### @hasAnyRole

Checks if the current user has any of the provided roles:

```blade
@hasAnyRole('admin', 'editor', 'moderator')
    <div class="management-tools">
        <h3>Management Tools</h3>
        <p>You have access to management features</p>
    </div>
@endhasAnyRole
```

### @hasAllRoles

Checks if the current user has all of the provided roles:

```blade
@hasAllRoles('admin', 'super-admin')
    <div class="super-admin-panel">
        <p>You have both admin and super-admin privileges</p>
        <button class="btn-danger">Critical Actions</button>
    </div>
@endhasAllRoles
```

### @hasPrivilege

Checks if the current user has a specific privilege:

```blade
@hasPrivilege('delete-users')
    <button class="btn btn-danger" onclick="deleteUser()">
        Delete User
    </button>
@endhasPrivilege

@hasPrivilege('view-reports')
    <a href="/reports" class="nav-link">
        <i class="icon-reports"></i> View Reports
    </a>
@endhasPrivilege
```

### @hasAnyPrivilege

Checks if the current user has any of the provided privileges:

```blade
@hasAnyPrivilege('edit-posts', 'delete-posts', 'publish-posts')
    <div class="post-actions">
        <h4>Post Management</h4>
        @hasPrivilege('edit-posts')
            <button>Edit</button>
        @endhasPrivilege
        @hasPrivilege('delete-posts')
            <button>Delete</button>
        @endhasPrivilege
        @hasPrivilege('publish-posts')
            <button>Publish</button>
        @endhasPrivilege
    </div>
@endhasAnyPrivilege
```

### @hasAllPrivileges

Checks if the current user has all of the provided privileges:

```blade
@hasAllPrivileges('create-invoices', 'approve-invoices')
    <button class="btn btn-success" onclick="createAndApproveInvoice()">
        Create and Approve Invoice
    </button>
@endhasAllPrivileges

@hasAllPrivileges('view-reports', 'export-reports')
    <div class="reports-section">
        <a href="/reports">View Reports</a>
        <button onclick="exportReport()">Export</button>
    </div>
@endhasAllPrivileges
```

### Combining Directives

You can nest and combine directives for complex authorization logic:

```blade
@hasRole('admin')
    <div class="admin-section">
        <h2>Admin Controls</h2>

        @hasPrivilege('manage-users')
            <a href="/admin/users">Manage Users</a>
        @endhasPrivilege

        @hasAnyPrivilege('view-reports', 'export-data')
            <a href="/admin/reports">Reports</a>
        @endhasAnyPrivilege
    </div>
@endhasRole

@hasAnyRole('editor', 'author')
    <div class="content-tools">
        @hasPrivilege('publish-posts')
            <button>Publish</button>
        @else
            <button disabled>Publish (requires approval)</button>
        @endhasPrivilege
    </div>
@endhasAnyRole
```

All directives leverage the methods from the `HasTyroRoles` trait and are automatically registered when the Tyro package is loaded. They provide a clean, readable way to conditionally display content based on user permissions without cluttering your Blade templates with PHP logic.

## Middleware for Route Protection

Tyro ships with a complete set of middleware aliases for protecting your routes—whether you're building an API, web app, or both. These are registered automatically when you install the package.

### Available Middleware

| Middleware              | When to use it                                                                | Example                                    |
| ----------------------- | ----------------------------------------------------------------------------- | ------------------------------------------ |
| `auth:sanctum`          | Ensures the request is authenticated via Sanctum (or your configured guard).  | `auth:sanctum`                             |
| `ability:comma,list`    | Require _all_ listed abilities (role slugs and/or privilege slugs).           | `'ability:admin,editor,reports.run'`       |
| `abilities:comma,list`  | Allow access when the token has _any_ of the listed abilities.                | `'abilities:billing.view,finance.approve'` |
| `role:comma,list`       | Require _all_ listed roles on the authenticated user (supports wildcard `*`). | `'role:admin,super-admin'`                 |
| `roles:comma,list`      | Allow access when the user holds _any_ of the listed roles.                   | `'roles:editor,admin'`                     |
| `privilege:comma,list`  | Require _all_ listed privileges directly on the authenticated user.           | `'privilege:reports.run,export.generate'`  |
| `privileges:comma,list` | Allow access when the user has _any_ of the listed privileges.                | `'privileges:billing.view,reports.run'`    |
| `tyro.log`              | Log request/response pairs for auditing.                                      | `'tyro.log'`                               |

### Protecting Routes (Examples)

```php
use Illuminate\Support\Facades\Route;

// Require user to be admin
Route::middleware(['auth:sanctum', 'role:admin'])
    ->get('admin/dashboard', AdminDashboardController::class);

// Allow either editor or admin
Route::middleware(['auth:sanctum', 'roles:editor,admin'])
    ->post('articles/publish', PublishArticleController::class);

// Require a specific privilege
Route::middleware(['auth:sanctum', 'privilege:reports.run'])
    ->get('reports', ReportsController::class);

// Allow any of multiple privileges
Route::middleware(['auth:sanctum', 'privileges:billing.view,reports.run'])
    ->get('dashboard/widgets', DashboardController::class);

// Audit sensitive routes
Route::middleware(['auth:sanctum', 'role:admin', 'tyro.log'])
    ->delete('users/{user}', [UserController::class, 'destroy']);
```

### Using Abilities in Policies

```php
public function destroy(User $user, Report $report): bool
{
    return $user->hasRole('admin') || $user->can('reports.delete');
}
```

## Audit Trail

Tyro includes a database-backed audit trail to track administrative actions. This is essential for security auditing and compliance.

### Recorded Events

-   **User Actions:** Role assignment, role removal, user suspension, and unsuspension.
-   **Role Actions:** Role creation, updates, and deletion.
-   **Privilege Actions:** Privilege creation, updates, and deletion.

### CLI Management

```bash
# View recent audit logs
php artisan tyro:audit-list --limit=50

# Filter by event
php artisan tyro:audit-list --event=role.assigned

# Purge old logs (older than 30 days)
php artisan tyro:audit-purge
```

### Configuration

Customize retention and toggle the feature in `config/tyro.php`:

```php
'audit' => [
    'enabled' => true,
    'retention_days' => 30,
],
```

For more details, see the [Detailed Audit Trail Documentation](docs/audit-trail.md).

## CLI Commands (40+ Tools)

Tyro ships with a powerful CLI toolbox for managing users, roles, privileges, and tokens—perfect for automation, CI/CD pipelines, and incident response.

### User Management Commands

| Command                 | Purpose                                                                 |
| ----------------------- | ----------------------------------------------------------------------- |
| `tyro:user-create`      | Create a new user with name/email/password and attach the default role. |
| `tyro:user-update`      | Update a user's details by ID or email.                                 |
| `tyro:user-delete`      | Delete a user (prevents deleting the last admin).                       |
| `tyro:user-list`        | List all users with suspension status.                                  |
| `tyro:user-list-with-roles` | List all users with their assigned roles.                           |
| `tyro:user-suspend`     | Suspend a user with optional reason (revokes all tokens).               |
| `tyro:user-unsuspend`   | Remove suspension from a user.                                          |
| `tyro:user-suspended`   | List all suspended users with reasons.                                  |

### Role Management Commands

| Command                      | Purpose                                            |
| ---------------------------- | -------------------------------------------------- |
| `tyro:role-list`                 | Display all roles with user counts.                |
| `tyro:role-list-with-privileges` | Display roles with their attached privileges.      |
| `tyro:role-create`           | Create a new role.                                 |
| `tyro:role-update`           | Update a role's name or slug.                      |
| `tyro:role-delete`           | Delete a role (protected roles cannot be deleted). |
| `tyro:role-assign`           | Assign a role to a user.                           |
| `tyro:role-remove`           | Remove a role from a user.                         |
| `tyro:role-users`            | List all users with a specific role.               |
| `tyro:user-roles`            | Display a user's roles and their privileges.       |

### Privilege Management Commands

| Command                 | Purpose                                           |
| ----------------------- | ------------------------------------------------- |
| `tyro:privilege-list`       | List all privileges and which roles have them.    |
| `tyro:privilege-create`    | Create a new privilege.                           |
| `tyro:privilege-update` | Update a privilege's name or slug.                |
| `tyro:privilege-delete` | Delete a privilege.                               |
| `tyro:privilege-attach` | Attach a privilege to a role.                     |
| `tyro:privilege-detach` | Detach a privilege from a role.                   |
| `tyro:user-privileges`  | Display all privileges a user inherits via roles. |

### Authentication & Token Commands

| Command                 | Purpose                                                      |
| ----------------------- | ------------------------------------------------------------ |
| `tyro:auth-login`            | Mint a Sanctum token for a user (by ID or email).            |
| `tyro:user-token`      | Mint a token without password prompt (respects suspensions). |
| `tyro:auth-logout`           | Revoke a specific token.                                     |
| `tyro:auth-logout-all`       | Revoke all tokens for a specific user.                       |
| `tyro:auth-logout-all-users` | Revoke all tokens for all users (emergency rotation).        |
| `tyro:auth-me`               | Inspect a token to see user and abilities.                   |

### Audit Management Commands

| Command            | Purpose                                  |
| ------------------ | ---------------------------------------- |
| `tyro:audit-list`       | List recent audit logs with filters.     |
| `tyro:audit-purge` | Purge old audit logs based on retention. |

### Setup & Maintenance Commands

| Command                   | Purpose                                                 |
| ------------------------- | ------------------------------------------------------- |
| `tyro:sys-install`            | Full installation: migrations, seeds, user model setup. |
| `tyro:user-prepare` | Add required traits to your User model.                 |
| `tyro:seed-all`               | Run full seeder (roles, privileges, admin user).        |
| `tyro:seed-roles`         | Seed only the default roles.                            |
| `tyro:seed-privileges`    | Seed only the default privileges.                       |
| `tyro:role-purge`        | Remove all roles and assignments.                       |
| `tyro:privilege-purge`   | Remove all privileges and assignments.                  |
| `tyro:publish-config`     | Publish the config file.                                |
| `tyro:publish-migrations` | Publish migration files.                                |
| `tyro:sys-version`            | Display current Tyro version.                           |
| `tyro:sys-about`              | Display Tyro info and links.                            |
| `tyro:sys-doc`                | Open documentation.                                     |
| `tyro:sys-star`               | Open GitHub to star the repo ⭐                         |
| `tyro:sys-postman` | Open the Postman collection URL.                        |

All commands accept non-interactive `--option` flags, making them perfect for scripts and automation.

## User Suspension

Tyro includes first-class user suspension support to freeze accounts without deleting them:

```php
// Suspend a user (revokes all tokens automatically)
$user->suspend('Pending account review');

// Check if suspended
if ($user->isSuspended()) {
    $reason = $user->getSuspensionReason();
}

// Unsuspend
$user->unsuspend();
```

**CLI workflow:**

```bash
# Suspend a user
php artisan tyro:user-suspend --user=admin@example.com --reason="Manual review"

# View all suspended users
php artisan tyro:user-suspended

# Unsuspend a user
php artisan tyro:user-unsuspend --user=admin@example.com
```

Suspended users cannot log in, and all their existing tokens are immediately revoked.

## Optional: REST API Endpoints

If you need REST endpoints for managing users, roles, and privileges (useful for admin panels, mobile apps, etc.), Tyro includes a complete API surface. These are enabled by default but can be disabled if you don't need them.

### Available Endpoints

Tyro registers the following endpoints (prefixed by `tyro.route_prefix`, default `api`):

-   **Public:** `GET /tyro`, `GET /tyro/version`, `POST /login`, `POST /users` (registration)
-   **Authenticated:** `GET /me`, `PUT|PATCH|POST /users/{user}`
-   **Admin-only:** User CRUD, Role CRUD, Privilege CRUD, Audit Log CRUD, user-role assignments, role-privilege assignments, user suspension

### Disabling the API

If you only need Tyro's code-level features (roles, privileges, CLI commands), disable the API:

```env
TYRO_DISABLE_API=true
```

### API Usage Examples

#### Authentication

```bash
# Register a user
curl -X POST http://localhost/api/users \
	-H "Accept: application/json" \
	-H "Content-Type: application/json" \
	-d '{"name":"Jane User","email":"jane@example.com","password":"password","password_confirmation":"password"}'

# Login
curl -X POST http://localhost/api/login \
	-H "Accept: application/json" \
	-H "Content-Type: application/json" \
	-d '{"email":"admin@tyro.project","password":"tyro"}'
```

#### Role Management (Admin)

```bash
TOKEN="<your-token>"

# List roles
curl http://localhost/api/roles -H "Authorization: Bearer ${TOKEN}"

# Attach a role to a user
curl -X POST http://localhost/api/users/5/roles \
	-H "Accept: application/json" \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer ${TOKEN}" \
	-d '{"role_id":4}'
```

#### Privilege Management (Admin)

```bash
# List privileges
curl http://localhost/api/privileges -H "Authorization: Bearer ${TOKEN}"

# Create a privilege
curl -X POST http://localhost/api/privileges \
	-H "Accept: application/json" \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer ${TOKEN}" \
	-d '{"name":"Run Reports","slug":"reports.run"}'

# Attach privilege to role
curl -X POST http://localhost/api/roles/4/privileges \
	-H "Accept: application/json" \
	-H "Content-Type: application/json" \
	-H "Authorization: Bearer ${TOKEN}" \
	-d '{"privilege_id":2}'
```

## FAQ

### How do I get the authenticated user's roles inside a controller?

```php
use Illuminate\Http\Request;

class ProfileController
{
	public function __invoke(Request $request)
	{
		$roleSlugs = $request->user()->tyroRoleSlugs();

		// Or eager-load Role models when you need metadata
		$roles = $request->user()->roles()->select(['id', 'name', 'slug'])->get();

		return response()->json(compact('roleSlugs', 'roles'));
	}
}
```

### How do I get the authenticated user's privileges?

```php
use Illuminate\Http\Request;

class ApiTokenController
{
	public function show(Request $request)
	{
		$privileges = $request->user()->tyroPrivilegeSlugs();

		return response()->json(['privileges' => $privileges]);
	}
}
```

### How do I assign or remove roles to a user from code?

```php
use HasinHayder\Tyro\Models\Role;
use App\Models\User;

class UserRoleController
{
	public function assignRoles()
	{
		$user = User::find(1);

		// Assign a single role
		$editorRole = Role::where('slug', 'editor')->first();
		$user->assignRole($editorRole);

		// Assign multiple roles
		$adminRole = Role::where('slug', 'admin')->first();
		$user->assignRole($adminRole);

		// Or use the roles relationship directly
		$customerRole = Role::where('slug', 'customer')->first();
		$user->roles()->attach($customerRole->id);
	}

	public function removeRoles()
	{
		$user = User::find(1);

		// Remove a single role
		$editorRole = Role::where('slug', 'editor')->first();
		$user->removeRole($editorRole);

		// Or use the roles relationship directly
		$user->roles()->detach($editorRole->id);

		// Remove all roles
		$user->roles()->detach();
	}
}
```

### How do I assign or remove privileges to a role?

```php
use HasinHayder\Tyro\Models\Role;
use HasinHayder\Tyro\Models\Privilege;

class RolePrivilegeController
{
	public function assignPrivileges()
	{
		$role = Role::where('slug', 'editor')->first();

		// Assign a single privilege
		$reportPrivilege = Privilege::where('slug', 'reports.run')->first();
		$role->privileges()->attach($reportPrivilege->id);

		// Assign multiple privileges at once
		$billingPrivilege = Privilege::where('slug', 'billing.view')->first();
		$exportPrivilege = Privilege::where('slug', 'reports.export')->first();
		$role->privileges()->attach([
			$billingPrivilege->id,
			$exportPrivilege->id,
		]);

		// Or sync privileges (replaces all existing privileges)
		$role->privileges()->sync([
			$reportPrivilege->id,
			$billingPrivilege->id,
		]);
	}

	public function removePrivileges()
	{
		$role = Role::where('slug', 'editor')->first();

		// Remove a single privilege
		$reportPrivilege = Privilege::where('slug', 'reports.run')->first();
		$role->privileges()->detach($reportPrivilege->id);

		// Remove multiple privileges
		$role->privileges()->detach([
			$reportPrivilege->id,
			$billingPrivilege->id,
		]);

		// Remove all privileges from the role
		$role->privileges()->detach();
	}
}
```

### How do I get the list of privileges in a role?

```php
use HasinHayder\Tyro\Models\Role;

class RolePrivilegesController
{
	public function show(Role $role)
	{
		// Load privileges relationship
		$role->loadMissing('privileges:id,name,slug');

		return response()->json([
			'role' => $role->only(['id', 'name', 'slug']),
			'privileges' => $role->privileges,
		]);
	}

	public function getPrivilegeSlugs(Role $role)
	{
		// Get only the privilege slugs as an array
		$privilegeSlugs = $role->privileges()->pluck('slug')->toArray();

		return response()->json(['privilege_slugs' => $privilegeSlugs]);
	}
}
```

### How do I check if a role has specific privileges?

The `Role` model includes `hasPrivilege()` and `hasPrivileges()` methods for checking privileges:

```php
use HasinHayder\Tyro\Models\Role;

class RoleCheckController
{
	public function checkPrivileges()
	{
		$role = Role::where('slug', 'editor')->first();

		// Check if role has a single privilege
		if ($role->hasPrivilege('reports.run')) {
			// Role has the reports.run privilege
		}

		// Check if role has ALL specified privileges
		if ($role->hasPrivileges(['reports.run', 'billing.view'])) {
			// Role has both reports.run AND billing.view privileges
		}

		// Check if role has ANY of the specified privileges
		$hasAny = $role->privileges()
			->whereIn('slug', ['reports.run', 'billing.view'])
			->exists();
	}
}
```

### How do I check if the authenticated user has particular roles?

```php
use Illuminate\Http\Request;

class ArticleController
{
	public function store(Request $request)
	{
		if (! $request->user()->hasRoles(['editor', 'admin'])) {
			abort(403, 'Editors or admins only.');
		}

		// Create the article
	}

	public function destroy(Request $request)
	{
		if (! $request->user()->hasRole('super-admin')) {
			abort(403, 'Super admins only.');
		}

		// Delete the article
	}
}
```

### How do I check if the authenticated user has specific privileges?

```php
use Illuminate\Http\Request;

class BillingReportController
{
	public function index(Request $request)
	{
		if (! $request->user()->hasPrivileges(['reports.run', 'billing.view'])) {
			abort(403, 'Missing reporting privileges.');
		}

		// Build the report
	}

	public function export(Request $request)
	{
		if (! $request->user()->can('reports.export')) {
			abort(403, 'Missing export privilege.');
		}

		// Return the file download
	}
}
```

### How do I check if a user is suspended and inspect the reason?

```php
use Illuminate\Http\Request;

class LoginStatusController
{
	public function __invoke(Request $request)
	{
		$user = $request->user();

		if ($user->isSuspended()) {
			return response()->json([
				'suspended' => true,
				'reason' => $user->getSuspensionReason(),
			], 423);
		}

		return response()->json(['suspended' => false]);
	}
}
```

## Database assets

-   `database/migrations/*` creates the `roles`, `user_roles`, `privileges`, and `privilege_role` tables (configurable via `config/tyro.php`).
-   `database/seeders/TyroSeeder` seeds the core roles, default privileges, and creates the admin bootstrap user.
-   `database/factories/UserFactory` targets whichever user model you configure, and `PrivilegeFactory` speeds up testing custom privileges.

## Development

-   `src/Providers/TyroServiceProvider.php` handles route loading, publishing, and middleware aliases.
-   Controllers live under `src/Http/Controllers/*` and operate against the configurable user model.
-   `routes/api.php` declares all endpoints and ability middleware in one place.

Contributions are welcome! Please open an issue or pull request with improvements, bug fixes, or new ideas.

## License

Tyro is open-sourced software licensed under the [MIT license](LICENSE).
