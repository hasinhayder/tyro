<?php

namespace HasinHayder\Tyro\View\Directives;

use Illuminate\Support\Facades\Blade;

class UserCanDirective {
    /**
     * Register the @usercan Blade directive.
     * Checks if the current user has a specific role or privilege.
     */
    public static function register(): void {
        $handler = function (string $ability) {
            $user = auth()->user();

            if (!$user) {
                return false;
            }

            // Check if user has the ability (role or privilege)
            return method_exists($user, 'can') ? $user->can($ability) : false;
        };

        Blade::if('usercan', $handler);
        Blade::if('userCan', $handler);
    }
}
