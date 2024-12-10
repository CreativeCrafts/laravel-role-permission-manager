<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager;

use CreativeCrafts\LaravelRolePermissionManager\Commands\AssignPermissionCommand;
use CreativeCrafts\LaravelRolePermissionManager\Commands\AssignRoleCommand;
use CreativeCrafts\LaravelRolePermissionManager\Commands\CreatePermissionCommand;
use CreativeCrafts\LaravelRolePermissionManager\Commands\CreateRoleCommand;
use CreativeCrafts\LaravelRolePermissionManager\Commands\ListRolesPermissionsCommand;
use CreativeCrafts\LaravelRolePermissionManager\Commands\RemovePermissionCommand;
use CreativeCrafts\LaravelRolePermissionManager\Commands\SyncPermissionsCommand;
use CreativeCrafts\LaravelRolePermissionManager\Http\Controllers\RolePermissionController;
use CreativeCrafts\LaravelRolePermissionManager\Middleware\HandleInertiaRequests;
use CreativeCrafts\LaravelRolePermissionManager\Middleware\PermissionMiddleware;
use CreativeCrafts\LaravelRolePermissionManager\Middleware\RoleMiddleware;
use CreativeCrafts\LaravelRolePermissionManager\Providers\AuthServiceProvider;
use CreativeCrafts\LaravelRolePermissionManager\Traits\HasRolesAndPermissions;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelRolePermissionManagerServiceProvider extends PackageServiceProvider
{
    /**
     * Register the user model for the role-permission manager.
     * This method should be called by the application after installing the package.
     *
     * @param string $userModel The fully qualified class name of the user model
     * @throws InvalidArgumentException If the user model is invalid
     */
    public static function registerUserModel(string $userModel): void
    {
        if (! class_exists($userModel)) {
            throw new InvalidArgumentException("User model {$userModel} does not exist.");
        }

        if (! is_subclass_of($userModel, Model::class)) {
            throw new InvalidArgumentException("User model {$userModel} must extend Eloquent Model.");
        }

        if (! in_array(HasRolesAndPermissions::class, class_uses_recursive($userModel), true)) {
            throw new InvalidArgumentException('The user model must use the HasRolesAndPermissions trait.');
        }

        config([
            'role-permission-manager.user_model' => $userModel,
        ]);
    }

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
                AssignRoleCommand::class,
            ])
            ->hasAssets()
            ->publishesServiceProvider('AuthServiceProvider');

        $this->publishes([
            __DIR__ . '/../resources/js' => resource_path('js/vendor/laravel-role-permission-manager'),
        ], 'laravel-role-permission-manager-typescript');
    }

    /**
     * Register package-specific bindings and services.
     * This method is called when the package is registered with the application.
     * It binds the 'laravel-role-permission-manager' to the container as a singleton
     * and registers the AuthServiceProvider.
     */
    public function packageRegistered(): void
    {
        $this->app->singleton('laravel-role-permission-manager', LaravelRolePermissionManager::class);
        $this->app->register(AuthServiceProvider::class);
    }

    /**
     * Perform package boot operations.
     * This method is called after the package has been booted. It sets up various
     * components of the package including middleware, user model validation,
     * gate checks, routes, cache, blade directives, and policies.
     */
    public function packageBooted(): void
    {
        $this->registerMiddleware();
        $this->registerGateCheck();
        $this->registerRoutes();
        $this->registerCache();
        $this->registerBladeDirectives();
        $this->registerPolicies();
    }

    /**
     * Register middleware for role and permission management.
     * This method registers the following middleware:
     * - 'role' middleware for handling role-based access control
     * - 'permission' middleware for handling permission-based access control
     * - Adds HandleInertiaRequests middleware to the 'web' middleware group
     */
    protected function registerMiddleware(): void
    {
        $this->app['router']->aliasMiddleware('role', RoleMiddleware::class);
        $this->app['router']->aliasMiddleware('permission', PermissionMiddleware::class);
        $this->app['router']->pushMiddlewareToGroup('web', HandleInertiaRequests::class);
    }

    /**
     * Register a gate check for permission-based authorization.
     * This method sets up a global gate check that runs before any other authorization checks.
     * It checks if the user has the required permission for a given ability.
     * The gate check is only registered if the configuration option is set to true.
     */
    protected function registerGateCheck(): void
    {
        if (config('role-permission-manager.register_permission_check_method')) {
            Gate::before(static function ($user, $ability): bool {
                return method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($ability);
            });
        }
    }

    /**
     * Register routes for the role-permission manager package.
     * This method sets up the routes for managing roles and permissions if the
     * 'use_package_routes' configuration option is enabled. It applies middleware
     * and a route prefix as specified in the package configuration.
     * The following routes are registered:
     * - GET /roles: Retrieves all roles
     * - GET /permissions: Retrieves all permissions
     * - GET /user/{userId}/permissions: Retrieves permissions for a specific user
     * - GET /user/{userId}/permissions/{scope}: Retrieves scoped permissions for a specific user
     */
    protected function registerRoutes(): void
    {
        if (config('role-permission-manager.use_package_routes')) {
            Route::middleware(config('role-permission-manager.route_middleware'))
                ->prefix(config('role-permission-manager.route_prefix'))
                ->group(function (): void {
                    Route::get('/roles', [RolePermissionController::class, 'getRoles']);
                    Route::get('/permissions', [RolePermissionController::class, 'getPermissions']);
                    Route::get(
                        '/user/{userId}/permissions',
                        [RolePermissionController::class, 'getUserPermissions']
                    );
                    Route::get(
                        '/user/{userId}/permissions/{scope}',
                        [RolePermissionController::class, 'getScopedPermissions']
                    );
                });
        }
    }

    /**
     * Register the cache repository for role-permission management.
     * This method sets up a singleton instance of the cache repository
     * if caching is enabled in the package configuration. The cache
     * repository is used for storing and retrieving role and permission data.
     * The cache store used is determined by the 'cache_store' setting
     * in the role-permission-manager configuration.
     */
    protected function registerCache(): void
    {
        if (config('role-permission-manager.use_cache')) {
            $this->app->singleton('role-permission.cache', function (Application $app): Repository {
                return new Repository(
                    $app['cache']->store(config('role-permission-manager.cache_store'))
                );
            });
        }
    }

    /**
     * Validates the user model configuration for the role-permission manager.
     * This function checks if the configured user model exists, extends the Eloquent Model,
     * and uses the HasRolesAndPermissions trait. It throws exceptions if any of these
     * conditions are not met.
     *
     * @throws InvalidArgumentException If the user model is not defined, does not exist,
     *                                  does not extend Eloquent Model, or does not use
     *                                  the HasRolesAndPermissions trait.
     */
    protected function validateUserModel(): void
    {
        $userModel = config('role-permission-manager.user_model') ?? '\App\Models\User';

        if (! class_exists($userModel)) {
            throw new InvalidArgumentException("User model {$userModel} is not defined or does not exist.");
        }

        if (! is_subclass_of($userModel, Model::class)) {
            throw new InvalidArgumentException('The user model must extend Illuminate\Database\Eloquent\Model');
        }

        if (! in_array(HasRolesAndPermissions::class, class_uses_recursive($userModel), true)) {
            throw new InvalidArgumentException('The user model must use the HasRolesAndPermissions trait');
        }
    }

    /**
     * Register custom Blade directives for role and permission checks.
     * This method sets up Blade directives to easily check for user permissions
     * and roles within Blade templates. It registers the following directives:
     * - @hasPermission and @endhasPermission: For checking user permissions
     * - @hasRole and @endhasRole: For checking user roles
     * These directives are only registered if the 'register_blade_directives'
     * configuration option is set to true.
     */
    protected function registerBladeDirectives(): void
    {
        if (config('role-permission-manager.register_blade_directives')) {
            Blade::directive('hasPermission', static function ($expression): string {
                return "<?php if (auth()->check() && auth()->user()->hasPermissionTo({$expression})): ?>";
            });

            Blade::directive('endhasPermission', static function (): string {
                return '<?php endif; ?>';
            });

            Blade::directive('hasRole', static function ($expression): string {
                return "<?php if (auth()->check() && auth()->user()->hasRole({$expression})): ?>";
            });

            Blade::directive('endhasRole', static function (): string {
                return '<?php endif; ?>';
            });
        }
    }

    /**
     * Register and publish policies for the role-permission manager.
     * This method publishes the policies from the package to the application's
     * Policies directory when running in console mode. This allows developers
     * to customize the policies as needed for their application.
     */
    protected function registerPolicies(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/Policies' => app_path('Policies'),
            ], 'laravel-role-permission-manager-policies');
        }
    }
}
