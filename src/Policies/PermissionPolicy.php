<?php

namespace CreativeCrafts\LaravelRolePermissionManager\Policies;

use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Traits\HasRolesAndPermissions;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;

class PermissionPolicy
{
    use HandlesAuthorization;

    /**
     * @param Authenticatable|HasRolesAndPermissions $user
     */
    public function viewAny($user): bool
    {
        return $user->hasPermissionTo('view permissions');
    }

    /**
     * @param Authenticatable|HasRolesAndPermissions $user
     */
    public function view($user, Permission $permission): bool
    {
        return $user->hasPermissionTo('view permissions');
    }

    /**
     * @param Authenticatable|HasRolesAndPermissions $user
     */
    public function create($user): bool
    {
        return $user->hasPermissionTo('create permissions');
    }

    /**
     * @param Authenticatable|HasRolesAndPermissions $user
     */
    public function update($user, Permission $permission): bool
    {
        return $user->hasPermissionTo('edit permissions');
    }

    /**
     * @param Authenticatable|HasRolesAndPermissions $user
     */
    public function delete($user, Permission $permission): bool
    {
        return $user->hasPermissionTo('delete permissions');
    }
}