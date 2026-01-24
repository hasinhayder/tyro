<?php

namespace HasinHayder\Tyro\View\Directives;

use Illuminate\Support\Facades\Blade;

class UserHasRoleDirective {
    /**
     * Register the @hasrole Blade directive.
     * Checks if the current user has a specific role.
     */
    public static function register(): void {
        $handler = function (string $role) {
            $user = auth()->user();

            if (!$user) {
                return false;
            }

            return method_exists($user, 'hasRole') ? $user->hasRole($role) : false;
        };

        Blade::if('hasrole', $handler);
        Blade::if('hasRole', $handler);
    }
}
