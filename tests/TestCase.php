<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Tests;

use CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManagerServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use RuntimeException;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'CreativeCrafts\\LaravelRolePermissionManager\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    public function getEnvironmentSetUp($app): void
    {
        $databaseDriver = getenv('LRPM_TEST_DB_CONNECTION') ?: 'testing';
        if ($databaseDriver === 'mysql') {
            $app['config']->set('database.connections.lrpm_mysql', [
                'driver' => 'mysql',
                'host' => getenv('LRPM_TEST_DB_HOST') ?: '127.0.0.1',
                'port' => getenv('LRPM_TEST_DB_PORT') ?: '3306',
                'database' => getenv('LRPM_TEST_DB_DATABASE') ?: 'lrpm_test',
                'username' => getenv('LRPM_TEST_DB_USERNAME') ?: 'root',
                'password' => getenv('LRPM_TEST_DB_PASSWORD') ?: '',
                'unix_socket' => '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_bin',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
            ]);
            $databaseDriver = 'lrpm_mysql';
        }

        if ($databaseDriver === 'pgsql') {
            $app['config']->set('database.connections.lrpm_pgsql', [
                'driver' => 'pgsql',
                'host' => getenv('LRPM_TEST_DB_HOST') ?: '127.0.0.1',
                'port' => getenv('LRPM_TEST_DB_PORT') ?: '5432',
                'database' => getenv('LRPM_TEST_DB_DATABASE') ?: 'lrpm_test',
                'username' => getenv('LRPM_TEST_DB_USERNAME') ?: 'postgres',
                'password' => getenv('LRPM_TEST_DB_PASSWORD') ?: '',
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ]);
            $databaseDriver = 'lrpm_pgsql';
        }

        $app['config']->set('database.default', $databaseDriver);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('role-permission-manager', require __DIR__ . '/../config/role-permission-manager.php');
        $app['config']->set(
            'role-permission-manager.user_model',
            Models\TestUser::class,
        );
        $app['config']->set('role-permission-manager.cache_store', 'array');

        if (in_array($databaseDriver, ['lrpm_mysql', 'lrpm_pgsql'], true)) {
            $databaseName = (string) $app['config']->get("database.connections.{$databaseDriver}.database");
            if (! str_ends_with($databaseName, '_test')) {
                throw new RuntimeException('Integration database name must end with _test.');
            }

            Schema::disableForeignKeyConstraints();
            foreach ([
                'custom_role_permissions',
                'custom_user_permissions',
                'custom_user_roles',
                'custom_permissions',
                'custom_roles',
                'legacy_permissions',
                'uuid_user_roles',
                'uuid_user_permissions',
                'uuid_role_permissions',
                'uuid_permissions',
                'uuid_roles',
                'uuid_users',
                'increment_user_roles',
                'increment_user_permissions',
                'increment_role_permissions',
                'increment_permissions',
                'increment_roles',
                'increment_users',
                'int4_user_roles',
                'int4_user_permissions',
                'int4_role_permissions',
                'int4_permissions',
                'int4_roles',
                'int4_users',
                'int2_user_roles',
                'int2_user_permissions',
                'int2_role_permissions',
                'int2_permissions',
                'int2_roles',
                'int2_users',
                'string_key_users',
                config('role-permission-manager.user_permission_table'),
                config('role-permission-manager.role_permission_table'),
                config('role-permission-manager.user_role_table'),
                config('role-permission-manager.permissions_table'),
                config('role-permission-manager.roles_table'),
                config('role-permission-manager.users_table', 'users'),
            ] as $table) {
                Schema::dropIfExists($table);
            }
            Schema::enableForeignKeyConstraints();
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->nullable()->unique();
            });
        }

        $baseMigration = include __DIR__ . '/../database/migrations/create_role_permission_manager_tables.php';
        $baseMigration->up();

        $identityMigration = include __DIR__ . '/../database/migrations/add_permission_identity_keys.php';
        $identityMigration->up();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelRolePermissionManagerServiceProvider::class,
        ];
    }
}
