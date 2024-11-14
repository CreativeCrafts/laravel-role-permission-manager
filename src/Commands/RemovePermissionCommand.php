<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Commands;

use CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManager;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Illuminate\Console\Command;

/**
 * @method static void revokePermissionFromRole(Role $role, Permission|string $permission, ?string $scope = null)
 */
class RemovePermissionCommand extends Command
{
    protected $signature = 'role-permission:remove 
                            {role : The slug or ID of the role}
                            {permissions* : The slug(s) or ID(s) of the permission(s) to remove}
                            {--use-ids : Use IDs instead of slugs for role and permissions}';

    protected $description = 'Remove one or more permissions from a role';

    /**
     * Handle the command execution for removing permissions from a role.
     * This method processes the command arguments and options, retrieves the specified role and permissions,
     * confirms the removal with the user, and then revokes the permissions from the role.
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

        if (! $this->confirmRemoval($role, $permissions)) {
            return self::FAILURE;
        }

        foreach ($permissions as $permission) {
            (new LaravelRolePermissionManager())->revokePermissionFromRole($role, $permission);
            $this->info("Permission '{$permission->name}' removed from role '{$role->name}' successfully.");
        }

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
     * Retrieves permissions based on the given identifiers.
     * This function searches for permissions using either their IDs or slugs,
     * depending on the $useIds parameter. It logs an error message for each
     * permission that is not found.
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
     * Confirms the removal of permissions from a role with the user.
     * This function generates a confirmation message listing the permissions to be removed from the role
     * and prompts the user for confirmation before proceeding with the removal.
     *
     * @param Role $role The role from which permissions will be removed.
     * @param Permission[] $permissions An array of Permission objects to be removed from the role.
     * @return bool Returns true if the user confirms the removal, false otherwise.
     */
    private function confirmRemoval(Role $role, array $permissions): bool
    {
        $permissionNames = implode(', ', array_map(static fn ($p) => $p->name, $permissions));
        return $this->confirm(
            "Are you sure you want to remove the following permissions from the role '{$role->name}'?\n{$permissionNames}"
        );
    }
}
