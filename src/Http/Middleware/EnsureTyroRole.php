<?php

namespace HasinHayder\Tyro\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EnsureTyroRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (! $user) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $required = $this->normalize($roles);

        if ($required->isEmpty()) {
            return $next($request);
        }

        $ownedRoles = $this->resolveRoleSlugs($request, $user);

        foreach ($required as $role) {
            if (! $this->matchesRole($ownedRoles, $role)) {
                // throw new AuthorizationException('Missing Tyro role: '.$role);
                throw new AuthorizationException('ACCESS DENIED.');
            }
        }

        return $next($request);
    }

    private function normalize(array $roles): Collection
    {
        return collect($roles)
            ->flatMap(function ($chunk) {
                $parts = is_string($chunk) ? explode(',', $chunk) : (array) $chunk;

                return collect($parts)->map(fn ($part) => trim((string) $part));
            })
            ->filter()
            ->unique()
            ->values();
    }

    private function resolveRoleSlugs(Request $request, $user): Collection
    {
        if ($request->attributes->has('tyro.role_slugs')) {
            return $request->attributes->get('tyro.role_slugs');
        }

        if (method_exists($user, 'tyroRoleSlugs')) {
            $slugs = collect($user->tyroRoleSlugs());
            $request->attributes->set('tyro.role_slugs', $slugs);

            return $slugs;
        }

        if ($user->relationLoaded('roles')) {
            $slugs = $user->roles->pluck('slug')->filter()->unique()->values();
            $request->attributes->set('tyro.role_slugs', $slugs);

            return $slugs;
        }

        if (method_exists($user, 'roles')) {
            $slugs = $user->roles()->select('slug')->pluck('slug')->filter()->unique()->values();
            $request->attributes->set('tyro.role_slugs', $slugs);

            return $slugs;
        }

        $empty = collect();
        $request->attributes->set('tyro.role_slugs', $empty);

        return $empty;
    }

    private function matchesRole(Collection $ownedRoles, string $requiredRole): bool
    {
        if ($requiredRole === '*') {
            return true;
        }

        return $ownedRoles->contains(function ($slug) use ($requiredRole) {
            return $slug === $requiredRole || $slug === '*';
        });
    }
}
