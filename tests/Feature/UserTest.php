<?php

namespace HasinHayder\Tyro\Tests\Feature;

use HasinHayder\Tyro\Models\Role;
use HasinHayder\Tyro\Tests\Fixtures\User;
use HasinHayder\Tyro\Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\Fluent\AssertableJson;

class UserTest extends TestCase {
    protected ?string $token = null;

    protected ?int $userId = null;

    protected function loginAdmin(): void {
        if ($this->token) {
            return;
        }

        $response = $this->postJson('/api/login', [
            'email' => 'admin@tyro.project',
            'password' => 'tyro',
        ]);

        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->token = $data['token'];
        $this->userId = $data['id'];
    }

    public function test_user_registration_and_duplicate_prevention(): void {
        $response = $this->postJson('/api/users', [
            'name' => 'Test User',
            'email' => 'test@test.com',
            'password' => 'test',
        ]);

        $response->assertJson(fn(AssertableJson $json) => $json
            ->where('email', 'test@test.com')
            ->where('name', 'Test User')
            ->etc());

        $duplicate = $this->postJson('/api/users', [
            'name' => 'Test User',
            'email' => 'test@test.com',
            'password' => 'test',
        ]);

        $duplicate->assertJson(fn(AssertableJson $json) => $json->where('error', 1)->where('message', 'user already exists'));
    }

    public function test_user_login_and_update_self(): void {
        $this->postJson('/api/users', [
            'name' => 'Test User',
            'email' => 'self@test.com',
            'password' => 'secret',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'self@test.com',
            'password' => 'secret',
        ]);

        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $update = $this->withHeader('Authorization', 'Bearer ' . $data['token'])
            ->put('/api/users/' . $data['id'], ['name' => 'Mini Me']);

        $update->assertJson(fn(AssertableJson $json) => $json->where('name', 'Mini Me')->etc());
    }

    public function test_admin_can_update_any_user_and_delete(): void {
        $target = User::create([
            'name' => 'Delete Me',
            'email' => 'deleteme@test.com',
            'password' => Hash::make('secret'),
        ]);

        $this->loginAdmin();

        $update = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->put('/api/users/' . $target->id, ['name' => 'Admin Updated']);

        $update->assertJson(fn(AssertableJson $json) => $json->where('name', 'Admin Updated')->etc());

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->delete('/api/users/' . $target->id);

        $response->assertJson(fn(AssertableJson $json) => $json->where('error', 0)->where('message', 'user deleted'));
    }

    public function test_cannot_delete_last_admin(): void {
        $this->loginAdmin();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->delete('/api/users/' . $this->userId);

        $response->assertStatus(409);
    }

    public function test_admin_can_suspend_and_unsuspend_user_via_api(): void {
        $target = User::create([
            'name' => 'Suspend API User',
            'email' => 'api-suspend@example.com',
            'password' => Hash::make('secret'),
        ]);

        $target->createToken('First API Token');
        $target->createToken('Second API Token');

        $this->loginAdmin();

        $suspend = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/users/' . $target->id . '/suspend', ['reason' => 'Policy review']);

        $suspend->assertStatus(200)
            ->assertJson(fn(AssertableJson $json) => $json
                ->where('status', 'suspended')
                ->where('reason', 'Policy review')
                ->where('revoked_tokens', 2)
                ->etc());

        $this->assertNotNull($target->fresh()->suspended_at);
        $this->assertSame(0, $target->fresh()->tokens()->count());

        $unsuspend = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->delete('/api/users/' . $target->id . '/suspend');

        $unsuspend->assertStatus(200)
            ->assertJson(fn(AssertableJson $json) => $json
                ->where('status', 'active')
                ->etc());

        $this->assertNull($target->fresh()->suspended_at);
        $this->assertNull($target->fresh()->suspension_reason);
    }

    public function test_non_admin_cannot_suspend_users(): void {
        $userRole = Role::where('slug', 'user')->first();

        $actor = User::create([
            'name' => 'Regular User',
            'email' => 'regular@example.com',
            'password' => Hash::make('secret'),
        ]);

        $actor->roles()->sync([$userRole->id]);

        $target = User::create([
            'name' => 'Target User',
            'email' => 'target@example.com',
            'password' => Hash::make('secret'),
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'regular@example.com',
            'password' => 'secret',
        ]);

        $token = json_decode($login->getContent(), true, 512, JSON_THROW_ON_ERROR)['token'];

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/users/' . $target->id . '/suspend', ['reason' => 'Nope']);

        $response->assertStatus(403);
        $this->assertNull($target->fresh()->suspended_at);
    }
}
