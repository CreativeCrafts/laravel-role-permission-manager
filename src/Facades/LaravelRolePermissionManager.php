<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManager
 */
class LaravelRolePermissionManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-role-permission-manager';
    }
}
