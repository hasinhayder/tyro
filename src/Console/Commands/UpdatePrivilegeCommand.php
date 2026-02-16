<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Models\Privilege;
use HasinHayder\Tyro\Support\TyroCache;

class UpdatePrivilegeCommand extends BaseTyroCommand
{
    protected $signature = 'tyro:privilege-update {--privilege=} {--name=} {--slug=} {--description=}';

    protected $aliases = ['tyro:update-privilege'];

    protected $description = 'Update an existing privilege record';

    // protected $aliases = ['tyro:update:privilege'];

    public function handle(): int
    {
        $identifier = $this->option('privilege') ?? $this->ask('Privilege ID or slug');

        if (! $identifier) {
            $this->error('A privilege identifier is required.');

            return self::FAILURE;
        }

        $privilege = $this->findPrivilege($identifier);

        if (! $privilege) {
            $this->error('Privilege not found.');

            return self::FAILURE;
        }

        $nameInput = $this->option('name');
        if ($nameInput === null) {
            $nameInput = $this->ask('Privilege name', $privilege->name ?? $privilege->slug);
        }
        $name = trim((string) ($nameInput ?? ''));
        if ($name === '') {
            $name = $privilege->name ?? $privilege->slug;
        }

        $slugInput = $this->option('slug');
        if ($slugInput === null) {
            $slugInput = $this->ask('Privilege slug', $privilege->slug);
        }
        $slug = trim((string) ($slugInput ?? ''));
        if ($slug === '') {
            $slug = $privilege->slug;
        }

        $duplicate = Privilege::where('slug', $slug)
            ->where('id', '!=', $privilege->id)
            ->exists();

        if ($duplicate) {
            $this->error(sprintf('Privilege slug "%s" already exists.', $slug));

            return self::FAILURE;
        }

        $descriptionInput = $this->option('description');
        if ($descriptionInput === null) {
            $descriptionInput = $this->ask('Description (optional)', (string) ($privilege->description ?? ''));
        }
        $description = trim((string) ($descriptionInput ?? ''));
        $description = $description === '' ? null : $description;

        if ($name === ($privilege->name ?? '') && $slug === $privilege->slug && $description === ($privilege->description ?? null)) {
            $this->info('No changes detected.');

            return self::SUCCESS;
        }

        $privilege->update([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
        ]);

        TyroCache::forgetUsersByPrivilege($privilege);

        $this->info(sprintf('Privilege "%s" updated.', $privilege->slug));

        return self::SUCCESS;
    }
}
