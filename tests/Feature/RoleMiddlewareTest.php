<?php

namespace HasinHayder\Tyro\Tests\Feature;

use HasinHayder\Tyro\Models\Role;
use HasinHayder\Tyro\Tests\Fixtures\User;
use HasinHayder\Tyro\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

class RoleMiddlewareTest extends TestCase {
    public function test_role_middleware_requires_every_listed_role(): void {
        Route::middleware(['auth:sanctum', 'role:admin,super-admin'])
            ->get('/tyro/middleware/role-all', fn () => response()->json(['ok' => true]));

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('slug', 'admin')->first()->id]);

        Sanctum::actingAs($user);

        $this->getJson('/tyro/middleware/role-all')->assertForbidden();

        $user->roles()->sync([
            Role::where('slug', 'admin')->first()->id,
            Role::where('slug', 'super-admin')->first()->id,
        ]);

        $this->getJson('/tyro/middleware/role-all')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_roles_middleware_passes_when_any_role_matches(): void {
        Route::middleware(['auth:sanctum', 'roles:admin,super-admin'])
            ->get('/tyro/middleware/roles-any', fn () => response()->json(['ok' => true]));

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('slug', 'admin')->first()->id]);

        Sanctum::actingAs($user);

        $this->getJson('/tyro/middleware/roles-any')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_roles_middleware_blocks_when_no_roles_match(): void {
        Route::middleware(['auth:sanctum', 'roles:admin,super-admin'])
            ->get('/tyro/middleware/roles-block', fn () => response()->json(['ok' => true]));

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('slug', 'customer')->first()->id]);

        Sanctum::actingAs($user);

        $this->getJson('/tyro/middleware/roles-block')->assertForbidden();
    }
}
