<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager;

use CreativeCrafts\LaravelRolePermissionManager\Contracts\LaravelRolePermissionManagerContract;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class LaravelRolePermissionManager implements LaravelRolePermissionManagerContract
{
    /**
     * Create a new role in the system.
     * This function creates a new role with the given name, slug, and optional description and parent role.
     * After creating the role, it clears the role cache to ensure the new role is immediately available.
     *
     * @param string $name The name of the role.
     * @param string $slug A unique identifier for the role.
     * @param string|null $description An optional description of the role.
     * @param Role|null $parent An optional parent role for hierarchical role structures.
     * @return Role The newly created role instance.
     */
    public function createRole(string $name, string $slug, ?string $description = null, ?Role $parent = null): Role
    {
        $role = Role::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'parent_id' => $parent?->id,
        ]);

        $this->clearRoleCache();

        return $role;
    }

    /**
     * Set the parent role for a given role.
     * This method associates a parent role with the given role, creating a hierarchical
     * structure. After setting the parent, it saves the changes and clears the role cache
     * to ensure the updated hierarchy is reflected in subsequent queries.
     *
     * @param Role $role The role for which to set the parent.
     * @param Role|null $parent The parent role to be associated, or null to remove the parent.
     */
    public function setRoleParent(Role $role, ?Role $parent): void
    {
        $role->parent()->associate($parent);
        $role->save();

        $this->clearRoleCache();
    }

    /**
     * Retrieve all permissions associated with a specific role.
     * This method fetches all permissions that are directly assigned to the given role,
     * as well as permissions inherited from parent roles in a hierarchical structure.
     *
     * @param Role $role The role object for which to retrieve permissions.
     * @return Collection A collection of Permission objects associated with the role.
     */
    public function getAllPermissionsForRole(Role $role): Collection
    {
        return $role->getAllPermissions();
    }

    /**
     * Create a new permission in the system.
     * This function creates a new permission with the given name, slug, optional scope, and description.
     * It applies case sensitivity based on configuration settings and clears the permission cache
     * to ensure the new permission is immediately available.
     *
     * @param string $name The name of the permission.
     * @param string $slug A unique identifier for the permission.
     * @param string|null $scope An optional scope for the permission, allowing for context-specific permissions.
     * @param string|null $description An optional description of the permission.
     * @return Permission The newly created permission instance.
     */
    public function createPermission(
        string $name,
        string $slug,
        ?string $scope = null,
        ?string $description = null
    ): Permission {
        $name = config('role-permission-manager.case_sensitive_permissions') ? $name : strtolower($name);
        $slug = config('role-permission-manager.case_sensitive_permissions') ? $slug : strtolower($slug);

        $permission = Permission::create([
            'name' => $name,
            'slug' => $slug,
            'scope' => $scope,
            'description' => $description,
        ]);

        $this->clearPermissionCache();

        return $permission;
    }

    /**
     * Retrieve all roles from the system.
     * This method fetches all roles from the database and caches the result
     * for improved performance. The cache duration is determined by the
     * configured cache expiration time.
     *
     * @return Collection A collection of all Role models in the system.
     */
    public function getAllRoles(): Collection
    {
        return Cache::remember('all_roles', $this->getCacheExpirationTime(), function () {
            return Role::all();
        });
    }

    /**
     * Retrieve all permissions from the system.
     * This method fetches all permissions from the database and caches the result
     * for improved performance. The cache duration is determined by the
     * configured cache expiration time.
     *
     * @return Collection A collection of all Permission models in the system.
     */
    public function getAllPermissions(): Collection
    {
        return Cache::remember('all_permissions', $this->getCacheExpirationTime(), function () {
            return Permission::all();
        });
    }

    /**
     * Assign a permission to a role.
     * This function grants a specific permission to a role. If the permission is
     * provided as a string, it will be looked up by its slug and scope. The function
     * ensures that the permission is added to the role without removing existing permissions.
     * After assigning the permission, it clears the role cache to reflect the changes.
     *
     * @param Role $role The role to which the permission will be assigned.
     * @param Permission|string $permission The permission to be assigned. Can be either a Permission object or a permission slug.
     * @param string|null $scope The scope of the permission, if applicable. Default is null.
     */
    public function givePermissionToRole(Role $role, Permission|string $permission, ?string $scope = null): void
    {
        $permissionModel = is_string($permission)
            ? Permission::where('slug', $permission)->where('scope', $scope)->firstOrFail()
            : $permission;

        $role->permissions()->syncWithoutDetaching($permissionModel);

        $this->clearRoleCache();
    }

    /**
     * Revoke a permission from a role.
     * This method removes a specific permission from a role. If the permission is
     * provided as a string, it will be looked up by its slug and scope. After
     * revoking the permission, it clears the role cache to reflect the changes.
     *
     * @param Role $role The role from which the permission will be revoked.
     * @param Permission|string $permission The permission to be revoked. Can be either a Permission object or a permission slug.
     * @param string|null $scope The scope of the permission, if applicable. Default is null.
     */
    public function revokePermissionFromRole(Role $role, Permission|string $permission, ?string $scope = null): void
    {
        $permissionModel = is_string($permission)
            ? Permission::where('slug', $permission)->where('scope', $scope)->firstOrFail()
            : $permission;

        $role->permissions()->detach($permissionModel);

        $this->clearRoleCache();
    }

    /**
     * Synchronize permissions for a role.
     *
     * This function updates the permissions associated with a role. It adds new permissions
     * and removes existing ones to match the provided list of permissions.
     *
     * @param Role $role The role to update permissions for.
     * @param array $permissions An array of permission slugs to sync with the role.
     *
     * @return array An associative array containing two keys:
     *               - 'attached': An array of names of newly added permissions.
     *               - 'detached': An array of names of removed permissions.
     */
    public function syncPermissions(Role $role, array $permissions): array
    {
        $permissionIds = Permission::whereIn('slug', $permissions)->pluck('id')->toArray();
        $syncResult = $role->permissions()->sync($permissionIds);

        return [
            'attached' => Permission::whereIn('id', $syncResult['attached'])->pluck('name')->toArray(),
            'detached' => Permission::whereIn('id', $syncResult['detached'])->pluck('name')->toArray(),
        ];
    }

    /**
     * Check if a user has a specific permission.
     * This function determines whether a given user has a particular permission,
     * optionally within a specific scope. It handles super admin permissions,
     * auto-creation of permissions, and wildcard permission matching.
     *
     * @param mixed $user The user object to check permissions for.
     * @param string $permission The permission to check for.
     * @param string|null $scope Optional. The scope of the permission. Default is null.
     * @return bool Returns true if the user has the specified permission, false otherwise.
     */
    public function hasPermissionTo(mixed $user, string $permission, ?string $scope = null): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if (config('role-permission-manager.auto_create_permissions')) {
            $this->createPermission($permission, $permission, $scope);
        }

        $userPermissions = $this->getAllPermissionsForUser($user);

        foreach ($userPermissions as $userPermission) {
            if (config('role-permission-manager.enable_wildcard_permission')) {
                if ($userPermission->wildcardMatch($permission, $scope)) {
                    return true;
                }
            } elseif ($userPermission->name === $permission && ($scope === null || $userPermission->scope === $scope)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieve all permissions associated with a user.
     * This function fetches and caches all permissions for a given user,
     * including both direct permissions and those inherited through roles.
     * The result is cached to improve performance on subsequent calls.
     *
     * @param mixed $user The user object for which to retrieve permissions.
     *                    This should be an instance of a class that has
     *                    'permissions' and 'roles' relationships defined.
     * @return Collection A collection of unique Permission objects associated with the user,
     *                    including both direct and role-based permissions.
     */
    public function getAllPermissionsForUser(mixed $user): Collection
    {
        return Cache::remember(
            "user_permissions_{$user->id}",
            $this->getCacheExpirationTime(),
            static function () use ($user) {
                $directPermissions = $user->permissions;
                $rolePermissions = $user->roles->flatMap->getAllPermissions();

                return $directPermissions->merge($rolePermissions)->unique(function ($permission): string {
                    return $permission->slug . '-' . $permission->scope;
                });
            }
        );
    }

    /**
     * Retrieve all permissions for a given scope or all permissions if no scope is specified.
     * This function fetches permissions from the database based on the provided scope.
     * The results are cached to improve performance on subsequent calls.
     *
     * @param string|null $scope The scope for which to retrieve permissions. If null, all permissions are retrieved.
     * @return Collection A collection of Permission models matching the specified scope or all permissions.
     */
    public function getAllPermissionsForScope(?string $scope = null): Collection
    {
        $cacheKey = $scope !== null && $scope !== '' && $scope !== '0' ? "all_permissions_scope_{$scope}" : 'all_permissions';

        return Cache::remember($cacheKey, $this->getCacheExpirationTime(), static function () use ($scope) {
            return $scope !== null && $scope !== '' && $scope !== '0' ? Permission::where('scope', $scope)->get(
            ) : Permission::all();
        });
    }

    /**
     * Check if a user has the super admin role.
     * This function determines whether the given user has been assigned the super admin role,
     * as defined in the role-permission-manager configuration.
     *
     * @param mixed $user The user object to check for super admin status.
     *                    This should be an instance of a class that has a 'roles' relationship defined.
     * @return bool Returns true if the user has the super admin role, false otherwise.
     */
    public function isSuperAdmin(mixed $user): bool
    {
        return $user->roles()->where('name', config('role-permission-manager.super_admin_role'))->exists();
    }

    /**
     * Retrieve all sub-roles of a given role recursively.
     * This function fetches all child roles of the given role and their descendants,
     * creating a flattened collection of all sub-roles in the hierarchy.
     *
     * @param Role $role The parent role for which to retrieve sub-roles.
     * @return Collection A flattened collection of all sub-roles (children and their descendants).
     */
    public function getSubRoles(Role $role): Collection
    {
        return $role->getAllChildren()->flatMap(function ($child) {
            return collect([$child])->merge($this->getSubRoles($child));
        });
    }

    /**
     * Grant a permission to a role and all its sub-roles recursively.
     * This function assigns the specified permission to the given role and then
     * recursively grants the same permission to all sub-roles (children and their descendants)
     * of the given role. This ensures that the permission propagates down the role hierarchy.
     *
     * @param Role $role The role to which the permission will be granted, along with its sub-roles.
     * @param Permission|string $permission The permission to be granted. Can be either a Permission object or a permission slug.
     */
    public function grantPermissionToRoleAndSubRoles(Role $role, Permission|string $permission): void
    {
        $this->givePermissionToRole($role, $permission);

        $subRoles = $this->getSubRoles($role);
        foreach ($subRoles as $subRole) {
            $this->givePermissionToRole($subRole, $permission);
        }
    }

    /**
     * Revoke a permission from a role and all its sub-roles recursively.
     * This function removes the specified permission from the given role and then
     * recursively revokes the same permission from all sub-roles (children and their descendants)
     * of the given role. This ensures that the permission removal propagates down the role hierarchy.
     *
     * @param Role $role The role from which the permission will be revoked, along with its sub-roles.
     * @param Permission|string $permission The permission to be revoked. Can be either a Permission object or a permission slug.
     */
    public function revokePermissionFromRoleAndSubRoles(Role $role, Permission|string $permission): void
    {
        $this->revokePermissionFromRole($role, $permission);

        $subRoles = $this->getSubRoles($role);
        foreach ($subRoles as $subRole) {
            $this->revokePermissionFromRole($subRole, $permission);
        }
    }

    /**
     * Retrieve a role by its slug.
     * This function searches for and returns a role based on the provided slug.
     * If no matching role is found, it returns null.
     *
     * @param string $slug The unique slug identifier of the role to retrieve.
     * @return Role|null Returns the Role object if found, or null if no matching role exists.
     */
    public function getRoleBySlug(string $slug): ?Role
    {
        return Role::where('slug', $slug)->first();
    }

    /**
     * Get the cache expiration time from the configuration.
     * This method retrieves the cache expiration time from the role-permission-manager
     * configuration. If the configuration value is not set, it defaults to 60 minutes.
     *
     * @return int The cache expiration time in minutes.
     */
    private function getCacheExpirationTime(): int
    {
        return config('role-permission-manager.cache_expiration_time', 60);
    }

    /**
     * Clear the cached roles.
     * This method removes the 'all_roles' key from the cache,
     * effectively invalidating the cached roles' data. This should
     * be called whenever roles are modified to ensure fresh data
     * is retrieved on subsequent requests.
     */
    private function clearRoleCache(): void
    {
        Cache::forget('all_roles');
    }

    /**
     * Clear the cached permissions.
     * This method removes the 'all_permissions' key from the cache,
     * effectively invalidating the cached permission's data. This should
     * be called whenever permissions are modified to ensure fresh data
     * is retrieved on subsequent requests.
     */
    private function clearPermissionCache(): void
    {
        Cache::forget('all_permissions');
    }
}
