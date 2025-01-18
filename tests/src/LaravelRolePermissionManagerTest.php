<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\Contracts\AuthenticatableWithRolesAndPermissions;
use CreativeCrafts\LaravelRolePermissionManager\Exceptions\InvalidParentException;
use CreativeCrafts\LaravelRolePermissionManager\Exceptions\InvalidScopeException;
use CreativeCrafts\LaravelRolePermissionManager\Exceptions\UnableToCreateRoleException;
use CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManager;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

covers(LaravelRolePermissionManager::class);

it('can create role', function () {
    $manager = app(LaravelRolePermissionManager::class);

    $role = $manager->createRole('Admin', 'admin', 'Administrator role');

    expect($role)->toBeInstanceOf(Role::class)
        ->and($role->name)->toBe('Admin')
        ->and($role->slug)->toBe('admin')
        ->and($role->description)->toBe('Administrator role')
        ->and($role->parent_id)->toBeNull();
});

it('can create role with parent', function () {
    $manager = app(LaravelRolePermissionManager::class);

    $parentRole = $manager->createRole('Super Admin', 'super-admin', 'Super Administrator role');
    $childRole = $manager->createRole('Admin', 'admin', 'Administrator role', $parentRole);

    expect($childRole)->toBeInstanceOf(Role::class)
        ->and($childRole->name)->toBe('Admin')
        ->and($childRole->slug)->toBe('admin')
        ->and($childRole->description)->toBe('Administrator role')
        ->and($childRole->parent_id)->toBe($parentRole->id);
});

it('creates role with default values when only name is provided', function () {
    $manager = app(LaravelRolePermissionManager::class);

    $role = $manager->createRole('Editor', 'editor');

    expect($role)->toBeInstanceOf(Role::class)
        ->and($role->name)->toBe('Editor')
        ->and($role->slug)->toBe('editor')
        ->and($role->description)->toBeNull()
        ->and($role->parent_id)->toBeNull();
});

it('creates unique roles', function () {
    $manager = app(LaravelRolePermissionManager::class);

    $manager->createRole('Admin', 'admin');

    $manager->createRole('Admin', 'admin');
})->throws(UnableToCreateRoleException::class);

it('throws exception when setting invalid parent role', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $role = Role::factory()->create();
    $invalidParent = new Role();

    $manager->setRoleParent($role, $invalidParent);
})->throws(InvalidParentException::class, 'Parent role must be a persisted Role model instance.');

it('returns empty collection for non-existent role permissions', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $nonExistentRole = new Role();

    $permissions = $manager->getAllPermissionsForRole($nonExistentRole);

    expect($permissions)->toBeInstanceOf(Collection::class)
        ->and($permissions)->toBeEmpty();
});

it('throws exception when creating permission with invalid scope', function () {
    $manager = app(LaravelRolePermissionManager::class);

    $scope = ['invalid_scope'];

    $manager->createPermission('Test', 'test', 'description', $scope);

    assert(
        is_null($scope) || is_string($scope),
        new InvalidScopeException(
            'Scope must be a string or null.',
            Response::HTTP_NOT_ACCEPTABLE
        )
    );
})->throws(TypeError::class, 'Argument #4 ($scope) must be of type ?string, array given');

it('returns cached roles when available', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $cachedRoles = collect([new Role(['name' => 'Cached Role'])]);

    Cache::shouldReceive('remember')
        ->once()
        ->andReturn($cachedRoles);

    $roles = $manager->getAllRoles();

    expect($roles)->toBe($cachedRoles);
});

it('returns cached permissions when available', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $cachedPermissions = collect(
        [new Permission(['name' => 'Cached Permission'])]
    );

    Cache::shouldReceive('remember')
        ->once()
        ->andReturn($cachedPermissions);

    $permissions = $manager->getAllPermissions();

    expect($permissions)->toBe($cachedPermissions);
});

it('throws exception when giving non-existent permission to role', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $role = Role::factory()->create();

    $manager->givePermissionToRole($role, 'non_existent_permission');
})->throws(ModelNotFoundException::class);

it('does nothing when revoking non-existent permission from role', function () {
    $tableName = config('role-permission-manager.role_permission_table');
    expect($tableName)->toBe('role_has_permissions');

    $manager = app(LaravelRolePermissionManager::class);
    $role = Role::factory()->create();

    $tableExists = Schema::hasTable($tableName);
    expect($tableExists)->toBeTrue();

    $manager->revokePermissionFromRole($role, 'non_existent_permission');

    expect($role->fresh()->permissions)->toBeEmpty();
});

it('returns empty arrays when syncing with empty permissions array', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $role = Role::factory()->create();

    $result = $manager->syncPermissions($role, []);

    expect($result)->toBe(['attached' => [], 'detached' => []]);
});

it('returns true for super admin regardless of permission', function () {
    $managerMock = Mockery::mock(LaravelRolePermissionManager::class)->makePartial();

    $superAdmin = new class () {
        public int $id = 1;

        public function isSuperAdmin(): bool
        {
            return true;
        }
    };

    $managerMock->shouldReceive('isSuperAdmin')
        ->once()
        ->with($superAdmin)
        ->andReturn(true);

    $managerMock->shouldReceive('getAllPermissionsForUser')
        ->never()
        ->with($superAdmin)
        ->andReturn(new Collection());

    $result = $managerMock->hasPermissionTo($superAdmin, 'any_permission');

    expect($result)->toBeTrue();
});

it('checks permissions for non-super admin users', function () {
    $managerMock = Mockery::mock(LaravelRolePermissionManager::class)->makePartial();

    $user = new class () {
        public int $id = 2;

        public function isSuperAdmin(): bool
        {
            return false;
        }
    };

    $managerMock->shouldReceive('isSuperAdmin')
        ->once()
        ->with($user)
        ->andReturn(false);

    $permission = new Permission(['name' => 'test_permission', 'slug' => 'test_permission']);

    $managerMock->shouldReceive('getAllPermissionsForUser')
        ->once()
        ->with($user)
        ->andReturn(new Collection([$permission]));

    $result = $managerMock->hasPermissionTo($user, 'test_permission');

    expect($result)->toBeTrue();
});

it('returns empty collection for user without roles or permissions', function () {
    $manager = app(LaravelRolePermissionManager::class);

    $user = Mockery::mock(AuthenticatableWithRolesAndPermissions::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn(1);

    $permissionsRelation = Mockery::mock(BelongsToMany::class);
    $permissionsRelation->shouldReceive('get')->andReturn(collect());
    $user->shouldReceive('permissions')->andReturn($permissionsRelation);

    $rolesRelation = Mockery::mock(BelongsToMany::class);
    $rolesRelation->shouldReceive('get')->andReturn(collect());
    $user->shouldReceive('roles')->andReturn($rolesRelation);

    Cache::shouldReceive('remember')
        ->once()
        ->with("user_permissions_1", Mockery::any(), Mockery::any())
        ->andReturn(collect());

    $permissions = $manager->getAllPermissionsForUser($user);

    expect($permissions)->toBeInstanceOf(Collection::class)
        ->and($permissions)->toBeEmpty();
});

it('returns empty collection for non-existent scope', function () {
    $manager = app(LaravelRolePermissionManager::class);

    $permissions = $manager->getAllPermissionsForScope('non_existent_scope');

    expect($permissions)->toBeInstanceOf(Collection::class)
        ->and($permissions)->toBeEmpty();
});

it('returns false for isSuperAdmin when user is not super admin', function () {
    $manager = app(LaravelRolePermissionManager::class);

    $user = Mockery::mock(AuthenticatableWithRolesAndPermissions::class);

    $rolesRelation = Mockery::mock(BelongsToMany::class);
    $rolesRelation->shouldReceive('where->exists')->andReturn(false);

    $user->shouldReceive('roles')->andReturn($rolesRelation);

    $result = $manager->isSuperAdmin($user);

    expect($result)->toBeFalse();
});

it('returns empty collection for role without sub-roles', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $role = Role::factory()->create();

    $subRoles = $manager->getSubRoles($role);

    expect($subRoles)->toBeInstanceOf(Collection::class)
        ->and($subRoles)->toBeEmpty();
});

it('does nothing when granting permission to role without sub-roles', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $role = Role::factory()->create();
    $permission = Permission::factory()->create();

    $manager->grantPermissionToRoleAndSubRoles($role, $permission);

    expect($role->permissions)->toHaveCount(1)
        ->and($role->permissions->first()->id)->toBe($permission->id);
});

it('does nothing when revoking permission from role without sub-roles', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $role = Role::factory()->create();
    $permission = Permission::factory()->create();
    $role->permissions()->attach($permission);

    $manager->revokePermissionFromRoleAndSubRoles($role, $permission);

    expect($role->fresh()->permissions)->toBeEmpty();
});

it('saves the role and clears cache when creating', function () {
    Cache::shouldReceive('forget')->once()->with('all_roles');

    $roleManager = new LaravelRolePermissionManager();
    $role = $roleManager->createRole('Test Role', 'test-role', 'Test description');

    expect($role)->toBeInstanceOf(Role::class)
        ->and($role->exists)->toBeTrue()
        ->and($role->name)->toBe('Test Role')
        ->and($role->slug)->toBe('test-role')
        ->and($role->description)->toBe('Test description');

    $savedRole = Role::where('slug', 'test-role')->first();
    expect($savedRole)->not->toBeNull();

    Cache::shouldHaveReceived('forget')->once()->with('all_roles');
});

it('creates a role with a parent', function () {
    $roleManager = new LaravelRolePermissionManager();
    $parentRole = $roleManager->createRole('Parent Role', 'parent-role');

    $childRole = $roleManager->createRole('Child Role', 'child-role', 'Child description', $parentRole);

    expect($childRole->parent_id)->toBe($parentRole->id)
        ->and($childRole->parent->slug)->toBe('parent-role');
});

it('can revoke non-existent permission from role', function () {
    $role = Role::factory()->create();
    $permission = Permission::factory()->create();

    $roleManager = new LaravelRolePermissionManager();
    $roleManager->revokePermissionFromRole($role, 'non-existent-permission');

    expect($role->permissions)->not->toContain($permission);
});

it('can sync permissions with non-existent permissions', function () {
    $role = Role::factory()->create();
    Permission::factory()->create();

    $roleManager = new LaravelRolePermissionManager();
    $result = $roleManager->syncPermissions($role, ['existing-permission', 'non-existent-permission']);

    expect($result['attached'])->toBeEmpty()
        ->and($result['detached'])->toBeEmpty();
});

it('sets a null parent role successfully', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $parentRole = $manager->createRole('Parent Role', 'parent-role');
    $childRole = $manager->createRole('Child Role', 'child-role', 'Child description', $parentRole);

    expect($childRole->parent_id)->toBe($parentRole->id);

    $manager->setRoleParent($childRole, null);

    expect($childRole->fresh()->parent_id)->toBeNull();
});

it('creates permission with case_insensitive config', function () {
    Config::set('role-permission-manager.case_sensitive_permissions', false);

    $manager = app(LaravelRolePermissionManager::class);
    $permission = $manager->createPermission('MyPermission', 'MyPermission');

    expect($permission->name)->toBe('mypermission')
        ->and($permission->slug)->toBe('mypermission');
});

it('does not auto-create permission when auto_create_permissions is false', function () {
    Config::set('role-permission-manager.auto_create_permissions', false);
    $manager = Mockery::mock(LaravelRolePermissionManager::class)->makePartial();
    $user = new class () {
        public int $id = 777;
    };

    $manager->shouldReceive('createPermission')->never();
    $manager->shouldReceive('isSuperAdmin')
        ->once()
        ->with($user)
        ->andReturn(false);
    $manager->shouldReceive('getAllPermissionsForUser')
        ->once()
        ->with($user)
        ->andReturn(collect());

    $result = $manager->hasPermissionTo($user, 'non_existent_permission');
    expect($result)->toBeFalse();
});

it('respects wildcard permission checking', function () {
    Config::set('role-permission-manager.enable_wildcard_permission', true);
    $manager = Mockery::mock(LaravelRolePermissionManager::class)->makePartial();

    $user = new class () {
        public int $id = 999;
    };

    $manager->shouldReceive('isSuperAdmin')
        ->once()
        ->with($user)
        ->andReturn(false);

    $permissionMock = Mockery::mock(Permission::class)->makePartial();
    $permissionMock->setRawAttributes([
        'name' => 'blog.*',
        'slug' => 'blog.*',
    ]);

    $permissionMock->shouldReceive('wildcardMatch')
        ->with('blog.create', null)
        ->andReturn(true);

    $manager->shouldReceive('getAllPermissionsForUser')
        ->once()
        ->with($user)
        ->andReturn(collect([$permissionMock]));

    $result = $manager->hasPermissionTo($user, 'blog.create');
    expect($result)->toBeTrue();
});

it('returns null when getRoleBySlug does not find a role', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $result = $manager->getRoleBySlug('this-slug-does-not-exist');
    expect($result)->toBeNull();
});

it('retrieves existing role by slug', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $role = $manager->createRole('Some Role', 'some-role');

    $foundRole = $manager->getRoleBySlug('some-role');
    expect($foundRole)->not->toBeNull()
        ->and($foundRole?->id)->toBe($role->id);
});

it('clears cache if environment is development in getAllPermissionsForUser', function () {
    Config::set('app.env', 'development');

    $manager = app(LaravelRolePermissionManager::class);

    $user = Mockery::mock(AuthenticatableWithRolesAndPermissions::class)
        ->makePartial();

    $user->id = 999;
    $user->shouldReceive('getAuthIdentifier')->andReturn(999);

    $permissionsRelation = Mockery::mock(BelongsToMany::class);
    $permissionsRelation->shouldReceive('get')->andReturn(collect());
    $user->shouldReceive('permissions')->andReturn($permissionsRelation);

    $rolesRelation = Mockery::mock(BelongsToMany::class);
    $rolesRelation->shouldReceive('get')->andReturn(collect());
    $user->shouldReceive('roles')->andReturn($rolesRelation);

    Cache::shouldReceive('forget')->once()->with("user_permissions_999");
    Cache::shouldReceive('remember')->once()->andReturn(collect());

    $permissions = $manager->getAllPermissionsForUser($user);
    expect($permissions)->toBeEmpty();
});

it('forgets a specific cache key when clearPermissionCache is called with argument', function () {
    $manager = app(LaravelRolePermissionManager::class);
    Cache::shouldReceive('forget')
        ->once()
        ->with('custom_key');

    $reflection = new ReflectionClass($manager);
    $method = $reflection->getMethod('clearPermissionCache');
    $method->setAccessible(true);
    $method->invoke($manager, 'custom_key');
});

it('assigns permissions to role and all sub-roles', function () {
    $manager = app(LaravelRolePermissionManager::class);

    $parentRole = $manager->createRole('Parent', 'parent');
    $childRole = $manager->createRole('Child', 'child', 'Child description', $parentRole);
    $grandChildRole = $manager->createRole('GrandChild', 'grandchild', 'Grandchild description', $childRole);

    $permission = Permission::factory()->create(['slug' => 'test-perm']);

    $manager->grantPermissionToRoleAndSubRoles($parentRole, $permission);

    expect($parentRole->permissions->pluck('slug'))->toContain('test-perm')
        ->and($childRole->fresh()->permissions->pluck('slug'))->toContain('test-perm')
        ->and($grandChildRole->fresh()->permissions->pluck('slug'))->toContain('test-perm');
});

it('revokes permissions from role and all sub-roles', function () {
    $manager = app(LaravelRolePermissionManager::class);

    $parentRole = $manager->createRole('Parent2', 'parent2');
    $childRole = $manager->createRole('Child2', 'child2', 'Child2 description', $parentRole);

    $permission = Permission::factory()->create(['slug' => 'perm-to-revoke']);

    $manager->grantPermissionToRoleAndSubRoles($parentRole, $permission);
    expect($parentRole->permissions->pluck('slug'))->toContain('perm-to-revoke')
        ->and($childRole->fresh()->permissions->pluck('slug'))->toContain('perm-to-revoke');

    $manager->revokePermissionFromRoleAndSubRoles($parentRole, $permission);
    expect($parentRole->fresh()->permissions)->toBeEmpty()
        ->and($childRole->fresh()->permissions)->toBeEmpty();
});

it('caches user roles in production environment', function () {
    Config::set('app.env', 'production');

    $manager = app(LaravelRolePermissionManager::class);

    $mockUser = Mockery::mock(AuthenticatableWithRolesAndPermissions::class)
        ->makePartial();
    $mockUser->id = 999;
    $mockUser->shouldReceive('getAuthIdentifier')->andReturn(999);

    $rolesRelation = Mockery::mock(BelongsToMany::class);
    $rolesRelation->shouldReceive('get')->andReturn(collect());
    $mockUser->shouldReceive('roles')->andReturn($rolesRelation);

    Cache::spy();

    Cache::shouldReceive('remember')
        ->once()
        ->with("user_roles_999", Mockery::any(), Mockery::any())
        ->andReturn(collect());

    $manager->getUserRoles($mockUser);

    Cache::shouldHaveReceived('forget')
        ->with('user_roles_999')
        ->once();
});

it('clears user role cache properly', function () {
    $manager = app(LaravelRolePermissionManager::class);

    $mockUser = Mockery::mock(AuthenticatableWithRolesAndPermissions::class)
        ->makePartial();
    $mockUser->id = 999;
    $mockUser->shouldReceive('getAuthIdentifier')->andReturn(999);

    Cache::shouldReceive('forget')->once()->with("user_roles_{$mockUser->id}");
    Cache::shouldReceive('forget')->once()->with("user_role_names_{$mockUser->id}");
    Cache::shouldReceive('forget')->once()->with("user_role_slugs_{$mockUser->id}");

    $manager->clearUserRoleCache($mockUser);
});

it('handles revoking permission by scope that does not exist', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $role = Role::factory()->create();
    $permission = Permission::factory()->create(['slug' => 'permission-scope-test', 'scope' => 'some_scope']);

    $role->permissions()->attach($permission);
    $manager->revokePermissionFromRole($role, 'permission-scope-test', 'no_such_scope');

    expect($role->fresh()->permissions->pluck('slug'))->toContain('permission-scope-test');
});
