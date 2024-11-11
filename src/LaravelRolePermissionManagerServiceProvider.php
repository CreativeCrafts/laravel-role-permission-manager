<?php

namespace CreativeCrafts\LaravelRolePermissionManager;

use CreativeCrafts\LaravelRolePermissionManager\Commands\AssignPermissionCommand;
use CreativeCrafts\LaravelRolePermissionManager\Commands\CreatePermissionCommand;
use CreativeCrafts\LaravelRolePermissionManager\Commands\CreateRoleCommand;
use CreativeCrafts\LaravelRolePermissionManager\Commands\ListRolesPermissionsCommand;
use CreativeCrafts\LaravelRolePermissionManager\Commands\RemovePermissionCommand;
use CreativeCrafts\LaravelRolePermissionManager\Commands\SyncPermissionsCommand;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelRolePermissionManagerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-role-permission-manager')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations(['create_role_permission_manager_tables'])
            ->hasCommands([
                CreateRoleCommand::class,
                CreatePermissionCommand::class,
                AssignPermissionCommand::class,
                RemovePermissionCommand::class,
                ListRolesPermissionsCommand::class,
                RemovePermissionCommand::class,
                SyncPermissionsCommand::class,
            ])
            ->publishesServiceProvider('AuthServiceProvider');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton('laravel-role-permission-manager', LaravelRolePermissionManager::class);
        $this->app->register(Providers\AuthServiceProvider::class);
    }

    public function packageBooted(): void
    {
        $this->registerMiddleware();
        $this->validateUserModel();
        $this->registerGateCheck();
        $this->registerRoutes();
        $this->registerCache();
        $this->registerBladeDirectives();
        $this->registerPolicies();
    }

    protected function registerMiddleware(): void
    {
        $this->app['router']->aliasMiddleware('role', Middleware\RoleMiddleware::class);
        $this->app['router']->aliasMiddleware('permission', Middleware\PermissionMiddleware::class);
        $this->app['router']->pushMiddlewareToGroup('web', Middleware\HandleInertiaRequests::class);
    }

    protected function registerGateCheck(): void
    {
        if (config('role-permission-manager.register_permission_check_method')) {
            Gate::before(static function ($user, $ability) {
                return method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($ability);
            });
        }
    }

    protected function registerRoutes(): void
    {
        if (config('role-permission-manager.use_package_routes')) {
            Route::middleware(config('role-permission-manager.route_middleware'))
                ->prefix(config('role-permission-manager.route_prefix'))
                ->group(function () {
                    Route::get('/roles', [Http\Controllers\RolePermissionController::class, 'getRoles']);
                    Route::get('/permissions', [Http\Controllers\RolePermissionController::class, 'getPermissions']);
                    Route::get(
                        '/user/{userId}/permissions',
                        [Http\Controllers\RolePermissionController::class, 'getUserPermissions']
                    );
                    Route::get(
                        '/user/{userId}/permissions/{scope}',
                        [Http\Controllers\RolePermissionController::class, 'getScopedPermissions']
                    );
                });
        }
    }

    protected function registerCache(): void
    {
        if (config('role-permission-manager.use_cache')) {
            $this->app->singleton('role-permission.cache', function (Application $app) {
                return new \Illuminate\Cache\Repository(
                    $app['cache']->store(config('role-permission-manager.cache_store'))
                );
            });
        }
    }

    protected function validateUserModel(): void
    {
        $userModel = config('auth.providers.users.model');

        if (! class_exists($userModel)) {
            throw new \InvalidArgumentException("User model {$userModel} does not exist.");
        }

        if (! is_subclass_of($userModel, Model::class)) {
            throw new \InvalidArgumentException('The user model must extend Illuminate\Database\Eloquent\Model');
        }

        if (! in_array(Traits\HasRolesAndPermissions::class, class_uses_recursive($userModel), true)) {
            throw new \InvalidArgumentException('The user model must use the HasRolesAndPermissions trait');
        }
    }

    protected function registerBladeDirectives(): void
    {
        if (config('role-permission-manager.register_blade_directives')) {
            Blade::directive('hasPermission', static function ($expression) {
                return "<?php if (auth()->check() && auth()->user()->hasPermissionTo({$expression})): ?>";
            });

            Blade::directive('endhasPermission', static function () {
                return '<?php endif; ?>';
            });

            Blade::directive('hasRole', static function ($expression) {
                return "<?php if (auth()->check() && auth()->user()->hasRole({$expression})): ?>";
            });

            Blade::directive('endhasRole', static function () {
                return '<?php endif; ?>';
            });
        }
    }

    protected function registerPolicies(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/Policies' => app_path('Policies'),
            ], 'laravel-role-permission-manager-policies');
        }
    }
}
