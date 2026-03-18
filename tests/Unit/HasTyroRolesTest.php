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
}
