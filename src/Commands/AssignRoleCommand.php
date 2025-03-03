<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Commands;

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
        $roleName = $this->argument('role');

        try {
            $user = $this->findUser($userIdentifier);

            /** @var Role $role */
            $role = Role::where('name', $roleName)->orWhere('slug', $roleName)->firstOrFail();
            $user->roles()->syncWithoutDetaching([$role->id]);

            $this->info("Role '{$role->name}' assigned to user '{$user->name}'.");
            return 0;
        } catch (ModelNotFoundException $e) {
            $this->error("Role '{$roleName}' not found: {$e->getMessage()}");
            return 1;
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

        /** @var Model $user */
        $user = $userModel::where('id', $identifier)
            ->orWhere('email', $identifier)
            ->firstOrFail();

        return $user;
    }
}
