<?php

namespace CreativeCrafts\LaravelRolePermissionManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Permission extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'scope'];

    protected $casts = [
        'scope' => 'string',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('role-permission-manager.permissions_table');
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(static function ($permission) {
            if (! $permission->slug) {
                $permission->slug = Str::slug($permission->name);
            }
        });
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(config('role-permission-manager.user_model'))
            ->using(config('role-permission-manager.user_permission_table'));
    }

    public function wildcardMatch($permission, $scope = null): bool
    {
        if (! config('role-permission-manager.enable_wildcard_permission')) {
            return $this->name === $permission && ($scope === null || $this->scope === $scope);
        }

        if ($scope !== null && $this->scope !== $scope) {
            return false;
        }

        $pattern = preg_quote($this->name, '/');
        $pattern = str_replace('\*', '.*', $pattern);
        $flags = config('role-permission-manager.case_sensitive_permissions') ? '' : 'i';

        return (bool) preg_match('/^'.$pattern.'$/'.$flags, $permission);
    }

    public function getSlugAttribute(): string
    {
        return $this->attributes['slug'] ?? Str::slug($this->name);
    }

    public function getScopeAttribute(): ?string
    {
        return $this->attributes['scope'] ?? null;
    }
}
