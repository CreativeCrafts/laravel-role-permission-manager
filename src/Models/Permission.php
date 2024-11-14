<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * Represents a permission in the application's authorization system.
 *
 * @property int $id The unique identifier of the permission
 * @property string $name The name of the permission
 * @property string $slug The slug of the permission (used for identification)
 * @property string|null $description A description of the permission
 * @property string|null $scope The scope of the permission, if any
 * @property-read Collection|Role[] $roles The roles associated with this permission
 * @property-read Collection|Model[] $users The users directly associated with this permission
 */
class Permission extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['name', 'slug', 'description', 'scope'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'scope' => 'string',
    ];

    /**
     * Create a new Permission instance.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('role-permission-manager.permissions_table');
    }

    /**
     * Get the roles that are associated with the permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Get the users that are directly associated with the permission.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(config('role-permission-manager.user_model'))
            ->using(config('role-permission-manager.user_permission_table'));
    }

    /**
     * Check if the permission matches a given permission string, considering wildcards and scope.
     *
     * @param string $permission The permission string to match against
     * @param string|null $scope The scope to consider in the match
     * @return bool Whether the permission matches
     */
    public function wildcardMatch(string $permission, string $scope = null): bool
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
        return (bool) preg_match('/^' . $pattern . '$/' . $flags, $permission);
    }

    /**
     * Get the slug attribute.
     */
    public function getSlugAttribute(): string
    {
        return $this->attributes['slug'] ?? Str::slug($this->name);
    }

    /**
     * Get the scope attribute.
     */
    public function getScopeAttribute(): ?string
    {
        return $this->attributes['scope'] ?? null;
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(static function ($permission): void {
            if (! $permission->slug) {
                $permission->slug = Str::slug($permission->name);
            }
        });
    }
}
