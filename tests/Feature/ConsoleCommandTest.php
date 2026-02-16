<?php

namespace HasinHayder\Tyro\Tests\Feature;

use HasinHayder\Tyro\Models\Privilege;
use HasinHayder\Tyro\Models\Role;
use HasinHayder\Tyro\Tests\Fixtures\FakeInstallCommand;
use HasinHayder\Tyro\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\Console\Tester\CommandTester;

class ConsoleCommandTest extends TestCase {
    public function test_create_user_command_creates_user(): void {
        $email = 'cli-user@example.com';

        $this->artisan('tyro:user-create', [
            '--name' => 'CLI User',
            '--email' => $email,
            '--password' => 'secret-password',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'email' => $email,
        ]);
    }

    public function test_roles_command_outputs_roles(): void {
        $this->artisan('tyro:role-list')
            ->expectsOutputToContain('Administrator')
            ->assertExitCode(0);
    }

    public function test_roles_with_privileges_command_outputs_privileges(): void {
        $role = Role::where('slug', 'editor')->first();
        $privilege = Privilege::factory()->create([
            'slug' => 'content.review',
        ]);

        $role->privileges()->syncWithoutDetaching([$privilege->id]);

        $this->assertTrue(
            $role->fresh()->privileges->contains('slug', 'content.review'),
            'Attached privilege should be visible on the role before running the command'
        );

        $exitCode = Artisan::call('tyro:role-list-with-privileges');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('editor', $output);
        $this->assertStringContainsString('content.review', str_replace(PHP_EOL, '', $output));
    }

    public function test_privileges_command_outputs_privileges(): void {
        $this->artisan('tyro:privilege-list')
            ->expectsOutputToContain('report.generate')
            ->assertExitCode(0);
    }

    public function test_add_privilege_command_creates_privilege(): void {
        $this->artisan('tyro:privilege-create', [
            'slug' => 'ci.build',
            '--name' => 'CI Build',
            '--description' => 'Allows triggering CI builds',
        ])->expectsOutputToContain('ci.build')
            ->assertExitCode(0);

        $this->assertDatabaseHas(config('tyro.tables.privileges', 'privileges'), ['slug' => 'ci.build']);
    }

    public function test_delete_privilege_command_deletes_privilege(): void {
        $privilege = Privilege::factory()->create([
            'slug' => 'obsolete.permission',
        ]);

        $this->artisan('tyro:privilege-delete', [
            'privilege' => $privilege->slug,
            '--force' => true,
        ])->expectsOutputToContain('deleted')
            ->assertExitCode(0);

        $this->assertDatabaseMissing(config('tyro.tables.privileges', 'privileges'), ['id' => $privilege->id]);
    }

    public function test_delete_privilege_command_prompts_for_identifier(): void {
        $privilege = Privilege::factory()->create([
            'slug' => 'obsolete.prompt',
        ]);

        $this->artisan('tyro:privilege-delete', ['--force' => true])
            ->expectsQuestion('Which privilege slug or ID should be deleted?', $privilege->slug)
            ->expectsOutputToContain('deleted')
            ->assertExitCode(0);

        $this->assertDatabaseMissing(config('tyro.tables.privileges', 'privileges'), ['id' => $privilege->id]);
    }

    public function test_attach_and_detach_privilege_commands(): void {
        $role = Role::where('slug', 'editor')->first();
        $privilege = Privilege::factory()->create([
            'slug' => 'content.feature',
        ]);

        $this->artisan('tyro:privilege-attach', [
            'privilege' => $privilege->slug,
            'role' => $role->slug,
        ])->expectsOutputToContain('attached')
            ->assertExitCode(0);

        $this->assertTrue($role->fresh()->privileges->contains('id', $privilege->id));

        $this->artisan('tyro:privilege-detach', [
            'privilege' => $privilege->slug,
            'role' => $role->slug,
        ])->expectsOutputToContain('detached')
            ->assertExitCode(0);

        $this->assertFalse($role->fresh()->privileges->contains('id', $privilege->id));
    }

    public function test_attach_privilege_command_prompts_for_arguments(): void {
        $role = Role::where('slug', 'editor')->first();
        $privilege = Privilege::factory()->create([
            'slug' => 'content.workflow',
        ]);

        $this->artisan('tyro:privilege-attach')
            ->expectsQuestion('Which privilege slug or ID should be attached?', $privilege->slug)
            ->expectsQuestion('Which role slug or ID should receive the privilege?', $role->slug)
            ->expectsOutputToContain('attached')
            ->assertExitCode(0);

        $this->assertTrue($role->fresh()->privileges->contains('slug', 'content.workflow'));
    }

    public function test_detach_privilege_command_prompts_for_arguments(): void {
        $role = Role::where('slug', 'editor')->first();
        $privilege = Privilege::factory()->create([
            'slug' => 'content.clean',
        ]);

        $role->privileges()->syncWithoutDetaching([$privilege->id]);

        $this->artisan('tyro:privilege-detach')
            ->expectsQuestion('Which privilege slug or ID should be detached?', $privilege->slug)
            ->expectsQuestion('Which role slug or ID should lose the privilege?', $role->slug)
            ->expectsOutputToContain('detached')
            ->assertExitCode(0);

        $this->assertFalse($role->fresh()->privileges->contains('slug', 'content.clean'));
    }

    public function test_purge_privileges_command_removes_all_privileges(): void {
        Privilege::factory()->count(2)->create();

        $this->artisan('tyro:privilege-purge', ['--force' => true])
            ->expectsOutputToContain('Deleted')
            ->assertExitCode(0);

        $this->assertSame(0, Privilege::count());
    }

    public function test_login_command_displays_token(): void {
        $this->artisan('tyro:auth-login', [
            '--email' => 'admin@tyro.project',
            '--password' => 'tyro',
        ])->expectsOutputToContain('Token:')
            ->assertExitCode(0);
    }

    public function test_login_command_accepts_user_id(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::where('email', 'admin@tyro.project')->first();

        $this->artisan('tyro:auth-login', [
            '--user' => (string) $user->id,
            '--password' => 'tyro',
        ])->expectsOutputToContain('Token:')
            ->assertExitCode(0);
    }

    public function test_list_users_command_outputs_admin(): void {
        $this->artisan('tyro:user-list')
            ->expectsOutputToContain('admin@tyro.project')
            ->assertExitCode(0);
    }

    public function test_users_with_roles_command_displays_role_ids(): void {
        $adminRole = Role::where('slug', 'admin')->first();

        $exitCode = Artisan::call('tyro:user-list-with-roles');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('admin@tyro.project', $output);
        $this->assertStringContainsString('#' . $adminRole->id, $output);
        $this->assertStringContainsString($adminRole->name, $output);
    }

    public function test_role_users_command_lists_users_for_given_role(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create([
            'name' => 'Role User',
            'email' => 'role-user@example.com',
            'password' => Hash::make('password'),
        ]);

        $role = Role::where('slug', 'user')->first();
        $user->roles()->sync([$role->id]);

        $exitCode = Artisan::call('tyro:role-users', ['role' => $role->slug]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString((string) $user->id, $output);
        $this->assertStringContainsString($user->email, $output);
        $this->assertStringContainsString($user->name, $output);
    }

    public function test_logout_command_revokes_single_token(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::where('email', 'admin@tyro.project')->first();
        $token = $user->createToken('Test CLI Token', ['admin'])->plainTextToken;

        $this->artisan('tyro:auth-logout', ['token' => $token])
            ->expectsOutputToContain('revoked')
            ->assertExitCode(0);

        $this->assertEquals(0, $user->tokens()->count());
    }

    public function test_quick_token_command_generates_token_by_user_id(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create([
            'name' => 'Quick Token User',
            'email' => 'quick-token@example.com',
            'password' => Hash::make('password'),
        ]);

        $user->roles()->sync(Role::where('slug', 'user')->pluck('id')->all());

        $this->artisan('tyro:user-token', ['user' => (string) $user->id])
            ->expectsOutputToContain('Token:')
            ->assertExitCode(0);

        $this->assertSame(1, $user->fresh()->tokens()->count());
    }

    public function test_quick_token_command_assigns_role_and_privilege_abilities(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create([
            'name' => 'Privilege Token User',
            'email' => 'priv-token@example.com',
            'password' => Hash::make('secret'),
        ]);

        $role = Role::where('slug', 'editor')->first();
        $privilege = Privilege::factory()->create(['slug' => 'generate.pdf']);
        $role->privileges()->syncWithoutDetaching([$privilege->id]);

        $user->roles()->sync([$role->id]);

        $this->artisan('tyro:user-token', ['user' => (string) $user->id])
            ->expectsOutputToContain('Token:')
            ->assertExitCode(0);

        $abilities = $user->fresh()->tokens()->first()->abilities;

        $this->assertContains($role->slug, $abilities);
        $this->assertContains('generate.pdf', $abilities);
    }

    public function test_quick_token_command_blocks_suspended_users(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create([
            'name' => 'Blocked Quick Token User',
            'email' => 'blocked-quick-token@example.com',
            'password' => Hash::make('secret'),
            'suspended_at' => Carbon::now(),
            'suspension_reason' => 'Manual review',
        ]);

        $this->artisan('tyro:user-token', [
            'user' => (string) $user->id,
        ])->expectsOutputToContain('User is suspended. Reason: Manual review')
            ->assertExitCode(1);

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_update_user_command_updates_fields(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create([
            'name' => 'Update Me',
            'email' => 'update-me@example.com',
            'password' => Hash::make('secret'),
        ]);

        $this->artisan('tyro:user-update', [
            '--user' => (string) $user->id,
            '--name' => 'Updated User',
            '--email' => 'updated-user@example.com',
        ])->expectsOutputToContain('updated')
            ->assertExitCode(0);

        $this->assertDatabaseHas($user->getTable(), [
            'id' => $user->id,
            'name' => 'Updated User',
            'email' => 'updated-user@example.com',
        ]);
    }

    public function test_update_user_command_updates_password_when_provided(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create([
            'name' => 'Password Update',
            'email' => 'password-update@example.com',
            'password' => Hash::make('old-secret'),
        ]);

        $this->artisan('tyro:user-update', [
            '--user' => (string) $user->id,
            '--password' => 'new-secret',
            '--email' => $user->email,
            '--name' => $user->name,
        ])->expectsOutputToContain('updated')
            ->assertExitCode(0);

        $this->assertTrue(Hash::check('new-secret', $user->fresh()->password));
    }

    public function test_suspend_user_command_sets_and_lifts_suspension(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create([
            'name' => 'Suspended User',
            'email' => 'suspend-me@example.com',
            'password' => Hash::make('secret'),
        ]);

        $this->artisan('tyro:user-suspend', [
            '--user' => (string) $user->id,
            '--reason' => 'Manual review',
            '--force' => true,
        ])->expectsOutputToContain('suspended')
            ->assertExitCode(0);

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->suspended_at);
        $this->assertSame('Manual review', $fresh->suspension_reason);

        $this->artisan('tyro:user-suspend', [
            '--user' => (string) $user->id,
            '--unsuspend' => true,
            '--force' => true,
        ])->expectsOutputToContain('no longer suspended')
            ->assertExitCode(0);

        $this->assertNull($user->fresh()->suspended_at);
    }

    public function test_suspend_user_command_revokes_tokens(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create([
            'email' => 'token-suspend@example.com',
            'password' => Hash::make('secret'),
        ]);

        $user->createToken('first');
        $user->createToken('second');

        $this->assertSame(2, $user->tokens()->count());

        $this->artisan('tyro:user-suspend', [
            '--user' => (string) $user->id,
            '--force' => true,
            '--reason' => 'Token cleanup',
        ])->expectsOutputToContain('Revoked 2 existing tokens')
            ->assertExitCode(0);

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_unsuspend_user_command_lifts_suspension(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create([
            'name' => 'Unsuspension Target',
            'email' => 'unsuspend@example.com',
            'password' => Hash::make('secret'),
            'suspended_at' => Carbon::now(),
            'suspension_reason' => 'Review',
        ]);

        $this->artisan('tyro:user-unsuspend', [
            '--user' => (string) $user->id,
            '--force' => true,
        ])->expectsOutputToContain('no longer suspended')
            ->assertExitCode(0);

        $fresh = $user->fresh();
        $this->assertNull($fresh->suspended_at);
        $this->assertNull($fresh->suspension_reason);
    }

    public function test_suspended_users_command_lists_records(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create([
            'name' => 'Suspended List User',
            'email' => 'suspended-list@example.com',
            'suspended_at' => Carbon::now(),
            'suspension_reason' => 'Testing',
        ]);

        $exitCode = Artisan::call('tyro:user-suspended');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString($user->email, $output);
        $this->assertStringContainsString('Testing', $output);
    }

    public function test_users_command_marks_suspended_users(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create([
            'name' => 'Color User',
            'email' => 'color-user@example.com',
            'suspended_at' => Carbon::now(),
            'suspension_reason' => 'Color testing',
        ]);

        $exitCode = Artisan::call('tyro:user-list');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Color User', $output);
        $this->assertStringContainsString('Yes (Color testing)', $output);
    }

    public function test_login_command_blocks_suspended_users(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create([
            'name' => 'CLI Suspended',
            'email' => 'cli-suspended@example.com',
            'password' => Hash::make('secret'),
            'suspended_at' => Carbon::now(),
            'suspension_reason' => 'Lockout',
        ]);

        $this->artisan('tyro:auth-login', [
            '--user' => (string) $user->id,
            '--password' => 'secret',
        ])->expectsOutputToContain('User is suspended. Reason: Lockout')
            ->assertExitCode(1);
    }

    public function test_update_role_command_updates_name_and_slug(): void {
        $role = Role::where('slug', 'editor')->first();

        $this->artisan('tyro:role-update', [
            '--role' => (string) $role->id,
            '--name' => 'Content Editor',
            '--slug' => 'content-editor',
        ])->expectsOutputToContain('updated')
            ->assertExitCode(0);

        $this->assertDatabaseHas(config('tyro.tables.roles', 'roles'), [
            'id' => $role->id,
            'name' => 'Content Editor',
            'slug' => 'content-editor',
        ]);
    }

    public function test_update_privilege_command_updates_record(): void {
        $privilege = Privilege::factory()->create([
            'slug' => 'docs.review',
            'name' => 'Docs Review',
        ]);

        $this->artisan('tyro:privilege-update', [
            '--privilege' => $privilege->slug,
            '--name' => 'Docs Audit',
            '--slug' => 'docs.audit',
            '--description' => 'Audit documentation',
        ])->expectsOutputToContain('updated')
            ->assertExitCode(0);

        $this->assertDatabaseHas(config('tyro.tables.privileges', 'privileges'), [
            'id' => $privilege->id,
            'name' => 'Docs Audit',
            'slug' => 'docs.audit',
            'description' => 'Audit documentation',
        ]);
    }

    public function test_delete_user_role_command_detaches_role(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create([
            'name' => 'Role Test User',
            'email' => 'role-test@example.com',
            'password' => Hash::make('password'),
        ]);

        $role = Role::where('slug', 'editor')->first();
        $user->roles()->sync([$role->id]);

        $this->artisan('tyro:role-remove', [
            '--user' => $user->email,
            '--role' => $role->slug,
        ])->expectsOutputToContain('removed')
            ->assertExitCode(0);

        $this->assertFalse($user->fresh()->roles->contains('id', $role->id));
    }

    public function test_user_roles_command_lists_roles(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::where('email', 'admin@tyro.project')->first();
        $adminRole = Role::where('slug', 'admin')->first();

        $exitCode = Artisan::call('tyro:user-roles', ['user' => $user->email]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString($user->email, $output);
        $this->assertStringContainsString('#' . $adminRole->id, $output);
        $this->assertStringContainsString($adminRole->name, $output);
    }

    public function test_user_privileges_command_lists_privileges(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::where('email', 'admin@tyro.project')->first();

        $exitCode = Artisan::call('tyro:user-privileges', ['user' => $user->email]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString($user->email, $output);
        $this->assertStringContainsString('report.generate', $output);
    }

    public function test_logout_all_command_revokes_every_token(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::where('email', 'admin@tyro.project')->first();
        $user->createToken('First', ['admin']);
        $user->createToken('Second', ['admin']);

        $this->artisan('tyro:auth-logout-all', ['--user' => $user->email, '--force' => true])
            ->expectsOutputToContain('All tokens revoked')
            ->assertExitCode(0);

        $this->assertEquals(0, $user->tokens()->count());
    }

    public function test_logout_all_users_command_revokes_tokens_for_everyone(): void {
        $userClass = config('tyro.models.user');
        $first = $userClass::factory()->create();
        $second = $userClass::factory()->create();

        $first->createToken('First token');
        $second->createToken('Second token');

        $this->assertSame(2, PersonalAccessToken::count());

        $this->artisan('tyro:auth-logout-all-users', ['--force' => true])
            ->expectsOutputToContain('Revoked 2 tokens')
            ->assertExitCode(0);

        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_about_command_outputs_summary(): void {
        $this->artisan('tyro:sys-about')
            ->expectsOutputToContain('Tyro for Laravel')
            ->assertExitCode(0);
    }

    public function test_doc_command_can_print_url(): void {
        $this->artisan('tyro:sys-doc', ['--no-open' => true])
            ->expectsOutputToContain('https://github.com/hasinhayder/tyro')
            ->assertExitCode(0);
    }

    public function test_postman_collection_command_can_print_url(): void {
        $this->artisan('tyro:postman', ['--no-open' => true])
            ->expectsOutputToContain('Tyro.postman_collection.json')
            ->assertExitCode(0);
    }

    public function test_install_command_runs_install_api_and_migrate(): void {
        $this->artisan('tyro:sys-install', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run: skipped install:api and migrate.')
            ->assertExitCode(0);
    }

    public function test_install_command_seeds_when_confirmed(): void {
        FakeInstallCommand::reset();

        $command = $this->app->make(FakeInstallCommand::class);
        $command->setLaravel($this->app);

        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);

        $this->assertSame(0, $tester->execute([]));

        $this->assertSame([
            'install:api',
            'tyro:user-prepare',
            'migrate',
            'tyro:seed-all',
        ], array_column(FakeInstallCommand::$recorded, 'command'));
    }

    public function test_install_command_prepares_user_model_when_seed_declined(): void {
        FakeInstallCommand::reset();

        $command = $this->app->make(FakeInstallCommand::class);
        $command->setLaravel($this->app);

        $tester = new CommandTester($command);
        $tester->setInputs(['no']);

        $this->assertSame(0, $tester->execute([]));

        $this->assertSame([
            'install:api',
            'tyro:user-prepare',
            'migrate',
        ], array_column(FakeInstallCommand::$recorded, 'command'));
    }

    public function test_flush_roles_command_truncates_roles(): void {
        $this->assertGreaterThan(0, Role::count());

        $this->artisan('tyro:role-purge', ['--force' => true])
            ->assertExitCode(0);

        $this->assertSame(0, Role::count());
    }

    public function test_seed_command_restores_defaults(): void {
        $userClass = config('tyro.models.user');
        
        // Run seed command
        $this->artisan('tyro:seed-all', ['--force' => true])
            ->assertExitCode(0);

        // Verify admin user exists
        $this->assertDatabaseHas('users', ['email' => 'admin@tyro.project']);
        
        // Verify all default roles exist
        $this->assertDatabaseHas(config('tyro.tables.roles'), ['slug' => 'admin']);
        $this->assertDatabaseHas(config('tyro.tables.roles'), ['slug' => 'user']);
        $this->assertDatabaseHas(config('tyro.tables.roles'), ['slug' => 'customer']);
        $this->assertDatabaseHas(config('tyro.tables.roles'), ['slug' => 'editor']);
        $this->assertDatabaseHas(config('tyro.tables.roles'), ['slug' => '*']);
        $this->assertDatabaseHas(config('tyro.tables.roles'), ['slug' => 'super-admin']);
        
        // Verify admin user has admin role
        $adminUser = $userClass::where('email', 'admin@tyro.project')->first();
        $this->assertNotNull($adminUser);
        $adminRole = Role::where('slug', 'admin')->first();
        $this->assertTrue($adminUser->roles->contains($adminRole));
        
        // Verify privileges are seeded
        $this->assertGreaterThan(0, Privilege::count());
        $this->assertGreaterThan(0, DB::table(config('tyro.tables.role_privilege', 'privilege_role'))->count());
    }

    public function test_seed_privileges_command_restores_default_privileges(): void {
        Privilege::query()->delete();

        $this->artisan('tyro:seed-privileges', ['--force' => true])
            ->expectsOutputToContain('privileges')
            ->assertExitCode(0);

        $this->assertDatabaseHas(config('tyro.tables.privileges', 'privileges'), ['slug' => 'report.generate']);
    }

    public function test_publish_config_command_exports_file(): void {
        $configPath = config_path('tyro.php');
        if (file_exists($configPath)) {
            unlink($configPath);
        }

        $this->artisan('tyro:publish-config', ['--force' => true])
            ->assertExitCode(0);

        $this->assertFileExists($configPath);
    }

    public function test_publish_migrations_command_runs_vendor_publish(): void {
        $this->artisan('tyro:publish-migrations', ['--force' => true])
            ->expectsOutputToContain('Tyro migrations (roles, privileges, suspension) published')
            ->assertExitCode(0);
    }

    public function test_prepare_user_model_command_updates_target_file(): void {
        $path = base_path('tests/runtime/User.php');
        File::ensureDirectoryExists(dirname($path));

        $stub = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
}
PHP;

        file_put_contents($path, $stub);

        try {
            $this->artisan('tyro:user-prepare', ['--path' => $path])
                ->expectsOutputToContain('Updated User model')
                ->assertExitCode(0);

            $updated = file_get_contents($path);
            $this->assertStringContainsString('use Laravel\\Sanctum\\HasApiTokens;', $updated);
            $this->assertStringContainsString('use HasinHayder\\Tyro\\Concerns\\HasTyroRoles;', $updated);
            $this->assertStringContainsString('use HasApiTokens, HasTyroRoles;', $updated);

            $this->artisan('tyro:user-prepare', ['--path' => $path])
                ->expectsOutputToContain('already prepared')
                ->assertExitCode(0);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
