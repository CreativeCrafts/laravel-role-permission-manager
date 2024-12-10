<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManager;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use CreativeCrafts\LaravelRolePermissionManager\Tests\Models\TestUser;
use CreativeCrafts\LaravelRolePermissionManager\Traits\HasRolesAndPermissions;
use Illuminate\Support\Facades\Config;

covers(HasRolesAndPermissions::class);

beforeEach(function () {
    setUpTableSchema();
    $this->user = TestUser::factory()->create();
    $this->role = Role::factory()->create();
    $this->permission = Permission::factory()->create();
});

it('can assign a role to a user', function () {
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);

    $managerMock = Mockery::mock(LaravelRolePermissionManager::class);
    $managerMock->shouldReceive('clearUserRoleCache')->once()->with($this->user);
    app()->instance(LaravelRolePermissionManager::class, $managerMock);

    $this->user->assignRole($role);

    expect($this->user->roles)->toHaveCount(1)
        ->and($this->user->roles->first()->id)->toBe($role->id);
});

it('can remove a role from the model', function () {
    $this->user->assignRole($this->role);
    expect($this->user->roles)->toHaveCount(1);

    $this->user->removeRole($this->role);
    $this->user->refresh();
    expect($this->user->roles)->toHaveCount(0);
});

it('can give permission to the model', function () {
    $this->user->givePermissionTo($this->permission);
    expect($this->user->permissions)->toHaveCount(1)
        ->and($this->user->permissions->first()->id)->toBe($this->permission->id);
});

it('can revoke permission from the model', function () {
    $this->user->givePermissionTo($this->permission);
    expect($this->user->permissions)->toHaveCount(1);

    $this->user->revokePermissionTo($this->permission);
    $this->user->refresh();
    expect($this->user->permissions)->toHaveCount(0);
});

it('can get all permissions for the model', function () {
    $this->user->givePermissionTo($this->permission);
    $rolePermission = Permission::factory()->create();
    $this->role->givePermissionTo($rolePermission);
    $this->user->assignRole($this->role);

    $allPermissions = $this->user->getAllPermissions();
    expect($allPermissions)->toHaveCount(2)
        ->and($allPermissions->pluck('id')->toArray())->toContain($this->permission->id, $rolePermission->id);
});

it('can get user roles', function () {
    $this->user->assignRole($this->role);
    $roles = $this->user->getUserRoles($this->user);
    expect($roles)->toHaveCount(1)
        ->and($roles->first()->id)->toBe($this->role->id);
});

it('can get user role names', function () {
    $this->user->assignRole($this->role);
    $roleNames = $this->user->getUserRoleNames($this->user);
    expect($roleNames)->toHaveCount(1)
        ->and($roleNames->first())->toBe($this->role->name);
});

it('can get user role slugs', function () {
    $this->user->assignRole($this->role);
    $roleSlugs = $this->user->getUserRoleSlugs($this->user);
    expect($roleSlugs)->toHaveCount(1)
        ->and($roleSlugs->first())->toBe($this->role->slug);
});

it('can check if model has any of the given roles', function () {
    $this->user->assignRole($this->role);
    expect($this->user->hasAnyRole([$this->role->slug, 'non-existent-role']))->toBeTrue()
        ->and($this->user->hasAnyRole(['non-existent-role']))->toBeFalse();
});

it('can check if model has all of the given roles', function () {
    $this->user->assignRole($this->role);
    $anotherRole = Role::factory()->create();
    $this->user->assignRole($anotherRole);

    expect($this->user->hasAllRoles([$this->role->slug, $anotherRole->slug]))->toBeTrue()
        ->and($this->user->hasAllRoles([$this->role->slug, 'non-existent-role']))->toBeFalse();
});

it('returns true for all permissions if user is super admin', function () {
    Config::set('role-permission-manager.super_admin_role', 'Super Admin');
    $superAdminRole = Role::factory()->create(['name' => 'Super Admin', 'slug' => 'super-admin']);
    $this->user->assignRole($superAdminRole);

    expect($this->user->hasPermissionTo('any-permission'))->toBeTrue();
});

it('can check if model is super admin', function () {
    Config::set('role-permission-manager.super_admin_role', 'Super Admin');
    $superAdminRole = Role::factory()->create(['name' => 'Super Admin', 'slug' => 'super-admin']);
    $this->user->assignRole($superAdminRole);

    expect($this->user->hasSuperAdminRole())->toBeTrue();

    $regularUser = TestUser::factory()->create();
    expect($regularUser->hasSuperAdminRole())->toBeFalse();
});

it('can check if model has a specific role', function () {
    $this->user->assignRole($this->role);
    expect($this->user->hasRole($this->role))->toBeTrue()
        ->and($this->user->hasRole('non-existent-role'))->toBeFalse();
});

it('throws an exception when trait is used on a non-Eloquent model', function () {
    $nonEloquentClass = new class () {
        use HasRolesAndPermissions;
    };

    expect(function () use ($nonEloquentClass) {
        $nonEloquentClass->assignRole('some-role');
    })->toThrow(LogicException::class, 'The HasRolesAndPermissions trait must be used on an Eloquent model.');
});
