<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use CreativeCrafts\LaravelRolePermissionManager\Tests\Models\TestUser;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

covers(Role::class);

beforeEach(function () {
    $this->role = Role::factory()->create();
    Config::set('role-permission-manager.user_model', TestUser::class);
});

it('uses the correct table name from config', function () {
    expect($this->role->getTable())->toBe(config('role-permission-manager.roles_table'));
});

describe('relationships', function () {
    it('has a users relationship', function () {
        expect($this->role->users())->toBeInstanceOf(BelongsToMany::class);
    });

    it('has a parent relationship', function () {
        expect($this->role->parent())->toBeInstanceOf(BelongsTo::class);
    });

    it('has a children relationship', function () {
        expect($this->role->children())->toBeInstanceOf(HasMany::class);
    });

    it('has a permissions relationship', function () {
        expect($this->role->permissions())->toBeInstanceOf(BelongsToMany::class);
    });
});

describe('getAllChildren method', function () {
    it('returns all children roles recursively', function () {
        $childRole = Role::factory()->create(['parent_id' => $this->role->id]);
        $grandchildRole = Role::factory()->create(['parent_id' => $childRole->id]);

        $allChildren = $this->role->getAllChildren();

        expect($allChildren)->toBeInstanceOf(Collection::class)
            ->and($allChildren)->toHaveCount(2)
            ->and($allChildren->contains(fn ($item) => $item->is($childRole)))->toBeTrue()
            ->and($allChildren->contains(fn ($item) => $item->is($grandchildRole)))->toBeTrue();
    });

    it('returns an empty collection for roles without children', function () {
        $allChildren = $this->role->getAllChildren();

        expect($allChildren)->toBeInstanceOf(Collection::class)
            ->and($allChildren)->toBeEmpty();
    });

    it('handles multiple levels of nesting', function () {
        $childRole1 = Role::factory()->create(['parent_id' => $this->role->id]);
        $childRole2 = Role::factory()->create(['parent_id' => $this->role->id]);
        $grandchildRole1 = Role::factory()->create(['parent_id' => $childRole1->id]);
        $grandchildRole2 = Role::factory()->create(['parent_id' => $childRole2->id]);
        $greatGrandchildRole = Role::factory()->create(['parent_id' => $grandchildRole1->id]);

        $allChildren = $this->role->getAllChildren();

        expect($allChildren)->toBeInstanceOf(Collection::class)
            ->and($allChildren)->toHaveCount(5)
            ->and($allChildren->contains(fn ($item) => $item->is($childRole1)))->toBeTrue()
            ->and($allChildren->contains(fn ($item) => $item->is($childRole2)))->toBeTrue()
            ->and($allChildren->contains(fn ($item) => $item->is($grandchildRole1)))->toBeTrue()
            ->and($allChildren->contains(fn ($item) => $item->is($grandchildRole2)))->toBeTrue()
            ->and($allChildren->contains(fn ($item) => $item->is($greatGrandchildRole)))->toBeTrue();
    });
});

describe('hasPermissionTo method', function () {
    it('returns true for super admin role', function () {
        $superAdminRole = Role::factory()->create(['name' => config('role-permission-manager.super_admin_role')]);
        expect($superAdminRole->hasPermissionTo('any-permission'))->toBeTrue();
    });

    it('returns true if role has the permission', function () {
        $permission = Permission::factory()->create();
        $this->role->givePermissionTo($permission);

        expect($this->role->hasPermissionTo($permission->name))->toBeTrue();
    });

    it('returns false if role does not have the permission', function () {
        expect($this->role->hasPermissionTo('non-existent-permission'))->toBeFalse();
    });
});

describe('isSuperAdmin method', function () {
    it('returns true for super admin role', function () {
        $superAdminRole = Role::factory()->create(['name' => config('role-permission-manager.super_admin_role')]);
        expect($superAdminRole->isSuperAdmin())->toBeTrue();
    });

    it('returns false for non-super admin role', function () {
        expect($this->role->isSuperAdmin())->toBeFalse();
    });
});

describe('getAllPermissions method', function () {
    it('returns all permissions including inherited ones', function () {
        $parentPermission = Permission::factory()->create();
        $childPermission = Permission::factory()->create();

        $parentRole = Role::factory()->create();
        $parentRole->givePermissionTo($parentPermission);

        $this->role->parent()->associate($parentRole);
        $this->role->save();
        $this->role->givePermissionTo($childPermission);

        $allPermissions = $this->role->getAllPermissions();

        expect($allPermissions)->toBeInstanceOf(Collection::class)
            ->and($allPermissions)->toHaveCount(2)
            ->and($allPermissions->contains(fn ($item) => $item->is($parentPermission)))->toBeTrue()
            ->and($allPermissions->contains(fn ($item) => $item->is($childPermission)))->toBeTrue();
    });

    it('returns only direct permissions if no parent role', function () {
        $directPermission = Permission::factory()->create();
        $this->role->givePermissionTo($directPermission);

        $allPermissions = $this->role->getAllPermissions();

        expect($allPermissions)->toBeInstanceOf(Collection::class)
            ->and($allPermissions)->toHaveCount(1)
            ->and($allPermissions->contains(fn ($item) => $item->is($directPermission)))->toBeTrue();
    });
});

describe('slug generation', function () {
    it('generates slug from name if not provided', function () {
        $role = Role::factory()->create(['name' => 'Test Role', 'slug' => null]);
        expect($role->slug)->toBe('test-role');
    });

    it('uses provided slug if available', function () {
        $role = Role::factory()->create(['name' => 'Test Role', 'slug' => 'custom-slug']);
        expect($role->slug)->toBe('custom-slug');
    });
});

describe('givePermissionTo method', function () {
    it('attaches a permission to the role', function () {
        $permission = Permission::factory()->create();
        $this->role->givePermissionTo($permission);

        $this->role->refresh();
        expect($this->role->permissions->contains(fn ($p) => $p->id === $permission->id))->toBeTrue();
    });

    it('does not duplicate permissions', function () {
        $permission = Permission::factory()->create();
        $this->role->givePermissionTo($permission);
        $this->role->givePermissionTo($permission);

        $this->role->refresh();
        expect($this->role->permissions)->toHaveCount(1);
    });

    it('accepts permission name as string and finds by name', function () {
        $permission = Permission::factory()->create();
        $this->role->givePermissionTo($permission->name);

        $this->role->refresh();
        expect($this->role->permissions->contains(fn ($p) => $p->name === $permission->name))->toBeTrue();
    });

    it('accepts permission slug as string and finds by slug', function () {
        $permission = Permission::factory()->create();
        $this->role->givePermissionTo($permission->slug);

        $this->role->refresh();
        expect($this->role->permissions->contains(fn ($p) => $p->slug === $permission->slug))->toBeTrue();
    });

    it('creates new permission when auto_create_permissions is true', function () {
        Config::set('role-permission-manager.auto_create_permissions', true);
        $newPermissionName = 'new_permission';

        $this->role->givePermissionTo($newPermissionName);

        $this->role->refresh();
        expect(Permission::where('name', $newPermissionName)->exists())->toBeTrue()
            ->and($this->role->permissions->contains(fn ($p) => $p->name === $newPermissionName))->toBeTrue();
    });

    it('throws exception when permission not found and auto_create_permissions is false', function () {
        Config::set('role-permission-manager.auto_create_permissions', false);
        $nonExistentPermission = 'non_existent_permission';

        $this->role->givePermissionTo($nonExistentPermission);
    })->throws(ModelNotFoundException::class);
});
