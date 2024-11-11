# Laravel Role Permission Manager

[![Latest Version on Packagist](https://img.shields.io/packagist/v/prince/laravel-role-permission-manager.svg?style=flat-square)](https://packagist.org/packages/prince/laravel-role-permission-manager)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/prince/laravel-role-permission-manager/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/prince/laravel-role-permission-manager/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/prince/laravel-role-permission-manager/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/prince/laravel-role-permission-manager/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/prince/laravel-role-permission-manager.svg?style=flat-square)](https://packagist.org/packages/prince/laravel-role-permission-manager)

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
composer require prince/laravel-role-permission-manager
```

2. Publish and run the migrations:

```bash
php artisan vendor:publish --tag="laravel-role-permission-manager-migrations"
php artisan migrate
```

3. Optionally, publish the config file:

```bash
php artisan vendor:publish --tag="laravel-role-permission-manager-config"
```

### Configuration

Customize the package behavior by editing the published config file config/role-permission-manager.php.

Key configuration options:

    - user_model: Specify the User model used by your application
    - roles_table: Define the name for the roles table
    - permissions_table: Define the name for the permissions table
    - cache_expiration_time: Set the cache expiration time for roles and permissions
    - default_guard: Specify the default guard for role and permission checks
    - use_package_routes: If set to true, the package will register its routes automatically.
    - route_middleware: Define the middleware to be applied to the package routes
    - route_prefix: Set the prefix for the package routes
    - super_admin_role: Define the role that should be considered as a super admin
    - auto_create_permissions: Enable or disable automatic creation of permissions
    - enable_wildcard_permission: Enable or disable wildcard permissions
    - case_sensitive_permissions: Choose whether permission names should be case-sensitive

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

3. **Caching**: Uses Laravel's cache system to store user permissions, improving performance by reducing database
   queries.

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
if (permissions.includes('edit-posts')) {
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

Usage:

```php
use CreativeCrafts\LaravelRolePermissionManager\Facades\LaravelRolePermissionManager;

LaravelRolePermissionManager::createRole('admin');
```

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
- `GET /user/{userId}/roles`: Get roles for a specific user
- `GET /user/{userId}/permissions`: Get permissions for a specific user
- `GET /user/{userId}/permissions/{scope}`: Get scoped permissions for a specific user

These routes are protected by middleware and can be accessed via the `RolePermissionController` included with the
package.

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

This TypeScript module provides a robust and type-safe way to manage permissions in your Vue.js frontend, complementing
the backend permission management provided by the Laravel Role Permission Manager package.

### React Permission Hooks

For React applications, the package provides a custom hook usePermissions.

#### Key Features

1. **Inertia.js Integration**: Works seamlessly with Inertia.js to access shared permissions data.
2. **Memoized Functions**: All permission checks are memoized for improved performance.
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

This hook expects the permissions data to be available in the Inertia page props under auth.permissions. Ensure this
data is properly shared from your Laravel backend when using Inertia.js.

This React hook provides a convenient and type-safe way to manage permissions in your React frontend when using
Inertia.js, complementing the Laravel Role Permission Manager package's backend functionality.

## Testing

To run the tests, use:

```bash
composer test
```

## Changelog

Please see CHANGELOG for more information on what has changed recently.

## Contributing

Please see CONTRIBUTING for details.

## Security Vulnerabilities

If you discover any security related issues, please email creativecraftssolutions@gmail.com instead of using the issue
tracker.

## Credits

    - Godspower Oduose
    - All Contributors

## License

The MIT License (MIT). Please see License File for more information.