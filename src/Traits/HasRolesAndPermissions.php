<?php

namespace CreativeCrafts\LaravelRolePermissionManager\Traits;

use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

/**
 * Trait HasRolesAndPermissions
 * This trait should be used on models that extend Illuminate\Database\Eloquent\Model
 * and require role and permission functionality.
 *
 * @mixin Model
 */
trait HasRolesAndPermissions
{
    public function assignRole(Role|string $role): void
    {
        $this->ensureCorrectUsage();
        $roleModel = $this->getRoleModel($role);
        $this->roles()->syncWithoutDetaching($roleModel);
    }

    protected function ensureCorrectUsage(): void
    {
        if (! $this instanceof Model) {
            throw new \LogicException('The HasRolesAndPermissions trait must be used on an Eloquent model.');
        }
    }

    /**
     * Get the Role model instance.
     *
     * @throws ModelNotFoundException
     */
    protected function getRoleModel(Role|string $role): Role
    {
        return is_string($role) ? Role::where('slug', $role)->firstOrFail() : $role;
    }

    public function roles(): BelongsToMany
    {
        $this->ensureCorrectUsage();

        return $this->belongsToMany(Role::class, Config::get('role-permission-manager.user_role_table'));
    }

    public function removeRole(Role|string $role): void
    {
        $this->ensureCorrectUsage();
        $roleModel = $this->getRoleModel($role);
        $this->roles()->detach($roleModel);
    }

    public function givePermissionTo(Permission|string $permission, ?string $scope = null): void
    {
        $this->ensureCorrectUsage();
        $permissionModel = $this->getPermissionModel($permission, $scope);
        $this->permissions()->syncWithoutDetaching($permissionModel);
    }

    protected function getPermissionModel(Permission|string $permission, ?string $scope = null): Permission
    {
        if (is_string($permission) && Config::get('role-permission-manager.auto_create_permissions')) {
            return Permission::firstOrCreate(
                ['slug' => $permission, 'scope' => $scope],
                ['name' => $permission]
            );
        }

        return is_string($permission)
            ? Permission::where('slug', $permission)->where('scope', $scope)->firstOrFail()
            : $permission;
    }

    public function permissions(): BelongsToMany
    {
        $this->ensureCorrectUsage();

        return $this->belongsToMany(Permission::class, Config::get('role-permission-manager.user_permission_table'));
    }

    public function revokePermissionTo(Permission|string $permission, ?string $scope = null): void
    {
        $this->ensureCorrectUsage();
        $permissionModel = $this->getPermissionModel($permission, $scope);
        $this->permissions()->detach($permissionModel);
    }

    public function getAllPermissions(?string $scope = null): Collection
    {
        $this->ensureCorrectUsage();

        return app('laravel-role-permission-manager')->getAllPermissionsForUser($this, $scope);
    }

    public function hasAnyRole(array $roles): bool
    {
        $this->ensureCorrectUsage();
        $query = $this->roles();
        if (! Config::get('role-permission-manager.case_sensitive_permissions')) {
            $query->whereRaw('LOWER(slug) IN (?)', [array_map('strtolower', $roles)]);
        } else {
            $query->whereIn('slug', $roles);
        }

        return $query->exists();
    }

    public function hasAllRoles(array $roles): bool
    {
        $this->ensureCorrectUsage();

        return $this->roles()->whereIn('slug', $roles)->count() === count($roles);
    }

    public function hasAnyPermission(array $permissions, ?string $scope = null): bool
    {
        $this->ensureCorrectUsage();

        return collect($permissions)->contains(fn ($permission) => $this->hasPermissionTo($permission, $scope));
    }

    public function hasPermissionTo(Permission|string $permission, ?string $scope = null): bool
    {
        $this->ensureCorrectUsage();
        if ($this->isSuperAdmin()) {
            return true;
        }

        return app('laravel-role-permission-manager')->hasPermissionTo($this, $permission, $scope);
    }

    public function isSuperAdmin(): bool
    {
        $this->ensureCorrectUsage();

        return $this->hasRole(Config::get('role-permission-manager.super_admin_role'));
    }

    public function hasRole(Role|string $role): bool
    {
        $this->ensureCorrectUsage();
        if (is_string($role)) {
            $query = $this->roles();
            if (! Config::get('role-permission-manager.case_sensitive_permissions')) {
                $query->whereRaw('LOWER(slug) = ?', [strtolower($role)]);
            } else {
                $query->where('slug', $role);
            }

            return $query->exists();
        }

        return $this->roles->contains($role);
    }

    public function hasAllPermissions(array $permissions, ?string $scope = null): bool
    {
        $this->ensureCorrectUsage();

        return collect($permissions)->every(fn ($permission) => $this->hasPermissionTo($permission, $scope));
    }
}
