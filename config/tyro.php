<?php

use HasinHayder\Tyro\Models\Privilege;
use HasinHayder\Tyro\Models\Role;
use HasinHayder\Tyro\Models\UserRole;

return [
    // 'version' => env('TYRO_VERSION', '1.2.3'),

    'disable_commands' => env('TYRO_DISABLE_COMMANDS', false),

    'guard' => env('TYRO_GUARD', 'sanctum'),

    'route_prefix' => env('TYRO_ROUTE_PREFIX', 'api'),
    'route_name_prefix' => env('TYRO_ROUTE_NAME_PREFIX', 'tyro.'),
    'route_middleware' => ['api'],
    'load_default_routes' => true,
    'disable_api' => env('TYRO_DISABLE_API', false),

    'models' => [
        'user' => env('TYRO_USER_MODEL', env('AUTH_MODEL', 'App\\Models\\User')),
        'role' => Role::class,
        'privilege' => Privilege::class,
        'pivot' => UserRole::class,
        'audit_log' => \HasinHayder\Tyro\Models\AuditLog::class,
    ],

    'tables' => [
        'users' => env('TYRO_USERS_TABLE', 'users'),
        'roles' => 'roles',
        'pivot' => 'user_roles',
        'privileges' => 'privileges',
        'role_privilege' => 'privilege_role',
        'audit_logs' => 'tyro_audit_logs',
    ],

    'audit' => [
        'enabled' => env('TYRO_AUDIT_ENABLED', true),
        'retention_days' => env('TYRO_AUDIT_RETENTION_DAYS', 30),
    ],

    'default_user_role_slug' => env('DEFAULT_ROLE_SLUG', 'user'),

    'protected_role_slugs' => ['admin', 'super-admin'],

    'delete_previous_access_tokens_on_login' => env('DELETE_PREVIOUS_ACCESS_TOKENS_ON_LOGIN', false),

    'cache' => [
        'enabled' => env('TYRO_CACHE_ENABLED', true),
        'store' => env('TYRO_CACHE_STORE'),
        'ttl' => env('TYRO_CACHE_TTL', 300),
    ],

    'password' => [
        // Minimum password length
        'min_length' => env('TYRO_PASSWORD_MIN_LENGTH', 8),

        // Maximum password length (null for no limit)
        'max_length' => env('TYRO_PASSWORD_MAX_LENGTH', null),

        // Require password confirmation
        'require_confirmation' => env('TYRO_PASSWORD_REQUIRE_CONFIRMATION', false),

        // Password complexity requirements
        'complexity' => [
            // Require at least one uppercase letter
            'require_uppercase' => env('TYRO_PASSWORD_REQUIRE_UPPERCASE', false),

            // Require at least one lowercase letter
            'require_lowercase' => env('TYRO_PASSWORD_REQUIRE_LOWERCASE', false),

            // Require at least one number
            'require_numbers' => env('TYRO_PASSWORD_REQUIRE_NUMBERS', false),

            // Require at least one special character
            'require_special_chars' => env('TYRO_PASSWORD_REQUIRE_SPECIAL_CHARS', false),

        ],

        // Common password validation
        'check_common_passwords' => env('TYRO_PASSWORD_CHECK_COMMON', false),

        // Disallow user information in password (email, name parts)
        'disallow_user_info' => env('TYRO_PASSWORD_DISALLOW_USER_INFO', false),
    ],

    'abilities' => [
        'admin' => ['admin', 'super-admin'],
        'user_update' => ['admin', 'super-admin', 'user'],
    ],
];
