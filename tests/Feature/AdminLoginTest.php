<?php

namespace HasinHayder\Tyro\Tests\Feature;

use HasinHayder\Tyro\Tests\TestCase;
use Illuminate\Testing\Fluent\AssertableJson;

class AdminLoginTest extends TestCase {
    public function test_admin_login_succeeds(): void {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@tyro.project',
            'password' => 'tyro',
        ]);

        $response->assertJson(fn(AssertableJson $json) => $json
            ->where('error', 0)
            ->has('token')
            ->etc());
    }

    public function test_admin_login_fails_with_bad_password(): void {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@tyro.project',
            'password' => 'wrong-password',
        ]);

        $response->assertJson(fn(AssertableJson $json) => $json
            ->where('error', 1)
            ->missing('token')
            ->has('message'));
    }

    public function test_admin_login_fails_when_suspended(): void {
        $userClass = config('tyro.models.user');
        $admin = $userClass::where('email', 'admin@tyro.project')->firstOrFail();
        $admin->forceFill([
            'suspended_at' => now(),
            'suspension_reason' => 'Security hold',
        ])->save();

        $response = $this->postJson('/api/login', [
            'email' => 'admin@tyro.project',
            'password' => 'tyro',
        ]);

        $response->assertStatus(423)
            ->assertJson(fn(AssertableJson $json) => $json
                ->where('error', 1)
                ->where('message', 'user is suspended'));
    }
}
