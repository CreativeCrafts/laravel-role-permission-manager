<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Represents a role in the application's authorization system.
 *
 * @property int $id The unique identifier of the role
 * @property string|null $name The name of the role
 * @property string|null $slug The slug of the role (used for identification)
 * @property string|null $description A description of the role
 * @property int|null $parent_id The ID of the parent role, if any
 * @property-read Role|null $parent The parent role
 * @property-read Collection|Role[] $children The child roles
 * @property-read Collection|Permission[] $permissions The permissions associated with this role
 */
class Role extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['name', 'slug', 'description', 'parent_id'];

    /**
     * Create a new Role instance.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('role-permission-manager.roles_table');
    }

    /**
     * Get the users that belong to the role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(config('role-permission-manager.user_model'))
            ->using(config('role-permission-manager.user_role_table'));
    }

    /**
     * Get the parent role.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(__CLASS__, 'parent_id');
    }

    /**
     * Get the child roles.
     */
    public function children(): HasMany
    {
        return $this->hasMany(__CLASS__, 'parent_id');
    }

    /**
     * Get all child roles recursively.
     */
    public function getAllChildren(): Collection
    {
        return $this->children()->with('children')->get();
    }

    /**
     * Check if the role has a specific permission.
     */
    public function hasPermissionTo(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->permissions()
            ->where('name', config('role-permission-manager.case_sensitive_permissions') ? '=' : 'LIKE', $permission)
            ->exists();
    }

    /**
     * Check if the role is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->name !== null && $this->name === config(
            'role-permission-manager.super_admin_role',
            'Super Admin'
        );
    }

    /**
     * Get the permissions associated with the role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)
            ->using(config('role-permission-manager.role_permission_table'));
    }

    /**
     * Get all permissions associated with the role, including inherited permissions.
     */
    public function getAllPermissions(): Collection
    {
        $allPermissions = $this->permissions;

        if ($this->parent) {
            $allPermissions = $allPermissions->merge($this->parent->getAllPermissions());
        }

        return $allPermissions->unique('id');
    }

    /**
     * Get the slug attribute.
     */
    public function getSlugAttribute(): string
    {
        return $this->attributes['slug'] ?? Str::slug($this->name ?? '');
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(static function ($role): void {
            if (! $role->slug) {
                $role->slug = Str::slug($role->name);
            }
        });
    }
}
