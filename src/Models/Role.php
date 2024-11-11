<?php

namespace CreativeCrafts\LaravelRolePermissionManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'parent_id'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('role-permission-manager.roles_table');
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(static function ($role) {
            if (! $role->slug) {
                $role->slug = Str::slug($role->name);
            }
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(config('role-permission-manager.user_model'))
            ->using(config('role-permission-manager.user_role_table'));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(__CLASS__, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(__CLASS__, 'parent_id');
    }

    public function hasPermissionTo(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->permissions()
            ->where('name', config('role-permission-manager.case_sensitive_permissions') ? '=' : 'LIKE', $permission)
            ->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->name === config('role-permission-manager.super_admin_role');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)
            ->using(config('role-permission-manager.role_permission_table'));
    }

    public function getAllPermissions(): Collection
    {
        $allPermissions = $this->permissions;

        if ($this->parent) {
            $allPermissions = $allPermissions->merge($this->parent->getAllPermissions());
        }

        return $allPermissions->unique('id');
    }

    public function getSlugAttribute(): string
    {
        return $this->attributes['slug'] ?? Str::slug($this->name);
    }
}
