<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\Helpers\ClassExistsWrapper;
use CreativeCrafts\LaravelRolePermissionManager\Http\Controllers\RolePermissionController;
use CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManager;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use CreativeCrafts\LaravelRolePermissionManager\Tests\Models\TestUser;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

covers(RolePermissionController::class);

beforeEach(function () {
    setUpTableSchema();
    $this->controller = new RolePermissionController();
    $this->manager = mock(LaravelRolePermissionManager::class);
    Config::set(
        'role-permission-manager.user_model',
        TestUser::class
    );
});

it('retrieves all roles', function () {
    Role::factory()->count(3)->create();

    $response = $this->controller->getRoles();

    expect($response->getStatusCode())->toBe(200)
        ->and(json_decode($response->getContent(), true))->toHaveCount(3);
});

it('retrieves permissions for a given scope', function () {
    $scope = 'test-scope';
    $permissions = collect([
        ['name' => 'Permission 1', 'slug' => 'permission-1'],
        ['name' => 'Permission 2', 'slug' => 'permission-2'],
    ]);

    $request = Request::create('/', 'GET', ['scope' => $scope]);
    $this->manager->shouldReceive('getAllPermissionsForScope')
        ->with($scope)
        ->once()
        ->andReturn($permissions);

    $response = $this->controller->getPermissions($request, $this->manager);

    expect($response->getStatusCode())->toBe(200)
        ->and(json_decode($response->getContent(), true))->toMatchArray($permissions->toArray());
});

it('retrieves roles for a specific user', function () {
    $user = createTestUser();
    $role = Role::factory()->create();
    $user->assignRole($role);

    $response = $this->controller->getUserRoles($user->id);

    expect($response->getStatusCode())->toBe(200)
        ->and(json_decode($response->getContent(), true))->toHaveCount(1)
        ->and(json_decode($response->getContent(), true)[0]['id'])->toBe($role->id);
});

it('retrieves all permissions for a specific user', function () {
    $user = createTestUser();
    $permissions = collect([
        ['name' => 'Permission 1', 'slug' => 'permission-1'],
        ['name' => 'Permission 2', 'slug' => 'permission-2'],
    ]);

    $this->manager->shouldReceive('getAllPermissionsForUser')
        ->with(
            Mockery::on(function ($arg) use ($user) {
                return $arg->id === $user->id;
            })
        )
        ->once()
        ->andReturn($permissions);

    $response = $this->controller->getUserPermissions($user->id, new Request(), $this->manager);
    $decodedResponse = json_decode($response->getContent(), true);
    $permissions = $permissions->toArray();

    expect($response->getStatusCode())->toBe(200)
        ->and($decodedResponse)->toMatchArray($permissions);
});

it('retrieves scoped permissions for a specific user', function () {
    $user = createTestUser();
    $scope = 'test-scope';
    $permission = Permission::factory()->create([
        'scope' => $scope
    ]);

    $user->givePermissionTo($permission, $scope);

    $response = $this->controller->getScopedPermissions($user->id, $scope);
    $decodedResponse = json_decode($response->getContent(), true)[0];

    expect($response->getStatusCode())->toBe(200)
        ->and($decodedResponse['id'])->toBe($permission->getAttribute('id'))
        ->and($decodedResponse['scope'])->toBe($scope)
        ->and($decodedResponse['name'])->toBe($permission->getAttribute('name'))
        ->and($decodedResponse['slug'])->toBe($permission->getAttribute('slug'))
        ->and($decodedResponse['description'])->toBe($permission->getAttribute('description'));
});

it('throws an exception when user is not found', function () {
    $this->controller->getUserRoles(999);
})->throws(
    ModelNotFoundException::class,
    'No query results for model [CreativeCrafts\LaravelRolePermissionManager\Tests\Models\TestUser] 999'
);

describe('User model retrieval', function () {
    it('uses the correct user model from config', function () {
        Config::set('role-permission-manager.user_model', null);
        Config::set('auth.providers.users.model', null);
        Config::set('role-permission-manager.user_model', 'App\Models\CustomUser');

        $mockWrapper = Mockery::mock(ClassExistsWrapper::class);
        $mockWrapper->shouldReceive('exists')
            ->with('App\Models\CustomUser')
            ->andReturn(true);

        $controller = new RolePermissionController($mockWrapper);
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('getUserModel');
        $method->setAccessible(true);

        $result = $method->invoke($controller);

        expect($result)->toBe('App\Models\CustomUser');
    });

    it('falls back to default user model if role-permission-manager config is not set', function () {
        Config::set('role-permission-manager.user_model', null);
        Config::set('auth.providers.users.model', null);
        Config::set('auth.providers.users.model', 'App\Models\User');

        $mockWrapper = Mockery::mock(ClassExistsWrapper::class);
        $mockWrapper->shouldReceive('exists')
            ->with('App\Models\User')
            ->andReturn(true);

        $controller = new RolePermissionController($mockWrapper);
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('getUserModel');
        $method->setAccessible(true);

        $result = $method->invoke($controller);

        expect($result)->toBe('App\Models\User');
    });

    it('throws an exception when configured user model does not exist', function () {
        // Set a non-existent model
        Config::set('role-permission-manager.user_model', 'App\Models\NonExistentUser');

        $mockWrapper = Mockery::mock(ClassExistsWrapper::class);
        $mockWrapper->shouldReceive('exists')
            ->andReturn(false);

        // Mock the class_exists function to return false
        $controller = new RolePermissionController($mockWrapper);
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('getUserModel');
        $method->setAccessible(true);

        expect(fn () => $method->invoke($controller))->toThrow(RuntimeException::class);
    });
});
