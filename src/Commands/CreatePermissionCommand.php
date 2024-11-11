<?php

namespace CreativeCrafts\LaravelRolePermissionManager\Commands;

use CreativeCrafts\LaravelRolePermissionManager\Facades\LaravelRolePermissionManager;
use Illuminate\Console\Command;

class CreatePermissionCommand extends Command
{
    protected $signature = 'role-permission:create-permission 
                            {name : The name of the permission} 
                            {slug : The slug of the permission} 
                            {description? : The description of the permission}
                            {--scope= : The scope of the permission}';

    protected $description = 'Create a new permission';

    public function handle(): int
    {
        $name = $this->argument('name');
        $slug = $this->argument('slug');
        $description = $this->argument('description');
        $scope = $this->option('scope');

        try {
            $permission = LaravelRolePermissionManager::createPermission($name, $slug, $description, $scope);
            $this->info("Permission '{$permission->name}' created successfully.");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to create permission: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
