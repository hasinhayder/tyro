<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Models\Privilege;

class AddPrivilegeCommand extends BaseTyroCommand
{
    protected $signature = 'tyro:privilege-create {slug? : Unique slug for the privilege}
        {--name= : Readable name for the privilege}
        {--description= : Optional description for the privilege}';

    protected $aliases = ['tyro:add-privilege'];

    protected $description = 'Create a new Tyro privilege record';

    public function handle(): int
    {
        $slug = $this->argument('slug') ?? $this->ask('Privilege slug');

        if (! $slug) {
            $this->error('Slug is required.');

            return self::FAILURE;
        }

        if (Privilege::where('slug', $slug)->exists()) {
            $this->error("Privilege [{$slug}] already exists.");

            return self::FAILURE;
        }

        $name = $this->option('name') ?? $this->ask('Privilege name', $slug);
        $description = $this->option('description') ?? $this->ask('Description (optional)', '');

        $privilege = Privilege::create([
            'slug' => $slug,
            'name' => $name,
            'description' => $description ?: null,
        ]);

        $this->info("Privilege [{$privilege->slug}] created (ID {$privilege->id}).");

        return self::SUCCESS;
    }
}
