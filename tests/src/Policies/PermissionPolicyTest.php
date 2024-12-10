<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\Contracts\AuthenticatableWithRolesAndPermissions;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Policies\PermissionPolicy;

covers(PermissionPolicy::class);

beforeEach(function () {
    $this->policy = new PermissionPolicy();
    $this->user = Mockery::mock(AuthenticatableWithRolesAndPermissions::class);
    $this->permission = Mockery::mock(Permission::class);
});

afterEach(function () {
    Mockery::close();
});

describe('viewAny method', function () {
    it('allows viewing any permissions when user has general permission', function () {
        $this->user->shouldReceive('hasPermissionTo')->with('view permissions')->andReturn(true);
        expect($this->policy->viewAny($this->user))->toBeTrue();
    });

    it('denies viewing any permissions when user lacks general permission', function () {
        $this->user->shouldReceive('hasPermissionTo')->with('view permissions')->andReturn(false);
        expect($this->policy->viewAny($this->user))->toBeFalse();
    });

    it('allows viewing specific permission when user has that permission', function () {
        $this->user->shouldReceive('hasPermissionTo')->with('view permissions')->andReturn(false);
        $this->user->shouldReceive('hasPermissionTo')->with('specific-permission')->andReturn(true);
        expect($this->policy->viewAny($this->user, 'specific-permission'))->toBeTrue();
    });
});

describe('view method', function () {
    it('allows viewing specific permission when user has general view permission', function () {
        $this->permission->shouldReceive('getAttribute')->with('name')->andReturn('test-permission');
        $this->user->shouldReceive('hasPermissionTo')->with('view permissions')->andReturn(true);
        expect($this->policy->view($this->user, $this->permission))->toBeTrue();
    });

    it('allows viewing specific permission when user has that specific permission', function () {
        $this->permission->shouldReceive('getAttribute')->with('name')->andReturn('test-permission');
        $this->user->shouldReceive('hasPermissionTo')->with('view permissions')->andReturn(false);
        $this->user->shouldReceive('hasPermissionTo')->with('test-permission')->andReturn(true);
        expect($this->policy->view($this->user, $this->permission))->toBeTrue();
    });

    it('denies viewing specific permission when user lacks both general and specific permissions', function () {
        $this->permission->shouldReceive('getAttribute')->with('name')->andReturn('test-permission');
        $this->user->shouldReceive('hasPermissionTo')->with('view permissions')->andReturn(false);
        $this->user->shouldReceive('hasPermissionTo')->with('test-permission')->andReturn(false);
        expect($this->policy->view($this->user, $this->permission))->toBeFalse();
    });
});

describe('create method', function () {
    it('allows creating permissions when user has general create permission', function () {
        $this->user->shouldReceive('hasPermissionTo')->with('create permissions')->andReturn(true);
        expect($this->policy->create($this->user))->toBeTrue();
    });

    it('denies creating permissions when user lacks general create permission', function () {
        $this->user->shouldReceive('hasPermissionTo')->with('create permissions')->andReturn(false);
        expect($this->policy->create($this->user))->toBeFalse();
    });

    it('allows creating specific permission when user has that permission', function () {
        $this->user->shouldReceive('hasPermissionTo')->with('create permissions')->andReturn(false);
        $this->user->shouldReceive('hasPermissionTo')->with('specific-permission')->andReturn(true);
        expect($this->policy->create($this->user, 'specific-permission'))->toBeTrue();
    });

    it('denies creating specific permission when user lacks both general and specific permissions', function () {
        $this->user->shouldReceive('hasPermissionTo')->with('create permissions')->andReturn(false);
        $this->user->shouldReceive('hasPermissionTo')->with('specific-permission')->andReturn(false);
        expect($this->policy->create($this->user, 'specific-permission'))->toBeFalse();
    });
});

describe('update method', function () {
    it('allows updating permission when user has general edit permission', function () {
        $this->permission->shouldReceive('getAttribute')->with('name')->andReturn('test-permission');
        $this->user->shouldReceive('hasPermissionTo')->with('edit permissions')->andReturn(true);
        expect($this->policy->update($this->user, $this->permission))->toBeTrue();
    });

    it('allows updating specific permission when user has that specific permission', function () {
        $this->permission->shouldReceive('getAttribute')->with('name')->andReturn('test-permission');
        $this->user->shouldReceive('hasPermissionTo')->with('edit permissions')->andReturn(false);
        $this->user->shouldReceive('hasPermissionTo')->with('test-permission')->andReturn(true);
        expect($this->policy->update($this->user, $this->permission))->toBeTrue();
    });

    it('denies updating specific permission when user lacks both general and specific permissions', function () {
        $this->permission->shouldReceive('getAttribute')->with('name')->andReturn('test-permission');
        $this->user->shouldReceive('hasPermissionTo')->with('edit permissions')->andReturn(false);
        $this->user->shouldReceive('hasPermissionTo')->with('test-permission')->andReturn(false);
        expect($this->policy->update($this->user, $this->permission))->toBeFalse();
    });
});

describe('delete method', function () {
    it('allows deleting permission when user has general delete permission', function () {
        $this->permission->shouldReceive('getAttribute')->with('name')->andReturn('test-permission');
        $this->user->shouldReceive('hasPermissionTo')->with('delete permissions')->andReturn(true);
        expect($this->policy->delete($this->user, $this->permission))->toBeTrue();
    });

    it('allows deleting specific permission when user has that specific permission', function () {
        $this->permission->shouldReceive('getAttribute')->with('name')->andReturn('test-permission');
        $this->user->shouldReceive('hasPermissionTo')->with('delete permissions')->andReturn(false);
        $this->user->shouldReceive('hasPermissionTo')->with('test-permission')->andReturn(true);
        expect($this->policy->delete($this->user, $this->permission))->toBeTrue();
    });

    it('denies deleting specific permission when user lacks both general and specific permissions', function () {
        $this->permission->shouldReceive('getAttribute')->with('name')->andReturn('test-permission');
        $this->user->shouldReceive('hasPermissionTo')->with('delete permissions')->andReturn(false);
        $this->user->shouldReceive('hasPermissionTo')->with('test-permission')->andReturn(false);
        expect($this->policy->delete($this->user, $this->permission))->toBeFalse();
    });
});
