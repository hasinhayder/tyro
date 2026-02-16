<?php

namespace HasinHayder\Tyro\Console\Commands;

class UnsuspendUserCommand extends BaseTyroCommand
{
    protected $signature = 'tyro:user-unsuspend
        {--user= : User ID or email address}
        {--force : Skip confirmation prompts}';

    protected $aliases = ['tyro:unsuspend-user'];

    protected $description = 'Lift the suspension for a Tyro user';

    public function handle(): int
    {
        $identifier = $this->option('user') ?? $this->ask('User ID or email');

        if (! $identifier) {
            $this->error('A user identifier is required.');

            return self::FAILURE;
        }

        $user = $this->findUser($identifier);

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $isSuspended = method_exists($user, 'isSuspended')
            ? $user->isSuspended()
            : (bool) ($user->suspended_at ?? false);

        if (! $isSuspended) {
            $this->info(sprintf('User %s is not suspended.', $user->email));

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! $this->confirm(sprintf('Un-suspend %s now?', $user->email))) {
                $this->warn('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $user->unsuspend();

        $this->info(sprintf('User %s is no longer suspended.', $user->email));

        return self::SUCCESS;
    }
}
