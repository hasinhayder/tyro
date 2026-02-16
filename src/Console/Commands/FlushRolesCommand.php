<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Support\TyroAudit;
use HasinHayder\Tyro\Support\TyroCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FlushRolesCommand extends BaseTyroCommand {
    protected $signature = 'tyro:role-purge {--force : Run without confirmation}';

    protected $aliases = ['tyro:purge-roles'];

    protected $description = 'Truncate the roles and pivot tables without re-seeding them';

    public function handle(): int {
        if (!$this->option('force') && !$this->confirm('This will truncate roles and user role assignments. Continue?', false)) {
            $this->warn('Operation cancelled.');

            return self::SUCCESS;
        }

        $rolesTable = config('tyro.tables.roles', 'roles');
        $pivotTable = config('tyro.tables.pivot', 'user_roles');

        Schema::disableForeignKeyConstraints();
        DB::table($pivotTable)->truncate();
        DB::table($rolesTable)->truncate();
        Schema::enableForeignKeyConstraints();
        TyroCache::forgetAllUsersWithRoles();

        TyroAudit::log('roles.flushed', null, null, [
            'roles_table' => $rolesTable,
            'pivot_table' => $pivotTable,
        ]);

        $this->info('Roles and pivot tables truncated.');

        return self::SUCCESS;
    }
}
