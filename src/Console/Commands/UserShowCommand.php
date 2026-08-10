<?php

namespace HasinHayder\Tyro\Console\Commands;

use Illuminate\Support\Carbon;

class UserShowCommand extends BaseTyroCommand {
    protected $signature = 'tyro:user-show {user? : User ID or email}';

    protected $aliases = ['tyro:show-user'];

    protected $description = 'Display a single user\'s details, roles, privileges, and suspension state';

    public function handle(): int {
        $identifier = $this->argument('user') ?? $this->ask('User ID or email');

        if (! $identifier) {
            $this->error('A user identifier is required.');

            return self::FAILURE;
        }

        $user = $this->findUser($identifier);

        if (! $user) {
            $this->error("User [{$identifier}] not found.");

            return self::FAILURE;
        }

        $user->loadMissing('roles.privileges');

        $isSuspended = method_exists($user, 'isSuspended') ? $user->isSuspended() : false;
        $roles = method_exists($user, 'roles') ? $user->roles->pluck('slug')->filter()->implode(', ') : '—';

        $this->table(
            ['Field', 'Value'],
            [
                ['ID', (string) $user->getKey()],
                ['Name', (string) ($user->name ?? 'N/A')],
                ['Email', (string) ($user->email ?? 'N/A')],
                ['Roles', $roles ?: '—'],
                ['Suspended', $isSuspended ? 'Yes' : 'No'],
                ['Suspension Reason', (string) (method_exists($user, 'getSuspensionReason') ? ($user->getSuspensionReason() ?? 'N/A') : 'N/A')],
                ['Created', $this->formatTimestamp($user->created_at ?? null)],
                ['Updated', $this->formatTimestamp($user->updated_at ?? null)],
            ]
        );

        $privileges = method_exists($user, 'privileges') ? $user->privileges()->sortBy('id')->values() : collect();

        if ($privileges->isNotEmpty()) {
            $this->table(
                ['ID', 'Slug', 'Name'],
                $privileges->map(fn ($privilege) => [
                    $privilege->id,
                    $privilege->slug,
                    $privilege->name,
                ])->toArray()
            );
        } else {
            $this->warn('No privileges resolved for this user.');
        }

        return self::SUCCESS;
    }

    protected function formatTimestamp($value): string {
        if (! $value) {
            return 'N/A';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }
}
