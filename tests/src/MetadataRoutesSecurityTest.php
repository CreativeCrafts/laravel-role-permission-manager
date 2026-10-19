<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\Contracts\AuthenticatableWithRolesAndPermissions;
use CreativeCrafts\LaravelRolePermissionManager\Contracts\MetadataAccessAuthorizer;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;

it('denies guests and ordinary users access to protected catalogs', function () {
    $ordinary = createTestUser();

    $this->getJson('/api/role-permissions/roles')->assertUnauthorized();
    $this->actingAs($ordinary)
        ->getJson('/api/role-permissions/roles')
        ->assertForbidden();
    $this->actingAs($ordinary)
        ->getJson('/api/role-permissions/permissions')
        ->assertForbidden();
});

it('authorizes role and permission catalogs independently', function () {
    $actor = createTestUser();
    $viewRoles = Permission::factory()->create([
        'name' => 'view roles',
        'slug' => 'view-roles',
        'scope' => null,
    ]);
    $actor->givePermissionTo($viewRoles);
    Role::factory()->create();

    $this->actingAs($actor)
        ->getJson('/api/role-permissions/roles')
        ->assertOk()
        ->assertJsonCount(1);
    $this->actingAs($actor)
        ->getJson('/api/role-permissions/permissions')
        ->assertForbidden();
});

it('allows self-inspection and denies guessed cross-user identifiers before disclosure', function () {
    $actor = createTestUser();
    $target = createTestUser();
    $permission = Permission::factory()->create([
        'name' => 'profile.view',
        'slug' => 'profile.view',
        'scope' => null,
    ]);
    $actor->givePermissionTo($permission);

    $this->actingAs($actor)
        ->getJson("/api/role-permissions/user/{$actor->id}/permissions")
        ->assertOk()
        ->assertJsonFragment(['slug' => 'profile.view']);

    $this->actingAs($actor)
        ->getJson("/api/role-permissions/user/{$target->id}/permissions")
        ->assertForbidden();
    $this->actingAs($actor)
        ->getJson('/api/role-permissions/user/999999/permissions')
        ->assertForbidden();
});

it('supports an explicit host authorizer for cross-user inspection', function () {
    app()->instance(MetadataAccessAuthorizer::class, new class () implements MetadataAccessAuthorizer {
        public function canViewRoleCatalog(AuthenticatableWithRolesAndPermissions $actor): bool
        {
            return false;
        }

        public function canViewPermissionCatalog(AuthenticatableWithRolesAndPermissions $actor): bool
        {
            return false;
        }

        public function canInspectOtherUsers(AuthenticatableWithRolesAndPermissions $actor): bool
        {
            return true;
        }

        public function canInspectUser(
            AuthenticatableWithRolesAndPermissions $actor,
            AuthenticatableWithRolesAndPermissions $target,
        ): bool {
            return true;
        }
    });

    $actor = createTestUser();
    $target = createTestUser();
    $zero = Permission::factory()->create([
        'name' => 'jobs.read zero',
        'slug' => 'jobs.read',
        'scope' => '0',
    ]);
    $other = Permission::factory()->create([
        'name' => 'jobs.read other',
        'slug' => 'jobs.read',
        'scope' => 'other',
    ]);
    $target->givePermissionTo($zero);
    $target->givePermissionTo($other);

    $this->actingAs($actor)
        ->getJson("/api/role-permissions/user/{$target->id}/permissions/0")
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['slug' => 'jobs.read', 'scope' => '0'])
        ->assertJsonMissing(['scope' => 'other']);

    $this->actingAs($actor)
        ->getJson('/api/role-permissions/user/999999/permissions')
        ->assertNotFound();
});

it('does not register the controller user-roles method as a public route', function () {
    $actor = createTestUser();

    $this->actingAs($actor)
        ->getJson("/api/role-permissions/user/{$actor->id}/roles")
        ->assertNotFound();
});
