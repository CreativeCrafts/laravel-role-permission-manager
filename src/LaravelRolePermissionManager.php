<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager;

use CreativeCrafts\LaravelRolePermissionManager\Actions\CreateNewPermission;
use CreativeCrafts\LaravelRolePermissionManager\Actions\CreateNewRole;
use CreativeCrafts\LaravelRolePermissionManager\Cache\AuthorizationCache;
use CreativeCrafts\LaravelRolePermissionManager\Contracts\LaravelRolePermissionManagerContract;
use CreativeCrafts\LaravelRolePermissionManager\DataTransferObjects\PermissionData;
use CreativeCrafts\LaravelRolePermissionManager\DataTransferObjects\PermissionReference;
use CreativeCrafts\LaravelRolePermissionManager\DataTransferObjects\RoleData;
use CreativeCrafts\LaravelRolePermissionManager\Exceptions\AmbiguousPermissionReferenceException;
use CreativeCrafts\LaravelRolePermissionManager\Exceptions\EmptyPermissionSyncException;
use CreativeCrafts\LaravelRolePermissionManager\Exceptions\InvalidParentException;
use CreativeCrafts\LaravelRolePermissionManager\Exceptions\InvalidPermissionReferenceException;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class LaravelRolePermissionManager implements LaravelRolePermissionManagerContract
{
    public function __construct(private ?AuthorizationCache $authorizationCache = null)
    {
        $this->authorizationCache ??= app(AuthorizationCache::class);
    }

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
        $roleData = new RoleData(
            name: $name,
            slug: $slug,
            description: $description,
            parent: $parent,
        );
        $role = (new CreateNewRole($roleData))();
        $this->clearRoleCache($role);
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
        if (! $role->exists) {
            throw new InvalidParentException(
                'Role must be a persisted Role model instance.',
                Response::HTTP_NOT_ACCEPTABLE
            );
        }

        if ($parent instanceof Role && ! $parent->exists) {
            throw new InvalidParentException(
                'Parent role must be a persisted Role model instance.',
                Response::HTTP_NOT_ACCEPTABLE
            );
        }

        $role->getConnection()->transaction(function () use ($role, $parent): void {
            if ($parent instanceof Role && ! $this->authorizationCache->sameDatabase($role, $parent)) {
                throw new InvalidParentException('Parent role must use the same database connection.');
            }

            $role->newQuery()->whereKey($role->getKey())->lockForUpdate()->firstOrFail();
            if ($parent instanceof Role) {
                $parent->newQuery()->whereKey($parent->getKey())->lockForUpdate()->firstOrFail();
            }

            $role->parent()->associate($parent);
            $role->save();
            $role->unsetRelation('parent');
            $role->unsetRelation('children');
            $role->unsetRelation('permissions');
            $this->clearRoleCache($role);
        });
    }

    /**
     * Get all permissions associated with a role.
     * This method retrieves all permissions that are directly assigned to the role
     * or inherited from parent roles.
     *
     * @param Role $role The role to get permissions for
     * @return Collection<int, Permission> A collection of Permission models
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
        ?string $description = null,
        ?string $scope = null,
        ?Connection $connection = null,
    ): Permission {
        $name = config('role-permission-manager.case_sensitive_permissions') ? $name : strtolower($name);
        $slug = config('role-permission-manager.case_sensitive_permissions') ? $slug : strtolower($slug);

        $permissionData = (new PermissionData(
            name: $name,
            slug: $slug,
            description: $description,
            scope: $scope,
        ));
        $permission = (new CreateNewPermission($permissionData, $connection?->getName()))();
        $this->clearPermissionCache($permission);

        return $permission;
    }

    /**
     * Retrieve all roles from the system.
     * This method fetches all roles from the database and caches the result
     * for improved performance. The cache duration is determined by the
     * configured cache expiration time.
     *
     * @return Collection<int, Role> A collection of all Role models in the system.
     */
    public function getAllRoles(): Collection
    {
        $role = new Role();
        $connection = $role->getConnection();
        /** @var list<int|string> $roleIds */
        $roleIds = $this->authorizationCache->remember(
            'role-catalog-ids',
            [
                'table' => $role->getTable(),
                'database' => $this->authorizationCache->databaseIdentity($connection),
            ],
            static fn (): array => Role::query()->orderBy((new Role())->getKeyName())->pluck(
                (new Role())->getKeyName()
            )->all(),
        );

        return $this->rolesByIds($roleIds, $connection);
    }

    /**
     * Retrieve all permissions from the system.
     * This method fetches all permissions from the database and caches the result
     * for improved performance. The cache duration is determined by the
     * configured cache expiration time.
     *
     * @return Collection<int, Permission> A collection of all Permission models in the system.
     */
    public function getAllPermissions(): Collection
    {
        $permission = new Permission();
        $connection = $permission->getConnection();
        /** @var list<int|string> $permissionIds */
        $permissionIds = $this->authorizationCache->remember(
            'permission-catalog-ids',
            [
                'table' => $permission->getTable(),
                'database' => $this->authorizationCache->databaseIdentity($connection),
            ],
            static fn (): array => Permission::query()->orderBy((new Permission())->getKeyName())->pluck(
                (new Permission())->getKeyName()
            )->all(),
        );

        return $this->permissionsByIds($permissionIds, $connection);
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
        $scopeWasProvided = func_num_args() >= 3;

        $role->getConnection()->transaction(function () use ($role, $permission, $scope, $scopeWasProvided): void {
            $role->newQuery()->whereKey($role->getKey())->lockForUpdate()->firstOrFail();
            $permissionModel = $this->resolvePermissionReference(
                $this->mutationPermissionReference($permission, $scope, $scopeWasProvided),
                true,
                $role->getConnection(),
            );

            $role->permissions()->syncWithoutDetaching([$permissionModel->getKey()]);
            $role->unsetRelation('permissions');
            $this->clearRoleCache($role);
        });
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
        $scopeWasProvided = func_num_args() >= 3;

        $role->getConnection()->transaction(function () use ($role, $permission, $scope, $scopeWasProvided): void {
            $role->newQuery()->whereKey($role->getKey())->lockForUpdate()->firstOrFail();
            $permissionModel = $this->resolvePermissionReference(
                $this->mutationPermissionReference($permission, $scope, $scopeWasProvided),
                true,
                $role->getConnection(),
            );

            $role->permissions()->detach($permissionModel);
            $role->unsetRelation('permissions');
            $this->clearRoleCache($role);
        });
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
    public function syncPermissions(Role $role, array $permissions, bool $clear = false): array
    {
        if (! $role->exists) {
            throw new InvalidPermissionReferenceException('Permissions can only be synchronized to a persisted role.');
        }

        if ($permissions === [] && ! $clear) {
            throw new EmptyPermissionSyncException(
                'An empty permission target requires explicit clear intent.'
            );
        }

        if ($permissions !== [] && $clear) {
            throw new EmptyPermissionSyncException(
                'Explicit clear cannot be combined with permission references.'
            );
        }

        return $role->getConnection()->transaction(function () use ($role, $permissions): array {
            $role->newQuery()->whereKey($role->getKey())->lockForUpdate()->firstOrFail();
            $resolved = collect($permissions)
                ->map(fn (mixed $reference): Permission => $this->resolvePermissionReference(
                    $reference,
                    true,
                    $role->getConnection(),
                ))
                ->unique(static fn (Permission $permission): mixed => $permission->getKey())
                ->values();

            $permissionModel = new Permission();
            $permissionKey = $permissionModel->qualifyColumn($permissionModel->getKeyName());
            $currentIds = $role->permissions()
                ->lockForUpdate()
                ->pluck($permissionKey)
                ->all();
            $targetIds = $resolved
                ->map(static fn (Permission $permission): mixed => $permission->getKey())
                ->all();

            $attached = $this->sortedIds(array_diff($targetIds, $currentIds));
            $detached = $this->sortedIds(array_diff($currentIds, $targetIds));
            $unchanged = $this->sortedIds(array_intersect($currentIds, $targetIds));

            $role->permissions()->sync($targetIds);
            $role->unsetRelation('permissions');
            $this->clearRoleCache($role);

            return [
                'attached' => $this->permissionDescriptors($attached, $role->getConnection()),
                'detached' => $this->permissionDescriptors($detached, $role->getConnection()),
                'unchanged' => $this->permissionDescriptors($unchanged, $role->getConnection()),
            ];
        });
    }

    public function clearPermissions(Role $role): array
    {
        return $this->syncPermissions($role, [], true);
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
    public function hasPermissionTo(mixed $user, Permission|string $permission, ?string $scope = null): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $userPermissions = $this->getAllPermissionsForUser($user);

        if ($permission instanceof Permission) {
            return $permission->exists
                && ($scope === null || $permission->scope === $scope)
                && $userPermissions->contains(
                    fn (Permission $assigned): bool => $this->authorizationCache->sameDatabaseRecord(
                        $assigned,
                        $permission,
                    )
                );
        }

        foreach ($userPermissions as $userPermission) {
            if ($userPermission->wildcardMatch($permission, $scope)) {
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
     * @return Collection<int, Permission> A collection of unique Permission objects associated with the user,
     *                                     including both direct and role-based permissions.
     */
    public function getAllPermissionsForUser(mixed $user, ?string $scope = null): Collection
    {
        $connection = $user instanceof Model ? $user->getConnection() : null;
        /** @var list<int|string> $permissionIds */
        $permissionIds = $this->authorizationCache->remember(
            'effective-permission-ids',
            $this->authorizationCache->principalIdentity($user),
            function () use ($user): array {
            if ($this->isSuperAdmin($user)) {
                $permission = new Permission();
                if ($user instanceof Model) {
                    $permission->setConnection($user->getConnection()->getName());
                }

                return $permission->newQuery()->pluck($permission->getKeyName())->all();
            }

            $directPermissions = $user->permissions()->get();
            $userRoles = $user->roles()->get();

            $rolePermissions = $userRoles->map(function ($role) {
                return $role->getAllPermissions();
            })->flatten();

                return $directPermissions
                    ->concat($rolePermissions)
                    ->unique(static fn (Permission $permission): mixed => $permission->getKey())
                    ->map(static fn (Permission $permission): mixed => $permission->getKey())
                    ->values()
                    ->all();
            },
        );

        $permissions = $this->permissionsByIds($permissionIds, $connection);

        if ($scope === null) {
            return $permissions;
        }

        return $permissions
            ->filter(static fn (Permission $permission): bool => $permission->scope === $scope)
            ->values();
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
        if ($scope === null) {
            return $this->getAllPermissions();
        }

        $permission = new Permission();
        $connection = $permission->getConnection();
        /** @var list<int|string> $permissionIds */
        $permissionIds = $this->authorizationCache->remember(
            'permission-catalog-scope-ids',
            [
                'table' => $permission->getTable(),
                'database' => $this->authorizationCache->databaseIdentity($connection),
                'scope' => $this->authorizationCache->scopeIdentity($scope, true),
            ],
            static fn (): array => Permission::query()
                ->where('scope', $scope)
                ->orderBy((new Permission())->getKeyName())
                ->pluck((new Permission())->getKeyName())
                ->all(),
        );

        return $this->permissionsByIds($permissionIds, $connection);
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
        $canonicalName = (string) config('role-permission-manager.super_admin_role', 'Super Admin');

        return $user->roles()
            ->pluck('name')
            ->contains(static fn (mixed $name): bool => $name === $canonicalName);
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
        return $role->getAllChildren();
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
    public function grantPermissionToRoleAndSubRoles(
        Role $role,
        Permission|string $permission,
        ?string $scope = null,
    ): void {
        $scopeWasProvided = func_num_args() >= 3;

        $role->getConnection()->transaction(function () use ($role, $permission, $scope, $scopeWasProvided): void {
            $permissionModel = $this->resolvePermissionReference(
                $this->mutationPermissionReference($permission, $scope, $scopeWasProvided),
                true,
                $role->getConnection(),
            );

            $roles = collect([$role])->concat($this->getSubRoles($role));
            foreach ($roles as $targetRole) {
                $targetRole->permissions()->syncWithoutDetaching([$permissionModel->getKey()]);
                $targetRole->unsetRelation('permissions');
            }

            $this->clearRoleCache($role);
        });
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
    public function revokePermissionFromRoleAndSubRoles(
        Role $role,
        Permission|string $permission,
        ?string $scope = null,
    ): void {
        $scopeWasProvided = func_num_args() >= 3;

        $role->getConnection()->transaction(function () use ($role, $permission, $scope, $scopeWasProvided): void {
            $permissionModel = $this->resolvePermissionReference(
                $this->mutationPermissionReference($permission, $scope, $scopeWasProvided),
                true,
                $role->getConnection(),
            );

            $roles = collect([$role])->concat($this->getSubRoles($role));
            foreach ($roles as $targetRole) {
                $targetRole->permissions()->detach($permissionModel->getKey());
                $targetRole->unsetRelation('permissions');
            }

            $this->clearRoleCache($role);
        });
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
     * Get all roles assigned to a user.
     *
     * @param mixed $user The user object for which to retrieve roles.
     * @return Collection A collection of Role objects assigned to the user.
     */
    public function getUserRoles(mixed $user): Collection
    {
        $connection = $user instanceof Model ? $user->getConnection() : null;
        /** @var list<int|string> $roleIds */
        $roleIds = $this->authorizationCache->remember(
            'principal-role-ids',
            $this->authorizationCache->principalIdentity($user),
            static fn (): array => $user->roles()
                ->orderBy((new Role())->qualifyColumn((new Role())->getKeyName()))
                ->pluck((new Role())->qualifyColumn((new Role())->getKeyName()))
                ->all(),
        );

        return $this->rolesByIds($roleIds, $connection);
    }

    /**
     * Get the names of all roles assigned to a user.
     *
     * @param mixed $user The user object for which to retrieve role names.
     * @return Collection A collection of role names (strings) assigned to the user.
     */
    public function getRoleNames(mixed $user): Collection
    {
        return $this->getUserRoles($user)->pluck('name')->values();
    }

    /**
     * Get the slugs of all roles assigned to a user.
     *
     * @param mixed $user The user object for which to retrieve role slugs.
     * @return Collection A collection of role slugs (strings) assigned to the user.
     */
    public function getRoleSlugs(mixed $user): Collection
    {
        return $this->getUserRoles($user)->pluck('slug')->values();
    }

    /**
     * Clear the cached roles for a specific user.
     *
     * @param mixed $user The user object for which to clear the role cache.
     */
    public function clearUserRoleCache(mixed $user): void
    {
        $this->authorizationCache->invalidate(
            $user instanceof Model ? $user : null,
            $user,
        );
    }

    public function resolvePermissionReference(
        mixed $reference,
        bool $lockForUpdate = false,
        ?Connection $connection = null,
    ): Permission {
        $permission = new Permission();
        if ($connection instanceof Connection) {
            $permission->setConnection($connection->getName());
        }
        $query = $permission->newQuery();
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        if ($reference instanceof Permission) {
            if (! $reference->exists || $reference->getKey() === null) {
                throw new InvalidPermissionReferenceException(
                    'Permission model references must be persisted.'
                );
            }

            if ($connection instanceof Connection && ! $this->authorizationCache->onConnection($reference, $connection)) {
                throw new InvalidPermissionReferenceException(
                    'Permission reference uses a different database connection.'
                );
            }

            return $query->whereKey($reference->getKey())->firstOrFail();
        }

        if (is_int($reference)) {
            if ($reference < 1) {
                throw new InvalidPermissionReferenceException('Permission IDs must be positive integers.');
            }

            return $query->whereKey($reference)->firstOrFail();
        }

        if (is_array($reference)) {
            $reference = PermissionReference::fromArray($reference);
        }

        if ($reference instanceof PermissionReference) {
            $matches = $query
                ->where('slug', $reference->slug)
                ->where('scope', $reference->scope)
                ->limit(2)
                ->get();

            if ($matches->isEmpty()) {
                throw (new ModelNotFoundException())->setModel(Permission::class);
            }

            if ($matches->count() > 1) {
                throw new AmbiguousPermissionReferenceException(
                    "Permission slug '{$reference->slug}' with the requested scope is not unique."
                );
            }

            return $matches->first();
        }

        if (is_string($reference) && $reference !== '') {
            $matches = $query->where('slug', $reference)->limit(2)->get();
            if ($matches->isEmpty()) {
                throw (new ModelNotFoundException())->setModel(Permission::class);
            }

            if ($matches->count() > 1) {
                throw new AmbiguousPermissionReferenceException(
                    "Permission slug '{$reference}' is ambiguous; provide an exact scope or ID."
                );
            }

            return $matches->first();
        }

        throw new InvalidPermissionReferenceException(
            'Permission references must be Permission, positive integer ID, non-empty slug, or explicit slug/scope.'
        );
    }

    /**
     * Retrieve a Permission model instance based on the provided permission and scope.
     * This function takes either a Permission object or a permission slug string and
     * returns the corresponding Permission model. If a string is provided, it searches
     * for the permission by its slug, optionally filtered by scope.
     *
     * @param Permission|string $permission The Permission object or the slug of the permission to retrieve.
     * @param string|null $scope Optional. The scope of the permission to filter by. Default is null.
     * @return Permission The retrieved Permission model instance.
     * @throws ModelNotFoundException If no matching permission is found.
     */
    protected function getPermissionModel(Permission|string $permission, ?string $scope = null): Permission
    {
        return $this->resolvePermissionReference(
            $this->mutationPermissionReference($permission, $scope, func_num_args() >= 2),
        );
    }

    private function mutationPermissionReference(
        Permission|string $permission,
        ?string $scope,
        bool $scopeWasProvided,
    ): Permission|PermissionReference|string {
        if ($permission instanceof Permission || ! $scopeWasProvided) {
            return $permission;
        }

        return new PermissionReference($permission, $scope);
    }

    /**
     * Clear the cached roles.
     * This method removes the 'all_roles' key from the cache,
     * effectively invalidating the cached roles' data. This should
     * be called whenever roles are modified to ensure fresh data
     * is retrieved on subsequent requests.
     */
    private function clearRoleCache(?Role $role = null): void
    {
        $this->authorizationCache->invalidate($role);
    }

    private function clearPermissionCache(?Permission $permission = null): void
    {
        $this->authorizationCache->invalidate($permission);
    }

    /**
     * @param list<int|string> $ids
     * @return Collection<int, Permission>
     */
    private function permissionsByIds(array $ids, ?Connection $connection = null): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $positions = array_flip(array_map('strval', $ids));
        $permission = new Permission();
        if ($connection instanceof Connection) {
            $permission->setConnection($connection->getName());
        }

        return $permission->newQuery()
            ->whereKey($ids)
            ->get()
            ->sortBy(static fn (Permission $permission): int => $positions[(string) $permission->getKey()])
            ->values();
    }

    /**
     * @param list<int|string> $ids
     * @return Collection<int, Role>
     */
    private function rolesByIds(array $ids, ?Connection $connection = null): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $positions = array_flip(array_map('strval', $ids));
        $role = new Role();
        if ($connection instanceof Connection) {
            $role->setConnection($connection->getName());
        }

        return $role->newQuery()
            ->whereKey($ids)
            ->get()
            ->sortBy(static fn (Role $role): int => $positions[(string) $role->getKey()])
            ->values();
    }

    /**
     * @param array<int|string, mixed> $ids
     * @return list<int|string>
     */
    private function sortedIds(array $ids): array
    {
        $ids = array_values($ids);
        usort($ids, static function (mixed $left, mixed $right): int {
            $leftKey = (string) $left;
            $rightKey = (string) $right;
            $bothNumeric = preg_match('/\A-?\d+\z/', $leftKey) === 1
                && preg_match('/\A-?\d+\z/', $rightKey) === 1;

            return $bothNumeric ? ((int) $leftKey <=> (int) $rightKey) : strcmp($leftKey, $rightKey);
        });

        return $ids;
    }

    /**
     * @param list<int|string> $ids
     * @return list<array{id: int|string, slug: string, scope: string|null}>
     */
    private function permissionDescriptors(array $ids, ?Connection $connection = null): array
    {
        return $this->permissionsByIds($ids, $connection)
            ->map(static fn (Permission $permission): array => [
                'id' => $permission->getKey(),
                'slug' => (string) $permission->slug,
                'scope' => $permission->scope,
            ])
            ->all();
    }
}
