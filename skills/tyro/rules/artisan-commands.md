# Artisan Commands

## Why It Matters

Tyro ships 49 Artisan commands that serve as the primary public interface for operators, CI pipelines, and automated workflows. Every administrative operation (user creation, role assignment, privilege management, suspension, seeding, installation) is available via the CLI. Commands are public APIs — changing signatures, removing prompts, or altering output formats breaks scripts and integrations. The command suite follows strict naming (`tyro:{entity}-{action}`), inherits from `BaseTyroCommand`, and must gracefully handle both interactive and non-interactive modes.

## Incorrect

```php
// Not using BaseTyroCommand helpers — duplicating user/role resolution
public function handle() {
    $user = User::where('email', $this->argument('user'))->first();
    if (! $user) {
        $this->error('User not found');
        return 1;
    }
}

// Silent failure — no exit code, no error message
public function handle() {
    $role = Role::find($this->argument('role'));
    $role->delete(); // What if null?
}

// Interactive-only — breaks CI
public function handle() {
    $name = $this->ask('Role name?');
    $slug = $this->ask('Role slug?');
    // No --force or non-interactive fallback
}

// Missing alias — user has to remember tyro:role-add instead of tyro:add-role
protected $signature = 'tyro:new-role';
// No $aliases property
```

## Correct

```php
// Use BaseTyroCommand helpers
protected $signature = 'tyro:assign-role {user : User ID or email} {role : Role ID or slug}';
protected $description = 'Assign a role to a user';

public function handle(): int {
    $user = $this->findUser($this->argument('user'));
    if (! $user) {
        $this->error('User not found.');
        return self::FAILURE;
    }
    $role = $this->findRole($this->argument('role'));
    if (! $role) {
        $this->error('Role not found.');
        return self::FAILURE;
    }
    if (method_exists($user, 'assignRole')) {
        $user->assignRole($role);
    } else {
        $user->roles()->attach($role);
        TyroCache::forgetUser($user);
    }
    $this->info("Role [{$role->slug}] assigned to user [{$user->email}].");
    return self::SUCCESS;
}

// Safety prompt for destructive operations with --force bypass
protected $signature = 'tyro:delete-role {role : Role ID or slug} {--force : Skip confirmation}';

public function handle(): int {
    $role = $this->findRole($this->argument('role'));
    if (! $role) {
        $this->error('Role not found.');
        return self::FAILURE;
    }
    if (in_array($role->slug, config('tyro.protected_role_slugs', []), true)) {
        $this->error('Cannot delete protected role.');
        return self::FAILURE;
    }
    if (! $this->option('force') && ! $this->confirm("Delete role [{$role->slug}]?")) {
        $this->warn('Cancelled.');
        return self::SUCCESS;
    }
    TyroCache::forgetUsersByRole($role);
    $role->delete();
    $this->info("Role [{$role->slug}] deleted.");
    return self::SUCCESS;
}

// Alias for discoverability
protected $signature = 'tyro:seed-all {--force : Run without confirmation}';
protected $aliases = ['tyro:seed'];
protected $description = 'Seed default roles, privileges, and bootstrap admin user';

// Dry-run support for install operations
protected $signature = 'tyro:sys-install
    {--force : Pass --force to migrate}
    {--dry-run : Print steps without executing}';

public function handle(): int {
    if ($this->option('dry-run')) {
        $this->warn('Dry run: skipped install:api and migrate.');
        return self::SUCCESS;
    }
    // ...
}

// CI-compatible — non-interactive mode
public function handle(): int {
    if ($this->input->isInteractive()) {
        if ($this->confirm('Seed data now?', true)) {
            // interactive
        }
    } else {
        // non-interactive: proceed with defaults
    }
}
```

## Notes

- Every command inherits from `BaseTyroCommand` which provides: `userClass()`, `newUserQuery()`, `findUser($identifier)`, `findRole($identifier)`, `findPrivilege($identifier)`, `defaultRole()`, `abilitiesForUser(User $user)`, `openUrl($url)`.
- Exceptions: `SetupAiSkillCommand` and `UpdateConfigCommand` extend `Command` directly (not `BaseTyroCommand`).
- The `findUser()` resolves by numeric ID or email. `findRole()` and `findPrivilege()` resolve by numeric ID or slug.
- Commands are conditionally disabled via `config('tyro.disable_commands')` or `TYRO_DISABLE_COMMANDS=true`.
- Command names follow `tyro:{entity}-{action}`: `tyro:role-create`, `tyro:role-delete`, `tyro:role-assign`, `tyro:privilege-attach`, `tyro:user-create`, `tyro:sys-install`, `tyro:auth-login`.
- System commands use `tyro:sys-{action}`: `tyro:sys-install`, `tyro:sys-about`, `tyro:sys-doc`, `tyro:sys-version`, `tyro:sys-star`, `tyro:sys-test`.
- Auth commands use `tyro:auth-{action}`: `tyro:auth-login`, `tyro:auth-logout`, `tyro:auth-logout-all`, `tyro:auth-logout-all-users`, `tyro:auth-me`.
- All commands must return `self::SUCCESS` (0) or `self::FAILURE` (1).
- Destructive commands (delete, flush, purge) require confirmation and/or `--force`.
- `SeedCommand` and `InstallCommand` cache-bust with `TyroCache::forgetAllUsersWithRoles()` after mutation.
- Audit events are logged inside command handlers or delegated to the model methods (e.g., `$role->attachPrivilege()` logs `privilege.attached`).

### Complete Command Reference

All 49 registered commands with their signatures and aliases:

| Signature | Aliases | Description |
|---|---|---|
| `tyro:sys-about` | `tyro:about` | Show Tyro's mission, version, and author details |
| `tyro:privilege-create {slug?} {--name=} {--description=}` | `tyro:add-privilege` | Create a new privilege |
| `tyro:role-create {--name=} {--slug=}` | `tyro:create-role` | Create a new role |
| `tyro:role-assign {--user=} {--role=}` | `tyro:assign-role` | Attach a role to a user |
| `tyro:privilege-attach {privilege?} {role?}` | `tyro:attach-privilege` | Attach a privilege to a role |
| `tyro:user-create {--name=} {--email=} {--password=}` | `tyro:create-user` | Create a user with default role |
| `tyro:privilege-delete {privilege?} {--force}` | `tyro:delete-privilege` | Delete a privilege |
| `tyro:role-delete {--role=} {--force}` | `tyro:delete-role` | Delete a role (protected slugs guarded) |
| `tyro:user-delete {--user=} {--force}` | `tyro:delete-user` | Delete a user (last-admin guard) |
| `tyro:role-remove {--user=} {--role=}` | `tyro:delete-user-role` | Detach a role from a user |
| `tyro:privilege-detach {privilege?} {role?}` | `tyro:detach-privilege` | Detach a privilege from a role |
| `tyro:sys-doc {--no-open}` | `tyro:doc` | Open documentation in browser |
| `tyro:role-purge {--force}` | `tyro:purge-roles` | Truncate roles and pivot tables |
| `tyro:sys-install {--force} {--dry-run}` | `tyro:install` | Bootstrap: Sanctum, migrations, seed, prepare model |
| `tyro:audit-list {--limit=20} {--event=} {--from=} {--to=}` | `tyro:audit` | Display recent audit logs with filters |
| `tyro:privilege-list` | `tyro:privileges` | List all privileges with roles |
| `tyro:role-list` | `tyro:roles` | List all roles |
| `tyro:role-list-with-privileges` | `tyro:roles-with-privileges` | List roles with attached privileges |
| `tyro:user-list` | `tyro:users` | List all users |
| `tyro:user-list-with-roles` | `tyro:users-with-roles` | List users with their roles |
| `tyro:auth-login {--user=} {--email=} {--password=} {--name=}` | `tyro:login` | Mint a Sanctum token via CLI |
| `tyro:auth-logout-all {--user=} {--force}` | `tyro:logout-all` | Delete all tokens for a user |
| `tyro:auth-logout-all-users {--force}` | `tyro:logout-all-users` | Revoke every token for all users |
| `tyro:auth-logout {token?} {--token=}` | `tyro:logout` | Delete a single token |
| `tyro:auth-me {token?} {--token=}` | `tyro:me` | Inspect which user a token belongs to |
| `tyro:sys-postman {--no-open}` | `tyro:postman-collection`, `tyro:postman` | Open Postman collection |
| `tyro:user-prepare {--path=}` | `tyro:prepare-user-model` | Add HasApiTokens + HasTyroRoles to User model |
| `tyro:publish-config {--force}` | (none) | Publish config file |
| `tyro:publish-migrations {--force}` | (none) | Publish migration files |
| `tyro:audit-purge {--days=} {--force}` | (none) | Purge old audit logs (respects retention) |
| `tyro:privilege-purge {--force}` | `tyro:purge-privileges` | Delete every privilege and detach from roles |
| `tyro:user-token {user?} {--name=}` | `tyro:quick-token` | Mint a token without credentials prompt |
| `tyro:role-users {role?}` | (none) | List users assigned to a role |
| `tyro:sys-test {--pest} {--phpunit} {--filter=} {--testsuite=} {--coverage} {--dry-run} {--extra=*}` | `tyro:run-tests`, `tyro:test` | Run project test suite |
| `tyro:seed-all {--force}` | `tyro:seed` | Seed roles, privileges, and admin user |
| `tyro:seed-privileges {--force}` | (none) | Seed privilege definitions |
| `tyro:seed-roles {--force}` | (none) | Seed role definitions |
| `tyro:setup-ai-skill` | (none) | Install AI skill for agent (Kilo, Claude, Copilot, etc.) |
| `tyro:sys-star {--no-open}` | `tyro:star` | Open GitHub repo for starring |
| `tyro:user-suspend {--user=} {--reason=} {--unsuspend} {--force}` | `tyro:suspend-user` | Suspend or unsuspend a user |
| `tyro:user-suspended` | `tyro:suspended-users` | List all suspended users |
| `tyro:user-unsuspend {--user=} {--force}` | `tyro:unsuspend-user` | Lift a user's suspension |
| `tyro:update-config {--with-backup}` | (none) | Update config with latest version |
| `tyro:privilege-update {--privilege=} {--name=} {--slug=} {--description=}` | `tyro:update-privilege` | Update a privilege |
| `tyro:role-update {--role=} {--name=} {--slug=}` | `tyro:update-role` | Update a role (protected slugs guarded) |
| `tyro:user-update {--user=} {--name=} {--email=} {--password=}` | `tyro:update-user` | Update a user |
| `tyro:user-privileges {user?}` | (none) | Display privileges inherited by a user |
| `tyro:user-roles {user?}` | (none) | Display a user's roles and privileges |
| `tyro:sys-version` | `tyro:version` | Show installed Tyro version |

### Special Commands

**`tyro:setup-ai-skill`** — Installs the Tyro skill directory (`skills/tyro/`) into the project for a chosen AI agent. Supports Kilo (`.kilo/skills/tyro`), Claude (`.claude/skills/tyro`), Copilot (`.github/skills/tyro`), Codex (`.codex/skills/tyro`), Gemini (`.gemini/skills/tyro`), Laravel Boost (`.ai/skills/tyro`). Always also installs to the universal discovery directory (`.agents/skills/tyro`). Uses atomic backup/restore for safe installation.

**`tyro:user-prepare`** — Analyzes and modifies the User model file on disk to add `HasApiTokens` and `HasTyroRoles` traits. Handles import parsing, trait insertion, and class body modification. Accepts `--path=` to override the default User model location.

**`tyro:sys-version`** — Prints the installed Tyro version (hardcoded in `VersionCommand`), Laravel version, and PHP version. Contains changelog history in source comments.

## Cross References

- audit-logs.md (commands that trigger audit events)
- caching.md (cache invalidation in command handlers)
- security.md (protected role checks in commands)
- naming-conventions.md (command naming patterns)
