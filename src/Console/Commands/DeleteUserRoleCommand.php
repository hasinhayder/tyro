<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Support\TyroCache;

class DeleteUserRoleCommand extends BaseTyroCommand
{
    protected $signature = 'tyro:role-remove {--user=} {--role=}';

    protected $aliases = ['tyro:delete-user-role'];

    protected $description = 'Detach a role from a user';

    public function handle(): int
    {
        $userIdentifier = $this->option('user') ?? $this->ask('User ID or email');
        $roleIdentifier = $this->option('role') ?? $this->ask('Role ID or slug');

        $user = $this->findUser($userIdentifier);
        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        if (! method_exists($user, 'roles')) {
            $this->error('The configured user model does not use the HasTyroRoles trait.');

            return self::FAILURE;
        }

        $role = $this->findRole($roleIdentifier);
        if (! $role) {
            $this->error('Role not found.');

            return self::FAILURE;
        }

        $user->removeRole($role);

        $this->info(sprintf('Role "%s" removed from %s.', $role->slug, $user->email));

        return self::SUCCESS;
    }
}
