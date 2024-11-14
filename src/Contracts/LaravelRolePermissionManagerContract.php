<?php

declare(strict_types=1);

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

    public function syncPermissions(Role $role, array $permissions): array;

    public function hasPermissionTo(mixed $user, string $permission, ?string $scope = null): bool;

    public function getAllPermissionsForUser(mixed $user): Collection;

    public function getAllPermissionsForScope(?string $scope = null): Collection;

    public function isSuperAdmin(mixed $user): bool;

    public function getSubRoles(Role $role): Collection;

    public function grantPermissionToRoleAndSubRoles(Role $role, Permission|string $permission): void;

    public function revokePermissionFromRoleAndSubRoles(Role $role, Permission|string $permission): void;
}
