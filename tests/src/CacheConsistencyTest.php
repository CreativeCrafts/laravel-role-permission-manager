<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\Cache\AuthorizationCache;
use CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManager;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use CreativeCrafts\LaravelRolePermissionManager\Tests\Models\TestUser;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

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
        ->and($cache->remember('identity-probe', $primaryApi, static fn (): string => 'api'))->toBe('api');
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
    $pendingInvalidations = (new ReflectionProperty(AuthorizationCache::class, 'pendingInvalidations'))
        ->getValue($cache);

    expect($cache->currentGeneration())->toBe($generation)
        ->and($pendingInvalidations)->toBe(0)
        ->and($user->hasPermissionTo('rollback.action'))->toBeTrue();
});

it('ignores legacy cache values during authorization', function () {
    $user = createTestUser();
    Cache::store('array')->forever("user_permissions_{$user->id}", collect([
        new Permission(['name' => 'legacy.admin', 'slug' => 'legacy.admin']),
    ]));

    expect($user->hasPermissionTo('legacy.admin'))->toBeFalse();
});
