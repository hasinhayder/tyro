<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Database\Seeders\PrivilegeSeeder;
use HasinHayder\Tyro\Support\TyroCache;

class SeedPrivilegesCommand extends BaseTyroCommand {
    protected $signature = 'tyro:seed-privileges {--force : Skip confirmation}';

    protected $description = 'Seed default privilege definitions and role assignments';

    public function handle(): int {
        if (!$this->option('force') && !$this->confirm('This will seed the privileges and role mappings. Are you sure to continue?', false)) {
            $this->warn('Operation cancelled.');

            return self::SUCCESS;
        }

        /** @var PrivilegeSeeder $seeder */
        $seeder = $this->laravel->make(PrivilegeSeeder::class);
        $seeder->setContainer($this->laravel)->setCommand($this);
        $seeder->run();
        TyroCache::forgetAllUsersWithRoles();

        $this->info('Default Tyro privileges and role mappings have been re-seeded.');

        return self::SUCCESS;
    }
}
