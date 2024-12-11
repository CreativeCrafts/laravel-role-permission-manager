<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Models;

use CreativeCrafts\LaravelRolePermissionManager\Exceptions\InvalidPermissionArgumentException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
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
    use HasFactory;

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
        if (! isset($this->attributes['slug']) && isset($this->attributes['name'])) {
            $this->attributes['slug'] = Str::slug($this->attributes['name']);
        }
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
        $allChildren = new Collection();

        foreach ($this->children as $child) {
            $allChildren->push($child);
            $allChildren = $allChildren->merge($child->getAllChildren());
        }

        return $allChildren;
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
        return $this->belongsToMany(Permission::class, config('role-permission-manager.role_permission_table'));
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

    public function givePermissionTo($permission): void
    {
        if (is_string($permission)) {
            $permissionModel = Permission::where('name', $permission)
                ->orWhere('slug', $permission)
                ->first();

            if (! $permissionModel) {
                if (Config::get('role-permission-manager.auto_create_permissions', false)) {
                    $permissionModel = Permission::create([
                        'name' => $permission,
                    ]);
                } else {
                    throw new ModelNotFoundException("Permission '$permission' does not exist.");
                }
            }
            $permission = $permissionModel;
        }

        if (! $permission instanceof Permission) {
            throw new InvalidPermissionArgumentException();
        }
        $this->permissions()->syncWithoutDetaching([$permission->id]);
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(static function (self $role): void {
            if (empty($role->slug) && ! empty($role->name)) {
                $role->slug = Str::slug($role->name);
            }
        });
    }

    /**
     * Define the slug attribute for the Permission model.
     * This method creates an Attribute instance for the slug field, providing custom
     * getter and setter logic. The getter returns an empty string if the slug is null,
     * while the setter generates a slug from the name if the provided value is null or empty.
     *
     * @return Attribute The Attribute instance for the slug field.
     */
    protected function slug(): Attribute
    {
        return Attribute::make(
            get: static fn ($value) => $value ?: '',
            set: function ($value) {
                if ($value === null) {
                    // @pest-mutate-ignore
                    return Str::slug($this->name ?: '');
                }
                // @pest-mutate-ignore
                return $value ?: Str::slug($this->name ?: '');
            }
        );
    }
}
