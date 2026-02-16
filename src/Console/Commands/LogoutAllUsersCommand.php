<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Support\TyroAudit;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutAllUsersCommand extends BaseTyroCommand
{
    protected $signature = 'tyro:auth-logout-all-users {--force : Skip the confirmation prompt}';

    protected $aliases = ['tyro:logout-all-users'];

    protected $description = 'Revoke every Sanctum token issued for all users';

    public function handle(): int
    {
        $count = PersonalAccessToken::count();

        if ($count === 0) {
            $this->info('No Sanctum tokens were found.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("This will revoke {$count} tokens for every user. Continue?")) {
            $this->warn('Operation cancelled.');

            return self::SUCCESS;
        }

        PersonalAccessToken::query()->delete();

        TyroAudit::log('system.tokens_purged', null, null, ['token_count' => $count]);

        $this->info(sprintf('Revoked %s tokens across all users.', $count));

        return self::SUCCESS;
    }
}
