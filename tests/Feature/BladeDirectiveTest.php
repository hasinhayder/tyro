<?php

namespace HasinHayder\Tyro\Tests\Feature;

use HasinHayder\Tyro\Models\Privilege;
use HasinHayder\Tyro\Models\Role;
use HasinHayder\Tyro\Tests\Fixtures\User;
use HasinHayder\Tyro\Tests\TestCase;
use Illuminate\Support\Facades\Blade;

class BladeDirectiveTest extends TestCase {
    public function test_has_role_directive_aliases(): void {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'admin')->first());
        $this->actingAs($user);

        $this->assertEquals('yes', trim(Blade::render('@hasrole("admin") yes @endhasrole')));
        $this->assertEquals('yes', trim(Blade::render('@hasRole("admin") yes @endhasRole')));
        $this->assertEquals('', trim(Blade::render('@hasrole("editor") yes @endhasrole')));
        $this->assertEquals('', trim(Blade::render('@hasRole("editor") yes @endhasRole')));
    }

    public function test_has_any_role_directive_aliases(): void {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'editor')->first());
        $this->actingAs($user);

        $this->assertEquals('yes', trim(Blade::render('@hasanyrole("admin", "editor") yes @endhasanyrole')));
        $this->assertEquals('yes', trim(Blade::render('@hasAnyRole("admin", "editor") yes @endhasAnyRole')));
        $this->assertEquals('', trim(Blade::render('@hasanyrole("admin", "super-admin") yes @endhasanyrole')));
        $this->assertEquals('', trim(Blade::render('@hasAnyRole("admin", "super-admin") yes @endhasAnyRole')));
    }

    public function test_has_all_roles_directive_aliases(): void {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'admin')->first());
        $user->roles()->attach(Role::where('slug', 'editor')->first());
        $this->actingAs($user);

        $this->assertEquals('yes', trim(Blade::render('@hasroles("admin", "editor") yes @endhasroles')));
        $this->assertEquals('yes', trim(Blade::render('@hasAllRoles("admin", "editor") yes @endhasAllRoles')));
        $this->assertEquals('', trim(Blade::render('@hasroles("admin", "super-admin") yes @endhasroles')));
        $this->assertEquals('', trim(Blade::render('@hasAllRoles("admin", "super-admin") yes @endhasAllRoles')));
    }

    public function test_has_privilege_directive_aliases(): void {
        $user = User::factory()->create();
        $adminRole = Role::where('slug', 'admin')->first();
        $user->roles()->attach($adminRole);
        $privilege = Privilege::where('slug', 'report.generate')->first();
        // Use syncWithoutDetaching to avoid unique constraint error
        $adminRole->privileges()->syncWithoutDetaching([$privilege->id]);

        $this->actingAs($user);

        $this->assertEquals('yes', trim(Blade::render('@hasprivilege("report.generate") yes @endhasprivilege')));
        $this->assertEquals('yes', trim(Blade::render('@hasPrivilege("report.generate") yes @endhasPrivilege')));
    }

    public function test_has_any_privilege_directive_aliases(): void {
        $user = User::factory()->create();
        $adminRole = Role::where('slug', 'admin')->first();
        $user->roles()->attach($adminRole);
        $privilege1 = Privilege::where('slug', 'report.generate')->first();
        $privilege2 = Privilege::where('slug', 'users.manage')->first();
        $adminRole->privileges()->syncWithoutDetaching([$privilege1->id]);

        $this->actingAs($user);

        $this->assertEquals('yes', trim(Blade::render('@hasanyprivilege("report.generate", "users.manage") yes @endhasanyprivilege')));
        $this->assertEquals('yes', trim(Blade::render('@hasAnyPrivilege("report.generate", "users.manage") yes @endhasAnyPrivilege')));
    }

    public function test_has_all_privileges_directive_aliases(): void {
        $user = User::factory()->create();
        $adminRole = Role::where('slug', 'admin')->first();
        $user->roles()->attach($adminRole);
        $privilege1 = Privilege::where('slug', 'report.generate')->first();
        $privilege2 = Privilege::where('slug', 'users.manage')->first();
        $adminRole->privileges()->syncWithoutDetaching([$privilege1->id, $privilege2->id]);

        $this->actingAs($user);

        $this->assertEquals('yes', trim(Blade::render('@hasprivileges("report.generate", "users.manage") yes @endhasprivileges')));
        $this->assertEquals('yes', trim(Blade::render('@hasAllPrivileges("report.generate", "users.manage") yes @endhasAllPrivileges')));
    }

    public function test_user_can_directive_aliases(): void {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'admin')->first());
        $this->actingAs($user);

        $this->assertEquals('yes', trim(Blade::render('@usercan("admin") yes @endusercan')));
        $this->assertEquals('yes', trim(Blade::render('@userCan("admin") yes @enduserCan')));
    }
}
