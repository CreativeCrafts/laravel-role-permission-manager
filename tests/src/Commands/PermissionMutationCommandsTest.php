<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use CreativeCrafts\LaravelRolePermissionManager\Tests\Models\TestUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

it('synchronizes fully valid permission slugs', function () {
    $role = Role::factory()->create(['name' => 'Editor', 'slug' => 'editor']);
    $first = Permission::factory()->create(['name' => 'First', 'slug' => 'first', 'scope' => null]);
    $second = Permission::factory()->create(['name' => 'Second', 'slug' => 'second', 'scope' => null]);

    $this->artisan('role-permission:sync', [
        'role' => 'editor',
        'permissions' => ['first', 'second'],
    ])
        ->expectsConfirmation(
            "Are you sure you want to sync the following permissions for the role 'Editor'?\nFirst, Second",
            'yes',
        )
        ->assertSuccessful()
        ->expectsOutputToContain("Permissions synced for role 'Editor' successfully.");

    expect($role->fresh()->permissions->modelKeys())->toEqualCanonicalizing([$first->id, $second->id]);
});

it('fails a partially invalid sync list without mutation or success output', function () {
    $role = Role::factory()->create(['name' => 'Editor', 'slug' => 'editor']);
    $existing = Permission::factory()->create(['name' => 'Existing', 'slug' => 'existing', 'scope' => null]);
    $replacement = Permission::factory()->create([
        'name' => 'Replacement',
        'slug' => 'replacement',
        'scope' => null,
    ]);
    $role->permissions()->attach($existing);

    $this->artisan('role-permission:sync', [
        'role' => 'editor',
        'permissions' => [$replacement->slug, 'missing'],
    ])
        ->assertFailed()
        ->doesntExpectOutput("Permissions synced for role 'Editor' successfully.");

    expect($role->fresh()->permissions->modelKeys())->toBe([$existing->id]);
});

it('requires exact scope for an ambiguous slug and preserves scope zero', function () {
    $role = Role::factory()->create(['name' => 'Editor', 'slug' => 'editor']);
    Permission::factory()->create(['name' => 'Shared null', 'slug' => 'shared', 'scope' => null]);
    $zero = Permission::factory()->create(['name' => 'Shared zero', 'slug' => 'shared', 'scope' => '0']);

    $this->artisan('role-permission:sync', [
        'role' => 'editor',
        'permissions' => ['shared'],
    ])->assertFailed();

    $this->artisan('role-permission:sync', [
        'role' => 'editor',
        'permissions' => ['shared'],
        '--scope' => '0',
    ])
        ->expectsConfirmation(
            "Are you sure you want to sync the following permissions for the role 'Editor'?\nShared zero",
            'yes',
        )
        ->assertSuccessful();

    expect($role->fresh()->permissions->modelKeys())->toBe([$zero->id]);
});

it('requires explicit clear and respects cancellation', function () {
    $role = Role::factory()->create(['name' => 'Editor', 'slug' => 'editor']);
    $permission = Permission::factory()->create(['name' => 'Existing', 'slug' => 'existing', 'scope' => null]);
    $role->permissions()->attach($permission);

    $this->artisan('role-permission:sync', [
        'role' => 'editor',
        'permissions' => [],
    ])->assertFailed();

    $this->artisan('role-permission:sync', [
        'role' => 'editor',
        'permissions' => [],
        '--clear' => true,
    ])
        ->expectsConfirmation(
            "Are you sure you want to remove all permissions from the role 'Editor'?",
            'no',
        )
        ->assertFailed();
    expect($role->fresh()->permissions->modelKeys())->toBe([$permission->id]);

    $this->artisan('role-permission:sync', [
        'role' => 'editor',
        'permissions' => [],
        '--clear' => true,
    ])
        ->expectsConfirmation(
            "Are you sure you want to remove all permissions from the role 'Editor'?",
            'yes',
        )
        ->assertSuccessful();
    expect($role->fresh()->permissions)->toBeEmpty();
});

it('rejects an omitted scope when the permission slug is ambiguous', function () {
    $role = Role::factory()->create(['name' => 'Editor', 'slug' => 'editor']);
    Permission::factory()->create(['name' => 'Shared null', 'slug' => 'shared', 'scope' => null]);
    Permission::factory()->create(['name' => 'Shared zero', 'slug' => 'shared', 'scope' => '0']);

    $this->artisan('role-permission:assign', [
        'role' => 'editor',
        'permission' => 'shared',
    ])->assertFailed();

    expect($role->fresh()->permissions)->toBeEmpty();
});

it('assigns and removes an exact scoped permission', function () {
    $role = Role::factory()->create(['name' => 'Editor', 'slug' => 'editor']);
    Permission::factory()->create(['name' => 'Shared null', 'slug' => 'shared', 'scope' => null]);
    $zero = Permission::factory()->create(['name' => 'Shared zero', 'slug' => 'shared', 'scope' => '0']);

    $this->artisan('role-permission:assign', [
        'role' => 'editor',
        'permission' => 'shared',
        '--scope' => '0',
    ])->assertSuccessful();
    expect($role->fresh()->permissions->modelKeys())->toBe([$zero->id]);

    $this->artisan('role-permission:remove', [
        'role' => 'editor',
        'permissions' => ['shared'],
        '--scope' => '0',
    ])
        ->expectsConfirmation(
            "Are you sure you want to remove the following permissions from the role 'Editor'?\nShared zero",
            'yes',
        )
        ->assertSuccessful();
    expect($role->fresh()->permissions)->toBeEmpty();
});

it('supports ID mode and rejects invalid IDs', function () {
    $role = Role::factory()->create(['name' => 'Editor', 'slug' => 'editor']);
    $permission = Permission::factory()->create(['name' => 'Numeric', 'slug' => '123', 'scope' => null]);

    $this->artisan('role-permission:sync', [
        'role' => (string) $role->id,
        'permissions' => [(string) $permission->id],
        '--use-ids' => true,
    ])
        ->expectsConfirmation(
            "Are you sure you want to sync the following permissions for the role 'Editor'?\nNumeric",
            'yes',
        )
        ->assertSuccessful();

    $this->artisan('role-permission:sync', [
        'role' => (string) $role->id,
        'permissions' => ['not-an-id'],
        '--use-ids' => true,
    ])->assertFailed();
});

it('assigns a role by numeric id, email, or string primary key', function () {
    $role = Role::factory()->create(['name' => 'Editor', 'slug' => 'editor']);
    $byId = createTestUser();
    $byId->forceFill(['email' => 'ada@example.com', 'name' => 'Ada'])->save();
    $byEmail = createTestUser();
    $byEmail->forceFill(['email' => 'grace@example.com', 'name' => 'Grace'])->save();

    $this->artisan('role-permission:assign-role', [
        'user' => (string) $byId->id,
        'role' => 'editor',
    ])->assertSuccessful();
    $this->artisan('role-permission:assign-role', [
        'user' => 'grace@example.com',
        'role' => 'editor',
    ])->assertSuccessful();

    expect($byId->fresh()->roles->modelKeys())->toBe([$role->id])
        ->and($byEmail->fresh()->roles->modelKeys())->toBe([$role->id]);

    Schema::create('string_key_users', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('name');
        $table->string('email')->nullable();
    });
    $stringUser = new class () extends TestUser {
        public $incrementing = false;
        protected $table = 'string_key_users';

        protected $keyType = 'string';
    };
    Config::set('role-permission-manager.user_model', $stringUser::class);
    $stringUser->forceFill([
        'id' => 'string-key@example.com',
        'name' => 'String Key',
        'email' => 'different@example.com',
    ])->save();

    Schema::disableForeignKeyConstraints();
    $this->artisan('role-permission:assign-role', [
        'user' => 'string-key@example.com',
        'role' => 'editor',
    ])->assertSuccessful();
    Schema::enableForeignKeyConstraints();

    expect($stringUser->fresh()->roles->modelKeys())->toBe([$role->id]);
});
