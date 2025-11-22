<?php

namespace HasinHayder\Tyro\Console\Commands;

class VersionCommand extends BaseTyroCommand {
    protected $signature = 'tyro:version';

    protected $description = 'Show the currently installed Tyro version';

    public function handle(): int {
        $version = config('tyro.version', 'unknown');
        $this->info('Tyro v' . $version);

        return self::SUCCESS;
    }
}
