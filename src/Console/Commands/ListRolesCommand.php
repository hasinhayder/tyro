<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Models\Role;

class ListRolesCommand extends BaseTyroCommand
{
    protected $signature = 'tyro:role-list';

    protected $aliases = ['tyro:roles'];

    protected $description = 'Display all Tyro roles';

    public function handle(): int
    {
        $roles = Role::query()->withCount('users')->orderBy('id')->get(['id', 'name', 'slug']);

        if ($roles->isEmpty()) {
            $this->warn('No roles found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Slug', 'Users'],
            $roles->map(fn ($role) => [
                $role->id,
                $role->name,
                $role->slug,
                $role->users_count,
            ])->toArray()
        );

        return self::SUCCESS;
    }
}
