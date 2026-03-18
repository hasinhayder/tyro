<?php

namespace HasinHayder\Tyro\Console\Commands;

class UserPrivilegesCommand extends BaseTyroCommand {
    protected $signature = 'tyro:user-privileges {user? : User ID or email}';

    protected $description = 'Display the privileges inherited by a specific user';

    public function handle(): int {
        $identifier = $this->argument('user') ?? $this->ask('User ID or email');

        if (! $identifier) {
            $this->error('A user identifier is required.');

            return self::FAILURE;
        }

        $user = $this->findUser($identifier);

        if (! $user) {
            $this->error("User [{$identifier}] not found.");

            return self::FAILURE;
        }

        if (! method_exists($user, 'roles')) {
            $this->error('The configured user model does not include Tyro roles.');

            return self::FAILURE;
        }

        $this->info(sprintf('User: %s <%s>', $user->name ?? 'N/A', $user->email));

        $privileges = $user->privileges()->sortBy('id')->values();

        if ($privileges->isEmpty()) {
            $this->warn('No privileges resolved for this user.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Slug', 'Name'],
            $privileges->map(fn ($privilege) => [
                $privilege->id,
                $privilege->slug,
                $privilege->name,
            ])->toArray()
        );

        return self::SUCCESS;
    }
}
