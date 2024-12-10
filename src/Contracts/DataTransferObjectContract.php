<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Contracts;

interface DataTransferObjectContract
{
    public function toArray(): array;
}
