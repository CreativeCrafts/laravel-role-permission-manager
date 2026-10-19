<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Authorization;

use CreativeCrafts\LaravelRolePermissionManager\Contracts\AuthenticatableWithRolesAndPermissions;
use CreativeCrafts\LaravelRolePermissionManager\Contracts\MetadataAccessAuthorizer;

final class DefaultMetadataAccessAuthorizer implements MetadataAccessAuthorizer
{
    public function canViewRoleCatalog(AuthenticatableWithRolesAndPermissions $actor): bool
    {
        return $actor->hasPermissionTo('view roles');
    }

    public function canViewPermissionCatalog(AuthenticatableWithRolesAndPermissions $actor): bool
    {
        return $actor->hasPermissionTo('view permissions');
    }

    public function canInspectOtherUsers(AuthenticatableWithRolesAndPermissions $actor): bool
    {
        return false;
    }

    public function canInspectUser(
        AuthenticatableWithRolesAndPermissions $actor,
        AuthenticatableWithRolesAndPermissions $target,
    ): bool {
        return $actor::class === $target::class
            && (string) $actor->getAuthIdentifier() === (string) $target->getAuthIdentifier();
    }
}
