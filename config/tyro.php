<?php

use HasinHayder\Tyro\Models\Privilege;
use HasinHayder\Tyro\Models\Role;
use HasinHayder\Tyro\Models\UserRole;

return [
    'version' => env('TYRO_VERSION', '1.1.3'),

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
    ],

    'tables' => [
        'users' => env('TYRO_USERS_TABLE', 'users'),
        'roles' => 'roles',
        'pivot' => 'user_roles',
        'privileges' => 'privileges',
        'role_privilege' => 'privilege_role',
    ],

    'default_user_role_slug' => env('DEFAULT_ROLE_SLUG', 'user'),

    'protected_role_slugs' => ['admin', 'super-admin'],

    'delete_previous_access_tokens_on_login' => env('DELETE_PREVIOUS_ACCESS_TOKENS_ON_LOGIN', false),

    'cache' => [
        'enabled' => env('TYRO_CACHE_ENABLED', true),
        'store' => env('TYRO_CACHE_STORE'),
        'ttl' => env('TYRO_CACHE_TTL', 300),
    ],

    'abilities' => [
        'admin' => ['admin', 'super-admin'],
        'user_update' => ['admin', 'super-admin', 'user'],
    ],
];
