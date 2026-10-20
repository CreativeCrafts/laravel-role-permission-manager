<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\Permissions\PermissionIdentity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private const NAME_INDEX = 'lprm_perm_name_scope_key_uq';
    private const SLUG_INDEX = 'lprm_perm_slug_scope_key_uq';
    private const POSTGRES_FUNCTION = 'lprm_permission_identity_keys';

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

        if (! ($hasNameKey && $hasSlugKey && $this->identitySchemaIsComplete($tableName))) {
            $this->assertNoDuplicateIdentities($tableName);

            if (! $hasNameKey && ! $hasSlugKey) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $this->addIdentityColumn($table, 'name_scope_key');
                    $this->addIdentityColumn($table, 'slug_scope_key');
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

        $this->ensureIdentityTriggers($tableName);
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

        $this->dropIdentityTriggers($tableName);

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
            $type = 'varchar(' . PermissionIdentity::LENGTH . ') character set utf8mb4 collate utf8mb4_bin not null';
            DB::statement(
                "alter table {$table} modify name_scope_key {$type}, modify slug_scope_key {$type}"
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

        if (
            $driver === 'sqlite'
            && (
                ! $this->identityColumnsAreRequired($tableName)
                || $this->identityColumnsHaveDefaults($tableName)
            )
        ) {
            $this->rebuildSqliteIdentityColumns($tableName);
        }
    }

    private function identitySchemaIsComplete(string $tableName): bool
    {
        return Schema::hasIndex($tableName, self::NAME_INDEX)
            && Schema::hasIndex($tableName, self::SLUG_INDEX)
            && $this->identityColumnsAreRequired($tableName)
            && ! $this->identityColumnsHaveDefaults($tableName);
    }

    private function identityColumnsHaveDefaults(string $tableName): bool
    {
        $columns = collect(Schema::getColumns($tableName))->keyBy('name');

        foreach (['name_scope_key', 'slug_scope_key'] as $column) {
            if (($columns->get($column)['default'] ?? null) !== null) {
                return true;
            }
        }

        return false;
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
            if ($index['primary']) {
                continue;
            }

            $indexName = str_starts_with($index['name'], 'sqlite_autoindex_') ? null : $index['name'];
            Schema::table($tableName, function (Blueprint $table) use ($index, $indexName): void {
                if ($index['unique']) {
                    $indexName === null
                        ? $table->unique($index['columns'])
                        : $table->unique($index['columns'], $indexName);

                    return;
                }

                $indexName === null
                    ? $table->index($index['columns'])
                    : $table->index($index['columns'], $indexName);
            });
        }
    }

    private function identityKey(string $field, string $value, mixed $scope): string
    {
        return PermissionIdentity::key($field, $value, $scope);
    }

    private function addIdentityColumn(Blueprint $table, string $name): void
    {
        $column = $table->string($name, PermissionIdentity::LENGTH)->nullable(false)->default('');
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $column->collation('utf8mb4_bin');
        } elseif ($driver === 'pgsql') {
            $column->collation('C');
        }
    }

    private function ensureIdentityTriggers(string $tableName): void
    {
        $driver = DB::connection()->getDriverName();
        $wrappedTable = DB::connection()->getSchemaGrammar()->wrapTable($tableName);
        $insertTrigger = $this->triggerName($tableName, 'insert');
        $updateTrigger = $this->triggerName($tableName, 'update');

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->dropTrigger($driver, $insertTrigger, $wrappedTable);
            $this->dropTrigger($driver, $updateTrigger, $wrappedTable);
            $assignments = 'SET NEW.name_scope_key = ' . PermissionIdentity::sql($driver, 'name')
                . ', NEW.slug_scope_key = ' . PermissionIdentity::sql($driver, 'slug');
            DB::unprepared(
                "CREATE TRIGGER {$insertTrigger} BEFORE INSERT ON {$wrappedTable} FOR EACH ROW {$assignments}"
            );
            DB::unprepared(
                "CREATE TRIGGER {$updateTrigger} BEFORE UPDATE ON {$wrappedTable} FOR EACH ROW {$assignments}"
            );

            return;
        }

        if ($driver === 'pgsql') {
            DB::unprepared($this->postgresIdentityFunction());
            $this->dropTrigger($driver, $insertTrigger, $wrappedTable);
            DB::unprepared(
                "CREATE TRIGGER {$insertTrigger} BEFORE INSERT OR UPDATE ON {$wrappedTable} FOR EACH ROW EXECUTE PROCEDURE "
                . self::POSTGRES_FUNCTION . '()'
            );

            return;
        }

        if ($driver === 'sqlite') {
            $this->dropTrigger($driver, $insertTrigger, $wrappedTable);
            $this->dropTrigger($driver, $updateTrigger, $wrappedTable);
            $nameSql = PermissionIdentity::sql($driver, 'name');
            $slugSql = PermissionIdentity::sql($driver, 'slug');
            $assignment = "name_scope_key = {$nameSql}, slug_scope_key = {$slugSql}";
            DB::unprepared(
                "CREATE TRIGGER {$insertTrigger} AFTER INSERT ON {$wrappedTable} FOR EACH ROW WHEN NEW.name_scope_key IS NOT ({$nameSql}) OR NEW.slug_scope_key IS NOT ({$slugSql}) BEGIN UPDATE {$wrappedTable} SET {$assignment} WHERE id = NEW.id; END"
            );
            DB::unprepared(
                "CREATE TRIGGER {$updateTrigger} AFTER UPDATE ON {$wrappedTable} FOR EACH ROW WHEN NEW.name_scope_key IS NOT ({$nameSql}) OR NEW.slug_scope_key IS NOT ({$slugSql}) BEGIN UPDATE {$wrappedTable} SET {$assignment} WHERE id = NEW.id; END"
            );

            return;
        }

        throw new RuntimeException(
            "Permission identity triggers are not implemented for the {$driver} driver."
        );
    }

    private function dropIdentityTriggers(string $tableName): void
    {
        $driver = DB::connection()->getDriverName();
        $wrappedTable = DB::connection()->getSchemaGrammar()->wrapTable($tableName);
        $this->dropTrigger($driver, $this->triggerName($tableName, 'insert'), $wrappedTable);
        $this->dropTrigger($driver, $this->triggerName($tableName, 'update'), $wrappedTable);

        if ($driver === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS ' . self::POSTGRES_FUNCTION . '()');
        }
    }

    private function dropTrigger(string $driver, string $trigger, string $wrappedTable): void
    {
        if ($driver === 'pgsql') {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger} ON {$wrappedTable}");

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }

    private function triggerName(string $tableName, string $event): string
    {
        return 'lprm_pi_' . substr(hash('sha256', $tableName . '|' . $event), 0, 16);
    }

    private function postgresIdentityFunction(): string
    {
        $nameSql = PermissionIdentity::sql('pgsql', 'name');
        $slugSql = PermissionIdentity::sql('pgsql', 'slug');

        return 'CREATE OR REPLACE FUNCTION ' . self::POSTGRES_FUNCTION . '() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN '
            . "NEW.name_scope_key := {$nameSql}; "
            . "NEW.slug_scope_key := {$slugSql}; "
            . 'RETURN NEW; END $$';
    }
};
