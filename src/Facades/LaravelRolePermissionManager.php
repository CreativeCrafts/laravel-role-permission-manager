<?php

namespace CreativeCrafts\LaravelRolePermissionManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManager
 */
class LaravelRolePermissionManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManager::class;
    }
}
