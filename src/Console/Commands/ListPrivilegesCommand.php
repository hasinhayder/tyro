<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Models\Privilege;

class ListPrivilegesCommand extends BaseTyroCommand
{
    protected $signature = 'tyro:privilege-list';

    protected $aliases = ['tyro:privileges'];

    protected $description = 'Display all Tyro privileges and their roles';

    public function handle(): int
    {
        $privileges = Privilege::with('roles:id,name,slug')->get(['id', 'name', 'slug']);

        if ($privileges->isEmpty()) {
            $this->warn('No privileges found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Slug', 'Name', 'Roles'],
            $privileges->map(function (Privilege $privilege) {
                $roles = $privilege->roles->map(fn ($role) => sprintf('%s (#%d)', $role->slug, $role->id))->implode(', ');

                return [
                    $privilege->id,
                    $privilege->slug,
                    $privilege->name,
                    $roles ?: '—',
                ];
            })->toArray()
        );

        return self::SUCCESS;
    }
}
