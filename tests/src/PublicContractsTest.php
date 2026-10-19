<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\Contracts\AuthenticatableWithRolesAndPermissions;
use CreativeCrafts\LaravelRolePermissionManager\Contracts\LaravelRolePermissionManagerContract;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use CreativeCrafts\LaravelRolePermissionManager\Tests\Models\TestUser;

it('keeps interface and implementation authorization signatures compatible', function () {
    $interface = new ReflectionMethod(AuthenticatableWithRolesAndPermissions::class, 'hasPermissionTo');
    $implementation = new ReflectionMethod(TestUser::class, 'hasPermissionTo');
    $managerContract = new ReflectionMethod(LaravelRolePermissionManagerContract::class, 'hasPermissionTo');

    expect((string) $interface->getParameters()[0]->getType())->toBe('CreativeCrafts\LaravelRolePermissionManager\Models\Permission|string')
        ->and((string) $implementation->getParameters()[0]->getType())->toBe(
            'CreativeCrafts\LaravelRolePermissionManager\Models\Permission|string'
        )
        ->and((string) $managerContract->getParameters()[1]->getType())->toBe(
            'CreativeCrafts\LaravelRolePermissionManager\Models\Permission|string'
        );
});

it('supports exact Permission models through the public user contract', function () {
    /** @var AuthenticatableWithRolesAndPermissions $user */
    $user = createTestUser();
    $permission = Permission::factory()->create([
        'name' => 'contracts.read',
        'slug' => 'contracts.read',
        'scope' => '0',
    ]);
    $user->givePermissionTo($permission);

    expect($user->hasPermissionTo($permission))->toBeTrue();
});

it('uses self when role getter arguments are omitted or null', function () {
    $user = createTestUser();
    $role = Role::factory()->create(['name' => 'Editor', 'slug' => 'editor']);
    $user->assignRole($role);

    expect($user->getUserRoles()->modelKeys())->toBe([$role->id])
        ->and($user->getUserRoleNames()->all())->toBe(['Editor'])
        ->and($user->getUserRoleSlugs(null)->all())->toBe(['editor']);
});

it('advertises optional getter arguments in reflection', function () {
    foreach (['getUserRoles', 'getUserRoleNames', 'getUserRoleSlugs'] as $methodName) {
        $method = new ReflectionMethod(TestUser::class, $methodName);
        expect($method->getNumberOfRequiredParameters())->toBe(0)
            ->and($method->getParameters()[0]->isDefaultValueAvailable())->toBeTrue()
            ->and($method->getParameters()[0]->getDefaultValue())->toBeNull();
    }
});
