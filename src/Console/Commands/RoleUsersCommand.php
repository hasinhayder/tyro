<?php

namespace HasinHayder\Tyro\Console\Commands;

class RoleUsersCommand extends BaseTyroCommand {
    protected $signature = 'tyro:role-users {role? : Role ID or slug}';

    protected $description = 'Display every user assigned to the given Tyro role';

    public function handle(): int {
        $identifier = $this->argument('role') ?? $this->ask('Role ID or slug');

        if (! $identifier) {
            $this->error('A role identifier is required.');

            return self::FAILURE;
        }

        $role = $this->findRole($identifier);

        if (! $role) {
            $this->error('Role not found.');

            return self::FAILURE;
        }

        $userRelation = $role->users();
        $userTable = $userRelation->getModel()->getTable();

        $users = $userRelation
            ->orderBy($userTable.'.id')
            ->get([
                $userTable.'.id as id',
                $userTable.'.name as name',
                $userTable.'.email as email',
            ]);

        if ($users->isEmpty()) {
            $this->warn(sprintf('No users currently belong to the "%s" role.', $role->slug));

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Email'],
            $users->map(fn ($user) => [
                $user->id,
                $user->name,
                $user->email,
            ])->toArray()
        );

        return self::SUCCESS;
    }
}
