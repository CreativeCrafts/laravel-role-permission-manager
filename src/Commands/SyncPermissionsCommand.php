<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Commands;

use CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManager;
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

    /**
     * Execute the console command to sync permissions for a role.
     * This method processes the command arguments and options, retrieves the specified role and permissions,
     * confirms the sync operation with the user, performs the sync, and displays the results.
     *
     * @return int Returns Command::SUCCESS on successful execution, or Command::FAILURE if an error occurs
     *             or the user cancels the operation.
     */
    public function handle(): int
    {
        $roleIdentifier = $this->argument('role');
        $permissionIdentifiers = $this->argument('permissions');
        $useIds = $this->option('use-ids');

        $role = $this->getRole($roleIdentifier, $useIds);
        if (! $role instanceof Role) {
            return self::FAILURE;
        }

        $permissions = $this->getPermissions($permissionIdentifiers, $useIds);
        if ($permissions === []) {
            return self::FAILURE;
        }

        if (! $this->confirmSync($role, $permissions)) {
            return self::FAILURE;
        }
        $result = (new LaravelRolePermissionManager())->syncPermissions($role, $permissions);
        $this->displayResults($role, $result);

        return self::SUCCESS;
    }

    /**
     * Retrieves a role based on the given identifier and search method.
     * This function searches for a role using either its ID or slug, depending on the $useIds parameter.
     * If the role is not found, it logs an error message.
     *
     * @param mixed $identifier The ID or slug of the role to retrieve.
     * @param bool $useIds Flag indicating whether to search by ID (true) or slug (false).
     * @return Role|null Returns the Role object if found, or null if not found.
     */
    private function getRole(mixed $identifier, bool $useIds): ?Role
    {
        $role = $useIds ? Role::find($identifier) : Role::where('slug', $identifier)->first();
        if (! $role) {
            $this->error("Role not found.");
            return null;
        }
        return $role;
    }

    /**
     * Retrieves permissions based on the given identifiers and search method.
     * This function searches for permissions using either their IDs or slugs,
     * depending on the $useIds parameter. If a permission is not found,
     * it logs an error message and continues to the next identifier.
     *
     * @param array $identifiers An array of permission identifiers (IDs or slugs)
     * @param bool $useIds Flag indicating whether to search by ID (true) or slug (false)
     * @return array An array of found Permission objects
     */
    private function getPermissions(array $identifiers, bool $useIds): array
    {
        $permissions = [];
        foreach ($identifiers as $identifier) {
            $permission = $useIds ? Permission::find($identifier) : Permission::where('slug', $identifier)->first();
            if (! $permission) {
                $this->error("Permission '{$identifier}' not found.");
                continue;
            }
            $permissions[] = $permission;
        }
        return $permissions;
    }

    /**
     * Confirms the sync operation with the user by displaying the role and permissions to be synced.
     * This function generates a confirmation message listing all the permissions to be synced for a given role,
     * and prompts the user for confirmation before proceeding with the sync operation.
     *
     * @param Role $role The role object for which permissions are to be synced.
     * @param array $permissions An array of Permission objects to be synced with the role.
     * @return bool Returns true if the user confirms the sync operation, false otherwise.
     */
    private function confirmSync(Role $role, array $permissions): bool
    {
        $permissionNames = implode(', ', array_map(fn ($p) => $p->name, $permissions));
        return $this->confirm(
            "Are you sure you want to sync the following permissions for the role '{$role->name}'?\n{$permissionNames}"
        );
    }

    /**
     * Display the results of the permission sync operation for a role.
     * This function outputs information about the permissions that were synced
     * for a given role. It displays messages for successfully synced permissions,
     * newly added permissions, removed permissions, and if no changes were made.
     *
     * @param Role $role The role object for which permissions were synced.
     * @param array $result An associative array containing the sync operation results.
     *                      Expected to have 'attached' and 'detached' keys with arrays of permission names.
     */
    private function displayResults(Role $role, array $result): void
    {
        $this->info("Permissions synced for role '{$role->name}' successfully.");
        if (! empty($result['attached'])) {
            $this->info("Added permissions: " . implode(', ', $result['attached']));
        }
        if (! empty($result['detached'])) {
            $this->info("Removed permissions: " . implode(', ', $result['detached']));
        }
        if (empty($result['attached']) && empty($result['detached'])) {
            $this->info("No changes were made.");
        }
    }
}
