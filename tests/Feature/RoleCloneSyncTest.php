<?php

namespace HasinHayder\Tyro\Tests\Feature;

use HasinHayder\Tyro\Models\Privilege;
use HasinHayder\Tyro\Models\Role;
use HasinHayder\Tyro\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class RoleCloneSyncTest extends TestCase {
    protected function createRoleWithPrivileges(string $slug, array $privilegeSlugs): Role {
        $role = Role::create([
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
        ]);

        foreach ($privilegeSlugs as $privilegeSlug) {
            $privilege = Privilege::firstOrCreate(
                ['slug' => $privilegeSlug],
                ['name' => ucfirst(str_replace('.', ' ', $privilegeSlug)), 'description' => 'Test privilege']
            );
            $role->attachPrivilege($privilege);
        }

        return $role;
    }

    public function test_role_clone_command_clones_role_with_all_privileges(): void {
        $source = $this->createRoleWithPrivileges('support', ['tickets.view', 'tickets.reply']);

        $this->artisan('tyro:role-clone', [
            'role' => 'support',
            '--name' => 'Support EU',
            '--slug' => 'support-eu',
        ])->expectsOutputToContain('cloned from "support" with 2 privilege(s)')
            ->assertExitCode(0);

        $clone = Role::where('slug', 'support-eu')->first();

        $this->assertNotNull($clone, 'Cloned role should exist');
        $this->assertSame('Support EU', $clone->name);
        $this->assertCount(2, $clone->privileges);
        $this->assertTrue($clone->privileges->contains('slug', 'tickets.view'));
        $this->assertTrue($clone->privileges->contains('slug', 'tickets.reply'));

        $this->assertCount(2, $source->fresh()->privileges, 'Source role must remain untouched');
    }

    public function test_role_clone_command_clones_role_without_privileges(): void {
        Role::create(['name' => 'Observer', 'slug' => 'observer']);

        $this->artisan('tyro:role-clone', [
            'role' => 'observer',
            '--name' => 'Observer Read Only',
            '--slug' => 'observer-ro',
        ])->expectsOutputToContain('with 0 privilege(s)')
            ->assertExitCode(0);

        $this->assertDatabaseHas(config('tyro.tables.roles', 'roles'), ['slug' => 'observer-ro']);
    }

    public function test_role_clone_command_rejects_duplicate_slug(): void {
        Role::create(['name' => 'Support', 'slug' => 'support']);
        Role::create(['name' => 'Support EU', 'slug' => 'support-eu']);

        $this->artisan('tyro:role-clone', [
            'role' => 'support',
            '--name' => 'Support EU',
            '--slug' => 'support-eu',
        ])->expectsOutputToContain('already exists')
            ->assertExitCode(1);
    }

    public function test_role_clone_command_fails_when_source_role_not_found(): void {
        $this->artisan('tyro:role-clone', [
            'role' => 'ghost-role',
            '--name' => 'Ghost',
            '--slug' => 'ghost',
        ])->expectsOutputToContain('not found')
            ->assertExitCode(1);

        $this->assertDatabaseMissing(config('tyro.tables.roles', 'roles'), ['slug' => 'ghost']);
    }

    public function test_role_clone_command_logs_audit_events(): void {
        $this->createRoleWithPrivileges('support', ['tickets.view']);

        $this->artisan('tyro:role-clone', [
            'role' => 'support',
            '--name' => 'Support EU',
            '--slug' => 'support-eu',
        ])->assertExitCode(0);

        $this->assertDatabaseHas(config('tyro.tables.audit_logs', 'tyro_audit_logs'), ['event' => 'role.created']);
        $this->assertDatabaseHas(config('tyro.tables.audit_logs', 'tyro_audit_logs'), ['event' => 'privilege.attached']);
    }

    public function test_role_clone_command_has_alias(): void {
        $this->createRoleWithPrivileges('support', ['tickets.view']);

        $this->artisan('tyro:clone-role', [
            'role' => 'support',
            '--name' => 'Support EU',
            '--slug' => 'support-eu',
        ])->assertExitCode(0);

        $this->assertDatabaseHas(config('tyro.tables.roles', 'roles'), ['slug' => 'support-eu']);
    }

    public function test_role_sync_command_adds_missing_privileges(): void {
        $this->createRoleWithPrivileges('support', ['tickets.view', 'tickets.reply']);
        $this->createRoleWithPrivileges('support-eu', ['tickets.view']);

        $this->artisan('tyro:role-sync', [
            'source' => 'support',
            'target' => 'support-eu',
        ])->expectsOutputToContain('Drift detected')
            ->expectsOutputToContain('will be attached')
            ->expectsOutputToContain('+ tickets.reply')
            ->expectsOutputToContain('1 privilege(s) attached')
            ->assertExitCode(0);

        $target = Role::where('slug', 'support-eu')->first();

        $this->assertCount(2, $target->fresh()->privileges);
        $this->assertTrue($target->fresh()->privileges->contains('slug', 'tickets.reply'));
    }

    public function test_role_sync_command_keeps_extra_privileges_without_flag(): void {
        $this->createRoleWithPrivileges('support', ['tickets.view']);
        $this->createRoleWithPrivileges('support-eu', ['tickets.view', 'tickets.delete']);

        $this->artisan('tyro:role-sync', [
            'source' => 'support',
            'target' => 'support-eu',
        ])->expectsOutputToContain('Drift detected')
            ->expectsOutputToContain('will be KEPT')
            ->expectsOutputToContain('- tickets.delete')
            ->assertExitCode(0);

        $this->assertCount(2, Role::where('slug', 'support-eu')->first()->fresh()->privileges);
    }

    public function test_role_sync_command_removes_extra_privileges_with_flag(): void {
        $this->createRoleWithPrivileges('support', ['tickets.view']);
        $this->createRoleWithPrivileges('support-eu', ['tickets.view', 'tickets.delete']);

        $this->artisan('tyro:role-sync', [
            'source' => 'support',
            'target' => 'support-eu',
            '--remove-extra' => true,
        ])->expectsOutputToContain('will be DETACHED')
            ->expectsOutputToContain('1 detached')
            ->assertExitCode(0);

        $target = Role::where('slug', 'support-eu')->first()->fresh();

        $this->assertCount(1, $target->privileges);
        $this->assertTrue($target->privileges->contains('slug', 'tickets.view'));
    }

    public function test_role_sync_command_dry_run_makes_no_changes(): void {
        $this->createRoleWithPrivileges('support', ['tickets.view', 'tickets.reply']);
        $this->createRoleWithPrivileges('support-eu', ['tickets.view']);

        $this->artisan('tyro:role-sync', [
            'source' => 'support',
            'target' => 'support-eu',
            '--dry-run' => true,
        ])->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('would be attached')
            ->assertExitCode(0);

        $target = Role::where('slug', 'support-eu')->first()->fresh();

        $this->assertCount(1, $target->privileges, 'Dry run must not modify the target role');
    }

    public function test_role_sync_command_reports_when_already_in_sync(): void {
        $this->createRoleWithPrivileges('support', ['tickets.view']);
        $this->createRoleWithPrivileges('support-eu', ['tickets.view']);

        $exitCode = Artisan::call('tyro:role-sync', [
            'source' => 'support',
            'target' => 'support-eu',
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('already in sync', $output);
        $this->assertStringNotContainsString('Drift detected', $output, 'In-sync roles must not produce drift warnings');
    }

    public function test_role_sync_command_fails_when_roles_not_found(): void {
        $this->createRoleWithPrivileges('support-eu', ['tickets.view']);

        $this->artisan('tyro:role-sync', [
            'source' => 'ghost-role',
            'target' => 'support-eu',
        ])->expectsOutputToContain('not found')
            ->assertExitCode(1);

        $this->artisan('tyro:role-sync', [
            'source' => 'support-eu',
            'target' => 'ghost-role',
        ])->expectsOutputToContain('not found')
            ->assertExitCode(1);
    }

    public function test_role_sync_command_rejects_same_source_and_target(): void {
        $this->createRoleWithPrivileges('support', ['tickets.view']);

        $this->artisan('tyro:role-sync', [
            'source' => 'support',
            'target' => 'support',
        ])->expectsOutputToContain('must be different')
            ->assertExitCode(1);
    }

    public function test_role_sync_command_has_alias(): void {
        $this->createRoleWithPrivileges('support', ['tickets.view', 'tickets.reply']);
        $this->createRoleWithPrivileges('support-eu', ['tickets.view']);

        $this->artisan('tyro:sync-roles', [
            'source' => 'support',
            'target' => 'support-eu',
        ])->assertExitCode(0);

        $this->assertCount(2, Role::where('slug', 'support-eu')->first()->fresh()->privileges);
    }

    public function test_role_sync_command_rejects_admin_as_target(): void {
        $this->createRoleWithPrivileges('support', ['tickets.view']);

        $adminPrivileges = Role::where('slug', 'admin')->first()->privileges()->pluck('slug')->all();

        $this->artisan('tyro:role-sync', [
            'source' => 'support',
            'target' => 'admin',
        ])->expectsOutputToContain('protected and cannot be used as a sync target')
            ->assertExitCode(1);

        $this->assertSame(
            $adminPrivileges,
            Role::where('slug', 'admin')->first()->fresh()->privileges()->pluck('slug')->all(),
            'Admin role privileges must remain untouched'
        );
    }

    public function test_role_sync_command_rejects_admin_as_target_even_in_dry_run(): void {
        $this->createRoleWithPrivileges('support', ['tickets.view']);

        $adminPrivileges = Role::where('slug', 'admin')->first()->privileges()->pluck('slug')->all();

        $this->artisan('tyro:role-sync', [
            'source' => 'support',
            'target' => 'admin',
            '--dry-run' => true,
        ])->expectsOutputToContain('protected and cannot be used as a sync target')
            ->assertExitCode(1);

        $this->assertSame(
            $adminPrivileges,
            Role::where('slug', 'admin')->first()->fresh()->privileges()->pluck('slug')->all(),
            'Admin role privileges must remain untouched'
        );
    }

    public function test_role_sync_command_rejects_super_admin_as_target_even_in_dry_run(): void {
        $this->createRoleWithPrivileges('support', ['tickets.view']);

        $this->artisan('tyro:role-sync', [
            'source' => 'support',
            'target' => 'super-admin',
            '--dry-run' => true,
        ])->expectsOutputToContain('protected and cannot be used as a sync target')
            ->assertExitCode(1);
    }

    public function test_role_sync_command_allows_protected_role_as_source(): void {
        $admin = Role::where('slug', 'admin')->first();
        $this->assertNotNull($admin, 'Seeded admin role should exist');

        $this->createRoleWithPrivileges('support-eu', []);

        $this->artisan('tyro:role-sync', [
            'source' => 'admin',
            'target' => 'support-eu',
        ])->assertExitCode(0);

        $target = Role::where('slug', 'support-eu')->first()->fresh();

        $this->assertCount($admin->privileges()->count(), $target->privileges);
        $this->assertSame(
            $admin->privileges()->pluck('slug')->sort()->values()->all(),
            $target->privileges->pluck('slug')->sort()->values()->all()
        );
    }
}
