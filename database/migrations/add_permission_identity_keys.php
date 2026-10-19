<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private const NAME_INDEX = 'lprm_perm_name_scope_key_uq';
    private const SLUG_INDEX = 'lprm_perm_slug_scope_key_uq';

    public function up(): void
    {
        $tableName = (string) config('role-permission-manager.permissions_table');
        $hasNameKey = Schema::hasColumn($tableName, 'name_scope_key');
        $hasSlugKey = Schema::hasColumn($tableName, 'slug_scope_key');

        if ($hasNameKey xor $hasSlugKey) {
            throw new RuntimeException(
                'Permission identity-key migration found a partial schema; restore or remove the partial change first.'
            );
        }

        if (
            $hasNameKey
            && $hasSlugKey
            && Schema::hasIndex($tableName, self::NAME_INDEX)
            && Schema::hasIndex($tableName, self::SLUG_INDEX)
            && $this->identityColumnsAreRequired($tableName)
        ) {
            return;
        }

        $this->assertNoDuplicateIdentities($tableName);

        if (! $hasNameKey && ! $hasSlugKey) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->char('name_scope_key', 64)->nullable(false)->default('');
                $table->char('slug_scope_key', 64)->nullable(false)->default('');
            });
        }

        $this->backfillIdentityKeys($tableName);
        $this->enforceRequiredIdentityColumns($tableName);

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (! Schema::hasIndex($tableName, self::NAME_INDEX)) {
                $table->unique('name_scope_key', self::NAME_INDEX);
            }

            if (! Schema::hasIndex($tableName, self::SLUG_INDEX)) {
                $table->unique('slug_scope_key', self::SLUG_INDEX);
            }
        });
    }

    public function down(): void
    {
        $tableName = (string) config('role-permission-manager.permissions_table');
        if (
            ! Schema::hasColumn($tableName, 'name_scope_key')
            || ! Schema::hasColumn($tableName, 'slug_scope_key')
        ) {
            return;
        }

        $hasNameIndex = Schema::hasIndex($tableName, self::NAME_INDEX);
        $hasSlugIndex = Schema::hasIndex($tableName, self::SLUG_INDEX);

        Schema::table($tableName, function (Blueprint $table) use ($hasNameIndex, $hasSlugIndex): void {
            if ($hasNameIndex) {
                $table->dropUnique(self::NAME_INDEX);
            }

            if ($hasSlugIndex) {
                $table->dropUnique(self::SLUG_INDEX);
            }

            $table->dropColumn(['name_scope_key', 'slug_scope_key']);
        });
    }

    private function assertNoDuplicateIdentities(string $tableName): void
    {
        $seenNames = [];
        $seenSlugs = [];
        $duplicates = [];
        foreach (DB::table($tableName)->orderBy('id')->cursor() as $permission) {
            $nameKey = $this->identityKey('name', (string) $permission->name, $permission->scope);
            $slugKey = $this->identityKey('slug', (string) $permission->slug, $permission->scope);

            if (isset($seenNames[$nameKey])) {
                $duplicates[] = "name ids {$seenNames[$nameKey]},{$permission->id}";
            } else {
                $seenNames[$nameKey] = $permission->id;
            }

            if (isset($seenSlugs[$slugKey])) {
                $duplicates[] = "slug ids {$seenSlugs[$slugKey]},{$permission->id}";
            } else {
                $seenSlugs[$slugKey] = $permission->id;
            }
        }

        if ($duplicates !== []) {
            throw new RuntimeException(
                'Permission identity preflight failed; no schema changes were made: ' . implode('; ', $duplicates)
            );
        }
    }

    private function backfillIdentityKeys(string $tableName): void
    {
        foreach (DB::table($tableName)->orderBy('id')->cursor() as $permission) {
            DB::table($tableName)->where('id', $permission->id)->update([
                'name_scope_key' => $this->identityKey('name', (string) $permission->name, $permission->scope),
                'slug_scope_key' => $this->identityKey('slug', (string) $permission->slug, $permission->scope),
            ]);
        }
    }

    private function enforceRequiredIdentityColumns(string $tableName): void
    {
        $driver = DB::connection()->getDriverName();
        $table = DB::connection()->getSchemaGrammar()->wrapTable($tableName);

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                "alter table {$table} modify name_scope_key char(64) not null, modify slug_scope_key char(64) not null"
            );

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("alter table {$table} alter column name_scope_key set not null");
            DB::statement("alter table {$table} alter column slug_scope_key set not null");
            DB::statement("alter table {$table} alter column name_scope_key drop default");
            DB::statement("alter table {$table} alter column slug_scope_key drop default");

            return;
        }

        if ($driver === 'sqlite' && ! $this->identityColumnsAreRequired($tableName)) {
            $this->rebuildSqliteIdentityColumns($tableName);
        }
    }

    private function identityColumnsAreRequired(string $tableName): bool
    {
        $columns = collect(Schema::getColumns($tableName))->keyBy('name');

        return ($columns->get('name_scope_key')['nullable'] ?? true) === false
            && ($columns->get('slug_scope_key')['nullable'] ?? true) === false;
    }

    private function rebuildSqliteIdentityColumns(string $tableName): void
    {
        $connection = DB::connection();
        $grammar = $connection->getSchemaGrammar();
        $wrapped = $grammar->wrapTable($tableName);
        $temporary = $tableName . '_lprm_identity';
        $wrappedTemporary = $grammar->wrapTable($temporary);
        $indexes = Schema::getIndexes($tableName);
        $definitions = [];
        $columnList = [];

        foreach (Schema::getColumns($tableName) as $column) {
            $name = $column['name'];
            $columnList[] = $grammar->wrap($name);
            $definition = $grammar->wrap($name) . ' ' . ($column['type'] !== '' ? $column['type'] : 'text');
            $isIdentity = in_array($name, ['name_scope_key', 'slug_scope_key'], true);

            if ($column['auto_increment'] === true) {
                $definition .= ' primary key autoincrement';
            } elseif ($isIdentity || $column['nullable'] === false) {
                $definition .= ' not null';
            }

            if (! $isIdentity && $column['default'] !== null && $column['auto_increment'] !== true) {
                $definition .= ' default ' . $column['default'];
            }

            $definitions[] = $definition;
        }

        $connection->statement('pragma foreign_keys = off');

        try {
            $connection->statement('create table ' . $wrappedTemporary . ' (' . implode(', ', $definitions) . ')');
            $connection->statement(
                'insert into ' . $wrappedTemporary . ' (' . implode(', ', $columnList) . ')'
                . ' select ' . implode(', ', $columnList) . ' from ' . $wrapped
            );
            $connection->statement('drop table ' . $wrapped);
            $connection->statement('alter table ' . $wrappedTemporary . ' rename to ' . $wrapped);
        } finally {
            $connection->statement('pragma foreign_keys = on');
        }

        foreach ($indexes as $index) {
            if ($index['primary'] || str_starts_with($index['name'], 'sqlite_autoindex_')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($index): void {
                if ($index['unique']) {
                    $table->unique($index['columns'], $index['name']);

                    return;
                }

                $table->index($index['columns'], $index['name']);
            });
        }
    }

    private function identityKey(string $field, string $value, mixed $scope): string
    {
        $scopeTag = $scope === null ? "null\0" : "string\0{$scope}";

        return hash('sha256', "{$field}\0{$value}\0{$scopeTag}");
    }
};
