<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Database\Seeders\RoleSeeder;
use HasinHayder\Tyro\Support\TyroCache;

class SeedRolesCommand extends BaseTyroCommand
{
    protected $signature = 'tyro:seed-roles {--force : Skip confirmation}';

    protected $description = 'Seed default role definitions';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will seed the roles. Are you sure to continue?', false)) {
            $this->warn('Operation cancelled.');

            return self::SUCCESS;
        }

        /** @var RoleSeeder $seeder */
        $seeder = $this->laravel->make(RoleSeeder::class);
        $seeder->run();
        TyroCache::forgetAllUsersWithRoles();

        $this->info('Default Tyro roles have been re-seeded.');

        return self::SUCCESS;
    }
}
