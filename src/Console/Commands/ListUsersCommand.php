<?php

namespace HasinHayder\Tyro\Console\Commands;

use Illuminate\Support\Carbon;

class ListUsersCommand extends BaseTyroCommand {
    protected $signature = 'tyro:user-list';

    protected $aliases = ['tyro:users'];

    protected $description = 'Display all users tracked by Tyro';

    public function handle(): int {
        $users = $this->newUserQuery()
            ->with('roles:id,name,slug')
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'created_at', 'updated_at', 'suspended_at', 'suspension_reason']);

        if ($users->isEmpty()) {
            $this->warn('No users found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Email', 'Roles', 'Suspended', 'Created', 'Updated'],
            $users->map(function ($user) {
                $isSuspended = method_exists($user, 'isSuspended')
                    ? $user->isSuspended()
                    : (bool) ($user->suspended_at ?? false);

                $displayName = $isSuspended
                    ? sprintf('<fg=red>%s</>', $user->name ?? 'N/A')
                    : ($user->name ?? '');

                $roles = $user->relationLoaded('roles')
                    ? $user->roles->pluck('slug')->filter()->implode(', ')
                    : '';

                $reason = method_exists($user, 'getSuspensionReason')
                    ? $user->getSuspensionReason()
                    : ($user->suspension_reason ?? null);

                return [
                    $user->id,
                    $displayName ?: '—',
                    $user->email,
                    $roles ?: '—',
                    $isSuspended ? trim('Yes ' . ($reason ? sprintf('(%s)', $reason) : '')) : 'No',
                    $this->formatTimestamp($user->created_at),
                    $this->formatTimestamp($user->updated_at),
                ];
            })->toArray()
        );

        return self::SUCCESS;
    }

    protected function formatTimestamp($value): string {
        if (!$value) {
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
