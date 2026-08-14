<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Models\Role;
use Illuminate\Support\Str;

class RoleCloneCommand extends BaseTyroCommand {
    protected $signature = 'tyro:role-clone {role? : Role ID or slug to clone}
        {--name= : Name for the cloned role}
        {--slug= : Slug for the cloned role (defaults to a slugified name)}';

    protected $aliases = ['tyro:clone-role'];

    protected $description = 'Clone a role together with all of its privileges';

    public function handle(): int {
        $roleIdentifier = $this->argument('role');

        if (! $roleIdentifier) {
            $roleIdentifier = trim((string) $this->ask('Which role slug or ID should be cloned?')) ?: null;
        }

        $source = $this->findRole($roleIdentifier);

        if (! $source) {
            $this->error(sprintf('Role [%s] not found.', $roleIdentifier ?? 'N/A'));

            return self::FAILURE;
        }

        $name = $this->option('name') ?? $this->ask('Name for the cloned role');
        if (! $name) {
            $this->error('Role name is required.');

            return self::FAILURE;
        }

        $slug = $this->option('slug') ?? $this->ask('Role slug (leave blank to use name)');
        $slug = $slug ? Str::slug($slug) : Str::slug($name);

        if (Role::where('slug', $slug)->exists()) {
            $this->error(sprintf('Role with slug "%s" already exists.', $slug));

            return self::FAILURE;
        }

        $clone = Role::create([
            'name' => $name,
            'slug' => $slug,
        ]);

        $privileges = $source->privileges()->get();

        foreach ($privileges as $privilege) {
            $clone->attachPrivilege($privilege);
        }

        $this->info(sprintf(
            'Role "%s" (%s) cloned from "%s" with %d privilege(s).',
            $clone->name,
            $clone->slug,
            $source->slug,
            $privileges->count()
        ));

        if ($privileges->isNotEmpty()) {
            $this->table(
                ['Privilege', 'Name'],
                $privileges->map(fn ($privilege) => [$privilege->slug, $privilege->name])->all()
            );
        }

        return self::SUCCESS;
    }
}
