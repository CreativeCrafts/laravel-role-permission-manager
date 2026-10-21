<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Commands;

use CreativeCrafts\LaravelRolePermissionManager\DataTransferObjects\PermissionReference;
use CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManager;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Illuminate\Console\Command;
use Throwable;

class AssignPermissionCommand extends Command
{
    protected $signature = 'role-permission:assign
                            {role : Exact role slug}
                            {permission : Exact permission slug}
                            {--scope= : Exact permission scope, including an empty string or 0}
                            {--create-permission : Create the exact permission if it does not exist}';

    protected $description = 'Assign a permission to a role';

    public function __construct(private readonly LaravelRolePermissionManager $manager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $roleName = $this->argument('role');
        $permissionName = $this->argument('permission');
        $scopeWasProvided = $this->option('scope') !== null;
        $scope = $this->option('scope');
        $scope = is_string($scope) ? $scope : null;
        $reference = $scopeWasProvided
            ? new PermissionReference((string) $permissionName, $scope)
            : (string) $permissionName;

        try {
            $role = Role::where('slug', $roleName)->firstOrFail();

            try {
                $permission = $this->manager->resolvePermissionReference($reference);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
                if (! $this->option('create-permission')) {
                    throw $exception;
                }

                $permission = $this->manager->createPermission(
                    $permissionName,
                    $permissionName,
                    scope: $scope,
                );
                $this->info("Permission '{$permissionName}' created.");
            }

            $this->manager->givePermissionToRole($role, $permission);

            $this->info("Permission '{$permission->name}' assigned to role '{$role->name}' successfully.");
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error("Permission assignment failed: {$exception->getMessage()}");
            return self::FAILURE;
        }
    }
}
