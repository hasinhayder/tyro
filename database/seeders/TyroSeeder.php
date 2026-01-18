<?php

namespace HasinHayder\Tyro\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TyroSeeder extends Seeder {
    public function run(): void {
        // $this->truncateTyroTables();
        $this->call([
            RoleSeeder::class,
            PrivilegeSeeder::class,
            UsersSeeder::class,
        ]);
    }

    protected function truncateTyroTables(): void {
        $userClass = config('tyro.models.user', config('auth.providers.users.model', 'App\\Models\\User'));
        $userTable = (new $userClass)->getTable();
        $rolesTable = config('tyro.tables.roles', 'roles');
        $pivotTable = config('tyro.tables.pivot', 'user_roles');
        $privilegesTable = config('tyro.tables.privileges', 'privileges');
        $rolePrivilegesTable = config('tyro.tables.role_privilege', 'privilege_role');

        Schema::disableForeignKeyConstraints();

        foreach ([$rolePrivilegesTable, $privilegesTable, $pivotTable, $userTable, $rolesTable] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();
    }
}
