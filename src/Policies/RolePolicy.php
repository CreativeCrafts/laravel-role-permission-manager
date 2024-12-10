<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Policies;

use CreativeCrafts\LaravelRolePermissionManager\Contracts\AuthenticatableWithRolesAndPermissions;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * This policy class defines authorization rules for role-related actions.
 * It uses the HandlesAuthorization trait for common authorization methods.
 */
class RolePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any roles.
     * This method checks if the user has the necessary permissions to view roles.
     * If a specific role name is provided, it also checks for that role.
     *
     * @param AuthenticatableWithRolesAndPermissions $user The user to check permissions for
     * @param string|null $roleName Optional. The specific role name to check
     * @return bool Returns true if the user has permission to view roles, false otherwise
     */
    public function viewAny(AuthenticatableWithRolesAndPermissions $user, string $roleName = null): bool
    {
        if ($user->hasPermissionTo('view roles')) {
            return true;
        }

        return $roleName !== null && $user->hasRole($roleName);
    }

    /**
     * Determine whether the user can view a specific role.
     * This method checks if the user has the necessary permissions to view a specific role.
     * It allows access if the user has either the general 'view roles' permission or
     * the specific role being viewed.
     *
     * @param AuthenticatableWithRolesAndPermissions $user The user to check permissions for
     * @param Role $role The specific role object being viewed
     * @return bool Returns true if the user has permission to view the specific role, false otherwise
     */
    public function view(AuthenticatableWithRolesAndPermissions $user, Role $role): bool
    {
        if ($user->hasPermissionTo('view roles')) {
            return true;
        }
        return $user->hasRole($role->name);
    }

    /**
     * Determine whether the user can create roles.
     * This method checks if the user has the necessary permissions to create roles.
     * If a specific role name is provided, it also checks for that role.
     *
     * @param AuthenticatableWithRolesAndPermissions $user The user to check permissions for
     * @param string|null $roleName Optional. The specific role name to check
     * @return bool Returns true if the user has permission to create roles, false otherwise
     */
    public function create(AuthenticatableWithRolesAndPermissions $user, string $roleName = null): bool
    {
        if ($user->hasPermissionTo('create roles')) {
            return true;
        }

        return $roleName !== null && $user->hasRole($roleName);
    }

    /**
     * Determine whether the user can update a specific role.
     * This method checks if the user has the necessary permissions to update a specific role.
     * It allows access if the user has either the general 'edit roles' permission or
     * the specific role being updated.
     *
     * @param AuthenticatableWithRolesAndPermissions $user The user to check permissions for
     * @param Role $role The specific role object being updated
     * @return bool Returns true if the user has permission to update the specific role, false otherwise
     */
    public function update(AuthenticatableWithRolesAndPermissions $user, Role $role): bool
    {
        if ($user->hasPermissionTo('edit roles')) {
            return true;
        }
        return $user->hasRole($role->name);
    }

    /**
     * Determine whether the user can delete a specific role.
     * This method checks if the user has the necessary permissions to delete a specific role.
     * It allows access if the user has either the general 'delete roles' permission or
     * the specific role being deleted.
     *
     * @param AuthenticatableWithRolesAndPermissions $user The user to check permissions for
     * @param Role $role The specific role object being deleted
     * @return bool Returns true if the user has permission to delete the specific role, false otherwise
     */
    public function delete(AuthenticatableWithRolesAndPermissions $user, Role $role): bool
    {
        if ($user->hasPermissionTo('delete roles')) {
            return true;
        }
        return $user->hasRole($role->name);
    }
}
