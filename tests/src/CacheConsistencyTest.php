<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\Cache\AuthorizationCache;
use CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManager;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use CreativeCrafts\LaravelRolePermissionManager\Tests\Models\TestUser;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('performs zero cache store IO when caching is disabled', function () {
    Config::set('role-permission-manager.use_cache', false);
    $factory = Mockery::mock(CacheFactory::class);
    $factory->shouldNotReceive('store');
    $cache = new AuthorizationCache($factory);

    expect($cache->remember('probe', ['id' => 1], static fn (): string => 'fresh'))->toBe('fresh');
    $cache->invalidate();
});

it('honors the configured store and converts ttl minutes to an absolute expiration', function () {
    Config::set('role-permission-manager.use_cache', true);
    Config::set('role-permission-manager.cache_store', 'custom');
    Config::set('role-permission-manager.cache_expiration_time', 60);
    Carbon::setTestNow('2026-10-16 12:00:00');

    $repository = Mockery::mock(Repository::class);
    $repository->shouldReceive('add')->once()->andReturn(false);
    $repository->shouldReceive('get')->once()->andReturn(7);
    $repository->shouldReceive('remember')
        ->once()
        ->with(
            Mockery::type('string'),
            Mockery::on(static fn ($expires): bool => $expires->equalTo(now()->addMinutes(60))),
            Mockery::type(Closure::class),
        )
        ->andReturnUsing(static fn (string $key, $expires, Closure $loader): mixed => $loader());
    $factory = Mockery::mock(CacheFactory::class);
    $factory->shouldReceive('store')->with('custom')->twice()->andReturn($repository);

    expect((new AuthorizationCache($factory))->remember(
        'probe',
        ['id' => 1],
        static fn (): string => 'cached',
    ))->toBe('cached');

    Carbon::setTestNow();
});

it('separates cache identities by user model and guard', function () {
    $cache = app(AuthorizationCache::class);
    $primary = createTestUser();
    $alternate = new class () extends TestUser {
    };
    $alternate->setRawAttributes(['id' => $primary->id, 'name' => 'alternate'], true);
    $alternate->exists = true;

    $primaryWeb = $cache->principalIdentity($primary, 'web');
    $alternateWeb = $cache->principalIdentity($alternate, 'web');
    $primaryApi = $cache->principalIdentity($primary, 'api');

    expect($cache->remember('identity-probe', $primaryWeb, static fn (): string => 'primary'))->toBe('primary')
        ->and($cache->remember('identity-probe', $alternateWeb, static fn (): string => 'alternate'))->toBe('alternate')
        ->and($cache->remember('identity-probe', $primaryApi, static fn (): string => 'api'))->toBe('api')
        ->and($primaryWeb['database'])->toBe($cache->databaseIdentity($primary->getConnection()))
        ->and($alternateWeb['database'])->toBe($primaryWeb['database']);
});

it('does not reuse cached permission ids from another database connection', function () {
    $defaultPermission = Permission::factory()->create([
        'name' => 'Default side',
        'slug' => 'default-side',
        'scope' => null,
    ]);
    $defaultRole = Role::factory()->create(['name' => 'Default role', 'slug' => 'default-role']);
    $defaultUser = createTestUser();
    $defaultUser->permissions()->attach($defaultPermission);
    $defaultUser->roles()->attach($defaultRole);

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
        $tenantUser->setConnection('tenant_cache');
        $tenantUser->permissions()->attach($tenantPermission->getKey());
        $tenantUser->roles()->attach($tenantRole->getKey());
    } finally {
        config()->set('database.default', $originalDefault);
    }

    expect($defaultUser->getAllPermissions()->pluck('slug')->all())->toBe(['default-side'])
        ->and($tenantUser->getAllPermissions()->pluck('slug')->all())->toBe(['tenant-side'])
        ->and($tenantUser->getAllPermissions()->pluck('slug')->all())->toBe(['tenant-side'])
        ->and($defaultUser->getUserRoles()->pluck('slug')->all())->toBe(['default-role'])
        ->and($tenantUser->getUserRoles()->pluck('slug')->all())->toBe(['tenant-role'])
        ->and($tenantUser->getUserRoles()->pluck('slug')->all())->toBe(['tenant-role']);
});

it('does not cache an open transaction grant when another connection publishes invalidation', function () {
    config()->set('database.connections.side_cache', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    DB::purge('side_cache');

    $permission = Permission::factory()->create([
        'name' => 'Held grant',
        'slug' => 'held-grant',
        'scope' => null,
    ]);
    $user = createTestUser();

    DB::beginTransaction();
    try {
        $user->givePermissionTo($permission);
        $side = new Permission();
        $side->setConnection('side_cache');
        app(AuthorizationCache::class)->invalidate($side);

        expect($user->hasPermissionTo('held-grant'))->toBeTrue();
    } finally {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }

    expect($user->hasPermissionTo('held-grant'))->toBeFalse();
});

it('invalidates direct role and privileged revocation for loaded and independent models', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $permission = Permission::factory()->create([
        'name' => 'secure.action',
        'slug' => 'secure.action',
        'scope' => null,
    ]);
    $role = Role::factory()->create(['name' => 'Operator', 'slug' => 'operator']);
    $manager->givePermissionToRole($role, $permission);
    $user = createTestUser();
    $user->assignRole($role);
    $independent = TestUser::query()->findOrFail($user->id);
    $user->load('roles');

    expect($user->hasPermissionTo('secure.action'))->toBeTrue()
        ->and($independent->hasPermissionTo('secure.action'))->toBeTrue();

    $user->removeRole($role);

    expect($user->hasPermissionTo('secure.action'))->toBeFalse()
        ->and($independent->hasPermissionTo('secure.action'))->toBeFalse();

    $super = Role::factory()->create(['name' => 'Super Admin', 'slug' => 'super-admin']);
    $user->assignRole($super);
    expect($user->hasPermissionTo('anything'))->toBeTrue();
    $user->removeRole($super);
    expect($user->hasPermissionTo('anything'))->toBeFalse();
});

it('invalidates users when a retained role loses a permission or parent', function () {
    $manager = app(LaravelRolePermissionManager::class);
    $permission = Permission::factory()->create([
        'name' => 'inherited.action',
        'slug' => 'inherited.action',
        'scope' => null,
    ]);
    $parent = Role::factory()->create(['name' => 'Parent', 'slug' => 'parent']);
    $child = Role::factory()->create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->id]);
    $manager->givePermissionToRole($parent, $permission);
    $user = createTestUser();
    $user->assignRole($child);

    expect($user->hasPermissionTo('inherited.action'))->toBeTrue();

    $manager->revokePermissionFromRole($parent, $permission);
    expect($user->hasPermissionTo('inherited.action'))->toBeFalse();

    $manager->givePermissionToRole($parent, $permission);
    expect($user->hasPermissionTo('inherited.action'))->toBeTrue();
    $manager->setRoleParent($child, null);
    expect($user->hasPermissionTo('inherited.action'))->toBeFalse();

    $manager->setRoleParent($child, $parent);
    expect($user->hasPermissionTo('inherited.action'))->toBeTrue();
    $child->updateQuietly(['parent_id' => null]);
    expect($user->hasPermissionTo('inherited.action'))->toBeFalse();
});

it('does not reuse a cached grant when a committed generation publish fails', function () {
    Config::set('role-permission-manager.use_cache', true);
    Config::set('role-permission-manager.cache_expiration_time', 60);
    $repository = new class (Cache::store('array')->getStore()) extends CacheRepository {
        public int $remainingFailures = 2;

        public function increment($key, $value = 1)
        {
            if ($this->remainingFailures > 0) {
                $this->remainingFailures--;

                throw new RuntimeException('redis down');
            }

            return parent::increment($key, $value);
        }
    };
    $factory = Mockery::mock(CacheFactory::class);
    $factory->shouldReceive('store')->andReturn($repository);
    $cache = new AuthorizationCache($factory);
    $role = Role::factory()->create();

    expect($cache->remember('grant', ['id' => $role->getKey()], static fn (): string => 'stale'))->toBe('stale');

    expect(fn () => $role->getConnection()->transaction(function () use ($cache, $role): void {
        $cache->invalidate($role);
    }))->toThrow(RuntimeException::class);

    expect($cache->remember('grant', ['id' => $role->getKey()], static fn (): string => 'fresh'))->toBe('fresh')
        ->and($cache->remember('grant', ['id' => $role->getKey()], static fn (): string => 'published'))->toBe('published')
        ->and($cache->remember('grant', ['id' => $role->getKey()], static fn (): string => 'stale-again'))->toBe('published');
});

it('does not publish rolled-back revocation state', function () {
    $cache = app(AuthorizationCache::class);
    $user = createTestUser();
    $permission = Permission::factory()->create([
        'name' => 'rollback.action',
        'slug' => 'rollback.action',
        'scope' => null,
    ]);
    $user->givePermissionTo($permission);
    expect($user->hasPermissionTo('rollback.action'))->toBeTrue();
    $generation = $cache->currentGeneration();

    try {
        DB::transaction(function () use ($user, $permission): void {
            $user->revokePermissionTo($permission);
            throw new RuntimeException('force rollback');
        });
    } catch (RuntimeException) {
    }

    $user->unsetRelation('permissions');

    expect($cache->currentGeneration())->toBe($generation)
        ->and($user->hasPermissionTo('rollback.action'))->toBeTrue();

    $pendingInvalidations = (new ReflectionProperty(AuthorizationCache::class, 'pendingInvalidations'))
        ->getValue($cache);

    expect($pendingInvalidations)->toBe([]);
});

it('does not replace an existing cache generation with the initial value', function () {
    Config::set('role-permission-manager.use_cache', true);
    Config::set('role-permission-manager.cache_expiration_time', 60);
    Cache::forever('creativecrafts:lprm:v2:generation', 4);

    expect(app(AuthorizationCache::class)->currentGeneration())->toBe(4);
});

it('ignores legacy cache values during authorization', function () {
    $user = createTestUser();
    Cache::store('array')->forever("user_permissions_{$user->id}", collect([
        new Permission(['name' => 'legacy.admin', 'slug' => 'legacy.admin']),
    ]));

    expect($user->hasPermissionTo('legacy.admin'))->toBeFalse();
});
