<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Exceptions;

use DomainException;
use Symfony\Component\HttpFoundation\Response;

final class UnableToCreateRoleException extends DomainException
{
    public function __construct(
        string $message = 'Unable to create role',
        int $code = Response::HTTP_INTERNAL_SERVER_ERROR
    ) {
        parent::__construct($message, $code);
    }
}
