<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Commands;

use CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManager;
use CreativeCrafts\LaravelRolePermissionManager\DataTransferObjects\PermissionReference;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Throwable;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'role-permission:sync 
                            {role : The slug or ID of the role}
                            {permissions* : The slug(s) or ID(s) of the permissions to sync}
                            {--use-ids : Use IDs instead of slugs for role and permissions}
                            {--scope= : Exact scope for every slug, including an empty string or 0}
                            {--clear : Explicitly remove all permissions from the role}';

    protected $description = 'Sync permissions for a role';

    public function __construct(private readonly LaravelRolePermissionManager $manager)
    {
        parent::__construct();
    }

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
        $useIds = (bool) $this->option('use-ids');
        $clear = (bool) $this->option('clear');

        if ($clear && $permissionIdentifiers !== []) {
            $this->error('The --clear option cannot be combined with permission identifiers.');
            return self::FAILURE;
        }

        if (! $clear && $permissionIdentifiers === []) {
            $this->error('Provide permissions or use --clear explicitly.');
            return self::FAILURE;
        }

        $role = $this->getRole($roleIdentifier, $useIds);
        if (! $role instanceof Role) {
            return self::FAILURE;
        }

        try {
            $permissions = $this->getPermissions($permissionIdentifiers, $useIds);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        if (! $this->confirmSync($role, $permissions, $clear)) {
            return self::FAILURE;
        }

        try {
            $result = $this->manager->syncPermissions($role, $permissions, $clear);
            $this->displayResults($role, $result);
        } catch (Throwable $exception) {
            $this->error("Permission synchronization failed: {$exception->getMessage()}");
            return self::FAILURE;
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
        $scopeWasProvided = $this->option('scope') !== null;
        $scope = $this->option('scope');

        foreach ($identifiers as $identifier) {
            if ($useIds) {
                if (! ctype_digit((string) $identifier) || (int) $identifier < 1) {
                    throw new InvalidArgumentException("Permission ID '{$identifier}' is invalid.");
                }
                $reference = (int) $identifier;
            } elseif ($scopeWasProvided) {
                $reference = new PermissionReference((string) $identifier, is_string($scope) ? $scope : null);
            } else {
                $reference = (string) $identifier;
            }

            $permissions[] = $this->manager->resolvePermissionReference($reference);
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
    private function confirmSync(Role $role, array $permissions, bool $clear): bool
    {
        if ($clear) {
            return $this->confirm(
                "Are you sure you want to remove all permissions from the role '{$role->name}'?"
            );
        }

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
            $this->info('Added permissions: ' . $this->formatDescriptors($result['attached']));
        }
        if (! empty($result['detached'])) {
            $this->info('Removed permissions: ' . $this->formatDescriptors($result['detached']));
        }
        if (empty($result['attached']) && empty($result['detached'])) {
            $this->info("No changes were made.");
        }
    }

    private function formatDescriptors(array $descriptors): string
    {
        return implode(', ', array_map(
            static fn (array $descriptor): string => sprintf(
                '%s[%s]',
                $descriptor['slug'],
                $descriptor['scope'] === null ? 'null' : $descriptor['scope'],
            ),
            $descriptors,
        ));
    }
}
