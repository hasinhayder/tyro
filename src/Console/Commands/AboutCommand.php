<?php

namespace HasinHayder\Tyro\Console\Commands;

class AboutCommand extends BaseTyroCommand {
    protected $signature = 'tyro:sys-about';

    protected $aliases = ['tyro:about'];

    protected $description = 'Show Tyro\'s mission, version, and author details';

    public function handle(): int {
        $version = config('tyro.version', 'unknown');

        $this->info('Tyro for Laravel');
        $this->line(str_repeat('-', 50));
        $this->line('• Version: ' . $version);
        $this->line('• Author: Hasin Hayder (@hasinhayder)');
        $this->newLine();
        $this->line('Tyro is the ultimate Authentication, Authorization, and');
        $this->line('Role & Privilege Management solution for Laravel 12.');
        $this->newLine();
        $this->line('✓ Complete role-based access control (RBAC)');
        $this->line('✓ Fine-grained privilege management');
        $this->line('✓ User suspension workflows with token revocation');
        $this->line('✓ 40+ CLI commands for automation & incident response');
        $this->line('✓ Blade directives for clean, readable templates');
        $this->line('✓ Ready-to-use middleware for route protection');
        $this->line('✓ Optional REST API for remote management');
        $this->line('✓ Sanctum integration with auto-derived abilities');
        $this->newLine();
        $this->line('Works for APIs, web apps, and hybrid applications.');
        $this->newLine();
        $this->line('• GitHub: https://github.com/hasinhayder/tyro');
        $this->line('• Run `tyro:sys-doc` to open documentation');

        return self::SUCCESS;
    }
}
