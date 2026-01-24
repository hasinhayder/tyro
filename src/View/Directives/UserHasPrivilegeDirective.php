<?php

namespace HasinHayder\Tyro\View\Directives;

use Illuminate\Support\Facades\Blade;

class UserHasPrivilegeDirective {
    /**
     * Register the @hasprivilege Blade directive.
     * Checks if the current user has a specific privilege.
     */
    public static function register(): void {
        $handler = function (string $privilege) {
            $user = auth()->user();

            if (!$user) {
                return false;
            }

            return method_exists($user, 'hasPrivilege') ? $user->hasPrivilege($privilege) : false;
        };

        Blade::if('hasprivilege', $handler);
        Blade::if('hasPrivilege', $handler);
    }
}
