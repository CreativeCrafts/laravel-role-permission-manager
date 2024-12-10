<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Traits;

use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use LogicException;

/**
 * This trait should be used on models that extend Illuminate\Database\Eloquent\Model
 * and require role and permission functionality.
 *
 * @package CreativeCrafts\LaravelRolePermissionManager\Traits
 * @mixin Model
 */
trait HasRolesAndPermissions
{
    /**
     * Assign a role to the model.
     * This method assigns a role to the current model instance. If the role
     * is already assigned, it will not be duplicated.
     *
     * @param Role|string $role The role to be assigned. Can be either a Role object or a string representing the role's slug.
     * @throws LogicException If the trait is not used on an Eloquent model.
     * @throws ModelNotFoundException If the role is provided as a string and cannot be found.
     */
    public function assignRole(Role|string $role): void
    {
        $this->ensureCorrectUsage();
        $roleModel = $this->getRoleModel($role);
        $this->roles()->syncWithoutDetaching($roleModel);
        app('laravel-role-permission-manager')->clearUserRoleCache($this);
    }

    /**
     * Get the roles associated with the model.
     * This function defines a many-to-many relationship between the current model
     * and the Role model. It ensures that the trait is being used correctly before
     * establishing the relationship.
     *
     * @return BelongsToMany A query builder for the role's relationship.
     * @throws LogicException If the trait is not used on an Eloquent model.
     */
    public function roles(): BelongsToMany
    {
        $this->ensureCorrectUsage();
        return $this->belongsToMany(Role::class, Config::get('role-permission-manager.user_role_table'));
    }

    /**
     * Remove a role from the model.
     * This method removes a specified role from the current model instance.
     * If the role is not currently assigned to the model, this operation has no effect.
     *
     * @param Role|string $role The role to be removed. Can be either a Role object or a string representing the role's slug.
     * @throws LogicException If the trait is not used on an Eloquent model.
     * @throws ModelNotFoundException If the role is provided as a string and cannot be found.
     */
    public function removeRole(Role|string $role): void
    {
        $this->ensureCorrectUsage();
        $roleModel = $this->getRoleModel($role);
        $this->roles()->detach($roleModel);
    }

    /**
     * Assign a permission to the model.
     * This method grants a specified permission to the current model instance.
     * If the permission is already assigned, it will not be duplicated.
     *
     * @param Permission|string $permission The permission to be assigned. Can be either a Permission object or a string representing the permission's slug.
     * @param string|null $scope The scope of the permission. Default is null.
     * @throws LogicException If the trait is not used on an Eloquent model.
     * @throws ModelNotFoundException If the permission is provided as a string and cannot be found.
     */
    public function givePermissionTo(Permission|string $permission, ?string $scope = null): void
    {
        $this->ensureCorrectUsage();
        $permissionModel = $this->getPermissionModel($permission, $scope);
        $this->permissions()->syncWithoutDetaching($permissionModel);
    }

    /**
     * Get the permissions associated with the model.
     * This function defines a many-to-many relationship between the current model
     * and the Permission model.
     *
     * @return BelongsToMany A query builder for the permission's relationship.
     * @throws LogicException If the trait is not used on an Eloquent model.
     */
    public function permissions(): BelongsToMany
    {
        $this->ensureCorrectUsage();
        return $this->belongsToMany(Permission::class, Config::get('role-permission-manager.user_permission_table'));
    }

    /**
     * Revoke a specific permission from the model.
     * This method removes a specified permission from the current model instance.
     * If the permission is not currently assigned to the model, this operation has no effect.
     *
     * @param Permission|string $permission The permission to be revoked. Can be either a Permission object or a string representing the permission's slug.
     * @param string|null $scope The scope of the permission. Default is null.
     * @throws LogicException If the trait is not used on an Eloquent model.
     * @throws ModelNotFoundException If the permission is provided as a string and cannot be found.
     */
    public function revokePermissionTo(Permission|string $permission, ?string $scope = null): void
    {
        $this->ensureCorrectUsage();
        $permissionModel = $this->getPermissionModel($permission, $scope);
        $this->permissions()->detach($permissionModel);
    }

    /**
     * Retrieve all permissions assigned to the user.
     * This method fetches all permissions associated with the user, either directly
     * assigned or inherited through roles. If a scope is provided, it will filter
     * the permissions to that specific scope.
     *
     * @param string|null $scope Optional. The scope to filter permissions by.
     *                           If null, all permissions regardless of scope will be returned.
     * @return Collection A collection of Permission models associated with the user.
     * @throws LogicException If the trait is not used on an Eloquent model.
     */
    public function getAllPermissions(?string $scope = null): Collection
    {
        $this->ensureCorrectUsage();
        return app('laravel-role-permission-manager')->getAllPermissionsForUser($this, $scope);
    }

    /**
     * Retrieve the roles associated with a user.
     * This method fetches all roles assigned to a specified user or the current user if no user is provided.
     * It delegates the actual role retrieval to the 'laravel-role-permission-manager' service.
     *
     * @param mixed $user Optional. The user whose roles are to be retrieved. If null, the current user's roles will be fetched.
     * @return Collection A collection of Role models associated with the user.
     * @throws LogicException If the trait is not used on an Eloquent model.
     */
    public function getUserRoles(mixed $user): Collection
    {
        $this->ensureCorrectUsage();
        $selectedUser = $user ?? $this;
        return app('laravel-role-permission-manager')->getUserRoles($selectedUser);
    }

    /**
     * Retrieve the names of all roles assigned to the user.
     * This method fetches the names of all roles associated with the current user
     * by delegating the task to the 'laravel-role-permission-manager' service.
     * It ensures that the trait is being used correctly before making the call.
     *
     * @param mixed $user Optional. The user whose role names are to be retrieved. If null, the current user's role names will be fetched.
     * @return Collection A collection of role names (strings) associated with the user.
     * @throws LogicException If the trait is not used on an Eloquent model.
     */
    public function getUserRoleNames(mixed $user): Collection
    {
        $this->ensureCorrectUsage();
        $selectedUser = $user ?? $this;
        return app('laravel-role-permission-manager')->getRoleNames($selectedUser);
    }

    /**
     * Retrieve the slugs of all roles assigned to the user.
     * This method fetches the slugs of all roles associated with the current user
     * by delegating the task to the 'laravel-role-permission-manager' service.
     * It ensures that the trait is being used correctly before making the call.
     *
     * @param mixed $user Optional. The user whose role slugs are to be retrieved. If null, the current user's role slugs will be fetched.
     * @return Collection A collection of role slugs (strings) associated with the user.
     * @throws LogicException If the trait is not used on an Eloquent model.
     */
    public function getUserRoleSlugs(mixed $user): Collection
    {
        $this->ensureCorrectUsage();
        $selectedUser = $user ?? $this;
        return app('laravel-role-permission-manager')->getRoleSlugs($selectedUser);
    }

    /**
     * Check if the model has any of the given roles.
     * This method determines whether the model instance has at least one of the specified roles.
     * It takes into account the case sensitivity configuration for permissions.
     *
     * @param array $roles An array of role slugs to check against.
     * @return bool Returns true if the model has at least one of the specified roles, false otherwise.
     * @throws LogicException If the trait is not used on an Eloquent model.
     */
    public function hasAnyRole(array $roles): bool
    {
        $this->ensureCorrectUsage();
        $query = $this->roles();

        if (! Config::get('role-permission-manager.case_sensitive_permissions')) {
            $lowercaseRoles = array_map('strtolower', $roles);
            $query->whereRaw(
                'LOWER(slug) IN (' . implode(',', array_fill(0, count($lowercaseRoles), '?')) . ')',
                $lowercaseRoles
            );
        } else {
            $query->whereIn('slug', $roles);
        }

        return $query->exists();
    }

    /**
     * Check if the model has all the given roles.
     * This method determines whether the model instance has all the specified roles.
     * It compares the count of matching roles to the total number of roles provided.
     *
     * @param array $roles An array of role slugs to check against.
     * @return bool Returns true if the model has all the specified roles, false otherwise.
     * @throws LogicException If the trait is not used on an Eloquent model.
     */
    public function hasAllRoles(array $roles): bool
    {
        $this->ensureCorrectUsage();
        return $this->roles()->whereIn('slug', $roles)->count() === count($roles);
    }

    /**
     * Check if the model has any of the given permissions.
     * This method determines whether the model instance has at least one of the specified permissions.
     * It takes into account an optional scope for the permissions.
     *
     * @param array $permissions An array of permission slugs or Permission objects to check against.
     * @param string|null $scope Optional. The scope of the permissions to check. Default is null.
     * @return bool Returns true if the model has at least one of the specified permissions, false otherwise.
     * @throws LogicException If the trait is not used on an Eloquent model.
     */
    public function hasAnyPermission(array $permissions, ?string $scope = null): bool
    {
        $this->ensureCorrectUsage();
        return collect($permissions)->contains(fn ($permission) => $this->hasPermissionTo($permission, $scope));
    }

    /**
     * Check if the model has a specific permission.
     * This method determines whether the current model instance has the specified permission.
     * It first checks if the user is a super admin (who has all permissions by default).
     * If not, it delegates the permission check to the role-permission manager.
     *
     * @param Permission|string $permission The permission to check. Can be either a Permission object or a string representing the permission's slug.
     * @param string|null $scope Optional. The scope of the permission to check. Default is null.
     * @return bool Returns true if the model has the specified permission, false otherwise.
     * @throws LogicException If the trait is not used on an Eloquent model.
     */
    public function hasPermissionTo(Permission|string $permission, ?string $scope = null): bool
    {
        $this->ensureCorrectUsage();
        if ($this->hasSuperAdminRole()) {
            return true;
        }
        return app('laravel-role-permission-manager')->hasPermissionTo($this, $permission, $scope);
    }

    /**
     * Check if the model has super admin privileges.
     * This method determines whether the current model instance has the super admin role.
     * The super admin role is defined in the configuration file.
     *
     * @return bool Returns true if the model has the super admin role, false otherwise.
     * @throws LogicException If the trait is not used on an Eloquent model.
     */
    public function hasSuperAdminRole(): bool
    {
        $this->ensureCorrectUsage();
        return $this->hasRole(Config::get('role-permission-manager.super_admin_role'));
    }

    /**
     * Check if the model has a specific role.
     * This method determines whether the current model instance has the specified role.
     * It handles both Role objects and string representations of roles (slugs).
     * The method takes into account the case sensitivity configuration for permissions.
     *
     * @param Role|string $role The role to check. Can be either a Role object or a string representing the role's slug.
     * @return bool Returns true if the model has the specified role, false otherwise.
     * @throws LogicException If the trait is not used on an Eloquent model.
     */
    public function hasRole(Role|string $role): bool
    {
        $this->ensureCorrectUsage();
        if (is_string($role)) {
            $query = $this->roles();
            $caseSensitive = Config::get('role-permission-manager.case_sensitive_permissions');

            if (! $caseSensitive) {
                $query->where(function ($q) use ($role): void {
                    $q->whereRaw('LOWER(slug) = ?', [strtolower($role)])
                        ->orWhereRaw('LOWER(name) = ?', [strtolower($role)]);
                });
            } else {
                $query->where(function ($q) use ($role): void {
                    $q->where('slug', $role)
                        ->orWhere('name', $role);
                });
            }

            return $query->exists();
        }
        return $this->roles->contains($role);
    }

    /**
     * Check if the model has all the given permissions.
     * This method determines whether the current model instance has all the specified permissions.
     * It uses the Collection's 'every' method to ensure that the model has each of the given permissions.
     *
     * @param array $permissions An array of permission slugs or Permission objects to check against.
     * @param string|null $scope Optional. The scope of the permissions to check. Default is null.
     * @return bool Returns true if the model has all the specified permissions, false otherwise.
     * @throws LogicException If the trait is not used on an Eloquent model.
     */
    public function hasAllPermissions(array $permissions, ?string $scope = null): bool
    {
        $this->ensureCorrectUsage();
        return collect($permissions)->every(fn ($permission) => $this->hasPermissionTo($permission, $scope));
    }

    /**
     * Ensures that the trait is being used correctly on an Eloquent model.
     * This method checks if the current instance is an Eloquent model.
     * If not, it throws a LogicException to indicate improper usage of the trait.
     *
     * @throws LogicException If the trait is not used on an Eloquent model.
     */
    protected function ensureCorrectUsage(): void
    {
        if (! $this instanceof Model) {
            throw new LogicException('The HasRolesAndPermissions trait must be used on an Eloquent model.');
        }
    }

    /**
     * Get the Role model instance from a Role object or a role slug.
     * This method takes either a Role object or a string representing the role's slug
     * and returns the corresponding Role model instance.
     *
     * @param Role|string $role The role to retrieve. Can be either a Role object or a string representing the role's slug.
     * @return Role The Role model instance.
     * @throws ModelNotFoundException If the role is provided as a string and cannot be found in the database.
     */
    protected function getRoleModel(Role|string $role): Role
    {
        return is_string($role) ? Role::where('slug', $role)->firstOrFail() : $role;
    }

    /**
     * Retrieves or creates a Permission model instance based on the given permission and scope.
     * This method handles both Permission objects and string representations of permissions.
     * If auto-creation of permissions is enabled and a string is provided, it will create
     * a new Permission if it doesn't exist. Otherwise, it will attempt to find an existing
     * Permission based on the given slug and scope.
     *
     * @param Permission|string $permission The permission to retrieve or create. Can be either a Permission object or a string representing the permission's slug.
     * @param string|null $scope Optional. The scope of the permission. Default is null.
     * @return Permission The Permission model instance corresponding to the given permission and scope.
     * @throws ModelNotFoundException If the permission is provided as a string, auto-creation is disabled, and the permission cannot be found in the database.
     */
    protected function getPermissionModel(Permission|string $permission, ?string $scope = null): Permission
    {
        if (is_string($permission) && Config::get('role-permission-manager.auto_create_permissions')) {
            return Permission::firstOrCreate(
                [
                    'slug' => $permission,
                    'scope' => $scope,
                ],
                [
                    'name' => $permission,
                ]
            );
        }

        return is_string($permission)
            ? Permission::where('slug', $permission)->where('scope', $scope)->firstOrFail()
            : $permission;
    }
}
