<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        $response = $next($request);

        if ($this->isInertiaRequest($request)) {
            $this->sharePermissions($request);
        }

        return $response;
    }

    /**
     * Determine if the request is an Inertia request.
     * This method checks the 'X-Inertia' header to identify Inertia requests.
     *
     * @param Request $request The incoming HTTP request
     * @return bool True if it's an Inertia request, false otherwise
     */
    private function isInertiaRequest(Request $request): bool
    {
        return $request->header('X-Inertia') === 'true';
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
            Inertia::share([
                'auth' => [
                    'permissions' => $this->getUserPermissions($request),
                ],
            ]);
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

        return Cache::remember("user_permissions_{$user->id}", now()->addMinutes(60), static function () use ($user) {
            return $user->getAllPermissions()->pluck('slug')->toArray();
        });
    }
}
