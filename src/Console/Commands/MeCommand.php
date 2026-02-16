<?php

namespace HasinHayder\Tyro\Console\Commands;

use Laravel\Sanctum\PersonalAccessToken;

class MeCommand extends BaseTyroCommand
{
    protected $signature = 'tyro:auth-me {token?} {--token=}';

    protected $aliases = ['tyro:me'];

    protected $description = 'Inspect which user a given token belongs to';

    public function handle(): int
    {
        $tokenInput = $this->argument('token') ?? $this->option('token') ?? $this->ask('Paste the full Sanctum token');

        if (! $tokenInput) {
            $this->error('A token is required.');

            return self::FAILURE;
        }

        $token = PersonalAccessToken::findToken($tokenInput);

        if (! $token || ! $token->tokenable) {
            $this->error('Token not found.');

            return self::FAILURE;
        }

        $user = $token->tokenable;

        $this->table([
            'ID', 'Name', 'Email', 'Abilities', 'Token Name', 'Last Used',
        ], [[
            $user->id,
            $user->name,
            $user->email,
            implode(', ', $token->abilities ?? []),
            $token->name,
            optional($token->last_used_at)->toDateTimeString() ?? 'N/A',
        ]]);

        return self::SUCCESS;
    }
}
