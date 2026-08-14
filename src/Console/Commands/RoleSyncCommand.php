<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Models\Role;
use Illuminate\Support\Collection;

class RoleSyncCommand extends BaseTyroCommand {
    protected $signature = 'tyro:role-sync {source? : Role ID or slug to sync from}
        {target? : Role ID or slug to sync to}
        {--remove-extra : Detach privileges from the target that the source does not have}
        {--dry-run : Show the diff without applying any changes}';

    protected $aliases = ['tyro:sync-roles'];

    protected $description = 'Synchronize a role\'s privileges with another role';

    public function handle(): int {
        $sourceIdentifier = $this->argument('source');
        $targetIdentifier = $this->argument('target');

        if (! $sourceIdentifier) {
            $sourceIdentifier = trim((string) $this->ask('Which role slug or ID should be synced from?')) ?: null;
        }

        if (! $targetIdentifier) {
            $targetIdentifier = trim((string) $this->ask('Which role slug or ID should be synced to?')) ?: null;
        }

        $source = $this->findRole($sourceIdentifier);
        $target = $this->findRole($targetIdentifier);

        if (! $source) {
            $this->error(sprintf('Source role [%s] not found.', $sourceIdentifier ?? 'N/A'));

            return self::FAILURE;
        }

        if (! $target) {
            $this->error(sprintf('Target role [%s] not found.', $targetIdentifier ?? 'N/A'));

            return self::FAILURE;
        }

        if ($source->is($target)) {
            $this->error('Source and target roles must be different.');

            return self::FAILURE;
        }

        $sourcePrivileges = $source->privileges()->get();
        $targetPrivileges = $target->privileges()->get();

        $sourceBySlug = $sourcePrivileges->keyBy('slug');
        $targetBySlug = $targetPrivileges->keyBy('slug');

        $missing = $sourcePrivileges->reject(fn ($privilege) => $targetBySlug->has($privilege->slug));
        $extra = $targetPrivileges->reject(fn ($privilege) => $sourceBySlug->has($privilege->slug));
        $matched = $sourcePrivileges->filter(fn ($privilege) => $targetBySlug->has($privilege->slug));

        if ($missing->isEmpty() && $extra->isEmpty()) {
            $this->info(sprintf('Roles [%s] and [%s] are already in sync.', $source->slug, $target->slug));

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $removeExtra = (bool) $this->option('remove-extra');

        $rows = [];

        foreach ($missing as $privilege) {
            $rows[] = ['+', $privilege->slug, $privilege->name];
        }

        foreach ($extra as $privilege) {
            $rows[] = ['-', $privilege->slug, $privilege->name];
        }

        foreach ($matched as $privilege) {
            $rows[] = ['=', $privilege->slug, $privilege->name];
        }

        $this->table(['Action', 'Privilege', 'Name'], $rows);

        if ($dryRun) {
            $this->warn('DRY RUN — no changes were made.');
            $this->warnDrift($missing, $extra, $source, $target, $removeExtra, true);

            return self::SUCCESS;
        }

        $this->warnDrift($missing, $extra, $source, $target, $removeExtra, false);

        foreach ($missing as $privilege) {
            $target->attachPrivilege($privilege);
        }

        if ($removeExtra) {
            foreach ($extra as $privilege) {
                $target->detachPrivilege($privilege);
            }
        }

        $summary = sprintf(
            'Synced [%s] with [%s]: %d privilege(s) attached',
            $target->slug,
            $source->slug,
            $missing->count()
        );

        if ($removeExtra) {
            $summary .= sprintf(', %d detached', $extra->count());
        } elseif ($extra->isNotEmpty()) {
            $summary .= sprintf(' (%d extra privilege(s) kept, use --remove-extra to strip)', $extra->count());
        }

        $this->info($summary.'.');

        return self::SUCCESS;
    }

    /**
     * Print a clear warning for every privilege drift between the two roles.
     */
    protected function warnDrift(Collection $missing, Collection $extra, Role $source, Role $target, bool $removeExtra, bool $dryRun): void {
        if ($missing->isEmpty() && $extra->isEmpty()) {
            return;
        }

        $verb = $dryRun ? 'would be' : 'will be';

        $this->warn('Drift detected:');

        if ($missing->isNotEmpty()) {
            $this->warn(sprintf(
                '%d privilege(s) on [%s] are missing from [%s] and %s attached:',
                $missing->count(),
                $target->slug,
                $source->slug,
                $verb
            ));

            foreach ($missing as $privilege) {
                $this->warn('  + '.$privilege->slug);
            }
        }

        if ($extra->isNotEmpty()) {
            $fate = $removeExtra ? 'DETACHED' : 'KEPT (use --remove-extra to strip them)';

            $this->warn(sprintf(
                '%d privilege(s) on [%s] are not in [%s] and %s %s:',
                $extra->count(),
                $target->slug,
                $source->slug,
                $verb,
                $fate
            ));

            foreach ($extra as $privilege) {
                $this->warn('  - '.$privilege->slug);
            }
        }
    }
}
