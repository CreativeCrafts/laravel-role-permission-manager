<?php

namespace CreativeCrafts\LaravelRolePermissionManager\Policies;

use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use CreativeCrafts\LaravelRolePermissionManager\Traits\HasRolesAndPermissions;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;

class RolePolicy
{
    use HandlesAuthorization;

    /**
     * @param  Authenticatable|HasRolesAndPermissions  $user
     */
    public function viewAny($user): bool
    {
        return $user->hasPermissionTo('view roles');
    }

    /**
     * @param  Authenticatable|HasRolesAndPermissions  $user
     */
    public function view($user, Role $role): bool
    {
        return $user->hasPermissionTo('view roles');
    }

    /**
     * @param  Authenticatable|HasRolesAndPermissions  $user
     */
    public function create($user): bool
    {
        return $user->hasPermissionTo('create roles');
    }

    /**
     * @param  Authenticatable|HasRolesAndPermissions  $user
     */
    public function update($user, Role $role): bool
    {
        return $user->hasPermissionTo('edit roles');
    }

    /**
     * @param  Authenticatable|HasRolesAndPermissions  $user
     */
    public function delete($user, Role $role): bool
    {
        return $user->hasPermissionTo('delete roles');
    }
}
