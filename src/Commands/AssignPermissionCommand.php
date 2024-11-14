<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Commands;

use CreativeCrafts\LaravelRolePermissionManager\Facades\LaravelRolePermissionManager;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AssignPermissionCommand extends Command
{
    protected $signature = 'role-permission:assign {role} {permission} {--create-permission : Create the permission if it doesn\'t exist}';

    protected $description = 'Assign a permission to a role';

    public function handle(): int
    {
        $roleName = $this->argument('role');
        $permissionName = $this->argument('permission');

        try {
            $role = Role::where('slug', $roleName)->firstOrFail();
            $permission = Permission::where('slug', $permissionName)->first();

            if (! $permission && $this->option('create-permission')) {
                $permission = Permission::create([
                    'name' => $permissionName,
                    'slug' => $permissionName,
                ]);
                $this->info("Permission '{$permissionName}' created.");
            } elseif (! $permission) {
                $this->error("Permission '{$permissionName}' not found.");
                return self::FAILURE;
            }

            LaravelRolePermissionManager::givePermissionToRole($role, $permission);

            $this->info("Permission '{$permission->name}' assigned to role '{$role->name}' successfully.");
            return self::SUCCESS;
        } catch (ModelNotFoundException $e) {
            $this->error("Role '{$roleName}' not found.");
            return self::FAILURE;
        }
    }
}
