<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Policies;

use CreativeCrafts\LaravelRolePermissionManager\Contracts\AuthenticatableWithRolesAndPermissions;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use Illuminate\Auth\Access\HandlesAuthorization;

class PermissionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any permissions.
     * This method checks if the user has the necessary permissions to view permissions.
     * If a specific permission name is provided, it also checks for that permission.
     *
     * @param AuthenticatableWithRolesAndPermissions $user The user to check permissions for
     * @param string|null $permissionName Optional. The specific permission name to check
     * @return bool Returns true if the user has permission to view permissions, false otherwise
     */
    public function viewAny(AuthenticatableWithRolesAndPermissions $user, string $permissionName = null): bool
    {
        if ($user->hasPermissionTo('view permissions')) {
            return true;
        }

        return $permissionName !== null && $user->hasPermissionTo($permissionName);
    }

    /**
     * Determine whether the user can view a specific permission.
     * This method checks if the user has the necessary permissions to view a specific permission.
     * It allows access if the user has either the general 'view permissions' permission or
     * the specific permission being viewed.
     *
     * @param AuthenticatableWithRolesAndPermissions $user The user to check permissions for
     * @param Permission $permission The specific permission object being viewed
     * @return bool Returns true if the user has permission to view the specific permission, false otherwise
     */
    public function view(AuthenticatableWithRolesAndPermissions $user, Permission $permission): bool
    {
        if ($user->hasPermissionTo('view permissions')) {
            return true;
        }
        return $user->hasPermissionTo($permission->name);
    }

    /**
     * Determine whether the user can create permissions.
     * This method checks if the user has the necessary permissions to create permissions.
     * If a specific permission name is provided, it also checks for that permission.
     *
     * @param AuthenticatableWithRolesAndPermissions $user The user to check permissions for
     * @param string|null $permissionName Optional. The specific permission name to check
     * @return bool Returns true if the user has permission to create permissions, false otherwise
     */
    public function create(AuthenticatableWithRolesAndPermissions $user, string $permissionName = null): bool
    {
        if ($user->hasPermissionTo('create permissions')) {
            return true;
        }

        return $permissionName !== null && $user->hasPermissionTo($permissionName);
    }

    /**
     * Determine whether the user can update a specific permission.
     * This method checks if the user has the necessary permissions to update a specific permission.
     * It allows access if the user has either the general 'edit permissions' permission or
     * the specific permission being updated.
     *
     * @param AuthenticatableWithRolesAndPermissions $user The user to check permissions for
     * @param Permission $permission The specific permission object being updated
     * @return bool Returns true if the user has permission to update the specific permission, false otherwise
     */
    public function update(AuthenticatableWithRolesAndPermissions $user, Permission $permission): bool
    {
        if ($user->hasPermissionTo('edit permissions')) {
            return true;
        }
        return $user->hasPermissionTo($permission->name);
    }

    /**
     * Determine whether the user can delete a specific permission.
     * This method checks if the user has the necessary permissions to delete a specific permission.
     * It allows access if the user has either the general 'delete permissions' permission or
     * the specific permission being deleted.
     *
     * @param AuthenticatableWithRolesAndPermissions $user The user to check permissions for
     * @param Permission $permission The specific permission object being deleted
     * @return bool Returns true if the user has permission to delete the specific permission, false otherwise
     */
    public function delete(AuthenticatableWithRolesAndPermissions $user, Permission $permission): bool
    {
        if ($user->hasPermissionTo('delete permissions')) {
            return true;
        }
        return $user->hasPermissionTo($permission->name);
    }
}
