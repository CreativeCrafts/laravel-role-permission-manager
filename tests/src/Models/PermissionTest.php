<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use CreativeCrafts\LaravelRolePermissionManager\Tests\Models\TestUser;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Config;

covers(Permission::class);

beforeEach(function () {
    $this->permission = new Permission();
    Config::set('role-permission-manager.user_model', TestUser::class);
});

it('uses the correct table name from config', function () {
    Config::set('role-permission-manager.permissions_table', 'custom_permissions');
    $permission = new Permission();
    expect($permission->getTable())->toBe('custom_permissions');
});

describe('relationships', function () {
    it('has a many-to-many relationship with roles', function () {
        expect($this->permission->roles())->toBeInstanceOf(BelongsToMany::class);
        expect($this->permission->roles()->getRelated())->toBeInstanceOf(Role::class);
    });

    it('has a many-to-many relationship with users', function () {
        expect($this->permission->users())->toBeInstanceOf(BelongsToMany::class);
        expect($this->permission->users()->getRelated())->toBeInstanceOf(TestUser::class);
    });
});

describe('wildcardMatch method', function () {
    beforeEach(function () {
        Config::set('role-permission-manager.enable_wildcard_permission', true);
        Config::set('role-permission-manager.case_sensitive_permissions', false);
    });

    it('matches exact permission name', function () {
        $permission = Permission::factory()->create(['name' => 'edit-posts']);
        expect($permission->wildcardMatch('edit-posts'))->toBeTrue();
    });

    it('matches exact permission slug', function () {
        $permission = Permission::factory()->create(['name' => 'Edit Posts', 'slug' => 'edit-posts']);
        expect($permission->wildcardMatch('edit-posts'))->toBeTrue();
    });

    it('matches wildcard permission', function () {
        $permission = Permission::factory()->create(['name' => 'edit-*']);
        expect($permission->wildcardMatch('edit-posts'))->toBeTrue();
        expect($permission->wildcardMatch('edit-users'))->toBeTrue();
    });

    it('respects scope when matching', function () {
        $permission = Permission::factory()->create(['name' => 'edit-posts', 'scope' => 'admin']);
        expect($permission->wildcardMatch('edit-posts', 'admin'))->toBeTrue();
        expect($permission->wildcardMatch('edit-posts', 'user'))->toBeFalse();
    });

    it('is case-insensitive by default', function () {
        $permission = Permission::factory()->create(['name' => 'Edit-Posts']);
        expect($permission->wildcardMatch('edit-posts'))->toBeTrue();
    });

    it('is case-sensitive when configured', function () {
        Config::set('role-permission-manager.case_sensitive_permissions', true);
        $permission = Permission::factory()->create(['name' => 'Edit-Posts']);
        expect($permission->wildcardMatch('edit-posts'))->toBeFalse();
        expect($permission->wildcardMatch('Edit-Posts'))->toBeTrue();
    });

    it('returns false when wildcard is disabled and permissions do not match exactly', function () {
        Config::set('role-permission-manager.enable_wildcard_permission', false);
        $permission = Permission::factory()->create(['name' => 'edit-*']);
        expect($permission->wildcardMatch('edit-posts'))->toBeFalse();
    });
});

describe('attribute accessors', function () {
    it('generates slug from name if not provided', function () {
        $permission = new Permission(['name' => 'Edit Posts']);
        expect($permission->getAttributes())->toHaveKey('slug', 'edit-posts')
            ->and($permission->slug)->toBe('edit-posts');
    });

    it('uses provided slug if available', function () {
        $permission = new Permission([
            'name' => 'Edit Posts',
            'slug' => 'custom-slug'
        ]);
        expect($permission->slug)->toBe('custom-slug');
    });

    it('returns null for scope if not set', function () {
        $permission = new Permission(['name' => 'Edit Posts']);
        expect($permission->scope)->toBeNull();
    });

    it('returns scope value if set', function () {
        $permission = new Permission([
            'name' => 'Edit Posts',
            'scope' => 'admin'
        ]);
        expect($permission->scope)->toBe('admin');
    });
});

describe('slug generation', function () {
    it('automatically generates slug on creation if not provided', function () {
        $permission = Permission::create(['name' => 'Create New Post']);

        expect($permission->slug)->toBe('create-new-post', 'Slug was not generated on creation');

        $freshPermission = Permission::find($permission->id);

        expect($freshPermission)->not->toBeNull('Permission was not saved to the database');

        if ($freshPermission) {
            $attributes = $freshPermission->getAttributes();

            expect($attributes)->toBeArray('getAttributes() did not return an array')
                ->and($attributes)->toHaveKey('slug', 'create-new-post');

            if (isset($attributes['slug'])) {
                expect($attributes['slug'])->toBe('create-new-post', 'Saved slug does not match expected value');
            } else {
                $this->fail('Slug is not set in the attributes array');
            }

            expect($freshPermission->slug)->toBe('create-new-post', 'Slug accessor did not return the expected value');
        } else {
            $this->fail('Permission was not found in the database after creation');
        }
    });


    it('does not override provided slug on creation', function () {
        $permission = Permission::create([
            'name' => 'Create New Post',
            'slug' => 'custom-slug'
        ]);

        $freshPermission = Permission::find($permission->id);
        expect($freshPermission->slug)->toBe('custom-slug')
            ->and($freshPermission->getAttributes()['slug'])->toBe('custom-slug');
    });

    it('generates slug when updating name if slug is empty', function () {
        $permission = Permission::create(['name' => 'Old Name']);
        $permission->name = 'New Name';
        $permission->slug = null;
        $permission->save();

        $freshPermission = Permission::find($permission->id);
        expect($freshPermission->slug)->toBe('new-name')
            ->and($freshPermission->getAttributes()['slug'])->toBe('new-name');
    });

    it('does not change slug when updating name if slug is not empty', function () {
        $permission = Permission::create(['name' => 'Old Name', 'slug' => 'old-slug']);
        $permission->name = 'New Name';
        $permission->save();

        $freshPermission = Permission::find($permission->id);
        expect($freshPermission->slug)->toBe('old-slug')
            ->and($freshPermission->getAttributes()['slug'])->toBe('old-slug');
    });
});
