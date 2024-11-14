<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Contracts;

use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

interface AuthenticatableWithRolesAndPermissions extends Authenticatable
{
    public function hasPermissionTo(string $permission): bool;

    public function assignRole(Role|string $role): void;

    public function roles(): BelongsToMany;

    public function removeRole(Role|string $role): void;

    public function givePermissionTo(Permission|string $permission, ?string $scope = null): void;

    public function permissions(): BelongsToMany;

    public function revokePermissionTo(Permission|string $permission, ?string $scope = null): void;

    public function getAllPermissions(?string $scope = null): Collection;

    public function hasAnyRole(array $roles): bool;

    public function hasAllRoles(array $roles): bool;

    public function hasAnyPermission(array $permissions, ?string $scope = null): bool;

    public function isSuperAdmin(): bool;

    public function hasRole(Role|string $role): bool;

    public function hasAllPermissions(array $permissions, ?string $scope = null): bool;
}
