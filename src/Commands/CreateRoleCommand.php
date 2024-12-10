<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Commands;

use CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManager;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Exception;
use Illuminate\Console\Command;
use function Laravel\Prompts\text;

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
        if (! $name) {
            $name = text(
                'Enter the name of the role',
                required: '* Role name is required',
            );
        }
        $slug = $this->argument('slug');
        if (! $slug) {
            $slug = text(
                'Enter the slug of the role',
                required: '* Role slug is required',
            );
        }
        $description = $this->argument('description');

        if (! $description) {
            $description = text(
                'Enter the description of the role',
                hint: 'Optional',
            );
        }
        $parentSlug = $this->option('parent');
        if (! $parentSlug) {
            $parentSlug = text(
                'Enter the slug of the parent role',
                hint: 'Optional',
            );
        }

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
