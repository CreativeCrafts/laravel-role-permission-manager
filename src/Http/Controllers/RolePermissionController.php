<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Http\Controllers;

use CreativeCrafts\LaravelRolePermissionManager\Contracts\AuthenticatableWithRolesAndPermissions;
use CreativeCrafts\LaravelRolePermissionManager\Contracts\MetadataAccessAuthorizer;
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

    private MetadataAccessAuthorizer $metadataAccessAuthorizer;

    public function __construct(
        ?ClassExistsWrapper $classExistsWrapper = null,
        ?MetadataAccessAuthorizer $metadataAccessAuthorizer = null,
    ) {
        $this->classExistsWrapper = $classExistsWrapper ?? new ClassExistsWrapper();
        $this->metadataAccessAuthorizer = $metadataAccessAuthorizer
            ?? app(MetadataAccessAuthorizer::class);
    }

    /**
     * Retrieve all roles.
     *
     * @return JsonResponse A JSON response containing all roles.
     */
    public function getRoles(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->metadataAccessAuthorizer->canViewRoleCatalog($actor), 403);

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
        $actor = $this->actor($request);
        abort_unless($this->metadataAccessAuthorizer->canViewPermissionCatalog($actor), 403);

        /** @var string|null $scope */
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
    public function getUserRoles(Request $request, int|string $userId): JsonResponse
    {
        $user = $this->authorizedTarget($request, $userId);
        $roles = $user->roles()->get();
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
        $user = $this->authorizedTarget($request, $userId);
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
    public function getScopedPermissions(Request $request, int|string $userId, string $scope): JsonResponse
    {
        $user = $this->authorizedTarget($request, $userId);
        $permissions = $user->getAllPermissions($scope);
        return response()->json($permissions);
    }

    private function actor(Request $request): AuthenticatableWithRolesAndPermissions
    {
        $actor = $request->user();
        abort_unless($actor instanceof AuthenticatableWithRolesAndPermissions, 403);

        return $actor;
    }

    private function authorizedTarget(
        Request $request,
        int|string $userId,
    ): AuthenticatableWithRolesAndPermissions {
        $actor = $this->actor($request);
        $userModel = $this->getUserModel();

        if (
            $actor::class === $userModel
            && (string) $actor->getAuthIdentifier() === (string) $userId
        ) {
            return $actor;
        }

        abort_unless($this->metadataAccessAuthorizer->canInspectOtherUsers($actor), 403);

        $target = $userModel::findOrFail($userId);
        if (! $target instanceof AuthenticatableWithRolesAndPermissions) {
            throw new RuntimeException(
                "Configured user model '{$userModel}' must implement "
                . AuthenticatableWithRolesAndPermissions::class . '.'
            );
        }

        abort_unless($this->metadataAccessAuthorizer->canInspectUser($actor, $target), 403);

        return $target;
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

        if (! is_string($modelClass) || $modelClass === '') {
            throw new RuntimeException('Configured user model must be a non-empty class name.');
        }

        if (! $this->classExistsWrapper->exists($modelClass)) {
            throw new RuntimeException("Configured user model '{$modelClass}' does not exist.");
        }

        /** @var class-string $modelClass */
        return $modelClass;
    }
}
