<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Support\TyroCache;

class DeletePrivilegeCommand extends BaseTyroCommand {
    protected $signature = 'tyro:privilege-delete {privilege? : Privilege ID or slug}
        {--force : Skip confirmation prompt}';

    protected $aliases = ['tyro:delete-privilege'];

    protected $description = 'Delete a Tyro privilege record';

    public function handle(): int {
        $identifier = $this->argument('privilege');

        if (! $identifier) {
            $identifier = trim((string) $this->ask('Which privilege slug or ID should be deleted?')) ?: null;
        }

        if (! $identifier) {
            $this->error('A privilege identifier is required.');

            return self::FAILURE;
        }

        $privilege = $this->findPrivilege($identifier);

        if (! $privilege) {
            $this->error("Privilege [{$identifier}] not found.");

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $attachedRoles = $privilege->roles()->pluck('slug')->all();

            if ($attachedRoles !== []) {
                $this->warn(sprintf(
                    'This privilege is attached to %s: %s.',
                    count($attachedRoles) === 1 ? 'this role' : 'these roles',
                    implode(', ', $attachedRoles)
                ));

                if (! $this->confirm('Do you want to delete it?')) {
                    $this->warn('Operation cancelled.');

                    return self::SUCCESS;
                }
            } elseif (! $this->confirm(sprintf('Delete privilege "%s" (%s)?', $privilege->name, $privilege->slug))) {
                $this->warn('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        TyroCache::forgetUsersByPrivilege($privilege);
        $privilege->roles()->detach();
        $privilege->delete();

        $this->info(sprintf('Privilege "%s" deleted.', $privilege->slug));

        return self::SUCCESS;
    }
}
