<?php

namespace HasinHayder\Tyro\Tests\Unit;

use HasinHayder\Tyro\Models\Privilege;
use HasinHayder\Tyro\Models\Role;
use HasinHayder\Tyro\Support\TyroCache;
use HasinHayder\Tyro\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class HasTyroRolesTest extends TestCase {
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

    public function test_user_can_falls_back_to_gate_when_privilege_missing(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create();

        $this->assertFalse($user->can('nonexistent.privilege'));
    }

    public function test_role_slug_cache_requires_invalidation(): void {
        config(['cache.default' => 'array', 'tyro.cache.store' => 'array', 'tyro.cache.enabled' => true]);
        Cache::store('array')->clear();

        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create();
        $role = Role::where('slug', 'user')->firstOrFail();

        $user->roles()->attach($role);
        $this->assertTrue($user->fresh()->hasRole('user'));

        $user->roles()->detach($role);
        $this->assertTrue($user->fresh()->hasRole('user'));

        TyroCache::forgetUser($user);

        $this->assertFalse($user->fresh()->hasRole('user'));
    }

    public function test_privilege_cache_flushes_when_role_cache_cleared(): void {
        config(['cache.default' => 'array', 'tyro.cache.store' => 'array', 'tyro.cache.enabled' => true]);
        Cache::store('array')->clear();

        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create();
        $role = Role::where('slug', 'user')->firstOrFail();
        $privilege = Privilege::factory()->create([
            'slug' => 'custom.export',
            'name' => 'Custom Export',
        ]);

        $role->privileges()->syncWithoutDetaching($privilege);
        $user->roles()->attach($role);

        $this->assertTrue($user->fresh()->can('custom.export'));

        $role->privileges()->detach($privilege);
        $this->assertTrue($user->fresh()->can('custom.export'));

        TyroCache::forgetUsersByRole($role);

        $this->assertFalse($user->fresh()->can('custom.export'));
    }

    public function test_is_not_returns_inverse_of_has_role(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create();
        $role = Role::where('slug', 'user')->firstOrFail();

        $this->assertTrue($user->fresh()->isNot('user'));
        $this->assertFalse($user->fresh()->isNot('admin'));

        $user->roles()->attach($role);
        $this->assertFalse($user->fresh()->isNot('user'));
        $this->assertTrue($user->fresh()->isNot('admin'));
    }

    public function test_is_not_returns_false_for_everything_when_wildcard_role_present(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create();
        $wildcard = Role::factory()->create(['slug' => '*', 'name' => 'Wildcard']);

        $user->roles()->attach($wildcard);

        // With '*' present, hasRole() short-circuits to true for any slug,
        // so isNot() must return false for slugs the user doesn't literally have.
        $this->assertFalse($user->fresh()->isNot('admin'));
        $this->assertFalse($user->fresh()->isNot('anything-else'));
    }

    public function test_has_no_roles_returns_true_when_user_has_no_roles(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create();

        $this->assertTrue($user->fresh()->hasNoRoles());

        $role = Role::where('slug', 'user')->firstOrFail();
        $user->roles()->attach($role);
        $this->assertFalse($user->fresh()->hasNoRoles());
    }

    public function test_roles_count_returns_number_of_distinct_roles(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create();

        $this->assertSame(0, $user->fresh()->rolesCount());

        $userRole = Role::where('slug', 'user')->firstOrFail();
        $editorRole = Role::where('slug', 'editor')->first() ?? Role::factory()->create(['slug' => 'editor', 'name' => 'Editor']);

        $user->roles()->attach($userRole);
        $user->roles()->attach($editorRole);
        $this->assertSame(2, $user->fresh()->rolesCount());
    }

    public function test_sync_roles_attaches_and_detaches_diff_correctly(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create();
        $userRole = Role::where('slug', 'user')->firstOrFail();
        $editorRole = Role::where('slug', 'editor')->first() ?? Role::factory()->create(['slug' => 'editor', 'name' => 'Editor']);
        $adminRole = Role::where('slug', 'admin')->first() ?? Role::factory()->create(['slug' => 'admin', 'name' => 'Admin']);

        // Seed: user currently has 'user' and 'editor'
        $user->roles()->attach($userRole);
        $user->roles()->attach($editorRole);

        $result = $user->fresh()->syncRoles(['editor', 'admin']);

        $this->assertSame(['admin'], $result['attached']);
        $this->assertSame(['user'], $result['detached']);

        $freshSlugs = $user->fresh()->tyroRoleSlugs();
        sort($freshSlugs);
        $this->assertSame(['admin', 'editor'], $freshSlugs);
    }

    public function test_sync_roles_accepts_role_instances_and_is_idempotent(): void {
        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create();
        $userRole = Role::where('slug', 'user')->firstOrFail();

        $first = $user->fresh()->syncRoles([$userRole]);
        $this->assertSame(['user'], $first['attached']);
        $this->assertSame([], $first['detached']);

        $second = $user->fresh()->syncRoles([$userRole]);
        $this->assertSame([], $second['attached']);
        $this->assertSame([], $second['detached']);

        $this->assertSame(['user'], $user->fresh()->tyroRoleSlugs());
    }

    public function test_sync_roles_throws_when_slug_is_unknown(): void {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $userClass = config('tyro.models.user');
        $user = $userClass::factory()->create();

        $user->syncRoles(['nonexistent-slug']);
    }
}
