<?php

namespace HasinHayder\Tyro\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EnsureAnyTyroPrivilege {
    public function handle(Request $request, Closure $next, string ...$privileges) {
        $user = $request->user();

        if (! $user) {
            // throw new AuthorizationException('This action is unauthorized.');
            throw new AuthorizationException('ACCESS DENIED.');
        }

        $required = $this->normalize($privileges);

        if ($required->isEmpty()) {
            return $next($request);
        }

        $ownedPrivileges = $this->resolvePrivilegeSlugs($user);

        $hasPrivilege = $required->contains(fn ($privilege) => $ownedPrivileges->contains(fn ($slug) => $slug === $privilege || $slug === '*'));

        if (! $hasPrivilege) {
            // throw new AuthorizationException('Missing the required Tyro privileges.');
            throw new AuthorizationException('ACCESS DENIED.');
        }

        return $next($request);
    }

    private function normalize(array $privileges): Collection {
        return collect($privileges)
            ->flatMap(function ($chunk) {
                $parts = is_string($chunk) ? explode(',', $chunk) : (array) $chunk;

                return collect($parts)->map(fn ($part) => trim((string) $part));
            })
            ->filter()
            ->unique()
            ->values();
    }

    private function resolvePrivilegeSlugs($user): Collection {
        if (method_exists($user, 'tyroPrivilegeSlugs')) {
            return collect($user->tyroPrivilegeSlugs());
        }

        if (method_exists($user, 'privileges')) {
            $privileges = $user->privileges();

            if ($privileges instanceof Collection) {
                return $privileges->pluck('slug')->filter()->unique()->values();
            }
        }

        return collect();
    }
}
