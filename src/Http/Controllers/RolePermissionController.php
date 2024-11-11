<?php

namespace CreativeCrafts\LaravelRolePermissionManager\Http\Controllers;

use CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManager;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;

class RolePermissionController extends Controller
{
    public function getRoles(): JsonResponse
    {
        $roles = Role::all();
        return response()->json($roles);
    }

    public function getPermissions(Request $request, LaravelRolePermissionManager $manager): JsonResponse
    {
        $scope = $request->query('scope');
        $permissions = $manager->getAllPermissionsForScope($scope);
        return response()->json($permissions);
    }

    public function getUserRoles($userId): JsonResponse
    {
        $userModel = $this->getUserModel();
        $user = $userModel::findOrFail($userId);
        $roles = $user->roles;
        return response()->json($roles);
    }

    private function getUserModel()
    {
        return Config::get('auth.providers.users.model', \App\Models\User::class);
    }

    public function getUserPermissions($userId, Request $request, LaravelRolePermissionManager $manager): JsonResponse
    {
        $userModel = $this->getUserModel();
        $user = $userModel::findOrFail($userId);
        $permissions = $manager->getAllPermissionsForUser($user);
        return response()->json($permissions);
    }

    public function getScopedPermissions($userId, $scope): JsonResponse
    {
        $userModel = $this->getUserModel();
        $user = $userModel::findOrFail($userId);
        $permissions = $user->getAllPermissions($scope);
        return response()->json($permissions);
    }
}