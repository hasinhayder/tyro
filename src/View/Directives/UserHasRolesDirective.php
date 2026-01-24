<?php

namespace HasinHayder\Tyro\View\Directives;

use Illuminate\Support\Facades\Blade;

class UserHasRolesDirective {
    /**
     * Register the @hasroles Blade directive.
     * Checks if the current user has all of the provided roles.
     */
    public static function register(): void {
        $handler = function (...$roles) {
            $user = auth()->user();

            if (!$user || !method_exists($user, 'hasRoles')) {
                return false;
            }

            return $user->hasRoles($roles);
        };

        Blade::if('hasroles', $handler);
        Blade::if('hasAllRoles', $handler);
    }
}
