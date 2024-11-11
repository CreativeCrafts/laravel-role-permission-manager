<?php

namespace CreativeCrafts\LaravelRolePermissionManager\Commands;

use CreativeCrafts\LaravelRolePermissionManager\Facades\LaravelRolePermissionManager;
use Illuminate\Console\Command;

class CreateRoleCommand extends Command
{
    protected $signature = 'role-permission:create-role 
                            {name : The name of the role}
                            {slug : The slug of the role}
                            {description? : The description of the role}
                            {--parent= : The slug of the parent role}';

    protected $description = 'Create a new role';

    public function handle(): int
    {
        $name = $this->argument('name');
        $slug = $this->argument('slug');
        $description = $this->argument('description');
        $parentSlug = $this->option('parent');

        try {
            $role = LaravelRolePermissionManager::createRole($name, $slug, $description, $parentSlug);
            $this->info("Role '{$role->name}' created successfully.");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to create role: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
