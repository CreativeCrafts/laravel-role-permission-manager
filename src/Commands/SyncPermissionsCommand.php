<?php

namespace CreativeCrafts\LaravelRolePermissionManager\Commands;

use CreativeCrafts\LaravelRolePermissionManager\Facades\LaravelRolePermissionManager;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Illuminate\Console\Command;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'role-permission:sync 
                            {role : The slug or ID of the role}
                            {permissions* : The slug(s) or ID(s) of the permissions to sync}
                            {--use-ids : Use IDs instead of slugs for role and permissions}';

    protected $description = 'Sync permissions for a role';

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

        if (!$this->confirmSync($role, $permissions)) {
            return self::FAILURE;
        }

        $result = LaravelRolePermissionManager::syncPermissions($role, $permissions);

        $this->displayResults($role, $result);

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

    private function confirmSync($role, $permissions): bool
    {
        $permissionNames = implode(', ', array_map(fn($p) => $p->name, $permissions));
        return $this->confirm(
            "Are you sure you want to sync the following permissions for the role '{$role->name}'?\n{$permissionNames}"
        );
    }

    private function displayResults($role, $result): void
    {
        $this->info("Permissions synced for role '{$role->name}' successfully.");
        if (!empty($result['attached'])) {
            $this->info("Added permissions: " . implode(', ', $result['attached']));
        }
        if (!empty($result['detached'])) {
            $this->info("Removed permissions: " . implode(', ', $result['detached']));
        }
        if (empty($result['attached']) && empty($result['detached'])) {
            $this->info("No changes were made.");
        }
    }
}