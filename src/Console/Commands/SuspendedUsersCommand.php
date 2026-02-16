<?php

namespace HasinHayder\Tyro\Console\Commands;

use Illuminate\Support\Carbon;

class SuspendedUsersCommand extends BaseTyroCommand
{
    protected $signature = 'tyro:user-suspended';

    protected $aliases = ['tyro:suspended-users'];

    protected $description = 'List every Tyro user currently suspended';

    public function handle(): int
    {
        $users = $this->newUserQuery()
            ->whereNotNull('suspended_at')
            ->orderByDesc('suspended_at')
            ->get(['id', 'name', 'email', 'suspended_at', 'suspension_reason']);

        if ($users->isEmpty()) {
            $this->warn('No suspended users.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Email', 'Suspended At', 'Reason'],
            $users->map(function ($user) {
                return [
                    $user->id,
                    sprintf('<fg=red>%s</>', $user->name ?? '—'),
                    $user->email,
                    $this->formatTimestamp($user->suspended_at),
                    $user->suspension_reason ? (string) $user->suspension_reason : '—',
                ];
            })->toArray()
        );

        return self::SUCCESS;
    }

    protected function formatTimestamp($value): string
    {
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
