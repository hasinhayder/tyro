<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Models\Privilege;
use HasinHayder\Tyro\Support\TyroAudit;
use HasinHayder\Tyro\Support\TyroCache;
use Illuminate\Support\Facades\DB;

class PurgePrivilegesCommand extends BaseTyroCommand
{
    protected $signature = 'tyro:privilege-purge {--force : Skip confirmation prompt}';

    protected $aliases = ['tyro:purge-privileges'];

    protected $description = 'Delete every privilege record and detach them from roles';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will delete every privilege. Continue?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        DB::table(config('tyro.tables.role_privilege', 'privilege_role'))->truncate();
        $deleted = Privilege::query()->delete();
        TyroCache::forgetAllUsersWithRoles();

        TyroAudit::log('privileges.purged', null, null, ['deleted_count' => $deleted]);

        $this->info("Deleted {$deleted} privilege(s).");

        return self::SUCCESS;
    }
}
