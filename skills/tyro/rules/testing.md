# Testing

## Why It Matters

Tyro is a security framework — bugs in authorization logic can lead to privilege escalation or data exposure. Every permission check, role assignment, cache invalidation path, middleware gate, Blade directive, and Artisan command must be tested. The Pest PHP test suite uses Orchestra Testbench for Laravel-specific testing, an in-memory SQLite database, and a standardized fixture User model with the `HasTyroRoles` trait.

## Incorrect

Testing authorization without a real authenticated user:

```php
// Do NOT test without authenticating the acting user
$response = $this->getJson('/api/roles');
// This would hit the auth middleware and fail
```

Testing cache behavior without verifying cache busting:

```php
// Do NOT test cache without verifying invalidation
$user->assignRole($role);
$this->assertTrue($user->hasRole('admin'));
// Missing: verify that cache actually was flushed
```

Writing integration tests that depend on external services or databases:

```php
// Do NOT depend on external databases
$this->assertDatabaseHas('users', ['email' => 'test@example.com']);
// Requires a real MySQL connection rather than SQLite :memory:
```

## Correct

Use the TestCase base class patterns (tests/TestCase.php):

```php
// Extend Orchestra Testbench with TyroServiceProvider
abstract class TestCase extends Orchestra {
    protected function defineEnvironment($app): void {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('tyro.models.user', User::class);
    }

    protected function getPackageProviders($app): array {
        return [
            TyroServiceProvider::class,
            \Laravel\Sanctum\SanctumServiceProvider::class,
        ];
    }
}
```

Test role and privilege checks with authenticated users (tests/Unit/HasTyroRolesTest.php):

```php
public function test_user_can_returns_true_when_role_has_privilege(): void {
    $userClass = config('tyro.models.user');
    $user = $userClass::factory()->create();

    $role = Role::where('slug', 'user')->firstOrFail();
    $privilege = Privilege::factory()->create([
        'slug' => 'reports.generate',
        'name' => 'Generate Reports',
    ]);

    $role->privileges()->syncWithoutDetaching($privilege);
    $user->roles()->attach($role);

    $this->assertTrue($user->fresh()->can('reports.generate'));
}
```

Test cache invalidation explicitly (tests/Unit/HasTyroRolesTest.php):

```php
public function test_role_slug_cache_requires_invalidation(): void {
    config(['cache.default' => 'array', 'tyro.cache.store' => 'array', 'tyro.cache.enabled' => true]);
    Cache::store('array')->clear();

    $user = User::factory()->create();
    $role = Role::where('slug', 'user')->firstOrFail();

    $user->roles()->attach($role);
    $this->assertTrue($user->fresh()->hasRole('user'));

    $user->roles()->detach($role);
    $this->assertTrue($user->fresh()->hasRole('user')); // Stale cache

    TyroCache::forgetUser($user);
    $this->assertFalse($user->fresh()->hasRole('user')); // Now correct
}
```

Test Artisan commands with expectsOutput/expectsQuestion (tests/Feature/ConsoleCommandTest.php):

```php
public function test_create_user_command_creates_user(): void {
    $email = 'cli-user@example.com';

    $this->artisan('tyro:user-create', [
        '--name' => 'CLI User',
        '--email' => $email,
        '--password' => 'secret-password',
    ])->assertExitCode(0);

    $this->assertDatabaseHas('users', ['email' => $email]);
}

public function test_delete_privilege_command_prompts_for_identifier(): void {
    $privilege = Privilege::factory()->create(['slug' => 'obsolete.prompt']);

    $this->artisan('tyro:privilege-delete', ['--force' => true])
        ->expectsQuestion('Which privilege slug or ID should be deleted?', $privilege->slug)
        ->expectsOutputToContain('deleted')
        ->assertExitCode(0);
}
```

Test middleware with authenticated requests (tests/Feature/RoleMiddlewareTest.php):

```php
public function test_role_middleware_allows_matching_role(): void {
    $user = User::factory()->create();
    $adminRole = Role::where('slug', 'admin')->first();
    $user->roles()->attach($adminRole);

    \Laravel\Sanctum\Sanctum::actingAs($user);

    $this->getJson('/api/roles')
        ->assertOk();
}

public function test_role_middleware_denies_wrong_role(): void {
    $user = User::factory()->create();
    $userRole = Role::where('slug', 'user')->first();
    $user->roles()->attach($userRole);

    \Laravel\Sanctum\Sanctum::actingAs($user);

    $this->getJson('/api/roles')
        ->assertForbidden();
}
```

Test Blade directives (tests/Feature/BladeDirectiveTest.php):

```php
public function test_hasrole_directive_renders_when_user_has_role(): void {
    $user = User::factory()->create();
    $user->assignRole(Role::where('slug', 'admin')->first());
    $this->actingAs($user);

    $rendered = Blade::render('@hasrole("admin") YES @endhasrole');
    $this->assertStringContainsString('YES', $rendered);
}
```

Test disabled commands mode (tests/Feature/DisabledCommandsTest.php):

```php
public function test_commands_are_not_registered_when_disabled(): void {
    $this->disableTyroCommands = true;
    // Reboot app with disabled commands
    $this->refreshApplication();

    $this->assertFalse(Artisan::has('tyro:role-list'));
}
```

## Notes

- Run tests with `vendor/bin/pest` — uses Pest PHP 3.x with the Pest Laravel plugin.
- Test fixtures in `tests/Fixtures/User.php` use `HasApiTokens`, `HasFactory`, `HasTyroRoles`, and `Notifiable`.
- All tests use SQLite `:memory:` — there is no external database dependency.
- The `TyroSeeder` is called in `setUp()` to populate default roles, privileges, and the admin user.
- Cache tests must use `'array'` cache driver and call `Cache::store('array')->clear()` between scenarios.
- Command tests use both `$this->artisan()->expectsOutput()` (captures stdout) and `Artisan::call()` (returns exit code).
- Test factories are in `database/factories/` with classmap autoloading.
- The `TestCase::$disableTyroCommands` and `$disableTyroApi` flags let you test disabled modes.

## Cross References

- [backward-compatibility.md](backward-compatibility.md) — regression test requirements for deprecations
- [performance.md](performance.md) — performance regression test patterns
- [extensibility.md](extensibility.md) — testing custom extensions
- [artisan-commands.md](artisan-commands.md) — command testing patterns
