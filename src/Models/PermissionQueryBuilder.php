<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Models;

use CreativeCrafts\LaravelRolePermissionManager\Cache\AuthorizationCache;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<Permission>
 */
final class PermissionQueryBuilder extends Builder
{
    /**
     * @param array<string, mixed> $values
     */
    public function update(array $values)
    {
        $updated = parent::update($values);

        if (
            $updated > 0
            && $this->changesPermissionIdentity($values)
            && app()->bound(AuthorizationCache::class)
        ) {
            app(AuthorizationCache::class)->invalidate();
        }

        return $updated;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function changesPermissionIdentity(array $values): bool
    {
        foreach (['name', 'slug', 'scope'] as $field) {
            if (array_key_exists($field, $values)) {
                return true;
            }
        }

        return false;
    }
}
