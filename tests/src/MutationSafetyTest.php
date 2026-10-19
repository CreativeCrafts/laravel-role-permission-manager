<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\DataTransferObjects\PermissionReference;
use CreativeCrafts\LaravelRolePermissionManager\Exceptions\AmbiguousPermissionReferenceException;
use CreativeCrafts\LaravelRolePermissionManager\Exceptions\EmptyPermissionSyncException;
use CreativeCrafts\LaravelRolePermissionManager\Exceptions\RoleHierarchyCycleException;
use CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManager;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

it('synchronizes mixed references only after complete resolution', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $role = Role::factory()->create();
    $byModel = Permission::factory()->create(['slug' => 'by-model', 'scope' => null]);
    $byId = Permission::factory()->create(['slug' => 'by-id', 'scope' => null]);
    $bySlug = Permission::factory()->create(['slug' => 'by-slug', 'scope' => null]);
    $byScope = Permission::factory()->create(['slug' => 'shared', 'scope' => '0']);

    $result = $manager->syncPermissions($role, [
        $byModel,
        $byId->id,
        'by-slug',
        new PermissionReference('shared', '0'),
        ['slug' => 'shared', 'scope' => '0'],
    ]);

    expect($role->fresh()->permissions->modelKeys())->toEqualCanonicalizing([
        $byModel->id,
        $byId->id,
        $bySlug->id,
        $byScope->id,
    ])->and($result['attached'])->toHaveCount(4)
        ->and($result['detached'])->toBeEmpty()
        ->and($result['unchanged'])->toBeEmpty();
});

it('fails a partially unresolved synchronization without detaching valid assignments', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $role = Role::factory()->create();
    $existing = Permission::factory()->create(['slug' => 'existing', 'scope' => null]);
    $replacement = Permission::factory()->create(['slug' => 'replacement', 'scope' => null]);
    $role->permissions()->attach($existing);

    expect(fn () => $manager->syncPermissions($role, [$replacement, 'missing']))
        ->toThrow(ModelNotFoundException::class)
        ->and($role->fresh()->permissions->modelKeys())->toBe([$existing->id]);
});

it('rejects ambiguous slug-only mutation references', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $role = Role::factory()->create();
    Permission::factory()->create(['name' => 'shared null', 'slug' => 'shared', 'scope' => null]);
    $zero = Permission::factory()->create(['name' => 'shared zero', 'slug' => 'shared', 'scope' => '0']);

    expect(fn () => $manager->syncPermissions($role, ['shared']))
        ->toThrow(AmbiguousPermissionReferenceException::class);

    $manager->syncPermissions($role, [new PermissionReference('shared', '0')]);
    expect($role->fresh()->permissions->modelKeys())->toBe([$zero->id]);
});

it('requires explicit clear and reports unchanged assignments deterministically', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $role = Role::factory()->create();
    $permission = Permission::factory()->create(['slug' => 'retained', 'scope' => '']);
    $role->permissions()->attach($permission);

    expect(fn () => $manager->syncPermissions($role, []))
        ->toThrow(EmptyPermissionSyncException::class);

    $unchanged = $manager->syncPermissions($role, [$permission, $permission]);
    expect($unchanged['attached'])->toBeEmpty()
        ->and($unchanged['detached'])->toBeEmpty()
        ->and($unchanged['unchanged'])->toBe([[
            'id' => $permission->id,
            'slug' => 'retained',
            'scope' => '',
        ]]);

    $cleared = $manager->clearPermissions($role);
    expect($cleared['detached'])->toHaveCount(1)
        ->and($role->fresh()->permissions)->toBeEmpty();
});

it('rolls back synchronization with its outer transaction', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $role = Role::factory()->create();
    $existing = Permission::factory()->create(['slug' => 'existing', 'scope' => null]);
    $replacement = Permission::factory()->create(['slug' => 'replacement', 'scope' => null]);
    $role->permissions()->attach($existing);

    try {
        DB::transaction(function () use ($manager, $role, $replacement): void {
            $manager->syncPermissions($role, [$replacement]);
            throw new RuntimeException('force rollback');
        });
    } catch (RuntimeException) {
    }

    expect($role->fresh()->permissions->modelKeys())->toBe([$existing->id]);
});

it('prevents self and indirect role hierarchy cycles', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $a = Role::factory()->create(['name' => 'A', 'slug' => 'a']);
    $b = Role::factory()->create(['name' => 'B', 'slug' => 'b', 'parent_id' => $a->id]);
    $c = Role::factory()->create(['name' => 'C', 'slug' => 'c', 'parent_id' => $b->id]);

    expect(fn () => $manager->setRoleParent($a, $a))
        ->toThrow(RoleHierarchyCycleException::class)
        ->and(fn () => $manager->setRoleParent($a, $c))
        ->toThrow(RoleHierarchyCycleException::class)
        ->and($a->fresh()->parent_id)->toBeNull();
});

it('detects historical cycles and traverses valid branches once', function () {
    $root = Role::factory()->create(['name' => 'Root', 'slug' => 'root']);
    $left = Role::factory()->create(['name' => 'Left', 'slug' => 'left', 'parent_id' => $root->id]);
    $right = Role::factory()->create(['name' => 'Right', 'slug' => 'right', 'parent_id' => $root->id]);
    $leaf = Role::factory()->create(['name' => 'Leaf', 'slug' => 'leaf', 'parent_id' => $left->id]);

    expect($root->getAllChildren()->pluck('id')->all())->toBe([$left->id, $right->id, $leaf->id]);

    DB::table(config('role-permission-manager.roles_table'))
        ->where('id', $root->id)
        ->update(['parent_id' => $leaf->id]);

    expect(fn () => $leaf->fresh()->getAllPermissions())
        ->toThrow(RoleHierarchyCycleException::class);
});

it('propagates one exact scoped permission identity to every descendant once', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $root = Role::factory()->create(['name' => 'Root', 'slug' => 'root']);
    $child = Role::factory()->create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $root->id]);
    $null = Permission::factory()->create(['name' => 'shared null', 'slug' => 'shared', 'scope' => null]);
    $zero = Permission::factory()->create(['name' => 'shared zero', 'slug' => 'shared', 'scope' => '0']);

    $manager->grantPermissionToRoleAndSubRoles($root, 'shared', '0');

    expect($root->fresh()->permissions->modelKeys())->toBe([$zero->id])
        ->and($child->fresh()->permissions->modelKeys())->toBe([$zero->id])
        ->and($root->fresh()->permissions->modelKeys())->not->toContain($null->id);
});

it('deduplicates all-role input and defines empty set behavior', function () {
    $user = createTestUser();
    $role = Role::factory()->create(['name' => 'Editor', 'slug' => 'editor']);
    $user->assignRole($role);

    expect($user->hasAllRoles(['editor', 'editor']))->toBeTrue()
        ->and($user->hasAllRoles([]))->toBeTrue()
        ->and($user->hasAnyRole([]))->toBeFalse();
});
