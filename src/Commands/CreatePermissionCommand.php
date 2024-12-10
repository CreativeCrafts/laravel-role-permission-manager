<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Commands;

use CreativeCrafts\LaravelRolePermissionManager\Facades\LaravelRolePermissionManager;
use Exception;
use Illuminate\Console\Command;
use function Laravel\Prompts\text;

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
        if (! $name) {
            $name = text(
                'Enter the name of the permission',
                required: '* Permission name is required',
            );
        }

        $slug = $this->argument('slug');
        if (! $slug) {
            $slug = text(
                'Enter the slug of the permission',
                required: '* Permission slug is required',
            );
        }

        $description = $this->argument('description');
        if (! $description) {
            $description = text(
                'Enter the description of the permission',
                hint: 'Optional',
            );
        }

        $scope = $this->option('scope');
        if (! $scope) {
            $scope = text(
                'Enter the scope of the permission',
                hint: 'Optional',
            );
        }

        try {
            $permission = LaravelRolePermissionManager::createPermission($name, $slug, $description, $scope);
            $this->info("Permission '{$permission->name}' created successfully.");
            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error("Failed to create permission: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
