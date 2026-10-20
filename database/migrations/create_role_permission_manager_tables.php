<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Create roles table
        Schema::create(config('role-permission-manager.roles_table'), function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on(config('role-permission-manager.roles_table'))->onDelete(
                'set null'
            );
        });

        // Create permissions table
        Schema::create(config('role-permission-manager.permissions_table'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('scope')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['name', 'scope']);
            $table->unique(['slug', 'scope']);
        });

        // Create role_user table
        Schema::create(config('role-permission-manager.user_role_table'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $this->addUserForeignKey($table);
            $table->timestamps();

            $table->foreign('role_id')->references('id')->on(config('role-permission-manager.roles_table'))->onDelete(
                'cascade'
            );
            $table->foreign(config('role-permission-manager.user_foreign_key', 'user_id'))
                ->references(config('role-permission-manager.user_model_key', 'id'))
                ->on(config('role-permission-manager.users_table', 'users'))
                ->onDelete('cascade');

            $table->unique(['role_id', config('role-permission-manager.user_foreign_key', 'user_id')]);
        });

        // Create permission_role table
        Schema::create(config('role-permission-manager.role_permission_table'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();

            $table->foreign('permission_id')->references('id')->on(
                config('role-permission-manager.permissions_table')
            )->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on(config('role-permission-manager.roles_table'))->onDelete(
                'cascade'
            );

            $table->unique(['permission_id', 'role_id']);
        });

        // Create user_permission table
        Schema::create(config('role-permission-manager.user_permission_table'), function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('permission_id');
            $this->addUserForeignKey($table);
            $table->timestamps();

            $table->foreign('permission_id')->references('id')->on(
                config('role-permission-manager.permissions_table')
            )->onDelete('cascade');
            $table->foreign(config('role-permission-manager.user_foreign_key', 'user_id'))
                ->references(config('role-permission-manager.user_model_key', 'id'))
                ->on(config('role-permission-manager.users_table', 'users'))
                ->onDelete('cascade');

            $table->unique(['permission_id', config('role-permission-manager.user_foreign_key', 'user_id')]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('role-permission-manager.user_permission_table'));
        Schema::dropIfExists(config('role-permission-manager.role_permission_table'));
        Schema::dropIfExists(config('role-permission-manager.user_role_table'));
        Schema::dropIfExists(config('role-permission-manager.permissions_table'));
        Schema::dropIfExists(config('role-permission-manager.roles_table'));
    }

    private function addUserForeignKey(Blueprint $table): void
    {
        $column = (string) config('role-permission-manager.user_foreign_key', 'user_id');
        $usersTable = (string) config('role-permission-manager.users_table', 'users');
        $userKey = (string) config('role-permission-manager.user_model_key', 'id');
        $definition = null;

        if (Schema::hasTable($usersTable) && Schema::hasColumn($usersTable, $userKey)) {
            $definition = collect(Schema::getColumns($usersTable))->firstWhere('name', $userKey);
        }

        if ($definition === null) {
            $table->unsignedBigInteger($column);

            return;
        }

        $typeName = strtolower((string) ($definition['type_name'] ?? ''));
        $type = strtolower((string) ($definition['type'] ?? ''));
        $length = $this->characterLength($type);
        $integerColumn = $this->integerColumnMethod($typeName, str_contains($type, 'unsigned'));

        if ($integerColumn !== null) {
            $table->{$integerColumn}($column);

            return;
        }

        if (in_array($typeName, ['uuid', 'guid'], true)) {
            $table->uuid($column);

            return;
        }

        if ($typeName === 'ulid') {
            $table->ulid($column);

            return;
        }

        if (in_array($typeName, ['char', 'bpchar'], true)) {
            $table->char($column, $length ?? 36);

            return;
        }

        if (in_array($typeName, ['varchar', 'string', 'character varying'], true)) {
            $length === null ? $table->string($column) : $table->string($column, $length);

            return;
        }

        $table->unsignedBigInteger($column);
    }

    private function integerColumnMethod(string $typeName, bool $unsigned): ?string
    {
        return match ($typeName) {
            'tinyint' => $unsigned ? 'unsignedTinyInteger' : 'tinyInteger',
            'smallint', 'int2' => $unsigned ? 'unsignedSmallInteger' : 'smallInteger',
            'mediumint' => $unsigned ? 'unsignedMediumInteger' : 'mediumInteger',
            'int', 'integer', 'int4' => $unsigned ? 'unsignedInteger' : 'integer',
            'bigint', 'int8' => $unsigned ? 'unsignedBigInteger' : 'bigInteger',
            default => null,
        };
    }

    private function characterLength(string $type): ?int
    {
        if (preg_match('/\((\d+)\)/', $type, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
};
