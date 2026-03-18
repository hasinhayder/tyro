<?php

namespace HasinHayder\Tyro\Tests\Feature;

use HasinHayder\Tyro\Models\Role;
use HasinHayder\Tyro\Tests\Fixtures\User;
use HasinHayder\Tyro\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

class PrivilegeMiddlewareTest extends TestCase {
    public function test_privilege_middleware_blocks_when_privilege_missing(): void {
        Route::middleware(['auth:sanctum', 'privilege:report.generate'])
            ->get('/tyro/middleware/privilege-block', fn () => response()->json(['ok' => true]));

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('slug', 'user')->first()->id]);

        Sanctum::actingAs($user);

        $this->getJson('/tyro/middleware/privilege-block')->assertForbidden();
    }

    public function test_privilege_middleware_allows_user_with_required_privilege(): void {
        Route::middleware(['auth:sanctum', 'privilege:report.generate'])
            ->get('/tyro/middleware/privilege-allow', fn () => response()->json(['ok' => true]));

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('slug', 'admin')->first()->id]);

        Sanctum::actingAs($user);

        $this->getJson('/tyro/middleware/privilege-allow')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_privileges_middleware_allows_when_any_privilege_matches(): void {
        Route::middleware(['auth:sanctum', 'privileges:billing.view,users.manage'])
            ->get('/tyro/middleware/privileges-any', fn () => response()->json(['ok' => true]));

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('slug', 'user')->first()->id]);

        Sanctum::actingAs($user);

        $this->getJson('/tyro/middleware/privileges-any')->assertOk();
    }

    public function test_privileges_middleware_blocks_when_no_privileges_match(): void {
        Route::middleware(['auth:sanctum', 'privileges:users.manage,roles.manage'])
            ->get('/tyro/middleware/privileges-block', fn () => response()->json(['ok' => true]));

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('slug', 'customer')->first()->id]);

        Sanctum::actingAs($user);

        $this->getJson('/tyro/middleware/privileges-block')->assertForbidden();
    }
}
