<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\DataTransferObjects;

use CreativeCrafts\LaravelRolePermissionManager\Contracts\DataTransferObjectContract;

final readonly class PermissionData implements DataTransferObjectContract
{
    public function __construct(
        protected string $name,
        protected string $slug,
        protected ?string $description = null,
        protected ?string $scope = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'scope' => $this->scope,
        ];
    }
}
