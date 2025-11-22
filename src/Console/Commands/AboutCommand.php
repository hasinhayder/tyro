<?php

namespace HasinHayder\Tyro\Console\Commands;

class AboutCommand extends BaseTyroCommand {
    protected $signature = 'tyro:about';

    protected $description = 'Show Tyro\'s mission, version, and author details';

    public function handle(): int {
        $version = config('tyro.version', 'unknown');

        $this->info('Tyro for Laravel');
        $this->line(str_repeat('-', 40));
        $this->line('• Version: ' . $version);
        $this->line('• Author: Hasin Hayder (@hasinhayder)');
        $this->line('• Description: Zero-config API boilerplate with Sanctum abilities, roles, suspensions, and artisan tooling.');
        $this->line('• GitHub: https://github.com/hasinhayder/tyro');

        return self::SUCCESS;
    }
}
