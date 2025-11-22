<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Database\Seeders\PrivilegeSeeder;
use HasinHayder\Tyro\Support\TyroCache;

class SeedPrivilegesCommand extends BaseTyroCommand {
    protected $signature = 'tyro:seed-privileges {--force : Skip confirmation even though this overwrites privileges and role mappings}';

    protected $description = 'Re-seed Tyro\'s default privilege definitions and role assignments';

    public function handle(): int {
        if (!$this->option('force') && !$this->confirm('This will overwrite existing privilege definitions and role mappings. Continue?', false)) {
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
