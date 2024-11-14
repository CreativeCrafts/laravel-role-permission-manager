<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Commands;

use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Illuminate\Console\Command;

class ListRolesPermissionsCommand extends Command
{
    protected $signature = 'role-permission:list
                            {--roles-only : List only roles}
                            {--permissions-only : List only permissions}
                            {--per-page=15 : Number of items to show per page}';

    protected $description = 'List all roles and permissions';

    public function handle(): int
    {
        $perPage = $this->option('per-page');

        if (! $this->option('permissions-only')) {
            $this->listRoles((int) $perPage);
        }

        if (! $this->option('roles-only')) {
            $this->listPermissions((int) $perPage);
        }

        return self::SUCCESS;
    }

    private function listRoles(int $perPage): void
    {
        $roles = Role::withCount('permissions')->paginate($perPage);

        $this->info('Roles:');
        $this->table(
            ['ID', 'Name', 'Slug', 'Description', 'Permissions Count'],
            $roles->map(fn ($role): array => [
                $role->id,
                $role->name,
                $role->slug,
                $role->description,
                $role->permissions_count,
            ])
        );

        $this->info($roles->total() . ' roles in total.');
        $roles->hasPages() && $this->info('Use --page option to see more results.');
    }

    private function listPermissions(int $perPage): void
    {
        $permissions = Permission::paginate($perPage);

        $this->info('Permissions:');
        $this->table(
            ['ID', 'Name', 'Slug', 'Description'],
            $permissions->map(fn ($permission): array => [
                $permission->id,
                $permission->name,
                $permission->slug,
                $permission->description,
            ])
        );

        $this->info($permissions->total() . ' permissions in total.');
        $permissions->hasPages() && $this->info('Use --page option to see more results.');
    }
}
