<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\Exceptions\InvalidParentException;
use CreativeCrafts\LaravelRolePermissionManager\Exceptions\InvalidPermissionReferenceException;
use CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManager;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use CreativeCrafts\LaravelRolePermissionManager\Tests\Models\TestUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('does not treat same ids on another database as the same authorization record', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $defaultPermission = Permission::factory()->create([
        'name' => 'Default side',
        'slug' => 'default-side',
        'scope' => null,
    ]);
    $defaultRole = Role::factory()->create(['name' => 'Default role', 'slug' => 'default-role']);
    $defaultUser = createTestUser();
    $defaultUser->givePermissionTo($defaultPermission);
    $defaultUser->assignRole($defaultRole);
    $manager->givePermissionToRole($defaultRole, $defaultPermission);

    $originalDefault = (string) config('database.default');
    config()->set('database.connections.tenant_cache', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    try {
        config()->set('database.default', 'tenant_cache');
        DB::purge('tenant_cache');
        DB::reconnect('tenant_cache');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
        });
        $migrations = dirname(__DIR__, 2) . '/database/migrations';
        (include $migrations . '/create_role_permission_manager_tables.php')->up();
        (include $migrations . '/add_permission_identity_keys.php')->up();

        $tenantPermission = Permission::factory()->create([
            'name' => 'Tenant side',
            'slug' => 'tenant-side',
            'scope' => null,
        ]);
        $tenantRole = Role::factory()->create(['name' => 'Tenant role', 'slug' => 'tenant-role']);
        $tenantUser = TestUser::factory()->create(['name' => 'Tenant']);
        foreach ([$tenantPermission, $tenantRole, $tenantUser] as $model) {
            $model->setConnection('tenant_cache');
        }
        $tenantUser->givePermissionTo($tenantPermission);
        $tenantUser->assignRole($tenantRole);
        $manager->givePermissionToRole($tenantRole, $tenantPermission);
    } finally {
        config()->set('database.default', $originalDefault);
    }

    expect($defaultPermission->getKey())->toBe($tenantPermission->getKey())
        ->and($defaultRole->getKey())->toBe($tenantRole->getKey())
        ->and($defaultUser->hasPermissionTo($defaultPermission))->toBeTrue()
        ->and($defaultUser->hasPermissionTo($tenantPermission))->toBeFalse()
        ->and($tenantUser->hasPermissionTo($tenantPermission))->toBeTrue()
        ->and($tenantUser->hasPermissionTo($defaultPermission))->toBeFalse()
        ->and($defaultUser->hasRole($defaultRole))->toBeTrue()
        ->and($defaultUser->hasRole($tenantRole))->toBeFalse()
        ->and($tenantUser->hasRole($tenantRole))->toBeTrue()
        ->and($tenantUser->hasRole($defaultRole))->toBeFalse()
        ->and($defaultRole->hasPermissionTo($defaultPermission))->toBeTrue()
        ->and($defaultRole->hasPermissionTo($tenantPermission))->toBeFalse()
        ->and($tenantRole->hasPermissionTo($tenantPermission))->toBeTrue()
        ->and($tenantRole->hasPermissionTo($defaultPermission))->toBeFalse();

    expect(fn () => $manager->givePermissionToRole($tenantRole, $defaultPermission))
        ->toThrow(InvalidPermissionReferenceException::class)
        ->and(fn () => $defaultUser->assignRole($tenantRole))
        ->toThrow(InvalidPermissionReferenceException::class)
        ->and(fn () => $manager->setRoleParent($tenantRole, $defaultRole))
        ->toThrow(InvalidParentException::class)
        ->and($tenantRole->fresh()->parent_id)->toBeNull();

    $synced = $manager->syncPermissions($tenantRole, [$tenantPermission]);

    expect($synced['unchanged'][0]['slug'])->toBe('tenant-side')
        ->and($tenantRole->fresh()->permissions->pluck('slug')->all())->toBe(['tenant-side']);
});
