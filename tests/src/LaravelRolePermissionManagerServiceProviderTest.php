<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManagerServiceProvider;
use CreativeCrafts\LaravelRolePermissionManager\Middleware\HandleInertiaRequests;
use CreativeCrafts\LaravelRolePermissionManager\Middleware\PermissionMiddleware;
use CreativeCrafts\LaravelRolePermissionManager\Middleware\RoleMiddleware;
use CreativeCrafts\LaravelRolePermissionManager\Tests\Models\TestUser;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

covers(LaravelRolePermissionManagerServiceProvider::class);


beforeEach(function () {
    Config::set('role-permission-manager.use_cache', false);
    Config::set('role-permission-manager.register_permission_check_method', false);
    Config::set('role-permission-manager.use_package_routes', false);
    Config::set('role-permission-manager.register_blade_directives', false);
    Config::set('role-permission-manager.route_prefix', 'role-permission');
    Config::set('role-permission-manager.route_middleware', []);
});

it('throws exception if user model does not exist', function () {
    LaravelRolePermissionManagerServiceProvider::registerUserModel('NonExistentClass');
})->throws(InvalidArgumentException::class, 'does not exist');

it('throws exception if user model is not a subclass of Eloquent Model', function () {
    LaravelRolePermissionManagerServiceProvider::registerUserModel(stdClass::class); // stdClass is not a Model
})->throws(InvalidArgumentException::class, 'must extend Eloquent Model');

/* it('throws exception if user model does not use HasRolesAndPermissions trait', function () {
    LaravelRolePermissionManagerServiceProvider::registerUserModel(TestUser::class);
})->throws(InvalidArgumentException::class, 'must use the HasRolesAndPermissions trait');*/

it('registers a valid user model into config', function () {
    LaravelRolePermissionManagerServiceProvider::registerUserModel(TestUser::class);
    expect(config('role-permission-manager.user_model'))->toBe(TestUser::class);
});

it('binds the laravel-role-permission-manager singleton and registers AuthServiceProvider', function () {
    $provider = new LaravelRolePermissionManagerServiceProvider(app());
    $provider->packageRegistered();

    $bound = app()->bound('laravel-role-permission-manager');
    expect($bound)->toBeTrue();
});

it('packageBooted calls registerMiddleware, etc.', function () {
    $providerMock = Mockery::mock(LaravelRolePermissionManagerServiceProvider::class)
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();

    $providerMock->shouldAllowMockingProtectedMethods();
    $providerMock->shouldReceive('registerMiddleware')->once();
    $providerMock->shouldReceive('registerGateCheck')->once();
    $providerMock->shouldReceive('registerRoutes')->once();
    $providerMock->shouldReceive('registerCache')->once();
    $providerMock->shouldReceive('registerBladeDirectives')->once();
    $providerMock->shouldReceive('registerPolicies')->once();

    $providerMock->packageBooted();
});

it('registers middleware', function () {
    $router = app('router');
    $routerSpy = Mockery::spy($router);
    app()->instance('router', $routerSpy);

    $provider = new LaravelRolePermissionManagerServiceProvider(app());

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('registerMiddleware');
    $method->setAccessible(true);
    $method->invoke($provider);

    $routerSpy->shouldHaveReceived('aliasMiddleware')->with('role', RoleMiddleware::class);
    $routerSpy->shouldHaveReceived('aliasMiddleware')->with('permission', PermissionMiddleware::class);
    $routerSpy->shouldHaveReceived('pushMiddlewareToGroup')->with('web', HandleInertiaRequests::class);
});

it('registers gate check if config is enabled', function () {
    Config::set('role-permission-manager.register_permission_check_method', true);
    Gate::spy();

    $provider = new LaravelRolePermissionManagerServiceProvider(app());

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('registerGateCheck');
    $method->setAccessible(true);
    $method->invoke($provider);

    Gate::shouldHaveReceived('before')->once();
});

it('does not register gate check if config is disabled', function () {
    Config::set('role-permission-manager.register_permission_check_method', false);

    Gate::spy();

    $provider = new LaravelRolePermissionManagerServiceProvider(app());
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('registerGateCheck');
    $method->setAccessible(true);
    $method->invoke($provider);

    Gate::shouldNotHaveReceived('before');
});

/* it('registers routes if config is enabled', function () {
    Config::set('role-permission-manager.use_package_routes', true);

    Route::spy();

    $provider = new LaravelRolePermissionManagerServiceProvider(app());
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('registerRoutes');
    $method->setAccessible(true);
    $method->invoke($provider);

    Route::shouldHaveReceived('middleware')
        ->with(Config::get('role-permission-manager.route_middleware'))
        ->once();
    Route::shouldHaveReceived('prefix')
        ->with(Config::get('role-permission-manager.route_prefix'))
        ->once();
    Route::shouldHaveReceived('group')
        ->once();
});*/

it('does not register routes if config is disabled', function () {
    Config::set('role-permission-manager.use_package_routes', false);
    Route::spy();

    $provider = new LaravelRolePermissionManagerServiceProvider(app());
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('registerRoutes');
    $method->setAccessible(true);
    $method->invoke($provider);

    Route::shouldNotHaveReceived('middleware');
    Route::shouldNotHaveReceived('prefix');
    Route::shouldNotHaveReceived('group');
});

it('registers cache if config is enabled', function () {
    Config::set('role-permission-manager.use_cache', true);
    Config::set('role-permission-manager.cache_store', 'array');

    $appMock = Mockery::mock(app());
    $appMock->shouldReceive('singleton')
        ->once()
        ->with('role-permission.cache', Mockery::type('callable'));

    $provider = new LaravelRolePermissionManagerServiceProvider($appMock);
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('registerCache');
    $method->setAccessible(true);
    $method->invoke($provider);
});

it('does not register cache if config is disabled', function () {
    Config::set('role-permission-manager.use_cache', false);
    $appMock = Mockery::mock(app());
    $appMock->shouldNotReceive('singleton')->with('role-permission.cache', Mockery::any());

    $provider = new LaravelRolePermissionManagerServiceProvider($appMock);
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('registerCache');
    $method->setAccessible(true);
    $method->invoke($provider);
});

it('registers blade directives if config is enabled', function () {
    Config::set('role-permission-manager.register_blade_directives', true);
    Blade::spy();

    $provider = new LaravelRolePermissionManagerServiceProvider(app());
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('registerBladeDirectives');
    $method->setAccessible(true);
    $method->invoke($provider);

    Blade::shouldHaveReceived('directive')->with('hasPermission', Mockery::on(function ($closure) {
        return is_callable($closure);
    }))->once();

    Blade::shouldHaveReceived('directive')->with('endhasPermission', Mockery::any())->once();
    Blade::shouldHaveReceived('directive')->with('hasRole', Mockery::any())->once();
    Blade::shouldHaveReceived('directive')->with('endhasRole', Mockery::any())->once();
});

it('does not register blade directives if config is disabled', function () {
    Config::set('role-permission-manager.register_blade_directives', false);
    Blade::spy();

    $provider = new LaravelRolePermissionManagerServiceProvider(app());
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('registerBladeDirectives');
    $method->setAccessible(true);
    $method->invoke($provider);

    Blade::shouldNotHaveReceived('directive');
});

/* it('publishes policies if runningInConsole is true', function () {
    $appMock = Mockery::mock(app());
    $appMock->shouldReceive('runningInConsole')->andReturn(true);

    $providerMock = Mockery::mock(LaravelRolePermissionManagerServiceProvider::class, [$appMock])
        ->makePartial();
    $providerMock->shouldAllowMockingProtectedMethods();
    $providerMock->shouldReceive('publishes')
        ->once()
        ->with(
            Mockery::on(function ($array) {
                return isset($array[__DIR__ . '/Policies']);
            }),
            'laravel-role-permission-manager-policies'
        );

    $reflection = new ReflectionClass($providerMock);
    $method = $reflection->getMethod('registerPolicies');
    $method->setAccessible(true);
    $method->invoke($providerMock);
});*/

it('does not publish policies if runningInConsole is false', function () {
    $appMock = Mockery::mock(app());
    $appMock->shouldReceive('runningInConsole')->andReturn(false);

    $providerMock = Mockery::mock(LaravelRolePermissionManagerServiceProvider::class, [$appMock])
        ->makePartial();
    $providerMock->shouldAllowMockingProtectedMethods();
    $providerMock->shouldNotReceive('publishes');

    $reflection = new ReflectionClass($providerMock);
    $method = $reflection->getMethod('registerPolicies');
    $method->setAccessible(true);
    $method->invoke($providerMock);
});
