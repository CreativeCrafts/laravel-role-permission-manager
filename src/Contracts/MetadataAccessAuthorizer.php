<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Contracts;

interface MetadataAccessAuthorizer
{
    public function canViewRoleCatalog(AuthenticatableWithRolesAndPermissions $actor): bool;

    public function canViewPermissionCatalog(AuthenticatableWithRolesAndPermissions $actor): bool;

    public function canInspectOtherUsers(AuthenticatableWithRolesAndPermissions $actor): bool;

    public function canInspectUser(
        AuthenticatableWithRolesAndPermissions $actor,
        AuthenticatableWithRolesAndPermissions $target,
    ): bool;
}
