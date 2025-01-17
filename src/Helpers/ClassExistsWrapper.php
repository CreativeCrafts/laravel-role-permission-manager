<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Helpers;

class ClassExistsWrapper
{
    public function exists(string $class): bool
    {
        return class_exists($class);
    }
}
