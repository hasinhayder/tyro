<?php

namespace HasinHayder\Tyro\View\Directives;

use Illuminate\Support\Facades\Blade;

class UserHasAnyPrivilegeDirective {
    /**
     * Register the @hasanyprivilege Blade directive.
     * Checks if the current user has any of the provided privileges.
     */
    public static function register(): void {
        $handler = function (...$privileges) {
            $user = auth()->user();

            if (!$user || !method_exists($user, 'hasPrivilege')) {
                return false;
            }

            foreach ($privileges as $privilege) {
                if ($user->hasPrivilege($privilege)) {
                    return true;
                }
            }

            return false;
        };

        Blade::if('hasanyprivilege', $handler);
        Blade::if('hasAnyPrivilege', $handler);
    }
}
