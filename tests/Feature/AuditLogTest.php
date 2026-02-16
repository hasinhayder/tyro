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

test('audit logs can be filtered by date range via api', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::where('slug', 'admin')->first());

    // Clear logs created by setup/other tests
    AuditLog::truncate();

    AuditLog::create(['event' => 'past.event', 'created_at' => now()->subDays(5)]);
    AuditLog::create(['event' => 'today.event', 'created_at' => now()]);
    AuditLog::create(['event' => 'future.event', 'created_at' => now()->addDays(5)]);

    $this->actingAs($admin)
        ->getJson('/api/audit-logs?from=' . now()->subDays(1)->format('Y-m-d') . '&to=' . now()->addDays(1)->format('Y-m-d'))
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['event' => 'today.event'])
        ->assertJsonMissing(['event' => 'past.event'])
        ->assertJsonMissing(['event' => 'future.event']);
});

test('role-privilege attachment is audited', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $role = Role::create(['name' => 'Audited Role', 'slug' => 'audited-role']);
    $privilege = \HasinHayder\Tyro\Models\Privilege::create(['name' => 'Audited Privilege', 'slug' => 'audited.privilege']);

    $role->attachPrivilege($privilege);

    $this->assertDatabaseHas(config('tyro.tables.audit_logs'), [
        'event' => 'privilege.attached',
        'auditable_type' => Role::class,
        'auditable_id' => $role->id,
    ]);
});
