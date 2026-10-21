<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Permissions;

use InvalidArgumentException;
use RuntimeException;

/**
 * Canonical permission identity stored in the unique key columns.
 *
 * The database triggers repeat this encoding. Character lengths are used
 * because MySQL CHAR_LENGTH, PostgreSQL char_length, SQLite length, and
 * mb_strlen agree on UTF-8 text.
 */
final class PermissionIdentity
{
    public const LENGTH = 600;

    public static function key(string $field, ?string $value, mixed $scope): string
    {
        self::assertField($field);
        $value ??= '';
        if ($scope !== null && ! is_string($scope)) {
            $scope = (string) $scope;
        }

        return $field
            . ':'
            . mb_strlen($value, 'UTF-8')
            . ':'
            . $value
            . ':'
            . self::scopePart(is_string($scope) ? $scope : null);
    }

    public static function sql(string $driver, string $field): string
    {
        self::assertField($field);

        return match ($driver) {
            'mysql', 'mariadb' => "CONCAT('{$field}:', CHAR_LENGTH(IFNULL(NEW.{$field}, '')), ':', IFNULL(NEW.{$field}, ''), ':', IF(NEW.scope IS NULL, '0', CONCAT('1:', CHAR_LENGTH(NEW.scope), ':', NEW.scope)))",
            'pgsql' => "'{$field}:' || char_length(coalesce(NEW.{$field}, '')) || ':' || coalesce(NEW.{$field}, '') || ':' || case when NEW.scope is null then '0' else '1:' || char_length(NEW.scope) || ':' || NEW.scope end",
            'sqlite' => "'{$field}:' || length(coalesce(NEW.{$field}, '')) || ':' || coalesce(NEW.{$field}, '') || ':' || case when NEW.scope is null then '0' else '1:' || length(NEW.scope) || ':' || NEW.scope end",
            default => throw new RuntimeException("Permission identity SQL is not implemented for the {$driver} driver."),
        };
    }

    private static function scopePart(?string $scope): string
    {
        if ($scope === null) {
            return '0';
        }

        return '1:' . mb_strlen($scope, 'UTF-8') . ':' . $scope;
    }

    private static function assertField(string $field): void
    {
        if (! in_array($field, ['name', 'slug'], true)) {
            throw new InvalidArgumentException('Permission identity fields must be name or slug.');
        }
    }
}
