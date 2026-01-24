<?php

namespace HasinHayder\Tyro\View\Directives;

use Illuminate\Support\Facades\Blade;

class UserHasPrivilegesDirective {
    /**
     * Register the @hasprivileges Blade directive.
     * Checks if the current user has all of the provided privileges.
     */
    public static function register(): void {
        $handler = function (...$privileges) {
            $user = auth()->user();

            if (!$user || !method_exists($user, 'hasPrivileges')) {
                return false;
            }

            return $user->hasPrivileges($privileges);
        };

        Blade::if('hasprivileges', $handler);
        Blade::if('hasAllPrivileges', $handler);
    }
}
