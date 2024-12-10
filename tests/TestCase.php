<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Tests;

use CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManagerServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

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
        config()->set('database.default', 'testing');
        $app['config']->set('role-permission-manager', require __DIR__ . '/../config/role-permission-manager.php');

        $migration = include __DIR__ . '/../database/migrations/create_role_permission_manager_tables.php';
        $migration->up();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelRolePermissionManagerServiceProvider::class,
        ];
    }
}
