<?php

namespace HasinHayder\Tyro\Providers;

use HasinHayder\Tyro\Console\Commands\AboutCommand;
use HasinHayder\Tyro\Console\Commands\AddPrivilegeCommand;
use HasinHayder\Tyro\Console\Commands\AddRoleCommand;
use HasinHayder\Tyro\Console\Commands\AssignRoleCommand;
use HasinHayder\Tyro\Console\Commands\AttachPrivilegeCommand;
use HasinHayder\Tyro\Console\Commands\CreateUserCommand;
use HasinHayder\Tyro\Console\Commands\DeletePrivilegeCommand;
use HasinHayder\Tyro\Console\Commands\DeleteRoleCommand;
use HasinHayder\Tyro\Console\Commands\DeleteUserCommand;
use HasinHayder\Tyro\Console\Commands\DeleteUserRoleCommand;
use HasinHayder\Tyro\Console\Commands\DetachPrivilegeCommand;
use HasinHayder\Tyro\Console\Commands\DocCommand;
use HasinHayder\Tyro\Console\Commands\FlushRolesCommand;
use HasinHayder\Tyro\Console\Commands\InstallCommand;
use HasinHayder\Tyro\Console\Commands\ListAuditLogsCommand;
use HasinHayder\Tyro\Console\Commands\ListPrivilegesCommand;
use HasinHayder\Tyro\Console\Commands\ListRolesCommand;
use HasinHayder\Tyro\Console\Commands\ListRolesWithPrivilegesCommand;
use HasinHayder\Tyro\Console\Commands\ListUsersCommand;
use HasinHayder\Tyro\Console\Commands\ListUsersWithRolesCommand;
use HasinHayder\Tyro\Console\Commands\LoginCommand;
use HasinHayder\Tyro\Console\Commands\LogoutAllCommand;
use HasinHayder\Tyro\Console\Commands\LogoutAllUsersCommand;
use HasinHayder\Tyro\Console\Commands\LogoutCommand;
use HasinHayder\Tyro\Console\Commands\MeCommand;
use HasinHayder\Tyro\Console\Commands\PostmanCollectionCommand;
use HasinHayder\Tyro\Console\Commands\PrepareUserModelCommand;
use HasinHayder\Tyro\Console\Commands\PublishConfigCommand;
use HasinHayder\Tyro\Console\Commands\PublishMigrationsCommand;
use HasinHayder\Tyro\Console\Commands\PurgeAuditLogsCommand;
use HasinHayder\Tyro\Console\Commands\PurgePrivilegesCommand;
use HasinHayder\Tyro\Console\Commands\QuickTokenCommand;
use HasinHayder\Tyro\Console\Commands\RoleUsersCommand;
use HasinHayder\Tyro\Console\Commands\RunTestsCommand;
use HasinHayder\Tyro\Console\Commands\SeedCommand;
use HasinHayder\Tyro\Console\Commands\SeedPrivilegesCommand;
use HasinHayder\Tyro\Console\Commands\SeedRolesCommand;
use HasinHayder\Tyro\Console\Commands\StarCommand;
use HasinHayder\Tyro\Console\Commands\SuspendedUsersCommand;
use HasinHayder\Tyro\Console\Commands\SuspendUserCommand;
use HasinHayder\Tyro\Console\Commands\UnsuspendUserCommand;
use HasinHayder\Tyro\Console\Commands\UpdatePrivilegeCommand;
use HasinHayder\Tyro\Console\Commands\UpdateRoleCommand;
use HasinHayder\Tyro\Console\Commands\UpdateUserCommand;
use HasinHayder\Tyro\Console\Commands\UserPrivilegesCommand;
use HasinHayder\Tyro\Console\Commands\UserRolesCommand;
use HasinHayder\Tyro\Console\Commands\VersionCommand;

use HasinHayder\Tyro\Http\Middleware\EnsureAnyTyroPrivilege;
use HasinHayder\Tyro\Http\Middleware\EnsureAnyTyroRole;
use HasinHayder\Tyro\Http\Middleware\EnsureTyroPrivilege;
use HasinHayder\Tyro\Http\Middleware\EnsureTyroRole;
use HasinHayder\Tyro\Http\Middleware\TyroLog;
use HasinHayder\Tyro\Models\Privilege;
use HasinHayder\Tyro\Models\Role;
use HasinHayder\Tyro\View\Directives\UserCanDirective;
use HasinHayder\Tyro\View\Directives\UserHasAnyPrivilegeDirective;
use HasinHayder\Tyro\View\Directives\UserHasAnyRoleDirective;
use HasinHayder\Tyro\View\Directives\UserHasPrivilegeDirective;
use HasinHayder\Tyro\View\Directives\UserHasPrivilegesDirective;
use HasinHayder\Tyro\View\Directives\UserHasRoleDirective;
use HasinHayder\Tyro\View\Directives\UserHasRolesDirective;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

class TyroServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/../../config/tyro.php', 'tyro');
    }

    public function boot(): void {
        $this->registerPublishing();
        $this->registerRoutes();
        $this->registerMiddleware();
        $this->registerBindings();
        $this->registerCommands();
        $this->registerBladeDirectives();
        $this->registerObservers();

        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
            $this->loadFactoriesFrom(__DIR__ . '/../../database/factories');
        }
    }

    protected function registerRoutes(): void {
        if (config('tyro.disable_api', false)) {
            return;
        }

        if (!config('tyro.load_default_routes', true)) {
            return;
        }

        Route::group([
            'prefix' => trim(config('tyro.route_prefix', 'api'), '/'),
            'middleware' => config('tyro.route_middleware', ['api']),
            'as' => config('tyro.route_name_prefix', 'tyro.'),
        ], function (): void {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        });
    }

    protected function registerMiddleware(): void {
        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('tyro.log', TyroLog::class);
        $router->aliasMiddleware('privilege', EnsureTyroPrivilege::class);
        $router->aliasMiddleware('privileges', EnsureAnyTyroPrivilege::class);
        $router->aliasMiddleware('role', EnsureTyroRole::class);
        $router->aliasMiddleware('roles', EnsureAnyTyroRole::class);

        if (!array_key_exists('ability', $router->getMiddleware())) {
            $router->aliasMiddleware('ability', CheckForAnyAbility::class);
        }

        if (!array_key_exists('abilities', $router->getMiddleware())) {
            $router->aliasMiddleware('abilities', CheckAbilities::class);
        }
    }

    protected function registerBindings(): void {
        Route::model('role', Role::class);
        Route::model('privilege', Privilege::class);

        Route::bind('user', function ($value) {
            $userClass = config('tyro.models.user', config('auth.providers.users.model'));

            return $userClass::query()->findOrFail($value);
        });
    }

    protected function registerPublishing(): void {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../../config/tyro.php' => config_path('tyro.php'),
        ], 'tyro-config');

        $this->publishes([
            __DIR__ . '/../../database/migrations/' => database_path('migrations'),
        ], 'tyro-migrations');

        $this->publishes([
            __DIR__ . '/../../database/seeders/' => database_path('seeders'),
            __DIR__ . '/../../database/factories/' => database_path('factories'),
        ], 'tyro-database');

        $this->publishes([
            __DIR__ . '/../../resources/' => resource_path('vendor/tyro'),
        ], 'tyro-assets');
    }

    protected function registerCommands(): void {
        if (!$this->app->runningInConsole() || config('tyro.disable_commands', false)) {
            return;
        }

        $this->commands([
            AddRoleCommand::class,
            AddPrivilegeCommand::class,
            AboutCommand::class,
            AttachPrivilegeCommand::class,
            AssignRoleCommand::class,
            CreateUserCommand::class,
            DocCommand::class,
            DeleteRoleCommand::class,
            DeleteUserRoleCommand::class,
            DeleteUserCommand::class,
            DetachPrivilegeCommand::class,
            FlushRolesCommand::class,
            InstallCommand::class,
            ListAuditLogsCommand::class,
            ListPrivilegesCommand::class,
            ListRolesCommand::class,
            ListRolesWithPrivilegesCommand::class,
            ListUsersCommand::class,
            ListUsersWithRolesCommand::class,
            LoginCommand::class,
            LogoutAllCommand::class,
            LogoutAllUsersCommand::class,
            LogoutCommand::class,
            MeCommand::class,
            PrepareUserModelCommand::class,
            PurgeAuditLogsCommand::class,
            PurgePrivilegesCommand::class,
            PublishConfigCommand::class,
            PostmanCollectionCommand::class,
            PublishMigrationsCommand::class,
            QuickTokenCommand::class,
            RunTestsCommand::class,
            SuspendUserCommand::class,
            SuspendedUsersCommand::class,
            UnsuspendUserCommand::class,
            RoleUsersCommand::class,
            DeletePrivilegeCommand::class,
            SeedCommand::class,
            SeedPrivilegesCommand::class,
            SeedRolesCommand::class,
            StarCommand::class,
            UpdatePrivilegeCommand::class,
            UpdateRoleCommand::class,
            UpdateUserCommand::class,
            UserPrivilegesCommand::class,
            UserRolesCommand::class,
            VersionCommand::class,

        ]);
    }

    protected function registerBladeDirectives(): void {
        UserCanDirective::register();
        UserHasRoleDirective::register();
        UserHasAnyRoleDirective::register();
        UserHasRolesDirective::register();
        UserHasPrivilegeDirective::register();
        UserHasAnyPrivilegeDirective::register();
        UserHasPrivilegesDirective::register();
    }

    protected function registerObservers(): void {
        if (config('tyro.audit.enabled', true)) {
            Role::observe(\HasinHayder\Tyro\Models\Observers\RoleObserver::class);
            Privilege::observe(\HasinHayder\Tyro\Models\Observers\PrivilegeObserver::class);
        }
    }
}
