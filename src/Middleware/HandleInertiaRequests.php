<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * This middleware is responsible for handling Inertia.js requests and sharing user permissions
 * with the frontend when using Inertia.js.
 *
 * @package CreativeCrafts\LaravelRolePermissionManager\Middleware
 */
class HandleInertiaRequests
{
    /**
     * Handle an incoming request.
     * This method processes the request, checks if it's an Inertia request,
     * and shares user permissions if applicable.
     *
     * @param Request $request The incoming HTTP request
     * @param Closure $next The next middleware in the pipeline
     * @return mixed The response after processing
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $this->sharePermissions($request);

        return $next($request);
    }

    /**
     * Share user permissions with Inertia.
     * This method shares the authenticated user's permissions with the Inertia frontend,
     * but only if the Inertia package is available.
     *
     * @param Request $request The incoming HTTP request
     */
    private function sharePermissions(Request $request): void
    {
        if (class_exists('Inertia\Inertia')) {
            Inertia::share('auth.permissions', fn (): array => $this->getUserPermissions($request));
        }
    }

    /**
     * Get the authenticated user's permissions.
     * This method retrieves the user's permissions from the cache if available,
     * or fetches them from the database and caches them for future use.
     *
     * @param Request $request The incoming HTTP request
     * @return array An array of permission slugs
     */
    private function getUserPermissions(Request $request): array
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'getAllPermissions')) {
            return [];
        }

        return $user->getAllPermissions()->map(static function ($permission): array {
            return [
                'slug' => $permission->slug,
                'scope' => $permission->scope,
            ];
        })->values()->toArray();
    }
}
