// eslint-disable-next-line
// @ts-ignore
import {usePage} from '@inertiajs/react';
// @ts-ignore
import {useMemo} from 'react';

interface Permission {
  slug: string;
  scope?: string;
}

interface PageProps {
  auth: {
    permissions: Permission[];
  };

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  [key: string]: any;
}

type UsePermissions = {
  can: (permission: string, scope?: string | null) => boolean;
  hasAnyPermission: (permissionArray: string[]) => boolean;
  hasAllPermissions: (permissionArray: string[]) => boolean;
  getScopedPermissions: (scope: string) => Permission[];
  hasAnyPermissionInScope: (scope: string) => boolean;
};

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
export function usePermissions(): UsePermissions {
  const { auth } = usePage<PageProps>().props;
  const permissions = useMemo(() => auth.permissions || [], [auth.permissions]);

  /**
   * Checks if the user has a specific permission, optionally within a scope.
   *
   * @param permission - The name of the permission to check.
   * @param scope - Optional. The scope of the permission.
   * @returns A boolean indicating whether the user has the specified permission.
   */
  const can = useMemo(
    () =>
      (permission: string, scope: string | null = null): boolean => {
        if (scope) {
          return permissions.some(
            (p: Permission): boolean => p.slug === permission && p.scope === scope,
          );
        }

        return permissions.some((p: Permission): boolean => p.slug === permission);
      },
    [permissions],
  );

  /**
   * Checks if the user has any of the given permissions.
   *
   * @param permissionArray - An array of permission names to check.
   * @returns A boolean indicating whether the user has any of the specified permissions.
   */
  const hasAnyPermission = useMemo(
    () =>
      (permissionArray: string[]): boolean =>
        permissionArray.some(permission => can(permission)),
    [can],
  );

  /**
   * Checks if the user has all the given permissions.
   *
   * @param permissionArray - An array of permission names to check.
   * @returns A boolean indicating whether the user has all the specified permissions.
   */
  const hasAllPermissions = useMemo(
    () =>
      (permissionArray: string[]): boolean =>
        permissionArray.every(permission => can(permission)),
    [can],
  );

  const getScopedPermissions = useMemo(
    () =>
      (scope: string): Permission[] =>
        permissions.filter((p: Permission): boolean => p.scope === scope),
    [permissions],
  );

  const hasAnyPermissionInScope = useMemo(
    () =>
      (scope: string): boolean =>
        permissions.some((p: Permission): boolean => p.scope === scope),
    [permissions],
  );

  return {
    can,
    hasAnyPermission,
    hasAllPermissions,
    getScopedPermissions,
    hasAnyPermissionInScope,
  };
}
