<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Commands;

use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Illuminate\Console\Command;

class AssignRoleCommand extends Command
{
    protected $signature = 'role-permission:assign-role {user} {role}';

    protected $description = 'Assign a role to a user';

    public function handle(): int
    {
        $userIdentifier = $this->argument('user');
        $roleName = $this->argument('role');

        $user = $this->findUser($userIdentifier);
        $role = Role::where('name', $roleName)->orWhere('slug', $roleName)->firstOrFail();

        $user->roles()->syncWithoutDetaching([$role->id]);

        $this->info("Role '{$role->name}' assigned to user '{$user->name}'.");
        return 0;
    }

    private function findUser($identifier)
    {
        $userModel = config('role-permission-manager.user_model');
        return $userModel::where('id', $identifier)->orWhere('email', $identifier)->firstOrFail();
    }
}
