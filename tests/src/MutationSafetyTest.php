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
        ->toThrow(AmbiguousPermissionReferenceException::class)
        ->and(fn () => $manager->givePermissionToRole($role, 'shared'))
        ->toThrow(AmbiguousPermissionReferenceException::class)
        ->and(fn () => createTestUser()->givePermissionTo('shared'))
        ->toThrow(AmbiguousPermissionReferenceException::class)
        ->and($role->fresh()->permissions)->toBeEmpty();

    $manager->givePermissionToRole($role, 'shared', '0');
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

it('sorts synchronization categories by permission id', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $role = Role::factory()->create();
    $first = Permission::factory()->create(['name' => 'First', 'slug' => 'first-id', 'scope' => 'a']);
    $second = Permission::factory()->create(['name' => 'Second', 'slug' => 'second-id', 'scope' => 'b']);
    $third = Permission::factory()->create(['name' => 'Third', 'slug' => 'third-id', 'scope' => 'c']);
    $role->permissions()->attach([$third->getKey(), $second->getKey()]);

    $replaced = $manager->syncPermissions($role, [$second, $first]);

    expect(array_column($replaced['attached'], 'id'))->toBe([$first->getKey()])
        ->and(array_column($replaced['unchanged'], 'id'))->toBe([$second->getKey()])
        ->and(array_column($replaced['detached'], 'id'))->toBe([$third->getKey()]);

    $role->permissions()->attach($third->getKey());
    $cleared = $manager->syncPermissions($role, [$first]);

    expect(array_column($cleared['detached'], 'id'))->toBe([$second->getKey(), $third->getKey()])
        ->and(array_column($cleared['unchanged'], 'id'))->toBe([$first->getKey()]);

    $reversed = $manager->syncPermissions($role, [$third, $second, $first]);

    expect(array_column($reversed['attached'], 'id'))->toBe([$second->getKey(), $third->getKey()])
        ->and(array_column($reversed['unchanged'], 'id'))->toBe([$first->getKey()])
        ->and($reversed['detached'])->toBeEmpty();
});

it('rejects a cycle when parent_id is saved directly and keeps the check inside a transaction', function () {
    $parent = Role::factory()->create(['name' => 'Locked parent', 'slug' => 'locked-parent']);
    $child = Role::factory()->create(['name' => 'Locked child', 'slug' => 'locked-child']);
    $levelDuringLookup = 0;
    DB::listen(function ($query) use (&$levelDuringLookup, $child): void {
        if (! str_contains(strtolower($query->sql), 'parent_id')) {
            return;
        }

        $levelDuringLookup = max($levelDuringLookup, $child->getConnection()->transactionLevel());
    });

    $child->parent_id = $parent->getKey();
    $child->save();

    expect($child->fresh()->parent_id)->toBe($parent->getKey())
        ->and($levelDuringLookup)->toBeGreaterThan(0);

    $parent->parent_id = $child->getKey();

    expect(fn () => $parent->save())->toThrow(RoleHierarchyCycleException::class)
        ->and($parent->fresh()->parent_id)->toBeNull();

    expect(fn () => $parent->updateQuietly(['parent_id' => $child->getKey()]))
        ->toThrow(RoleHierarchyCycleException::class)
        ->and(fn () => $parent->saveQuietly())
        ->toThrow(RoleHierarchyCycleException::class)
        ->and($parent->fresh()->parent_id)->toBeNull();

    $child->updateQuietly(['parent_id' => null]);

    expect($child->fresh()->parent_id)->toBeNull();
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

it('locks the current hierarchy before a subtree grant and skips a role that has left it', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $root = Role::factory()->create(['name' => 'Locked root', 'slug' => 'locked-root']);
    $child = Role::factory()->create([
        'name' => 'Locked child',
        'slug' => 'locked-child',
        'parent_id' => $root->id,
    ]);
    $permission = Permission::factory()->create([
        'name' => 'Subtree grant',
        'slug' => 'subtree-grant',
        'scope' => null,
    ]);
    $manager->setRoleParent($child, null);

    $rolesTable = strtolower((string) config('role-permission-manager.roles_table'));
    $pivotTable = strtolower((string) config('role-permission-manager.role_permission_table'));
    $sawHierarchyLock = false;
    $pivotWriteAfterLock = false;

    DB::listen(function ($query) use (
        &$sawHierarchyLock,
        &$pivotWriteAfterLock,
        $root,
        $rolesTable,
        $pivotTable,
    ): void {
        $sql = strtolower($query->sql);
        $locksRoles = str_contains($sql, $rolesTable)
            && (str_contains($sql, '0 = 1') || str_contains($sql, 'for update'));
        if ($locksRoles && $root->getConnection()->transactionLevel() > 0) {
            $sawHierarchyLock = true;
        }

        if (
            $sawHierarchyLock
            && str_contains($sql, $pivotTable)
            && str_starts_with(ltrim($sql), 'insert')
        ) {
            $pivotWriteAfterLock = true;
        }
    });

    $manager->grantPermissionToRoleAndSubRoles($root, $permission);

    expect($sawHierarchyLock)->toBeTrue()
        ->and($pivotWriteAfterLock)->toBeTrue()
        ->and($root->fresh()->permissions->modelKeys())->toBe([$permission->id])
        ->and($child->fresh()->permissions)->toBeEmpty();

    $manager->setRoleParent($child, $root);
    $manager->grantPermissionToRoleAndSubRoles($root, $permission);
    $manager->setRoleParent($child, null);
    $manager->revokePermissionFromRoleAndSubRoles($root, $permission);

    expect($root->fresh()->permissions)->toBeEmpty()
        ->and($child->fresh()->permissions->modelKeys())->toBe([$permission->id]);
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
