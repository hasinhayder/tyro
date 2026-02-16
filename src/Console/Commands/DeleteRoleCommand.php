<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Support\TyroCache;

class DeleteRoleCommand extends BaseTyroCommand
{
    protected $signature = 'tyro:role-delete {--role=} {--force}';

    protected $aliases = ['tyro:delete-role'];

    protected $description = 'Delete a role (except the protected ones)';

    public function handle(): int
    {
        $identifier = $this->option('role') ?? $this->ask('Role ID or slug');
        $role = $this->findRole($identifier);

        if (! $role) {
            $this->error('Role not found.');

            return self::FAILURE;
        }

        $protected = config('tyro.protected_role_slugs', ['admin', 'super-admin']);
        if (in_array($role->slug, $protected, true)) {
            $this->error('This role is protected and cannot be deleted.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(sprintf('Delete role "%s" (%s)?', $role->name, $role->slug))) {
            $this->warn('Operation cancelled.');

            return self::SUCCESS;
        }

        TyroCache::forgetUsersByRole($role);
        $role->users()->detach();
        $role->delete();

        $this->info(sprintf('Role "%s" deleted.', $role->slug));

        return self::SUCCESS;
    }
}
