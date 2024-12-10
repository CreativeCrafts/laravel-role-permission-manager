<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | This is the User model used by LaravelRolePermissionManager to create
    | correct relations. Update the User if it is in a different namespace.
    */
    'user_model' => 'App\Models\User',

    /*
    |--------------------------------------------------------------------------
    | Roles Table Name
    |--------------------------------------------------------------------------
    |
    | This is the table name used by LaravelRolePermissionManager to save roles to the database.
    */
    'roles_table' => 'roles',

    /*
    |--------------------------------------------------------------------------
    | Permissions Table Name
    |--------------------------------------------------------------------------
    |
    | This is the table name used by LaravelRolePermissionManager to save permissions to the database.
    */
    'permissions_table' => 'permissions',

    /*
    |--------------------------------------------------------------------------
    | Cache Expiration Time
    |--------------------------------------------------------------------------
    |
    | This is the cache expiration time in minutes for caching roles and permissions.
    */
    'cache_expiration_time' => 60,
    /*
    |--------------------------------------------------------------------------
    | Role-Permission Relationship Table Name
    |--------------------------------------------------------------------------
    |
    | This is the table name for the relationship between roles and permissions.
    */
    'role_permission_table' => 'role_has_permissions',

    /*
    |--------------------------------------------------------------------------
    | User-Role Relationship Table Name
    |--------------------------------------------------------------------------
    |
    | This is the table name for the relationship between users and roles.
    */
    'user_role_table' => 'model_has_roles',

    /*
    |--------------------------------------------------------------------------
    | User-Permission Relationship Table Name
    |--------------------------------------------------------------------------
    |
    | This is the table name for the relationship between users and permissions.
    */
    'user_permission_table' => 'model_has_permissions',

    /*
    |--------------------------------------------------------------------------
    | Default Guard
    |--------------------------------------------------------------------------
    |
    | This is the default guard to be used for the role and permission checks.
    */
    'default_guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Use Package Routes
    |--------------------------------------------------------------------------
    |
    | If set to true, the package will register its routes automatically.
    */
    'use_package_routes' => true,

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    |
    | The middleware to be applied to the package routes.
    */
    'route_middleware' => ['web', 'auth'],

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix for all the package routes.
    */
    'route_prefix' => 'api/role-permissions',

    /*
    |--------------------------------------------------------------------------
    | Super Admin Role
    |--------------------------------------------------------------------------
    |
    | This is the name of the super admin role. Users with this role will have all permissions.
    */
    'super_admin_role' => 'Super Admin',

    /*
    |--------------------------------------------------------------------------
    | Auto Create Permissions
    |--------------------------------------------------------------------------
    |
    | If set to true, permissions will be created automatically when they are checked for the first time.
    */
    'auto_create_permissions' => false,

    /*
    |--------------------------------------------------------------------------
    | Enable Wildcard Permission
    |--------------------------------------------------------------------------
    |
    | If set to true, wildcard permissions will be enabled (e.g., 'posts.*' will grant all permissions related to posts).
    */
    'enable_wildcard_permission' => true,

    /*
    |--------------------------------------------------------------------------
    | Case Sensitive Permissions
    |--------------------------------------------------------------------------
    |
    | If set to true, permission names will be case-sensitive.
    */
    'case_sensitive_permissions' => false,

    /*
    |--------------------------------------------------------------------------
    | Register Permission Check Method
    |--------------------------------------------------------------------------
    |
    | If set to true, a global gate check will be registered that checks if the user has the required permission for a given ability.
    */
    'register_permission_check_method' => true,

    /*
    |--------------------------------------------------------------------------
    | Register Blade Directives
    |--------------------------------------------------------------------------
    |
    | If set to true, the package will register custom Blade directives for
    | checking roles and permissions in your Blade templates.
    */
    'register_blade_directives' => false,

    /*
    |--------------------------------------------------------------------------
    | Use Cache
    |--------------------------------------------------------------------------
    |
    | If set to true, the cache repository is used for storing and retrieving role and permission data.
    */
    'use_cache' => true,

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    |
    | This is the cache store used for storing and retrieving role and permission data.
    */
    'cache_store' => env('CACHE_STORE', 'database'),
];
