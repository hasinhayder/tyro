<?php

namespace HasinHayder\Tyro\Console\Commands;

use Illuminate\Console\Command;

class VersionCommand extends BaseTyroCommand {
    protected $signature = 'tyro:version';

    protected $description = 'Show the currently installed Tyro version';

    public function handle(): int {
        $version = "1.2.8"; //fix: resolve issue #6 authenticated users cannot update email verification timestamp anymore
        
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

//1.2.8 - fixed issue #6. Now authenticated users can not update their email verification timestamp anymore
//1.2.7 - merged PR 5 that improved middleware - https://github.com/hasinhayder/tyro/pull/5
//1.2.6 - merged PR 4 that improved the blade directives -https://github.com/hasinhayder/tyro/pull/4
//1.2.5 - merged PR 5 to avoid N+1 query issue