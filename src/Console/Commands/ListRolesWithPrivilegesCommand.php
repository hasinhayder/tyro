<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Models\Role;

class ListRolesWithPrivilegesCommand extends BaseTyroCommand
{
    protected $signature = 'tyro:role-list-with-privileges';

    protected $aliases = ['tyro:roles-with-privileges'];

    protected $description = 'Display each role along with its attached privileges';

    public function handle(): int
    {
        $roles = Role::query()
            ->with(['privileges:id,slug,name'])
            ->withCount('users')
            ->orderBy('id')
            ->get(['id', 'name', 'slug']);

        if ($roles->isEmpty()) {
            $this->warn('No roles found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Slug', 'Users', 'Privileges'],
            $roles->map(function (Role $role) {
                $privileges = $role->privileges
                    ->map(fn ($privilege) => $privilege->slug)
                    ->implode(', ');

                return [
                    $role->id,
                    $role->name,
                    $role->slug,
                    $role->users_count,
                    $privileges ?: '—',
                ];
            })->toArray()
        );

        return self::SUCCESS;
    }
}
