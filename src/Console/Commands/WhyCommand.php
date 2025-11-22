<?php

namespace HasinHayder\Tyro\Console\Commands;

class WhyCommand extends BaseTyroCommand {
    protected $signature = 'tyro:why';

    protected $description = 'Explain what Tyro brings to your API stack';

    public function handle(): int {
        $this->info('Tyro ships a production-ready Laravel API surface in minutes.');
        $this->line('• Auth stack: login, registration, profile, roles, privileges, and Sanctum tokens with abilities auto-derived from role + privilege slugs.');
        $this->line('• Security rails: user suspension CLI + REST endpoints that revoke every active token the moment an account is frozen.');
        $this->line('• Automation toolbox: 40+ `tyro:*` commands for onboarding, seeding, logouts, audits, and now quick-token safety checks.');
        $this->line('• Docs + samples: seeders, factories, a Postman collection, and a README packed with route + middleware examples.');
        $this->line('Need more context? Run `tyro:about` or visit https://github.com/hasinhayder/tyro');

        return self::SUCCESS;
    }
}
