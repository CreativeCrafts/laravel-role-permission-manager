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

    /**
     * Convert the permission data to an array.
     * This method transforms the permission data object into an associative array
     * representation that can be used for API responses, database operations, or
     * other data processing needs.
     *
     * @return array<string, string|null> An array containing the permission data with keys:
     *                                   'name', 'slug', 'description', and 'scope'
     */
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
