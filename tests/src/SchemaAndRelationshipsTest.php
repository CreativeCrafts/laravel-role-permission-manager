<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\Cache\AuthorizationCache;
use CreativeCrafts\LaravelRolePermissionManager\Exceptions\UnableToCreatePermissionException;
use CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManager;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use CreativeCrafts\LaravelRolePermissionManager\Permissions\PermissionIdentity;
use Illuminate\Database\QueryException;
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
        ->and($columns['name_scope_key']['default'])->toBe(DB::getDriverName() === 'sqlite' ? "''" : null)
        ->and($columns['slug_scope_key']['default'])->toBe(DB::getDriverName() === 'sqlite' ? "''" : null)
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
        $table->string('name_scope_key', PermissionIdentity::LENGTH)->nullable();
        $table->string('slug_scope_key', PermissionIdentity::LENGTH)->nullable();
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
        ->and(DB::table('legacy_permissions')->whereNull('name_scope_key')->doesntExist())->toBeTrue()
        ->and(DB::table('legacy_permissions')->value('name_scope_key'))
        ->toBe(PermissionIdentity::key('name', 'view reports', null));
});

it('keeps distinct permission identities separate and reports only cyclic role ids', function () {
    Permission::factory()->create([
        'name' => 'x|scope:string:y',
        'slug' => 'encoded-left',
        'scope' => 'z',
    ]);
    Permission::factory()->create([
        'name' => 'x',
        'slug' => 'encoded-right',
        'scope' => 'y|scope:string:z',
    ]);

    $this->artisan('role-permission:preflight')->assertSuccessful();

    $prefix = Role::factory()->create(['name' => 'Prefix', 'slug' => 'prefix']);
    $cycleStart = Role::factory()->create([
        'name' => 'Cycle start',
        'slug' => 'cycle-start',
        'parent_id' => $prefix->id,
    ]);
    $cycleEnd = Role::factory()->create([
        'name' => 'Cycle end',
        'slug' => 'cycle-end',
        'parent_id' => $cycleStart->id,
    ]);
    DB::table((string) config('role-permission-manager.roles_table'))
        ->where('id', $cycleStart->id)
        ->update(['parent_id' => $cycleEnd->id]);
    $cycleIds = [$cycleStart->id, $cycleEnd->id];
    sort($cycleIds);

    $this->artisan('role-permission:preflight')
        ->assertFailed()
        ->expectsOutputToContain('[role-hierarchy-cycle] cycle (ids: ' . implode(',', $cycleIds) . ')')
        ->doesntExpectOutputToContain('permission-duplicate')
        ->doesntExpectOutputToContain((string) $prefix->id);
});

it('matches user pivot columns to a string user primary key', function () {
    Schema::create('uuid_users', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
    });
    Config::set([
        'role-permission-manager.users_table' => 'uuid_users',
        'role-permission-manager.user_model_key' => 'id',
        'role-permission-manager.roles_table' => 'uuid_roles',
        'role-permission-manager.permissions_table' => 'uuid_permissions',
        'role-permission-manager.user_role_table' => 'uuid_user_roles',
        'role-permission-manager.role_permission_table' => 'uuid_role_permissions',
        'role-permission-manager.user_permission_table' => 'uuid_user_permissions',
    ]);
    $migration = include __DIR__ . '/../../database/migrations/create_role_permission_manager_tables.php';

    $migration->up();

    $userKey = collect(Schema::getColumns('uuid_users'))->firstWhere('name', 'id');
    $rolePivot = collect(Schema::getColumns('uuid_user_roles'))->firstWhere('name', 'user_id');
    $permissionPivot = collect(Schema::getColumns('uuid_user_permissions'))->firstWhere('name', 'user_id');

    expect($rolePivot['type_name'])->toBe($userKey['type_name'])
        ->and($permissionPivot['type_name'])->toBe($userKey['type_name']);
});

it('matches user pivot columns to an unsigned integer user primary key', function () {
    Schema::create('increment_users', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
    });
    Config::set([
        'role-permission-manager.users_table' => 'increment_users',
        'role-permission-manager.user_model_key' => 'id',
        'role-permission-manager.roles_table' => 'increment_roles',
        'role-permission-manager.permissions_table' => 'increment_permissions',
        'role-permission-manager.user_role_table' => 'increment_user_roles',
        'role-permission-manager.role_permission_table' => 'increment_role_permissions',
        'role-permission-manager.user_permission_table' => 'increment_user_permissions',
    ]);
    $migration = include __DIR__ . '/../../database/migrations/create_role_permission_manager_tables.php';

    $migration->up();

    $userKey = collect(Schema::getColumns('increment_users'))->firstWhere('name', 'id');
    $rolePivot = collect(Schema::getColumns('increment_user_roles'))->firstWhere('name', 'user_id');

    expect($rolePivot['type_name'])->toBe($userKey['type_name']);
});

it('derives identity keys when a query-builder insert omits them', function () {
    $table = (string) config('role-permission-manager.permissions_table');

    DB::table($table)->insert([
        'name' => 'raw insert',
        'slug' => 'raw-insert',
        'scope' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $row = DB::table($table)->where('slug', 'raw-insert')->first();

    expect($row->name_scope_key)->toBe(PermissionIdentity::key('name', 'raw insert', null))
        ->and($row->slug_scope_key)->toBe(PermissionIdentity::key('slug', 'raw-insert', null));
});

it('applies sql expressions in bulk permission updates', function () {
    $permission = Permission::factory()->create([
        'name' => 'Lower me',
        'slug' => 'Keep-Case',
        'scope' => 'team',
    ]);

    Permission::query()->whereKey($permission->getKey())->update([
        'slug' => DB::raw('lower(slug)'),
    ]);
    $fresh = $permission->fresh();

    expect($fresh->slug)->toBe('keep-case')
        ->and($fresh->slug_scope_key)->toBe(PermissionIdentity::key('slug', 'keep-case', 'team'))
        ->and($fresh->name_scope_key)->toBe(PermissionIdentity::key('name', 'Lower me', 'team'));
});

it('preserves null and empty slugs in bulk updates while model saves still generate one', function () {
    $permission = Permission::factory()->create([
        'name' => 'Keep Me',
        'slug' => 'keep-me',
        'scope' => 'team',
    ]);

    Permission::query()->whereKey($permission->getKey())->update(['slug' => null]);
    $cleared = $permission->fresh();

    expect($cleared->getRawOriginal('slug'))->toBeNull()
        ->and($cleared->slug_scope_key)->toBe(PermissionIdentity::key('slug', null, 'team'));

    Permission::query()->whereKey($permission->getKey())->update(['slug' => '']);
    $empty = $permission->fresh();

    expect($empty->getRawOriginal('slug'))->toBe('')
        ->and($empty->slug_scope_key)->toBe(PermissionIdentity::key('slug', '', 'team'));

    $empty->slug = null;
    $empty->save();

    expect($empty->fresh()->getRawOriginal('slug'))->toBe('keep-me');
});

it('publishes bulk identity invalidation only after the surrounding transaction commits', function () {
    $cache = app(AuthorizationCache::class);
    $permission = Permission::factory()->create([
        'name' => 'Committed',
        'slug' => 'committed',
        'scope' => 'team',
    ]);
    $generation = $cache->currentGeneration();

    try {
        DB::transaction(function () use ($permission): void {
            Permission::query()->whereKey($permission->getKey())->update(['slug' => 'rolled-back']);
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    expect($cache->currentGeneration())->toBe($generation)
        ->and($permission->fresh()->slug)->toBe('committed');

    DB::transaction(function () use ($permission): void {
        Permission::query()->whereKey($permission->getKey())->update(['slug' => 'committed-slug']);
    });

    expect($cache->currentGeneration())->toBeGreaterThan($generation)
        ->and($permission->fresh()->slug)->toBe('committed-slug');
});

it('keeps non-fillable columns when a bulk identity update is applied', function () {
    $permission = Permission::factory()->create([
        'name' => 'Original',
        'slug' => 'original-slug',
        'scope' => 'team',
    ]);
    $createdAt = $permission->created_at->copy()->subDay();

    Permission::query()->whereKey($permission->getKey())->update([
        'slug' => 'renamed-slug',
        'created_at' => $createdAt,
    ]);
    $fresh = $permission->fresh();

    expect($fresh->slug)->toBe('renamed-slug')
        ->and($fresh->created_at?->toDateTimeString())->toBe($createdAt->toDateTimeString())
        ->and($fresh->slug_scope_key)->toBe(PermissionIdentity::key('slug', 'renamed-slug', 'team'))
        ->and($fresh->name_scope_key)->toBe(PermissionIdentity::key('name', 'Original', 'team'));
});

it('recomputes identity keys for query-builder updates that bypass the model', function () {
    $permission = Permission::factory()->create([
        'name' => 'a😀b',
        'slug' => 'emoji',
        'scope' => null,
    ]);

    expect($permission->fresh()->name_scope_key)->toBe(PermissionIdentity::key('name', 'a😀b', null))
        ->and($permission->fresh()->slug_scope_key)->toBe(PermissionIdentity::key('slug', 'emoji', null));

    DB::table($permission->getTable())->where('id', $permission->getKey())->update([
        'name' => 'renamed',
        'scope' => '0',
        'name_scope_key' => 'stale',
        'slug_scope_key' => 'stale',
    ]);
    $fresh = $permission->fresh();

    expect($fresh->name_scope_key)->toBe(PermissionIdentity::key('name', 'renamed', '0'))
        ->and($fresh->slug_scope_key)->toBe(PermissionIdentity::key('slug', 'emoji', '0'));
});

it('rolls back a bulk identity update when a later row duplicates another', function () {
    $first = Permission::factory()->create(['name' => 'alpha', 'slug' => 'alpha', 'scope' => 'team']);
    $second = Permission::factory()->create(['name' => 'beta', 'slug' => 'beta', 'scope' => 'team']);

    expect(fn () => Permission::query()->whereKey([$first->getKey(), $second->getKey()])->update([
        'name' => 'same',
        'slug' => 'same',
        'scope' => 'team',
    ]))->toThrow(QueryException::class);

    expect($first->fresh()->name)->toBe('alpha')
        ->and($second->fresh()->slug)->toBe('beta');
});

it('maps postgres integer widths onto matching foreign-key columns', function () {
    $migration = include __DIR__ . '/../../database/migrations/create_role_permission_manager_tables.php';
    $method = new ReflectionMethod($migration, 'integerColumnMethod');

    expect($method->invoke($migration, 'int2', false))->toBe('smallInteger')
        ->and($method->invoke($migration, 'int4', false))->toBe('integer')
        ->and($method->invoke($migration, 'int8', false))->toBe('bigInteger')
        ->and($method->invoke($migration, 'int', true))->toBe('unsignedInteger')
        ->and($method->invoke($migration, 'bigint', false))->toBe('bigInteger');
});

it('keeps a postgres int4 user key from widening to bigint', function () {
    if (Schema::getConnection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL reports integer primary keys as int4.');
    }

    DB::statement('create table int4_users (id integer primary key, name varchar(255) not null)');
    DB::statement('create table int2_users (id smallint primary key, name varchar(255) not null)');
    Config::set([
        'role-permission-manager.users_table' => 'int4_users',
        'role-permission-manager.user_model_key' => 'id',
        'role-permission-manager.roles_table' => 'int4_roles',
        'role-permission-manager.permissions_table' => 'int4_permissions',
        'role-permission-manager.user_role_table' => 'int4_user_roles',
        'role-permission-manager.role_permission_table' => 'int4_role_permissions',
        'role-permission-manager.user_permission_table' => 'int4_user_permissions',
    ]);
    $migration = include __DIR__ . '/../../database/migrations/create_role_permission_manager_tables.php';

    $migration->up();

    $rolePivot = collect(Schema::getColumns('int4_user_roles'))->firstWhere('name', 'user_id');
    expect($rolePivot['type_name'])->toBe('int4');

    Config::set([
        'role-permission-manager.users_table' => 'int2_users',
        'role-permission-manager.roles_table' => 'int2_roles',
        'role-permission-manager.permissions_table' => 'int2_permissions',
        'role-permission-manager.user_role_table' => 'int2_user_roles',
        'role-permission-manager.role_permission_table' => 'int2_role_permissions',
        'role-permission-manager.user_permission_table' => 'int2_user_permissions',
    ]);
    $second = include __DIR__ . '/../../database/migrations/create_role_permission_manager_tables.php';
    $second->up();
    $smallPivot = collect(Schema::getColumns('int2_user_roles'))->firstWhere('name', 'user_id');

    expect($smallPivot['type_name'])->toBe('int2');
})->skip(
    fn (): bool => getenv('LRPM_TEST_DB_CONNECTION') !== 'pgsql',
    'PostgreSQL reports integer primary keys as int4.',
);

it('recomputes identity keys when permissions are bulk updated', function () {
    $permission = Permission::factory()->create([
        'name' => 'old name',
        'slug' => 'old-name',
        'scope' => null,
    ]);
    $originalNameKey = $permission->name_scope_key;
    $originalSlugKey = $permission->slug_scope_key;

    Permission::query()->whereKey($permission->getKey())->update([
        'name' => 'new name',
        'slug' => 'new-name',
        'scope' => 'team',
    ]);
    $fresh = $permission->fresh();

    expect($fresh->name)->toBe('new name')
        ->and($fresh->slug)->toBe('new-name')
        ->and($fresh->scope)->toBe('team')
        ->and($fresh->name_scope_key)->not->toBe($originalNameKey)
        ->and($fresh->slug_scope_key)->not->toBe($originalSlugKey);
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
