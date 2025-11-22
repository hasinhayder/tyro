<?php

namespace HasinHayder\Tyro\Console\Commands;

class PublishMigrationsCommand extends BaseTyroCommand {
    protected $signature = 'tyro:publish-migrations {--force : Overwrite the existing migration files if they already exist}';

    protected $description = 'Publish Tyro\'s migration files into your application';

    public function handle(): int {
        $options = [
            '--tag' => 'tyro-migrations',
        ];

        if ($this->option('force')) {
            $options['--force'] = true;
        }

        $this->call('vendor:publish', $options);

        $this->info('Tyro migrations (roles, privileges, suspension) published to database/migrations');

        return self::SUCCESS;
    }
}
