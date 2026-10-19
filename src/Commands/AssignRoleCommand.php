<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Commands;

use CreativeCrafts\LaravelRolePermissionManager\Contracts\AuthenticatableWithRolesAndPermissions;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AssignRoleCommand extends Command
{
    protected $signature = 'role-permission:assign-role {user} {role}';

    protected $description = 'Assign a role to a user';

    public function handle(): int
    {
        $userIdentifier = $this->argument('user');
        $roleSlug = $this->argument('role');

        try {
            $user = $this->findUser($userIdentifier);

            /** @var Role $role */
            $role = Role::where('slug', $roleSlug)->firstOrFail();
            if (! $user instanceof AuthenticatableWithRolesAndPermissions) {
                $this->error('Configured user model must implement the package authorization contract.');
                return self::FAILURE;
            }

            $user->assignRole($role);

            $this->info("Role '{$role->name}' assigned to user '{$user->getAttribute('name')}'.");
            return self::SUCCESS;
        } catch (ModelNotFoundException $e) {
            $this->error("User or role slug '{$roleSlug}' was not found: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Find a user by ID or email.
     *
     * @param string|int $identifier The user ID or email
     * @return Model The user model instance with roles relationship
     * @throws ModelNotFoundException If the user is not found
     */
    private function findUser(string|int $identifier): Model
    {
        $userModel = config('role-permission-manager.user_model');

        $query = $userModel::query();
        $query = ctype_digit((string) $identifier)
            ? $query->whereKey($identifier)
            : $query->where('email', $identifier);

        /** @var Model $user */
        $user = $query->firstOrFail();

        return $user;
    }
}
