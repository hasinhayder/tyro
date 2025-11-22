<?php

namespace HasinHayder\Tyro\Tests\Feature;

use HasinHayder\Tyro\Models\Role;
use HasinHayder\Tyro\Tests\Fixtures\User;
use HasinHayder\Tyro\Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\Fluent\AssertableJson;

class UserRoleTest extends TestCase {
    protected string $token;

    protected function setUp(): void {
        parent::setUp();

        $response = $this->postJson('/api/login', [
            'email' => 'admin@tyro.project',
            'password' => 'tyro',
        ]);

        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->token = $data['token'];
    }

    public function test_roles_are_listed_for_user(): void {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->get('/api/users/1/roles');

        $response->assertJson(fn(AssertableJson $json) => $json
            ->has('roles.0', fn($json) => $json
                ->where('slug', 'admin')
                ->etc())
            ->etc());
    }

    public function test_assign_role_to_user(): void {
        $newUser = User::create([
            'name' => 'Test User',
            'email' => 'testuser@tyro.project',
            'password' => Hash::make('abcd'),
        ]);
        $newUser->roles()->attach(Role::where('slug', 'user')->first());

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->post("/api/users/{$newUser->id}/roles", ['role_id' => Role::where('slug', 'customer')->first()->id]);

        $payload = $response->json();
        $this->assertCount(2, $payload['roles']);
    }

    public function test_remove_role_from_user(): void {
        $newUser = User::create([
            'name' => 'Test User 2',
            'email' => 'testuser2@tyro.project',
            'password' => Hash::make('abcd'),
        ]);
        $newUser->roles()->attach(Role::where('slug', 'user')->first());

        $customer = Role::where('slug', 'customer')->first();
        $userRole = Role::where('slug', 'user')->first();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->post("/api/users/{$newUser->id}/roles", ['role_id' => $customer->id]);
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->post("/api/users/{$newUser->id}/roles", ['role_id' => $userRole->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->delete("/api/users/{$newUser->id}/roles/{$customer->id}");

        $payload = $response->json();
        $this->assertCount(1, $payload['roles']);
    }
}
