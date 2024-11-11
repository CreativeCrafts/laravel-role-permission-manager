<?php

namespace CreativeCrafts\LaravelRolePermissionManager\Commands;

use CreativeCrafts\LaravelRolePermissionManager\Facades\LaravelRolePermissionManager;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Illuminate\Console\Command;

class RemovePermissionCommand extends Command
{
    protected $signature = 'role-permission:remove 
                            {role : The slug or ID of the role}
                            {permissions* : The slug(s) or ID(s) of the permission(s) to remove}
                            {--use-ids : Use IDs instead of slugs for role and permissions}';

    protected $description = 'Remove one or more permissions from a role';

    public function handle(): int
    {
        $roleIdentifier = $this->argument('role');
        $permissionIdentifiers = $this->argument('permissions');
        $useIds = $this->option('use-ids');

        $role = $this->getRole($roleIdentifier, $useIds);
        if (!$role) {
            return self::FAILURE;
        }

        $permissions = $this->getPermissions($permissionIdentifiers, $useIds);
        if (empty($permissions)) {
            return self::FAILURE;
        }

        if (!$this->confirmRemoval($role, $permissions)) {
            return self::FAILURE;
        }

        foreach ($permissions as $permission) {
            LaravelRolePermissionManager::revokePermissionFromRole($role, $permission);
            $this->info("Permission '{$permission->name}' removed from role '{$role->name}' successfully.");
        }

        return self::SUCCESS;
    }

    private function getRole($identifier, $useIds): ?string
    {
        $role = $useIds ? Role::find($identifier) : Role::where('slug', $identifier)->first();
        if (!$role) {
            $this->error("Role not found.");
            return null;
        }
        return $role;
    }

    private function getPermissions($identifiers, $useIds): array
    {
        $permissions = [];
        foreach ($identifiers as $identifier) {
            $permission = $useIds ? Permission::find($identifier) : Permission::where('slug', $identifier)->first();
            if (!$permission) {
                $this->error("Permission '{$identifier}' not found.");
                continue;
            }
            $permissions[] = $permission;
        }
        return $permissions;
    }

    private function confirmRemoval($role, $permissions): bool
    {
        $permissionNames = implode(', ', array_map(fn($p) => $p->name, $permissions));
        return $this->confirm(
            "Are you sure you want to remove the following permissions from the role '{$role->name}'?\n{$permissionNames}"
        );
    }
}