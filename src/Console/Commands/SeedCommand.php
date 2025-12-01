<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Database\Seeders\TyroSeeder;
use HasinHayder\Tyro\Support\TyroCache;

class SeedCommand extends BaseTyroCommand {
    protected $signature = 'tyro:seed {--force : Run without confirmation (will truncate users and roles tables)}';

    protected $description = 'Run the TyroSeeder to recreate default roles and the bootstrap admin user';

    public function handle(): int {
        if (!$this->option('force') && !$this->confirm('TyroSeeder truncates your users/roles/privileges tables before seeding. Continue?', false)) {
            $this->warn('Operation cancelled.');

            return self::SUCCESS;
        }

        $seeder = new TyroSeeder();
        $seeder->setContainer($this->laravel)->setCommand($this);
        $seeder->run();
        TyroCache::forgetAllUsersWithRoles();

        $this->info('TyroSeeder completed. Default roles and admin user restored.');

        return self::SUCCESS;
    }
}
