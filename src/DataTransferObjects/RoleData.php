<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\DataTransferObjects;

use CreativeCrafts\LaravelRolePermissionManager\Contracts\DataTransferObjectContract;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;

final readonly class RoleData implements DataTransferObjectContract
{
    public function __construct(
        protected string $name,
        protected string $slug,
        protected ?string $description = null,
        protected ?Role $parent = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
        ];
    }

    public function parent(): ?Role
    {
        return $this->parent;
    }
}
