<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Actions;

use CreativeCrafts\LaravelRolePermissionManager\Contracts\CreateNewRoleContract;
use CreativeCrafts\LaravelRolePermissionManager\DataTransferObjects\RoleData;
use CreativeCrafts\LaravelRolePermissionManager\Exceptions\UnableToCreateRoleException;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * This class is responsible for creating a new role in the system.
 */
final class CreateNewRole implements CreateNewRoleContract
{
    /**
     * @param RoleData $roleData The data transfer object containing the role information.
     */
    public function __construct(
        protected RoleData $roleData
    ) {
    }

    /**
     * Invoke the creation of a new role.
     * This method attempts to create a new role using the provided role data.
     * If a parent role is specified, it associates the new role with the parent.
     *
     * @return Role The newly created role.
     * @throws UnableToCreateRoleException If there's an error during role creation.
     */
    public function __invoke(): Role
    {
        try {
            $role = new Role($this->roleData->toArray());

            if ($this->roleData->parent() instanceof Role) {
                $role->parent()->associate($this->roleData->parent());
            }
            $role->save();
            return $role;
        } catch (Exception $exception) {
            $errorCode = is_int($exception->getCode()) ? $exception->getCode() : Response::HTTP_INTERNAL_SERVER_ERROR;
            throw new UnableToCreateRoleException(
                $exception->getMessage(),
                $errorCode
            );
        }
    }
}
