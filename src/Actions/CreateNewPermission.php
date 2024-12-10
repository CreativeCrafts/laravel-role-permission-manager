<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Actions;

use CreativeCrafts\LaravelRolePermissionManager\Contracts\CreateNewPermissionContract;
use CreativeCrafts\LaravelRolePermissionManager\DataTransferObjects\PermissionData;
use CreativeCrafts\LaravelRolePermissionManager\Exceptions\UnableToCreatePermissionException;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * This class is responsible for creating a new permission in the system.
 */
final class CreateNewPermission implements CreateNewPermissionContract
{
    /**
     * @param PermissionData $permissionData The data transfer object containing permission information.
     */
    public function __construct(
        protected PermissionData $permissionData
    ) {
    }

    /**
     * Invoke the creation of a new permission.
     * This method attempts to create a new permission using the provided permission data.
     * If successful, it returns the newly created Permission model.
     * If an exception occurs during creation, it throws an UnableToCreatePermissionException.
     *
     * @return Permission The newly created Permission model.
     * @throws UnableToCreatePermissionException If the permission creation fails.
     */
    public function __invoke(): Permission
    {
        try {
            return Permission::query()->create(
                $this->permissionData->toArray()
            );
        } catch (Exception $exception) {
            $errorCode = is_int($exception->getCode()) ? $exception->getCode() : Response::HTTP_INTERNAL_SERVER_ERROR;
            throw new UnableToCreatePermissionException(
                $exception->getMessage(),
                $errorCode
            );
        }
    }
}
