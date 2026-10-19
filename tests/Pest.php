<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\Tests\Models\TestUser;
use CreativeCrafts\LaravelRolePermissionManager\Tests\TestCase;
use Illuminate\Support\Facades\Config;

if (! function_exists('covers')) {
    function covers(string ...$classesOrFunctions): void
    {
    }
}

uses(TestCase::class)->in(__DIR__);

function setUpTableSchema(): void
{
    Config::set('role-permission-manager.user_model', TestUser::class);
}

function createTestUser(): TestUser
{
    return TestUser::factory()->create();
}
