<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Models\Role;
use HasinHayder\Tyro\Support\TyroAudit;
use HasinHayder\Tyro\Support\TyroCache;

class DeleteUserCommand extends BaseTyroCommand
{
    protected $signature = 'tyro:user-delete {--user=} {--force}';

    protected $aliases = ['tyro:delete-user'];

    protected $description = 'Delete a user while respecting the admin guardrails';

    public function handle(): int
    {
        $identifier = $this->option('user') ?? $this->ask('User ID or email');
        $user = $this->findUser($identifier);

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(sprintf('Delete %s (ID: %s)?', $user->email, $user->id))) {
            $this->warn('Operation cancelled.');

            return self::SUCCESS;
        }

        $adminRole = Role::where('slug', 'admin')->first();
        if ($adminRole && method_exists($user, 'roles') && $user->roles()->where('slug', $adminRole->slug)->exists()) {
            $adminCount = $adminRole->users()->count();
            if ($adminCount <= 1) {
                $this->error('Create another admin before deleting this user.');

                return self::FAILURE;
            }
        }

        TyroAudit::log('user.deleted', $user, $user->only(['name', 'email', 'id']));

        TyroCache::forgetUser($user);
        $user->delete();

        $this->info(sprintf('User %s deleted.', $user->email));

        return self::SUCCESS;
    }
}
