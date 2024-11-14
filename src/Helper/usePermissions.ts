// @ts-ignore
import {usePage} from '@inertiajs/react';
// @ts-ignore
import {useMemo} from 'react';

interface Permission {
  name: string;
  scope?: string;
}

interface PageProps {
  auth: {
    permissions: Permission[];
  };
}

/**
 * A custom hook that provides utility functions for managing and checking user permissions.
 *
 * This hook retrieves the user's permissions from the page props and provides several
 * memoized functions to check and manage these permissions efficiently.
 *
 * @returns An object containing the following functions:
 *   - can: Checks if the user has a specific permission, optionally within a scope.
 *   - hasAnyPermission: Checks if the user has any of the given permissions.
 *   - hasAllPermissions: Checks if the user has all the given permissions.
 *   - getScopedPermissions: Retrieves all permissions for a given scope.
 *   - hasAnyPermissionInScope: Checks if the user has any permission in a given scope.
 */
export function usePermissions() {
  const { auth } = usePage<PageProps>().props;
  const permissions = useMemo(() => auth.permissions || [], [auth.permissions]);

  /**
   * Checks if the user has a specific permission, optionally within a scope.
   *
   * @param permission - The name of the permission to check.
   * @param scope - Optional. The scope of the permission.
   * @returns A boolean indicating whether the user has the specified permission.
   */
  const can = useMemo(() => (permission: string, scope: string | null = null): boolean => {
    if (scope) {
      return permissions.some((p: { name: string; scope: string; }): boolean => p.name === permission && p.scope === scope);
    }
    return permissions.some((p: { name: string; }): boolean => p.name === permission);
  }, [permissions]);

  /**
   * Checks if the user has any of the given permissions.
   *
   * @param permissionArray - An array of permission names to check.
   * @returns A boolean indicating whether the user has any of the specified permissions.
   */
  const hasAnyPermission = useMemo(() => (permissionArray: string[]): boolean => {
    return permissionArray.some(permission => can(permission));
  }, [can]);

  /**
   * Checks if the user has all the given permissions.
   *
   * @param permissionArray - An array of permission names to check.
   * @returns A boolean indicating whether the user has all the specified permissions.
   */
  const hasAllPermissions = useMemo(() => (permissionArray: string[]): boolean => {
    return permissionArray.every(permission => can(permission));
  }, [can]);

  /**
   * Retrieves all permissions for a given scope.
   *
   * @param scope - The scope to filter permissions by.
   * @returns An array of Permission objects that match the given scope.
   */
  const getScopedPermissions = useMemo(() => (scope: string): Permission[] => {
    return permissions.filter((p: { scope: string; }): boolean => p.scope === scope);
  }, [permissions]);

  /**
   * Checks if the user has any permission in a given scope.
   *
   * @param scope - The scope to check for permissions.
   * @returns A boolean indicating whether the user has any permission in the specified scope.
   */
  const hasAnyPermissionInScope = useMemo(() => (scope: string): boolean => {
    return permissions.some((p: { scope: string; }): boolean => p.scope === scope);
  }, [permissions]);

  return {
    can,
    hasAnyPermission,
    hasAllPermissions,
    getScopedPermissions,
    hasAnyPermissionInScope
  };
}