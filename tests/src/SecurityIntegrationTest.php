<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\Middleware\RoleMiddleware;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use CreativeCrafts\LaravelRolePermissionManager\Tests\Models\TestTeam;
use CreativeCrafts\LaravelRolePermissionManager\Tests\Policies\TestTeamPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::policy(TestTeam::class, TestTeamPolicy::class);
});

it('abstains from unrelated Gate abilities so resource policies retain authority', function () {
    $owner = createTestUser();
    $nonOwner = createTestUser();
    $team = new TestTeam((int) $owner->getAuthIdentifier());
    $update = Permission::factory()->create(['name' => 'update', 'slug' => 'update', 'scope' => null]);
    $nonOwner->givePermissionTo($update);

    expect(Gate::forUser($owner)->allows('update', $team))->toBeTrue()
        ->and(Gate::forUser($nonOwner)->allows('update', $team))->toBeFalse();
});

it('handles only explicitly prefixed package Gate abilities', function () {
    $user = createTestUser();
    $permission = Permission::factory()->create([
        'name' => 'reports.view',
        'slug' => 'reports.view',
        'scope' => null,
    ]);
    $user->givePermissionTo($permission);
    Gate::define('host-grant', static fn (): bool => true);

    expect(Gate::forUser($user)->allows('role-permission:reports.view'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('role-permission:reports.delete'))->toBeFalse()
        ->and(Gate::forUser($user)->allows('host-grant'))->toBeTrue();
});

it('uses one exact canonical privileged role identity', function () {
    Config::set('role-permission-manager.super_admin_role', 'Super Admin');
    $canonical = Role::factory()->create(['name' => 'Super Admin', 'slug' => 'super-admin']);
    $shadowName = Role::factory()->create(['name' => 'super-admin', 'slug' => 'shadow-name']);
    $shadowSlug = Role::factory()->create(['name' => 'Ordinary', 'slug' => 'Super Admin']);

    $canonicalUser = createTestUser();
    $canonicalUser->assignRole($canonical);
    $shadowNameUser = createTestUser();
    $shadowNameUser->assignRole($shadowName);
    $shadowSlugUser = createTestUser();
    $shadowSlugUser->assignRole($shadowSlug);

    expect($canonical->isSuperAdmin())->toBeTrue()
        ->and($canonicalUser->hasSuperAdminRole())->toBeTrue()
        ->and($shadowName->isSuperAdmin())->toBeFalse()
        ->and($shadowNameUser->hasSuperAdminRole())->toBeFalse()
        ->and($shadowSlug->isSuperAdmin())->toBeFalse()
        ->and($shadowSlugUser->hasSuperAdminRole())->toBeFalse();
});

it('uses exact slugs in role middleware so a shadow name cannot impersonate a route role', function () {
    $shadow = Role::factory()->create(['name' => 'super-admin', 'slug' => 'ordinary']);
    $user = createTestUser();
    $user->assignRole($shadow);
    $request = Request::create('/protected');
    $request->headers->set('Accept', 'application/json');
    $request->setUserResolver(static fn () => $user);
    $this->actingAs($user);

    $response = (new RoleMiddleware())->handle(
        $request,
        static fn () => response('allowed'),
        'super-admin',
    );

    expect($response->getStatusCode())->toBe(403);
});

it('revokes canonical privilege even when roles were previously loaded', function () {
    $role = Role::factory()->create(['name' => 'Super Admin', 'slug' => 'super-admin']);
    $user = createTestUser();
    $user->assignRole($role);
    $user->load('roles');

    expect($user->hasPermissionTo('anything'))->toBeTrue();

    $user->removeRole($role);

    expect($user->relationLoaded('roles'))->toBeFalse()
        ->and($user->hasSuperAdminRole())->toBeFalse()
        ->and($user->hasPermissionTo('anything'))->toBeFalse();
});

it('enforces exact authorization scope including empty and zero', function () {
    $user = createTestUser();
    foreach ([null, '', '0', 'tenant-a'] as $index => $scope) {
        $permission = Permission::factory()->create([
            'name' => "jobs.edit.{$index}",
            'slug' => 'jobs.edit',
            'scope' => $scope,
        ]);
        $user->givePermissionTo($permission);
    }
    $scopedOnly = Permission::factory()->create([
        'name' => 'jobs.publish scoped',
        'slug' => 'jobs.publish',
        'scope' => '0',
    ]);
    $user->givePermissionTo($scopedOnly);

    expect($user->hasPermissionTo('jobs.edit'))->toBeTrue()
        ->and($user->hasPermissionTo('jobs.edit', ''))->toBeTrue()
        ->and($user->hasPermissionTo('jobs.edit', '0'))->toBeTrue()
        ->and($user->hasPermissionTo('jobs.edit', 'tenant-a'))->toBeTrue()
        ->and($user->hasPermissionTo('jobs.publish'))->toBeFalse()
        ->and($user->hasPermissionTo('jobs.publish', '0'))->toBeTrue();
});

it('anchors only asterisk wildcards and treats SQL wildcard characters literally', function () {
    $user = createTestUser();
    $wildcard = Permission::factory()->create([
        'name' => 'posts.*',
        'slug' => 'posts.*',
        'scope' => null,
    ]);
    $sqlLike = Permission::factory()->create([
        'name' => 'reports.%_daily',
        'slug' => 'reports.%_daily',
        'scope' => null,
    ]);
    $user->givePermissionTo($wildcard);
    $user->givePermissionTo($sqlLike);

    expect($user->hasPermissionTo('posts.update'))->toBeTrue()
        ->and($user->hasPermissionTo('other.posts.update'))->toBeFalse()
        ->and($user->hasPermissionTo('reports.X-daily'))->toBeFalse()
        ->and($user->hasPermissionTo('reports.%_daily'))->toBeTrue();
});

it('never creates a permission during authorization evaluation', function () {
    Config::set('role-permission-manager.auto_create_permissions', true);
    $user = createTestUser();
    $before = Permission::query()->count();

    expect($user->hasPermissionTo('missing.permission'))->toBeFalse()
        ->and(Permission::query()->count())->toBe($before);
});
