/**
 * Helper functions for checking permissions in Vue components
 * This module provides TypeScript-friendly utility functions for managing user permissions.
 */

/**
 * Represents a single permission with a slug and an optional scope.
 */
export interface Permission {
    slug: string;
    scope?: string | null;
}

/**
 * Extends the global Window interface to include the Laravel property.
 * This allows TypeScript to recognize the window.Laravel object.
 */
declare global {
    interface Window {
        Laravel?: {
            permissions: Permission[];
        };
    }
}

const currentPermissions = (): Permission[] => window.Laravel?.permissions || [];

/**
 * Check if the user has a specific permission.
 *
 * @param {string} permission - The permission slug to check.
 * @param {string|null} scope - The scope of the permission (optional).
 * @returns {boolean} True if the user has the permission, false otherwise.
 */
export function can(permission: string, scope: string | null = null): boolean {
    return currentPermissions().some(
        p => p.slug === permission && (scope === null ? (p.scope ?? null) === null : p.scope === scope),
    );
}

/**
 * Get all permissions for a specific scope.
 *
 * @param {string} scope - The scope to filter permissions by.
 * @returns {Permission[]} An array of Permission objects matching the given scope.
 */
export function getScopedPermissions(scope: string): Permission[] {
    return currentPermissions().filter(p => p.scope === scope);
}

/**
 * Check if user has any permission in a given scope.
 *
 * @param {string} scope - The scope to check.
 * @returns {boolean} True if the user has any permission in the given scope, false otherwise.
 */
export function hasAnyPermissionInScope(scope: string): boolean {
    return currentPermissions().some(p => p.scope === scope);
}

/**
 * Check if user has all specified permissions.
 *
 * @param {string[]} permissionSlugs - Array of permission slugs to check.
 * @returns {boolean} True if the user has all specified permissions, false otherwise.
 */
export function hasAllPermissions(permissionSlugs: string[]): boolean {
    return permissionSlugs.every(slug => can(slug));
}

/**
 * Check if user has any of the specified permissions.
 *
 * @param {string[]} permissionSlugs - Array of permission slugs to check.
 * @returns {boolean} True if the user has any of the specified permissions, false otherwise.
 */
export function hasAnyPermission(permissionSlugs: string[]): boolean {
    return permissionSlugs.some(slug => can(slug));
}

/**
 * Get all unique scopes from the user's permissions.
 * This function filters out undefined scopes and returns only unique values.
 *
 * @returns {string[]} An array of unique scope strings.
 */
export function getAllScopes(): string[] {
    const scopes = currentPermissions()
        .map(p => p.scope)
        .filter((scope): scope is string => scope !== undefined && scope !== null);
    return scopes.filter((scope, index, self) => self.indexOf(scope) === index);
}