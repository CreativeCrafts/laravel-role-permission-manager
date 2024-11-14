<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Middleware;

use Closure;
use CreativeCrafts\LaravelRolePermissionManager\Contracts\AuthenticatableWithRolesAndPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * This middleware is responsible for checking if the authenticated user has one of the required roles
 * to access a specific route or perform a specific action.
 * It can be used in route definitions or controller constructors to protect routes or actions
 * based on user roles.
 *
 * @package CreativeCrafts\LaravelRolePermissionManager\Middleware
 */
class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * This method checks if the user is authenticated and has at least one of the specified roles.
     * If the user is not authenticated or doesn't have any of the required roles, it returns an unauthorized response.
     *
     * @param Request $request The incoming HTTP request
     * @param Closure $next The next middleware in the pipeline
     * @param string ...$roles A variadic list of role names to check against
     * @return Response The response: either the next middleware's response if authorized, or an unauthorized response
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Check if the user is authenticated
        if (! Auth::check()) {
            return $this->unauthorized($request);
        }

        $user = $request->user();

        // Ensure the user implements the correct interface
        if (! $user instanceof AuthenticatableWithRolesAndPermissions) {
            return $this->unauthorized($request);
        }

        // Check if the user has any of the specified roles
        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        // If none of the roles match, return unauthorized
        return $this->unauthorized($request);
    }

    /**
     * Generate an unauthorized response.
     *
     * This method creates an appropriate unauthorized response based on whether
     * the request expects a JSON response or not.
     *
     * @param Request $request The incoming HTTP request
     * @return Response The unauthorized response
     */
    protected function unauthorized(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Unauthorized action.',
            ], 403);
        }

        abort(403, 'Unauthorized action.');
    }
}
