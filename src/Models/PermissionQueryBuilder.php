<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Models;

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
