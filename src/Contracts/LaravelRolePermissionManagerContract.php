<?php

namespace CreativeCrafts\LaravelRolePermissionManager\Contracts;

use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Illuminate\Support\Collection;

interface LaravelRolePermissionManagerContract
{
    public function createRole(string $name, string $slug, ?string $description = null, ?Role $parent = null): Role;

    public function setRoleParent(Role $role, ?Role $parent): void;

    public function getAllPermissionsForRole(Role $role): Collection;

    public function createPermission(
        string $name,
        string $slug,
        ?string $scope = null,
        ?string $description = null
    ): Permission;

    public function getAllRoles(): Collection;

    public function getAllPermissions(): Collection;

    public function givePermissionToRole(Role $role, Permission|string $permission, ?string $scope = null): void;

    public function revokePermissionFromRole(Role $role, Permission|string $permission, ?string $scope = null): void;

    public function syncPermissions(Role $role, array $permissions): void;

    public function hasPermissionTo(mixed $user, string $permission, ?string $scope = null): bool;

    public function getAllPermissionsForUser(mixed $user): Collection;

    public function getAllPermissionsForScope(?string $scope = null): Collection;

    public function isSuperAdmin(mixed $user): bool;
}
