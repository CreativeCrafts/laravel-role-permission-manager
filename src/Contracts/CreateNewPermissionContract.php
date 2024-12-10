<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Contracts;

use CreativeCrafts\LaravelRolePermissionManager\DataTransferObjects\PermissionData;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;

interface CreateNewPermissionContract
{
    public function __construct(
        PermissionData $permissionData
    );

    public function __invoke(): Permission;
}
