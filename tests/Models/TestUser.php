<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Tests\Models;

use CreativeCrafts\LaravelRolePermissionManager\Contracts\AuthenticatableWithRolesAndPermissions;
use CreativeCrafts\LaravelRolePermissionManager\Database\Factories\TestUserFactory;
use CreativeCrafts\LaravelRolePermissionManager\Models\Permission;
use CreativeCrafts\LaravelRolePermissionManager\Models\Role;
use CreativeCrafts\LaravelRolePermissionManager\Traits\HasRolesAndPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class TestUser extends Authenticatable implements AuthenticatableWithRolesAndPermissions
{
    use HasFactory;
    use HasRolesAndPermissions;

    public $timestamps = false;
    protected $fillable = ['name'];
    protected $table = 'users';

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            config('role-permission-manager.user_role_table'),
            'user_id',
            'role_id'
        );
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            config('role-permission-manager.user_permission_table'),
            'user_id',
            'permission_id'
        );
    }

    protected static function newFactory(): TestUserFactory
    {
        return TestUserFactory::new();
    }
}
