<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Models;

use CreativeCrafts\LaravelRolePermissionManager\Cache\AuthorizationCache;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<Permission>
 */
final class PermissionQueryBuilder extends Builder
{
    private static bool $rewritingIdentityUpdate = false;

    /**
     * @param array<string, mixed> $values
     */
    public function update(array $values)
    {
        if (self::$rewritingIdentityUpdate || ! $this->changesPermissionIdentity($values)) {
            return parent::update($values);
        }

        if ($this->containsSqlExpression($values)) {
            $updated = parent::update($values);
            if ($updated > 0 && app()->bound(AuthorizationCache::class)) {
                app(AuthorizationCache::class)->invalidate();
            }

            return $updated;
        }

        return $this->getConnection()->transaction(function () use ($values): int {
            self::$rewritingIdentityUpdate = true;

            try {
                $updated = 0;
                foreach ($this->clone()->get() as $permission) {
                    if (! $permission instanceof Permission) {
                        continue;
                    }

                    $permission->fill($values);
                    $permission->save();
                    $updated++;
                }

                return $updated;
            } finally {
                self::$rewritingIdentityUpdate = false;
            }
        });
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

    /**
     * @param array<string, mixed> $values
     */
    private function containsSqlExpression(array $values): bool
    {
        foreach ($values as $value) {
            if ($value instanceof Expression) {
                return true;
            }
        }

        return false;
    }
}
