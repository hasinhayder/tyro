<?php

namespace HasinHayder\Tyro\Console\Commands;

class PublishConfigCommand extends BaseTyroCommand {
    protected $signature = 'tyro:publish-config {--force : Overwrite the existing config file if it already exists}';

    protected $description = 'Publish Tyro\'s configuration file into your application';

    public function handle(): int {
        $options = [
            '--provider' => 'HasinHayder\\Tyro\\Providers\\TyroServiceProvider',
            '--tag' => 'tyro-config',
        ];

        if ($this->option('force')) {
            $options['--force'] = true;
        }

        $this->call('vendor:publish', $options);

        $this->info('Tyro configuration published to config/tyro.php');

        return self::SUCCESS;
    }
}
