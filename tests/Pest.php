<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\Tests\Models\TestUser;
use CreativeCrafts\LaravelRolePermissionManager\Tests\TestCase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class, LazilyRefreshDatabase::class)->in(__DIR__);
pest()->project()->github('CreativeCrafts/laravel-role-permission-manager');

function setUpTableSchema(): void
{
    Config::set('role-permission-manager.user_role_table', 'user_roles');
    Config::set('role-permission-manager.user_permission_table', 'user_permissions');
    Config::set('role-permission-manager.permissions_table', 'permissions');

    // Create the users table
    if (!Schema::hasTable('users')) {
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
        });
    }

    // Create the roles table
    if (!Schema::hasTable('roles')) {
        Schema::create('roles', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
        });
    }

    // Create the permissions table
    if (!Schema::hasTable('permissions')) {
        Schema::create('permissions', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('scope')->nullable();
        });
    }

    // Create the user_roles pivot table
    if (!Schema::hasTable('user_roles')) {
        Schema::create('user_roles', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
        });
    }

    // Create the user_permissions pivot table
    if (!Schema::hasTable('user_permissions')) {
        Schema::create('user_permissions', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade');
        });
    }
}

function createTestUser(): TestUser
{
    return TestUser::factory()->create();
}
