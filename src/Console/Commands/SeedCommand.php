<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Database\Seeders\TyroSeeder;
use HasinHayder\Tyro\Support\TyroAudit;
use HasinHayder\Tyro\Support\TyroCache;

class SeedCommand extends BaseTyroCommand {
    protected $signature = 'tyro:seed-all {--force : Run without confirmation}';

    protected $aliases = ['tyro:seed'];

    protected $description = 'Seed default roles, privileges, and bootstrap admin user';

    public function handle(): int {
        if (!$this->option('force') && !$this->confirm('This will seed roles, privileges, and the admin user. Are you sure to continue?', false)) {
            $this->warn('Operation cancelled.');

            return self::SUCCESS;
        }

        $seeder = new TyroSeeder();
        $seeder->setContainer($this->laravel)->setCommand($this);
        $seeder->run();
        TyroCache::forgetAllUsersWithRoles();

        TyroAudit::log('system.seeded', null, null, ['type' => 'full']);

        $this->info('TyroSeeder completed. Default roles and admin user restored.');

        return self::SUCCESS;
    }
}
