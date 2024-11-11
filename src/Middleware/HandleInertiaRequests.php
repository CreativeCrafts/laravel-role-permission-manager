<?php

namespace CreativeCrafts\LaravelRolePermissionManager\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HandleInertiaRequests
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($this->isInertiaRequest($request)) {
            $this->sharePermissions($request);
        }

        return $response;
    }

    private function isInertiaRequest(Request $request): bool
    {
        return $request->header('X-Inertia') === 'true';
    }

    private function sharePermissions(Request $request): void
    {
        if (class_exists('Inertia\Inertia')) {
            \Inertia\Inertia::share([
                'auth' => [
                    'permissions' => $this->getUserPermissions($request),
                ],
            ]);
        }
    }

    private function getUserPermissions(Request $request): array
    {
        $user = $request->user();

        if (!$user) {
            return [];
        }

        return Cache::remember("user_permissions_{$user->id}", now()->addMinutes(60), function () use ($user) {
            return $user->getAllPermissions()->pluck('slug')->toArray();
        });
    }
}