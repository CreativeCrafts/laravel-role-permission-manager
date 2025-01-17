<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Http\Controllers;

use CreativeCrafts\LaravelRolePermissionManager\Helpers\ClassExistsWrapper;
use CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManager;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use RuntimeException;

/**
 * This controller manages operations related to roles and permissions in the Laravel Role Permission Manager.
 */
class RolePermissionController extends Controller
{
    private ClassExistsWrapper $classExistsWrapper;

    public function __construct(ClassExistsWrapper $classExistsWrapper = null)
    {
        $this->classExistsWrapper = $classExistsWrapper ?? new ClassExistsWrapper();
    }

    /**
     * Retrieve all roles.
     *
     * @return JsonResponse A JSON response containing all roles.
     */
    public function getRoles(): JsonResponse
    {
        $roles = Role::all();
        return response()->json($roles);
    }

    /**
     * Retrieve permissions for a given scope.
     *
     * @param Request $request The incoming HTTP request.
     * @param LaravelRolePermissionManager $manager The role permission manager instance.
     * @return JsonResponse A JSON response containing the permissions for the specified scope.
     */
    public function getPermissions(Request $request, LaravelRolePermissionManager $manager): JsonResponse
    {
        $scope = $request->query('scope');
        $permissions = $manager->getAllPermissionsForScope($scope);
        return response()->json($permissions);
    }

    /**
     * Retrieve roles for a specific user.
     *
     * @param int|string $userId The ID of the user.
     * @return JsonResponse A JSON response containing the user's roles.
     */
    public function getUserRoles(int|string $userId): JsonResponse
    {
        $userModel = $this->getUserModel();
        $user = $userModel::findOrFail($userId);
        $roles = $user->roles;
        return response()->json($roles);
    }

    /**
     * Retrieve all permissions for a specific user.
     *
     * @param int|string $userId The ID of the user.
     * @param Request $request The incoming HTTP request.
     * @param LaravelRolePermissionManager $manager The role permission manager instance.
     * @return JsonResponse A JSON response containing all permissions for the user.
     */
    public function getUserPermissions(
        int|string $userId,
        Request $request,
        LaravelRolePermissionManager $manager
    ): JsonResponse {
        $userModel = $this->getUserModel();
        $user = $userModel::findOrFail($userId);
        $permissions = $manager->getAllPermissionsForUser($user);
        return response()->json($permissions);
    }

    /**
     * Retrieve scoped permissions for a specific user.
     *
     * @param int|string $userId The ID of the user.
     * @param string $scope The scope of the permissions to retrieve.
     * @return JsonResponse A JSON response containing the scoped permissions for the user.
     */
    public function getScopedPermissions(int|string $userId, string $scope): JsonResponse
    {
        $userModel = $this->getUserModel();
        $user = $userModel::findOrFail($userId);
        $permissions = $user->getAllPermissions($scope);
        return response()->json($permissions);
    }

    /**
     * Get the user model class.
     *
     * @return class-string The user model class.
     */
    private function getUserModel(): string
    {
        $modelClass = Config::get('role-permission-manager.user_model')
            ?? Config::get('auth.providers.users.model', 'App\Models\User');

        if (! $this->classExistsWrapper->exists($modelClass)) {
            throw new RuntimeException("Configured user model '{$modelClass}' does not exist.");
        }

        return $modelClass;
    }
}
