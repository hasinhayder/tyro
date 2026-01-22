<?php

namespace HasinHayder\Tyro\Console\Commands;

use Illuminate\Console\Command;

class VersionCommand extends BaseTyroCommand {
    protected $signature = 'tyro:version';

    protected $description = 'Show the currently installed Tyro version';

    public function handle(): int {
        $version = "1.2.3";
        
        $this->info('');
        $this->info('  ╔════════════════════════════════════════╗');
        $this->info('  ║                                        ║');
        $this->info('  ║        Tyro                            ║');
        $this->info('  ║                                        ║');
        $this->info('  ╚════════════════════════════════════════╝');
        $this->info('');
        $this->info("  Version: <comment>{$version}</comment>");
        $this->info('  Laravel: <comment>' . app()->version() . '</comment>');
        $this->info('  PHP: <comment>' . PHP_VERSION . '</comment>');
        $this->info('');
        $this->info('  Documentation: <comment>https://hasinhayder.github.io/tyro/doc.html</comment>');
        $this->info('  GitHub: <comment>https://github.com/hasinhayder/tyro</comment>');
        $this->info('');

        return self::SUCCESS;
    }
}
