<?php

namespace HasinHayder\Tyro\Tests\Feature;

use HasinHayder\Tyro\Models\Role;
use HasinHayder\Tyro\Tests\TestCase;
use Illuminate\Testing\Fluent\AssertableJson;

class RoleTest extends TestCase {
    protected string $token;

    protected function authenticate(): void {
        if (isset($this->token)) {
            return;
        }

        $response = $this->postJson('/api/login', [
            'email' => 'admin@tyro.project',
            'password' => 'tyro',
        ]);

        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->token = $data['token'];
    }

    public function test_list_roles_returns_seeded_roles(): void {
        $this->authenticate();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)->get('/api/roles');

        $response->assertJson(fn(AssertableJson $json) => $json
            ->has(6)
            ->first(fn($json) => $json
                ->where('name', 'Administrator')
                ->where('slug', 'admin')
                ->etc()));
    }

    public function test_update_role_name(): void {
        $this->authenticate();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->put('/api/roles/' . Role::where('slug', 'editor')->first()->id, [
                'name' => 'Chief Editor',
            ]);

        $response->assertJson(fn(AssertableJson $json) => $json->where('name', 'Chief Editor')->etc());
    }

    public function test_update_role_slug(): void {
        $this->authenticate();

        $roleId = Role::where('slug', 'editor')->first()->id;

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->put("/api/roles/{$roleId}", [
                'slug' => 'chief-editor',
            ]);

        $response->assertJson(fn(AssertableJson $json) => $json->where('slug', 'chief-editor')->etc());
    }

    public function test_protected_role_slug_cannot_change(): void {
        $this->authenticate();

        $adminId = Role::where('slug', 'admin')->first()->id;

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->put("/api/roles/{$adminId}", [
                'slug' => 'admin-x',
            ]);

        $response->assertJson(fn(AssertableJson $json) => $json->where('slug', 'admin')->etc());
    }

    public function test_create_and_delete_role(): void {
        $this->authenticate();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->post('/api/roles', [
                'name' => 'New Role',
                'slug' => 'new-role',
            ]);

        $response->assertJson(fn(AssertableJson $json) => $json->where('slug', 'new-role')->etc());

        $roleId = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR)['id'];

        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->delete("/api/roles/{$roleId}");

        $deleteResponse->assertJson(fn(AssertableJson $json) => $json->where('error', 0)->has('message'));
    }
}
