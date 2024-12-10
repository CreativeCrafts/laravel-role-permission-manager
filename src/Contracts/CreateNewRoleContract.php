<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Contracts;

use CreativeCrafts\LaravelRolePermissionManager\DataTransferObjects\RoleData;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;

interface CreateNewRoleContract
{
    public function __construct(
        RoleData $roleData
    );

    public function __invoke(): Role;
}
