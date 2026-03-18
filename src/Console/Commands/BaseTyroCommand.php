<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Models\Privilege;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Process\Process;

abstract class BaseTyroCommand extends Command {
    protected function userClass(): string {
        return (string) config('tyro.models.user', config('auth.providers.users.model', 'App\\Models\\User'));
    }

    protected function newUserQuery() {
        $class = $this->userClass();

        return $class::query();
    }

    protected function findUser(?string $identifier): ?Model {
        if (! $identifier) {
            return null;
        }

        $query = $this->newUserQuery();

        if (is_numeric($identifier)) {
            return $query->find($identifier);
        }

        return $query->where('email', $identifier)->first();
    }

    protected function findRole(?string $identifier): ?Role {
        if (! $identifier) {
            return null;
        }

        if (is_numeric($identifier)) {
            return Role::query()->find($identifier);
        }

        return Role::query()->where('slug', $identifier)->first();
    }

    protected function findPrivilege(?string $identifier): ?Privilege {
        if (! $identifier) {
            return null;
        }

        if (is_numeric($identifier)) {
            return Privilege::query()->find($identifier);
        }

        return Privilege::query()->where('slug', $identifier)->first();
    }

    protected function defaultRole(): ?Role {
        return Role::where('slug', config('tyro.default_user_role_slug', 'user'))->first();
    }

    protected function openUrl(string $url): bool {
        $command = match (PHP_OS_FAMILY) {
            'Darwin' => ['open', $url],
            'Windows' => ['cmd', '/c', 'start', '', $url],
            default => ['xdg-open', $url],
        };

        try {
            $process = new Process($command);
            $process->setTimeout(3);
            $process->disableOutput();
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function abilitiesForUser(Model $user): array {
        if (! method_exists($user, 'roles')) {
            return ['*'];
        }

        $roles = $user->roles()->with('privileges')->get();

        $abilities = $roles->pluck('slug')
            ->merge(
                $roles->flatMap(fn (Role $role) => $role->privileges->pluck('slug'))
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        return ! empty($abilities) ? $abilities : ['*'];
    }
}
