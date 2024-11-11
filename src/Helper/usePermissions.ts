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

export function usePermissions() {
  const { auth } = usePage<PageProps>().props;
  const permissions = useMemo(() => auth.permissions || [], [auth.permissions]);

  const can = useMemo(() => (permission: string, scope: string | null = null): boolean => {
    if (scope) {
      return permissions.some((p: { name: string; scope: string; }): boolean => p.name === permission && p.scope === scope);
    }
    return permissions.some((p: { name: string; }): boolean => p.name === permission);
  }, [permissions]);

  const hasAnyPermission = useMemo(() => (permissionArray: string[]): boolean => {
    return permissionArray.some(permission => can(permission));
  }, [can]);

  const hasAllPermissions = useMemo(() => (permissionArray: string[]): boolean => {
    return permissionArray.every(permission => can(permission));
  }, [can]);

  const getScopedPermissions = useMemo(() => (scope: string): Permission[] => {
    return permissions.filter((p: { scope: string; }): boolean => p.scope === scope);
  }, [permissions]);

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