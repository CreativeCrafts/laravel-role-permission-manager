<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\Exceptions\UnableToCreatePermissionException;
use CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManager;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('traverses every default relationship in both directions', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $user = createTestUser();
    $role = Role::factory()->create();
    $direct = Permission::factory()->create(['slug' => 'direct', 'scope' => null]);
    $rolePermission = Permission::factory()->create(['slug' => 'role-permission', 'scope' => null]);

    $user->assignRole($role);
    $user->givePermissionTo($direct);
    $manager->givePermissionToRole($role, $rolePermission);

    expect($user->roles()->first()->is($role))->toBeTrue()
        ->and($role->users()->first()->is($user))->toBeTrue()
        ->and($user->permissions()->first()->is($direct))->toBeTrue()
        ->and($direct->users()->first()->is($user))->toBeTrue()
        ->and($rolePermission->roles()->first()->is($role))->toBeTrue()
        ->and($role->permissions()->first()->is($rolePermission))->toBeTrue();
});

it('honors custom model and pivot table names bidirectionally', function () {
    Config::set('role-permission-manager.roles_table', 'custom_roles');
    Config::set('role-permission-manager.permissions_table', 'custom_permissions');
    Config::set('role-permission-manager.user_role_table', 'custom_user_roles');
    Config::set('role-permission-manager.user_permission_table', 'custom_user_permissions');
    Config::set('role-permission-manager.role_permission_table', 'custom_role_permissions');

    Schema::create('custom_roles', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->unique();
        $table->string('slug')->unique();
        $table->text('description')->nullable();
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->timestamps();
    });
    Schema::create('custom_permissions', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('slug');
        $table->string('scope')->nullable();
        $table->text('description')->nullable();
        $table->timestamps();
    });
    Schema::create('custom_user_roles', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('role_id');
        $table->timestamps();
        $table->unique(['user_id', 'role_id']);
    });
    Schema::create('custom_user_permissions', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('permission_id');
        $table->timestamps();
        $table->unique(['user_id', 'permission_id']);
    });
    Schema::create('custom_role_permissions', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('role_id');
        $table->unsignedBigInteger('permission_id');
        $table->timestamps();
        $table->unique(['role_id', 'permission_id']);
    });

    $user = createTestUser();
    $role = Role::query()->create(['name' => 'Custom', 'slug' => 'custom']);
    $permission = Permission::query()->create([
        'name' => 'custom.permission',
        'slug' => 'custom.permission',
        'scope' => '0',
    ]);
    $user->assignRole($role);
    $user->givePermissionTo($permission);
    $role->permissions()->attach($permission);

    expect($role->users()->first()->is($user))->toBeTrue()
        ->and($permission->users()->first()->is($user))->toBeTrue()
        ->and($permission->roles()->first()->is($role))->toBeTrue();
});

it('runs the published migrations down in foreign-key-safe order and reinstalls', function () {
    $identity = include __DIR__ . '/../../database/migrations/add_permission_identity_keys.php';
    $base = include __DIR__ . '/../../database/migrations/create_role_permission_manager_tables.php';

    $identity->down();
    $base->down();

    expect(Schema::hasTable(config('role-permission-manager.user_permission_table')))->toBeFalse()
        ->and(Schema::hasTable(config('role-permission-manager.role_permission_table')))->toBeFalse()
        ->and(Schema::hasTable(config('role-permission-manager.permissions_table')))->toBeFalse()
        ->and(Schema::hasTable(config('role-permission-manager.roles_table')))->toBeFalse();

    $base->up();
    $identity->up();

    $permissions = (string) config('role-permission-manager.permissions_table');
    $columns = collect(Schema::getColumns($permissions))->keyBy('name');

    expect(Schema::hasColumn($permissions, 'slug_scope_key'))->toBeTrue()
        ->and($columns['name_scope_key']['nullable'])->toBeFalse()
        ->and($columns['slug_scope_key']['nullable'])->toBeFalse()
        ->and(Schema::hasIndex($permissions, 'lprm_perm_name_scope_key_uq'))->toBeTrue()
        ->and(Schema::hasIndex($permissions, 'lprm_perm_slug_scope_key_uq'))->toBeTrue();
});

it('distinguishes null empty and zero scope while rejecting exact duplicates', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $null = $manager->createPermission('same null', 'same', scope: null);
    $empty = $manager->createPermission('same empty', 'same', scope: '');
    $zero = $manager->createPermission('same zero', 'same', scope: '0');

    expect([$null->scope, $empty->scope, $zero->scope])->toBe([null, '', '0']);

    expect(fn () => $manager->createPermission('same zero duplicate', 'same', scope: '0'))
        ->toThrow(UnableToCreatePermissionException::class);
});

it('blocks an additive identity migration before schema mutation when duplicates exist', function () {
    Config::set('role-permission-manager.permissions_table', 'legacy_permissions');
    Schema::create('legacy_permissions', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('slug')->nullable();
        $table->string('scope')->nullable();
        $table->text('description')->nullable();
        $table->timestamps();
    });
    DB::table('legacy_permissions')->insert([
        ['name' => 'duplicate', 'slug' => 'duplicate', 'scope' => null],
        ['name' => 'duplicate', 'slug' => 'duplicate', 'scope' => null],
    ]);
    $migration = include __DIR__ . '/../../database/migrations/add_permission_identity_keys.php';

    expect(fn () => $migration->up())->toThrow(RuntimeException::class)
        ->and(Schema::hasColumn('legacy_permissions', 'name_scope_key'))->toBeFalse()
        ->and(Schema::hasColumn('legacy_permissions', 'slug_scope_key'))->toBeFalse();
});

it('completes identity indexes when the columns already exist', function () {
    $installed = (string) config('role-permission-manager.permissions_table');
    // SQLite index names are database-wide, so the installed table cannot keep these names.
    Schema::table($installed, function (Blueprint $table): void {
        $table->dropUnique('lprm_perm_name_scope_key_uq');
        $table->dropUnique('lprm_perm_slug_scope_key_uq');
    });
    Config::set('role-permission-manager.permissions_table', 'legacy_permissions');
    Schema::create('legacy_permissions', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('slug')->nullable();
        $table->string('scope')->nullable();
        $table->text('description')->nullable();
        $table->timestamps();
        $table->char('name_scope_key', 64)->nullable();
        $table->char('slug_scope_key', 64)->nullable();
    });
    DB::table('legacy_permissions')->insert([
        'name' => 'view reports',
        'slug' => 'view-reports',
        'scope' => null,
        'name_scope_key' => null,
        'slug_scope_key' => null,
    ]);
    $migration = include __DIR__ . '/../../database/migrations/add_permission_identity_keys.php';

    $migration->up();

    $columns = collect(Schema::getColumns('legacy_permissions'))->keyBy('name');
    expect($columns['name_scope_key']['nullable'])->toBeFalse()
        ->and($columns['slug_scope_key']['nullable'])->toBeFalse()
        ->and(Schema::hasIndex('legacy_permissions', 'lprm_perm_name_scope_key_uq'))->toBeTrue()
        ->and(Schema::hasIndex('legacy_permissions', 'lprm_perm_slug_scope_key_uq'))->toBeTrue()
        ->and(DB::table('legacy_permissions')->whereNull('name_scope_key')->doesntExist())->toBeTrue();
});

it('reports read-only preflight blockers without changing rows', function () {
    $roleA = Role::factory()->create(['name' => 'shadow-slug', 'slug' => 'role-a']);
    $roleB = Role::factory()->create(['name' => 'Role B', 'slug' => 'shadow-slug']);
    $before = Role::query()->count();

    $this->artisan('role-permission:preflight')
        ->assertFailed()
        ->expectsOutputToContain('role-cross-field-collision');

    expect(Role::query()->count())->toBe($before)
        ->and($roleA->fresh()->name)->toBe('shadow-slug')
        ->and($roleB->fresh()->slug)->toBe('shadow-slug');
});
