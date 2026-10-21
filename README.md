# Laravel Role Permission Manager

[![Latest Version on Packagist](https://img.shields.io/packagist/v/creativecrafts/laravel-role-permission-manager.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-role-permission-manager)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-role-permission-manager/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/creativecrafts/laravel-role-permission-manager/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/creativecrafts/laravel-role-permission-manager/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/creativecrafts/laravel-role-permission-manager/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/creativecrafts/laravel-role-permission-manager.svg?style=flat-square)](https://packagist.org/packages/creativecrafts/laravel-role-permission-manager)

Laravel Role Permission Manager is a powerful yet simple package designed to streamline the management of roles and
permissions in Laravel applications. It provides an intuitive interface for creating, assigning, and managing user roles
and their associated permissions, allowing developers to implement complex authorization systems with ease.

This package is perfect for applications of all sizes, from small projects to large-scale enterprise systems, offering
flexibility and scalability in handling user access control. Whether you're building a simple blog with admin roles or a
complex multi-tenant application with granular permissions, Laravel Role Permission Manager has got you covered.

## Features

- Easy integration with Laravel's authentication system
- Flexible role and permission management
- Middleware for protecting routes based on roles and permissions
- Blade directives for easy view-level access control
- Easily extendable and customizable
- Comprehensive testing suite ensuring reliability
- Clear and concise API for programmatic access control

Security-sensitive upgrades can change authorization semantics. Review [UPGRADE.md](UPGRADE.md), run the read-only
preflight command, and update published frontend assets before deploying a new major version.

## Table of Contents

1. [Getting Started](#getting-started)
    - [Installation](#installation)
    - [Configuration](#configuration)
    - [Database Migrations](#database-migrations)
2. [Usage](#usage)
    - [Models](#models)
        - [Permission Model](#permission-model)
        - [Role Model](#role-model)
    - [Middleware](#middleware)
        - [HandleInertiaRequests Middleware](#handleinertiarequests-middleware)
            - [Key Features](#key-features)
            - [How it Works](#how-it-works)
            - [Usage](#usage)
            - [Accessing Permissions in the Frontend](#accessing-permissions-in-the-frontend)
        - [PermissionMiddleware](#permissionmiddleware)
            - [Key Features](#key-features)
            - [Usage](#usage)
            - [Multiple Permissions](#multiple-permissions)
            - [Handling Unauthorized Access](#handling-unauthorized-access)
        - [RoleMiddleware](#rolemiddleware)
            - [Key Features](#key-features-1)
            - [Usage](#usage-1)
            - [Multiple Roles](#multiple-roles)
            - [Handling Unauthorized Access](#handling-unauthorized-access-1)
    - [Facade](#facade)
    - [Artisan Commands](#artisan-commands)
    - [Automatic Route Registration](#automatic-route-registration)
        - [Customizing Route Registration](#customizing-route-registration)
3. [Authorization Policies](#authorization-policies)
    - [Policy Registration](#policy-registration)
    - [Using the Policies](#using-the-policies)
        - [Available Policy Methods](#available-policy-methods)
        - [Custom Policies](#custom-policies)
4. [Frontend Integration](#frontend-integration)
    - [Accessing Permissions in the Frontend](#accessing-permissions-in-the-frontend)
    - [Vue Permission Helper Functions](#vue-permission-helper-functions)
        - [Key Functions](#key-functions)
        - [Usage in Vue Components](#usage-in-vue-components)
        - [Note on Permissions Data](#note-on-permissions-data)
    - [React Permission Hooks](#react-permission-hooks)
        - [Key Features](#key-features)
        - [Usage](#usage-2)
        - [Available Functions](#available-functions)
        - [Note on Permissions Data](#note-on-permissions-data-1)
5. [Contributing](#contributing)
6. [Security](#Security-Vulnerabilities)
7. [Credits](#credits)
8. [License](#license)

## Getting Started

### Installation

1. Install the package via Composer:

```bash
composer require creativecrafts/laravel-role-permission-manager
```

2. Publish and run the migrations:

```bash
php artisan vendor:publish --tag="role-permission-manager-migrations"
php artisan migrate
```

3. Optionally, publish the config file:

```bash
php artisan vendor:publish --tag="role-permission-manager-config"
```

## Publishing TypeScript Files

To use the TypeScript helper files in your application, you can publish them using the following command:

```bash
php artisan vendor:publish --tag="laravel-role-permission-manager-typescript"
```

### Configuration

Customize the package behavior by editing the published config file config/role-permission-manager.php.

Key configuration options:

    - user_model: Specify the User model used by your application
    - roles_table: Define the name for the roles table
    - permissions_table: Define the name for the permissions table
    - cache_expiration_time: Set the cache expiration time in minutes
    - cache_store: Select the cache store used by package authorization state
    - default_guard: Specify the default guard for role and permission checks
    - use_package_routes: If set to true, the package will register its routes automatically.
    - route_middleware: Define the middleware to be applied to the package routes
    - route_prefix: Set the prefix for the package routes
    - super_admin_role: Define the role that should be considered as a super admin
    - gate_permission_prefix: Prefix package-owned Gate abilities (defaults to `role-permission:`)
    - auto_create_permissions: Enable or disable automatic creation of permissions
    - enable_wildcard_permission: Enable or disable wildcard permissions
    - case_sensitive_permissions: Choose whether permission names should be case-sensitive

### Registering Your User Model

After installing the package, you need to register your User model. This step is crucial for the package to work
correctly with your application's user model.

The user model must use `HasRolesAndPermissions` and implement
`CreativeCrafts\LaravelRolePermissionManager\Contracts\AuthenticatableWithRolesAndPermissions`. Metadata routes and
role or permission middleware reject a model that only uses the trait.

```php
use CreativeCrafts\LaravelRolePermissionManager\Contracts\AuthenticatableWithRolesAndPermissions;
use CreativeCrafts\LaravelRolePermissionManager\Traits\HasRolesAndPermissions;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements AuthenticatableWithRolesAndPermissions
{
    use HasRolesAndPermissions;
}
```

Add the following code to your `AppServiceProvider` or a custom service provider:

```php
use CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManagerServiceProvider;

public function boot()
{
    LaravelRolePermissionManagerServiceProvider::registerUserModel(\App\Models\User::class);
}
```

### Database Migrations

The package includes migrations for creating the necessary database tables. These will be published when you run the
vendor publish command for migrations.

## Usage

### Models

#### Permission Model

The Permission model represents individual permissions in your application.

Key features:

- Automatic slug generation
- Wildcard permissions support
- Scope support

Usage example:

```php
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;

$permission = Permission::create([
    'name' => 'Edit Posts',
    'description' => 'Allow user to edit posts',
    'scope' => 'blog'
]);
```

#### Role Model

The Role model represents roles that can be assigned to users and associated with permissions.

Key features:

- Automatic slug generation
- Role hierarchy support
- Permission checking methods

Usage example:

```php
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;

$role = Role::create([
    'name' => 'Editor',
    'description' => 'Can edit and publish content'
]);

if ($role->hasPermissionTo('edit-posts')) {
    // Allow editing posts
}
```

### Middleware

#### HandleInertiaRequests Middleware

The `HandleInertiaRequests` middleware is designed to work with Inertia.js applications, sharing the authenticated
user's permissions with the frontend.

#### Key Features

1. **Inertia Request Detection**: Automatically detects if the current request is an Inertia request.

2. **Permission Sharing**: Shares the authenticated user's permissions with Inertia, making them available on the
   frontend.

3. **Current Request Data**: Resolves permissions lazily from the current authenticated request. Package cache behavior
   follows `use_cache`, `cache_store`, and `cache_expiration_time`.

#### How it Works

1. The middleware checks if the request is an Inertia request.
2. If it is, it retrieves the authenticated user's permissions.
3. These permissions are then shared with Inertia, making them available in the frontend as `auth.permissions`.

#### Usage

To use this middleware, add it to your `app/Http/Kernel.php` file in the `web` middleware group:

```php
protected $middlewareGroups = [
    'web' => [
        // ... other middleware
        \CreativeCrafts\LaravelRolePermissionManager\Middleware\HandleInertiaRequests::class,
    ],
];
```

Once added, the middleware will automatically share the authenticated user's permissions with your Inertia frontend.

#### Accessing Permissions in the Frontend

Example: In your Vue components, you can access the user's permissions like this:

```typescript
const permissions = this.$page.props.auth.permissions;

// Check if user has a specific permission
if (permissions.some(permission =>
    permission.slug === 'edit-posts' && permission.scope === null
)) {
    // User can edit posts
}
```

This middleware enhances the integration between your Laravel backend and Inertia.js frontend, making it easier to
implement permission-based UI logic.

#### PermissionMiddleware

The `PermissionMiddleware` is designed to protect routes based on user permissions. It checks if the authenticated user
has the required permission(s) before allowing access to a route.

#### Key Features

1. **Route Protection**: Allows you to specify required permissions for accessing specific routes.
2. **Multiple Permission Support**: Can check for multiple permissions, granting access if the user has any of them.
3. **Authentication Check**: Ensures the user is authenticated before checking permissions.
4. **JSON Response Support**: Returns a JSON response for unauthorized actions if the request expects JSON.

#### Usage

To use this middleware, you can add it to your routes or controllers:

1. In your `app/Http/Kernel.php`, add an alias for the middleware:

```php
protected $routeMiddleware = [
    // ... other middleware
    'permission' => \CreativeCrafts\LaravelRolePermissionManager\Middleware\PermissionMiddleware::class,
];
```

2. Use it in your routes:

```php
Route::get('/admin/posts', [PostController::class, 'index'])->middleware('permission:view-posts');
```

3. Or use it in your controllers:

```php
public function __construct()
{
    $this->middleware('permission:edit-posts')->only('edit', 'update');
}
```

#### Multiple Permissions

You can specify multiple permissions. The middleware will allow access if the user has any of the specified permissions:

```php
Route::get('/admin/users', [UserController::class, 'index'])->middleware('permission:view-users,manage-users');
```

#### Handling Unauthorized Access

When a user doesn't have the required permission:

- For regular requests, it will abort with a 403 status and "Unauthorized action." message.
- For JSON requests, it will return a JSON response with a 403 status and an "Unauthorized action." message.
  This middleware provides a robust way to implement permission-based access control in your Laravel application,
  integrating seamlessly with the Laravel Role Permission Manager package.

#### RoleMiddleware

The `RoleMiddleware` is designed to protect routes based on user roles. It checks if the authenticated user has the
required role(s) before allowing access to a route.

#### Key Features

1. **Route Protection**: Allows you to specify required roles for accessing specific routes.
2. **Multiple Role Support**: Can check for multiple roles, granting access if the user has any of them.
3. **Authentication Check**: Ensures the user is authenticated before checking roles.
4. **JSON Response Support**: Returns a JSON response for unauthorized actions if the request expects JSON.

#### Usage

To use this middleware, you can add it to your routes or controllers:

1. In your `app/Http/Kernel.php`, add an alias for the middleware:

```php
protected $routeMiddleware = [
    // ... other middleware
    'role' => \CreativeCrafts\LaravelRolePermissionManager\Middleware\RoleMiddleware::class,
];
```

2. Use it in your routes:

```php
Route::get('/admin', [AdminController::class, 'index'])->middleware('role:admin');
```

3. Or use it in your controllers:

```php
public function __construct()
{
    $this->middleware('role:editor')->only('create', 'store');
}
```

#### Multiple Roles

You can specify multiple roles. The middleware will allow access if the user has any of the specified roles:

```php
Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('role:admin,manager');
```

#### Handling Unauthorized Access

When a user doesn't have the required role:

- For regular requests, it will abort with a 403 status and "Unauthorized action." message.
- For JSON requests, it will return a JSON response with a 403 status and an "Unauthorized action." message.
  This middleware provides a straightforward way to implement role-based access control in your Laravel application,
  complementing the permission-based middleware and integrating seamlessly with the Laravel Role Permission Manager
  package.

### Facade

The package provides a facade for easy access to role and permission management functions.

#### Key methods:

- `createRole(string $name, string $slug, ?string $description = null, ?Role $parent = null): Role`
- `createPermission(string $name, string $slug, ?string $description = null, ?string $scope = null): Permission`
- `givePermissionToRole(Role $role, Permission|string $permission, ?string $scope = null): void`
- `revokePermissionFromRole(Role $role, Permission|string $permission, ?string $scope = null): void`
- `syncPermissions(Role $role, array $permissions, bool $clear = false): array`
- `clearPermissions(Role $role): array`
- `hasPermissionTo(mixed $user, Permission|string $permission, ?string $scope = null): bool`
- `getSubRoles(Role $role): Collection`
- `grantPermissionToRoleAndSubRoles(Role $role, Permission|string $permission): void`
- `revokePermissionFromRoleAndSubRoles(Role $role, Permission|string $permission): void`

Usage example:

```php
use CreativeCrafts\LaravelRolePermissionManager\Facades\LaravelRolePermissionManager;

// Create a role
$role = LaravelRolePermissionManager::createRole('Editor', 'editor');

// Create a permission
$permission = LaravelRolePermissionManager::createPermission('Edit Posts', 'edit-posts');

// Give permission to role
LaravelRolePermissionManager::givePermissionToRole($role, $permission);

// Grant permission to role and its sub-roles
LaravelRolePermissionManager::grantPermissionToRoleAndSubRoles($role, 'edit-posts');

// Revoke permission from role and its sub-roles
LaravelRolePermissionManager::revokePermissionFromRoleAndSubRoles($role, 'edit-posts');

// Check if user has permission
$hasPermission = LaravelRolePermissionManager::hasPermissionTo($user, 'edit-posts');

// Get all sub-roles of a given role
$parentRole = Role::findByName('manager');
$subRoles = LaravelRolePermissionManager::getSubRoles($parentRole);

// Grant a permission to a role and all its sub-roles
$role = Role::findByName('manager');
LaravelRolePermissionManager::grantPermissionToRoleAndSubRoles($role, 'edit_posts');

// Revoke a permission from a role and all its sub-roles
LaravelRolePermissionManager::revokePermissionFromRoleAndSubRoles($role, 'delete_users');
```

These new methods allow for more granular control over role hierarchies and permissions. The getSubRoles method
retrieves all sub-roles of a given role, while grantPermissionToRoleAndSubRoles and revokePermissionFromRoleAndSubRoles
apply permission changes to both the specified role and all its sub-roles.

### Artisan Commands

The package provides several Artisan commands to manage roles and permissions:

    - create:role: Create a new role
    - create:permission: Create a new permission
    - assign:permission: Assign a permission to a role
    - remove:permission: Remove a permission from a role
    - list:roles-permissions: List all roles and permissions
    - sync:permissions: Synchronize permissions for a role

#### Automatic Route Registration

By default, the package registers the following routes:

- `GET /roles`: List all roles
- `GET /permissions`: List all permissions
- `GET /user/{userId}/permissions`: Get permissions for a specific user
- `GET /user/{userId}/permissions/{scope}`: Get scoped permissions for a specific user

The configured prefix defaults to `/api/role-permissions`. In addition to route middleware, catalogs require the
unscoped `view roles` or `view permissions` permission. Self-inspection is allowed; cross-user inspection is denied
unless the host binds a custom `MetadataAccessAuthorizer`. No user-role endpoint is registered.

#### Customizing Route Registration

You can customize the route registration by setting the `use_package_routes` configuration option to false. it is set to
true by default.

```php
'use_package_routes' => false,
'route_prefix' => 'api',
'route_middleware' => ['api'],
```

## Authorization Policies

The Laravel Role Permission Manager package comes with pre-defined authorization policies for the Role and Permission
models. These policies are automatically registered when you use the package.

### Policy Registration

The policies are registered in the `AuthServiceProvider` that comes with the package:

```php
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use CreativeCrafts\LaravelRolePermissionManager\Policies\PermissionPolicy;
use CreativeCrafts\LaravelRolePermissionManager\Policies\RolePolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Role::class => RolePolicy::class,
        Permission::class => PermissionPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
```

### Using the Policies

These policies allow you to control who can perform various actions on roles and permissions. You can use them in your
controllers or other parts of your application like this:

```php
// In a controller
public function update(Role $role)
{
    $this->authorize('update', $role);
    
    // Update logic here...
}
```

Or in a Blade template:

```blade
@can('update', $role)
    <!-- Show update button or form -->
@endcan
```

### Custom Policies

If you need to customize these policies, you can publish them to your application:

```bash
php artisan vendor:publish --tag="laravel-role-permission-manager-policies"
```

This will copy the policies to your app/Policies directory, where you can modify them as needed.

#### Available Policy Methods

Both the RolePolicy and PermissionPolicy typically include methods for standard CRUD operations:

    - viewAny: Determine whether the user can view any roles/permissions.
    - view: Determine whether the user can view the role/permission.
    - create: Determine whether the user can create roles/permissions.
    - update: Determine whether the user can update the role/permission.
    - delete: Determine whether the user can delete the role/permission.

Remember to adjust your application's authorization logic in these policies according to your specific requirements.

## Frontend Integration

### Accessing Permissions in the Frontend

Permissions are shared with the Inertia.js frontend and can be accessed in Vue components.

### Vue Permission Helper Functions

The package includes TypeScript-friendly utility functions for managing user permissions in Vue components.

#### Key Functions

1. `can(permission: string, scope: string | null = null): boolean`
    - Checks if the user has a specific permission, optionally within a given scope.

2. `getScopedPermissions(scope: string): Permission[]`
    - Retrieves all permissions for a specific scope.

3. `hasAnyPermissionInScope(scope: string): boolean`
    - Checks if the user has any permission in a given scope.

4. `hasAllPermissions(permissionSlugs: string[]): boolean`
    - Checks if the user has all specified permissions.

5. `hasAnyPermission(permissionSlugs: string[]): boolean`
    - Checks if the user has any of the specified permissions.

6. `getAllScopes(): string[]`
    - Retrieves all unique scopes from the user's permissions.

#### Usage in Vue Components

To use these helpers in your Vue components:

1. Import the helper functions:

```typescript
import {can, hasAnyPermission} from '@/path/to/permissions';
```

2. Use them in your component:

```text
<template>
  <div v-if="can('edit-posts')">
    <!-- Edit post content -->
  </div>
  <div v-if="hasAnyPermission(['manage-users', 'view-users'])">
    <!-- User management content -->
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import { can, hasAnyPermission } from '@/path/to/permissions';

export default defineComponent({
  setup() {
    return {
      can,
      hasAnyPermission,
    };
  },
});
</script>
```

#### Note on Permissions Data

These helpers expect the permissions data to be available on window.Laravel.permissions. Ensure this data is properly
populated on your frontend, typically by sharing it through your backend views or API responses.

The helper reads the current value on every check, treats omitted scope as null scope, and has no frontend privileged
role bypass. It is a display helper; backend authorization remains authoritative.

### React Permission Hooks

For React applications, the package provides a custom hook usePermissions.

#### Key Features

1. **Inertia.js Integration**: Works seamlessly with Inertia.js to access shared permissions data.
2. **Current Principal State**: Functions are rebuilt when current Inertia permission props change.
3. **Scoped Permissions**: Supports checking permissions within specific scopes.

#### Usage

To use the `usePermissions` hook in your React components:

1. Import the hook:

```typescript
import {usePermissions} from '@/path/to/usePermissions';
```

2. Use it in your component:

```typescript jsx
import React from 'react';
import {usePermissions} from '@/path/to/usePermissions';

const MyComponent: React.FC = () => {
    const {can, hasAnyPermission, hasAllPermissions} = usePermissions();

    return (
        <div>
            {can('edit-posts') && <button>Edit Post</button>}
            {hasAnyPermission(['manage-users', 'view-users']) && <UserManagement/>}
            {hasAllPermissions(['create-post', 'publish-post']) && <CreatePostForm/>}
        </div>
    );
};
```

#### Available Functions

    - can(permission: string, scope?: string): boolean
        Checks if the user has a specific permission, optionally within a given scope.
    - hasAnyPermission(permissions: string[]): boolean
        Checks if the user has any of the specified permissions.
    - hasAllPermissions(permissions: string[]): boolean
        Checks if the user has all of the specified permissions.
    - getScopedPermissions(scope: string): Permission[]
        Retrieves all permissions for a specific scope.
    - hasAnyPermissionInScope(scope: string): boolean
        Checks if the user has any permission in a given scope.

### Note on Permissions Data

This hook reads `auth.permissions` from the current Inertia page props. A missing `auth` prop, or a missing
permissions list, denies every check, including guest and post-logout pages. Share `auth.permissions` from the
backend when a signed-in user should see permitted actions. The package Inertia middleware shares an empty list for
guests.

This React hook provides a convenient and type-safe way to manage permissions in your React frontend when using
Inertia.js, complementing the Laravel Role Permission Manager package's backend functionality.

## Authorization and upgrade safety

- Package Gate checks use the `role-permission:` prefix by default and abstain from unrelated abilities.
- The configured super-admin value is an exact role name; role middleware accepts exact slugs.
- Omitted authorization scope matches only a null-scope grant. Empty string and `"0"` remain distinct.
- Wildcards are anchored and only `*` is special.
- Authorization checks never create permission records.
- Supported mutations are transactional and invalidate package authorization generations after commit.
- Multi-process deployments require a shared cache store for cross-process revocation consistency.

Before upgrading existing data, run:

```bash
php artisan role-permission:preflight
```

See [UPGRADE.md](UPGRADE.md) for breaking changes, migration, rollout, frontend republishing, and rollback instructions.

## Testing

Run the PHP, static-analysis, style, and frontend checks with:

```bash
composer test
composer analyse
vendor/bin/pint --test
npm ci
npm run typecheck
npm run test:frontend
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

    - [Godspower Oduose](https://github.com/rockblings)
    - [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see License File for more information.
