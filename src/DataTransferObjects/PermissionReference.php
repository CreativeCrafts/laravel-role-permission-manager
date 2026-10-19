<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\DataTransferObjects;

use CreativeCrafts\LaravelRolePermissionManager\Exceptions\InvalidPermissionReferenceException;

final readonly class PermissionReference
{
    public function __construct(
        public string $slug,
        public ?string $scope,
    ) {
        if ($this->slug === '') {
            throw new InvalidPermissionReferenceException('Permission reference slug cannot be empty.');
        }
    }

    /**
     * @param array{slug?: mixed, scope?: mixed} $reference
     */
    public static function fromArray(array $reference): self
    {
        if (
            ! array_key_exists('slug', $reference)
            || ! is_string($reference['slug'])
            || ! array_key_exists('scope', $reference)
            || ($reference['scope'] !== null && ! is_string($reference['scope']))
        ) {
            throw new InvalidPermissionReferenceException(
                'A structured permission reference requires string slug and explicit string|null scope.'
            );
        }

        return new self($reference['slug'], $reference['scope']);
    }
}
