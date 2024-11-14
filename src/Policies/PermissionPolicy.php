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
     * This method checks if the authenticated user has the permission to view permissions.
     *
     * @param AuthenticatableWithRolesAndPermissions $user
     *         The authenticated user instance.
     * @return bool
     *         Returns true if the user has permission to view permissions, false otherwise.
     */
    public function viewAny(AuthenticatableWithRolesAndPermissions $user): bool
    {
        return $user->hasPermissionTo('view permissions');
    }

    /**
     * Determine whether the user can view a specific permission.
     * This method checks if the authenticated user has the permission to view a specific permission.
     *
     * @param AuthenticatableWithRolesAndPermissions $user
     *        The authenticated user instance.
     * @param Permission $permission
     *        The specific permission instance to be viewed.
     * @return bool
     *         Returns true if the user has permission to view the specific permission, false otherwise.
     */
    public function view(AuthenticatableWithRolesAndPermissions $user, Permission $permission): bool
    {
        return $user->hasPermissionTo('view permissions');
    }

    /**
     * Determine whether the user can create permissions.
     * This method checks if the authenticated user has the permission to create new permissions.
     *
     * @param AuthenticatableWithRolesAndPermissions $user
     *        The authenticated user instance.
     * @return bool
     *         Returns true if the user has permission to create permissions, false otherwise.
     */
    public function create(AuthenticatableWithRolesAndPermissions $user): bool
    {
        return $user->hasPermissionTo('create permissions');
    }

    /**
     * Determine whether the user can update a specific permission.
     * This method checks if the authenticated user has the permission to edit permissions.
     *
     * @param AuthenticatableWithRolesAndPermissions $user
     *        The authenticated user instance.
     * @param Permission $permission
     *        The specific permission instance to be updated.
     * @return bool
     *         Returns true if the user has permission to edit permissions, false otherwise.
     */
    public function update(AuthenticatableWithRolesAndPermissions $user, Permission $permission): bool
    {
        return $user->hasPermissionTo('edit permissions');
    }

    /**
     * Determine whether the user can delete a specific permission.
     * This method checks if the authenticated user has the permission to delete permissions.
     *
     * @param AuthenticatableWithRolesAndPermissions $user
     *        The authenticated user instance.
     * @param Permission $permission
     *        The specific permission instance to be deleted.
     * @return bool
     *         Returns true if the user has permission to delete permissions, false otherwise.
     */
    public function delete(AuthenticatableWithRolesAndPermissions $user, Permission $permission): bool
    {
        return $user->hasPermissionTo('delete permissions');
    }
}
