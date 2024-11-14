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
     *
     * @param AuthenticatableWithRolesAndPermissions $user The authenticated user
     * @return bool True if the user has permission to view roles, false otherwise
     */
    public function viewAny(AuthenticatableWithRolesAndPermissions $user): bool
    {
        return $user->hasPermissionTo('view roles');
    }

    /**
     * Determine whether the user can view a specific role.
     *
     * @param AuthenticatableWithRolesAndPermissions $user The authenticated user
     * @param Role $role The role to be viewed
     * @return bool True if the user has permission to view roles, false otherwise
     */
    public function view(AuthenticatableWithRolesAndPermissions $user, Role $role): bool
    {
        return $user->hasPermissionTo('view roles');
    }

    /**
     * Determine whether the user can create roles.
     *
     * @param AuthenticatableWithRolesAndPermissions $user The authenticated user
     * @return bool True if the user has permission to create roles, false otherwise
     */
    public function create(AuthenticatableWithRolesAndPermissions $user): bool
    {
        return $user->hasPermissionTo('create roles');
    }

    /**
     * Determine whether the user can update a specific role.
     *
     * @param AuthenticatableWithRolesAndPermissions $user The authenticated user
     * @param Role $role The role to be updated
     * @return bool True if the user has permission to edit roles, false otherwise
     */
    public function update(AuthenticatableWithRolesAndPermissions $user, Role $role): bool
    {
        return $user->hasPermissionTo('edit roles');
    }

    /**
     * Determine whether the user can delete a specific role.
     *
     * @param AuthenticatableWithRolesAndPermissions $user The authenticated user
     * @param Role $role The role to be deleted
     * @return bool True if the user has permission to delete roles, false otherwise
     */
    public function delete(AuthenticatableWithRolesAndPermissions $user, Role $role): bool
    {
        return $user->hasPermissionTo('delete roles');
    }
}
