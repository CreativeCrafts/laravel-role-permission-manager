<?php

namespace CreativeCrafts\LaravelRolePermissionManager;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use CreativeCrafts\LaravelRolePermissionManager\Commands\LaravelRolePermissionManagerCommand;

class LaravelRolePermissionManagerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-role-permission-manager')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_role_permission_manager_table')
            ->hasCommand(LaravelRolePermissionManagerCommand::class);
    }
}
