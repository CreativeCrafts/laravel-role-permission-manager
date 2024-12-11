<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Exceptions;

use DomainException;
use Symfony\Component\HttpFoundation\Response;

/**
 * This class represents an exception that is thrown when the provider configuration is not found.
 *
 * @package CreativeCrafts\LaravelRolePermissionManager\Exceptions
 */
class InvalidPermissionArgumentException extends DomainException
{
    /**
     * @param string $message The error message. Defaults to 'Provider configuration not found for'.
     * @param int $code The HTTP status code for the error. Defaults to HTTP_NOT_ACCEPTABLE (406).
     * @throws DomainException If the parent constructor throws an exception.
     */
    public function __construct(
        string $message = 'The permission must be a string or a Permission object.',
        int $code = Response::HTTP_NOT_ACCEPTABLE
    ) {
        parent::__construct($message, $code);
    }
}
