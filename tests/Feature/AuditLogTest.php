<?php

use HasinHayder\Tyro\Models\AuditLog;
use HasinHayder\Tyro\Models\Role;
use HasinHayder\Tyro\Tests\Fixtures\User;

test('actions on user are audited', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::where('slug', 'admin')->first());
    
    $user = User::factory()->create();
    
    // Act as admin
    $this->actingAs($admin);
    
    $user->suspend('Testing audit');
    
    $this->assertDatabaseHas(config('tyro.tables.audit_logs'), [
        'event' => 'user.suspended',
        'auditable_id' => $user->id,
        'user_id' => $admin->id,
    ]);
});

test('actions on roles are audited via observer', function () {
    $admin = User::factory()->create();
    
    $this->actingAs($admin);
    
    $role = Role::create([
        'name' => 'New Role',
        'slug' => 'new-role'
    ]);
    
    $this->assertDatabaseHas(config('tyro.tables.audit_logs'), [
        'event' => 'role.created',
        'auditable_id' => $role->id,
    ]);
    
    $role->update(['name' => 'Updated Role']);
    
    $this->assertDatabaseHas(config('tyro.tables.audit_logs'), [
        'event' => 'role.updated',
        'auditable_id' => $role->id,
    ]);
});

test('audit logs can be listed via api', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::where('slug', 'admin')->first());
    
    AuditLog::create([
        'event' => 'test.event',
        'user_id' => $admin->id,
    ]);
    
    $this->actingAs($admin)
        ->getJson('/api/audit-logs')
        ->assertStatus(200)
        ->assertJsonFragment(['event' => 'test.event']);
});

test('audit logs can be purged', function () {
    AuditLog::create([
        'event' => 'old.event',
        'created_at' => now()->subDays(40),
    ]);
    
    AuditLog::create([
        'event' => 'new.event',
        'created_at' => now(),
    ]);
    
    $this->artisan('tyro:audit-purge', ['--days' => 30, '--force' => true])
        ->expectsOutput('Successfully purged 1 audit logs.')
        ->assertExitCode(0);
        
    $this->assertDatabaseMissing(config('tyro.tables.audit_logs'), ['event' => 'old.event']);
    $this->assertDatabaseHas(config('tyro.tables.audit_logs'), ['event' => 'new.event']);
});
