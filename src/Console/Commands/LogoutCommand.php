<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Support\TyroAudit;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutCommand extends BaseTyroCommand
{
    protected $signature = 'tyro:auth-logout {token?} {--token=}';

    protected $aliases = ['tyro:logout'];

    protected $description = 'Delete a single Sanctum token (log out the corresponding user session)';

    public function handle(): int
    {
        $tokenInput = $this->argument('token')
            ?? $this->option('token')
            ?? $this->ask('Paste the full Sanctum token');

        if (! $tokenInput) {
            $this->error('A token is required.');

            return self::FAILURE;
        }

        $token = PersonalAccessToken::findToken($tokenInput);

        if (! $token) {
            $this->error('Token not found.');

            return self::FAILURE;
        }

        $user = $token->tokenable;
        $tokenName = $token->name;
        $token->delete();

        if ($user) {
            TyroAudit::log('user.token_revoked', $user, null, ['token_name' => $tokenName]);
        }

        $this->info(sprintf('Token "%s" revoked for %s (ID %s).', $tokenName, $user?->email ?? 'unknown', $user?->id ?? 'N/A'));

        return self::SUCCESS;
    }
}
