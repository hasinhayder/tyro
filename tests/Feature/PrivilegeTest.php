<?php

namespace HasinHayder\Tyro\Tests\Feature;

use HasinHayder\Tyro\Models\Privilege;
use HasinHayder\Tyro\Models\Role;
use HasinHayder\Tyro\Tests\TestCase;
use Illuminate\Testing\Fluent\AssertableJson;

class PrivilegeTest extends TestCase {
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

    public function test_list_privileges_returns_seeded_privileges(): void {
        $this->authenticate();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)->get('/api/privileges');

        $response->assertJsonFragment(['slug' => 'report.generate']);
    }

    public function test_create_update_and_delete_privilege(): void {
        $this->authenticate();

        $payload = [
            'name' => 'Custom Report',
            'slug' => 'report.custom',
            'description' => 'Generates a bespoke report.',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->post('/api/privileges', $payload);

        $response->assertJson(fn(AssertableJson $json) => $json
            ->where('slug', 'report.custom')
            ->where('name', 'Custom Report')
            ->etc());

        $privilegeId = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR)['id'];

        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->put("/api/privileges/{$privilegeId}", [
                'name' => 'Updated Custom Report',
            ]);

        $updateResponse->assertJson(fn(AssertableJson $json) => $json
            ->where('name', 'Updated Custom Report')
            ->where('id', $privilegeId)
            ->etc());

        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->delete("/api/privileges/{$privilegeId}");

        $deleteResponse->assertJson(fn(AssertableJson $json) => $json
            ->where('error', 0)
            ->where('message', 'privilege has been deleted'));

        $this->assertDatabaseMissing(config('tyro.tables.privileges', 'privileges'), ['id' => $privilegeId]);
    }

    public function test_role_privilege_routes_attach_and_detach(): void {
        $this->authenticate();

        $role = Role::where('slug', 'editor')->firstOrFail();
        $privilege = Privilege::factory()->create([
            'slug' => 'content.publish',
            'name' => 'Publish Content',
        ]);

        $attachResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->post("/api/roles/{$role->id}/privileges", [
                'privilege_id' => $privilege->id,
            ]);

        $attachResponse->assertOk();

        $this->assertTrue($role->fresh()->privileges->contains('id', $privilege->id));

        $detachResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->delete("/api/roles/{$role->id}/privileges/{$privilege->id}");

        $detachResponse->assertOk();

        $this->assertFalse($role->fresh()->privileges->contains('id', $privilege->id));
    }
}
