<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Tests\Models;

use CreativeCrafts\LaravelRolePermissionManager\Traits\HasRolesAndPermissions;
use Illuminate\Foundation\Auth\User as Authenticatable;

class TraitOnlyUser extends Authenticatable
{
    use HasRolesAndPermissions;

    public $timestamps = false;

    protected $table = 'users';

    protected $guarded = [];
}
