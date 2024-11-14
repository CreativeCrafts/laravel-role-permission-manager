<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Commands;

use CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManager;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Exception;
use Illuminate\Console\Command;

class CreateRoleCommand extends Command
{
    protected LaravelRolePermissionManager $roleManager;

    protected $signature = 'role-permission:create-role 
                            {name : The name of the role}
                            {slug : The slug of the role}
                            {description? : The description of the role}
                            {--parent= : The slug of the parent role}';

    protected $description = 'Create a new role';

    public function __construct(LaravelRolePermissionManager $roleManager)
    {
        parent::__construct();
        $this->roleManager = $roleManager;
    }

    public function handle(): int
    {
        $name = $this->argument('name');
        $slug = $this->argument('slug');
        $description = $this->argument('description');
        $parentSlug = $this->option('parent');

        try {
            $parentRole = null;
            if ($parentSlug) {
                $parentRole = $this->roleManager->getRoleBySlug($parentSlug);
                if (! $parentRole instanceof Role) {
                    $this->error("Parent role with slug '{$parentSlug}' not found.");
                    return self::FAILURE;
                }
            }

            $role = $this->roleManager->createRole($name, $slug, $description, $parentRole);
            $this->info("Role '{$role->name}' created successfully.");
            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error("Failed to create role: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
