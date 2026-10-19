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

        if ($hasNameKey && $hasSlugKey) {
            return;
        }

        if ($hasNameKey || $hasSlugKey) {
            throw new RuntimeException(
                'Permission identity-key migration found a partial schema; restore or remove the partial change first.'
            );
        }

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

        Schema::table($tableName, function (Blueprint $table): void {
            $table->char('name_scope_key', 64)->nullable();
            $table->char('slug_scope_key', 64)->nullable();
        });

        foreach (DB::table($tableName)->orderBy('id')->cursor() as $permission) {
            DB::table($tableName)->where('id', $permission->id)->update([
                'name_scope_key' => $this->identityKey('name', (string) $permission->name, $permission->scope),
                'slug_scope_key' => $this->identityKey('slug', (string) $permission->slug, $permission->scope),
            ]);
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->unique('name_scope_key', self::NAME_INDEX);
            $table->unique('slug_scope_key', self::SLUG_INDEX);
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

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropUnique(self::NAME_INDEX);
            $table->dropUnique(self::SLUG_INDEX);
            $table->dropColumn(['name_scope_key', 'slug_scope_key']);
        });
    }

    private function identityKey(string $field, string $value, mixed $scope): string
    {
        $scopeTag = $scope === null ? "null\0" : "string\0{$scope}";

        return hash('sha256', "{$field}\0{$value}\0{$scopeTag}");
    }
};
