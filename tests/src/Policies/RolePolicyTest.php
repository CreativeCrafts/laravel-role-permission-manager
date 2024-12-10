<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\Contracts\AuthenticatableWithRolesAndPermissions;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use CreativeCrafts\LaravelRolePermissionManager\Policies\RolePolicy;

covers(RolePolicy::class);

beforeEach(function () {
    $this->policy = new RolePolicy();
    $this->user = Mockery::mock(AuthenticatableWithRolesAndPermissions::class);
    $this->role = Mockery::mock(Role::class);
});

afterEach(function () {
    Mockery::close();
});

describe('viewAny method', function () {
    it('allows viewing any roles when user has general permission', function () {
        $this->user->shouldReceive('hasPermissionTo')->with('view roles')->andReturn(true);
        expect($this->policy->viewAny($this->user))->toBeTrue();
    });

    it('denies viewing any roles when user lacks general permission', function () {
        $this->user->shouldReceive('hasPermissionTo')->with('view roles')->andReturn(false);
        expect($this->policy->viewAny($this->user))->toBeFalse();
    });

    it('allows viewing specific role when user has that role', function () {
        $this->user->shouldReceive('hasPermissionTo')->with('view roles')->andReturn(false);
        $this->user->shouldReceive('hasRole')->with('specific-role')->andReturn(true);
        expect($this->policy->viewAny($this->user, 'specific-role'))->toBeTrue();
    });
});

describe('view method', function () {
    it('allows viewing a role when user has general permission', function () {
        $this->user->shouldReceive('hasPermissionTo')->with('view roles')->andReturn(true);
        expect($this->policy->view($this->user, $this->role))->toBeTrue();
    });

    it('allows viewing a role when user has that specific role', function () {
        $this->user->shouldReceive('hasPermissionTo')->with('view roles')->andReturn(false);
        $this->role->shouldReceive('getAttribute')->with('name')->andReturn('specific-role');
        $this->user->shouldReceive('hasRole')->with('specific-role')->andReturn(true);
        expect($this->policy->view($this->user, $this->role))->toBeTrue();
    });

    it('denies viewing a role when user lacks both general permission and specific role', function () {
        $this->user->shouldReceive('hasPermissionTo')->with('view roles')->andReturn(false);
        $this->role->shouldReceive('getAttribute')->with('name')->andReturn('specific-role');
        $this->user->shouldReceive('hasRole')->with('specific-role')->andReturn(false);
        expect($this->policy->view($this->user, $this->role))->toBeFalse();
    });
});

describe('create method', function () {
    it('allows creating roles when user has general permission', function () {
        $this->user->shouldReceive('hasPermissionTo')->with('create roles')->andReturn(true);
        expect($this->policy->create($this->user))->toBeTrue();
    });

    it('denies creating roles when user lacks general permission', function () {
        $this->user->shouldReceive('hasPermissionTo')->with('create roles')->andReturn(false);
        expect($this->policy->create($this->user))->toBeFalse();
    });

    it('allows creating specific role when user has that role', function () {
        $this->user->shouldReceive('hasPermissionTo')->with('create roles')->andReturn(false);
        $this->user->shouldReceive('hasRole')->with('specific-role')->andReturn(true);
        expect($this->policy->create($this->user, 'specific-role'))->toBeTrue();
    });
});

describe('update method', function () {
    it('allows updating a role when user has general permission', function () {
        $this->user->shouldReceive('hasPermissionTo')->with('edit roles')->andReturn(true);
        expect($this->policy->update($this->user, $this->role))->toBeTrue();
    });

    it('allows updating a role when user has that specific role', function () {
        $this->user->shouldReceive('hasPermissionTo')->with('edit roles')->andReturn(false);
        $this->role->shouldReceive('getAttribute')->with('name')->andReturn('specific-role');
        $this->user->shouldReceive('hasRole')->with('specific-role')->andReturn(true);
        expect($this->policy->update($this->user, $this->role))->toBeTrue();
    });

    it('denies updating a role when user lacks both general permission and specific role', function () {
        $this->user->shouldReceive('hasPermissionTo')->with('edit roles')->andReturn(false);
        $this->role->shouldReceive('getAttribute')->with('name')->andReturn('specific-role');
        $this->user->shouldReceive('hasRole')->with('specific-role')->andReturn(false);
        expect($this->policy->update($this->user, $this->role))->toBeFalse();
    });
});

describe('delete method', function () {
    it('allows deleting a role when user has general permission', function () {
        $this->user->shouldReceive('hasPermissionTo')->with('delete roles')->andReturn(true);
        expect($this->policy->delete($this->user, $this->role))->toBeTrue();
    });

    it('allows deleting a role when user has that specific role', function () {
        $this->user->shouldReceive('hasPermissionTo')->with('delete roles')->andReturn(false);
        $this->role->shouldReceive('getAttribute')->with('name')->andReturn('specific-role');
        $this->user->shouldReceive('hasRole')->with('specific-role')->andReturn(true);
        expect($this->policy->delete($this->user, $this->role))->toBeTrue();
    });

    it('denies deleting a role when user lacks both general permission and specific role', function () {
        $this->user->shouldReceive('hasPermissionTo')->with('delete roles')->andReturn(false);
        $this->role->shouldReceive('getAttribute')->with('name')->andReturn('specific-role');
        $this->user->shouldReceive('hasRole')->with('specific-role')->andReturn(false);
        expect($this->policy->delete($this->user, $this->role))->toBeFalse();
    });
});
