<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    // @pest-mutate-ignore
    protected $fillable = ['name', 'slug', 'description', 'scope'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    // @pest-mutate-ignore
    protected $casts = [
        'scope' => 'string',
    ];

    /**
     * Constructor for the Permission model.
     * Initializes a new Permission instance, sets the table name from configuration,
     * and automatically generates a slug from the name if not provided.
     *
     * @param array $attributes An array of attributes to set on the model instance
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('role-permission-manager.permissions_table');

        if (! isset($this->attributes['slug']) && isset($this->attributes['name'])) {
            $this->attributes['slug'] = Str::slug($this->attributes['name']);
        }
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
            return ($this->name === $permission || $this->slug === $permission) &&
                ($scope === null || $this->scope === $scope);
        }

        if ($scope !== null && $this->scope !== $scope) {
            return false;
        }

        $pattern = preg_quote($this->name, '/');
        $pattern = str_replace('\*', '.*', $pattern);
        $slugPattern = preg_quote($this->slug, '/');
        $slugPattern = str_replace('\*', '.*', $slugPattern);
        $flags = config('role-permission-manager.case_sensitive_permissions') ? '' : 'i';

        $fullPattern = '/^(' . $pattern . '|' . $slugPattern . ')$/';

        if ($flags !== '') {
            $fullPattern .= $flags;
        }

        return (bool) preg_match($fullPattern, $permission);
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
     * This method is called when the model is being booted. It sets up a
     * 'saving' event listener that automatically generates a slug from the
     * permission name if the slug is empty and the name is not.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(static function (self $permission): void {
            if (empty($permission->slug) && ! empty($permission->name)) {
                $permission->slug = Str::slug($permission->name);
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
