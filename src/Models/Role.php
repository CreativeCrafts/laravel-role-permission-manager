<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Models;

use CreativeCrafts\LaravelRolePermissionManager\Cache\AuthorizationCache;
use CreativeCrafts\LaravelRolePermissionManager\DataTransferObjects\PermissionReference;
use CreativeCrafts\LaravelRolePermissionManager\Exceptions\InvalidPermissionArgumentException;
use CreativeCrafts\LaravelRolePermissionManager\Exceptions\RoleHierarchyCycleException;
use CreativeCrafts\LaravelRolePermissionManager\LaravelRolePermissionManager;
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
use LogicException;

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
     * @var list<string>
     */
    // @pest-mutate-ignore
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
        return $this->belongsToMany(
            config('role-permission-manager.user_model'),
            config('role-permission-manager.user_role_table'),
            'role_id',
            config('role-permission-manager.user_foreign_key', 'user_id'),
            $this->getKeyName(),
            config('role-permission-manager.user_model_key', 'id'),
        );
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
        return $this->collectDescendants(false);
    }

    /**
     * Lock this role and its current descendants for the surrounding transaction.
     *
     * Parent changes take the same hierarchy lock before updating parent_id, so a
     * subtree grant or revocation cannot apply a membership set that has already
     * been reparented.
     *
     * @return Collection<int, self>
     */
    public function lockedSelfAndDescendants(): Collection
    {
        if (! $this->exists) {
            /** @var Collection<int, self> $onlySelf */
            $onlySelf = collect([$this]);

            return $onlySelf;
        }

        $this->acquireHierarchyMutationLock();
        $self = $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

        return collect([$self])->concat($self->collectDescendants(true));
    }

    /**
     * Check if the role has a specific permission.
     */
    public function hasPermissionTo(Permission|string $permission, ?string $scope = null): bool
    {
        if ($permission instanceof Permission && ! $this->permissionModelMatchesConnection($permission)) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissions = $this->getAllPermissions();
        if ($permission instanceof Permission) {
            return $permission->exists
                && ($scope === null || $permission->scope === $scope)
                && $permissions->contains(
                    static fn (Permission $assigned): bool => app(AuthorizationCache::class)
                        ->sameDatabaseRecord($assigned, $permission)
                );
        }

        return $permissions->contains(
            static fn (Permission $assigned): bool => $assigned->wildcardMatch($permission, $scope)
        );
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
        if (! $this->exists) {
            return collect();
        }

        $permissions = collect();
        $visited = [];
        $current = $this;
        $maxNodes = max(1, (int) config('role-permission-manager.hierarchy_max_nodes', 10000));

        while ($current instanceof self) {
            $key = (string) $current->getKey();
            if (isset($visited[$key])) {
                throw new RoleHierarchyCycleException('A cycle exists in the persisted role hierarchy.');
            }

            $visited[$key] = true;
            if (count($visited) > $maxNodes) {
                throw new RoleHierarchyCycleException('Role hierarchy traversal exceeded the configured limit.');
            }

            $permissions = $permissions->concat($current->permissions()->get());
            $parentId = $current->getAttribute('parent_id');
            $current = $parentId === null ? null : $this->newQuery()->find($parentId);
        }

        return $permissions
            ->unique(static fn (Permission $permission): mixed => $permission->getKey())
            ->values();
    }

    /**
     * Get the slug attribute.
     */
    public function getSlugAttribute(): string
    {
        return $this->attributes['slug'] ?? Str::slug($this->name ?? '');
    }

    public function givePermissionTo(Permission|string $permission, ?string $scope = null): void
    {
        if (! $this->exists) {
            throw new InvalidPermissionArgumentException('Permissions can only be assigned to a persisted role.');
        }

        $scopeSpecified = func_num_args() > 1;
        $manager = app(LaravelRolePermissionManager::class);

        $this->getConnection()->transaction(function () use (
            $permission,
            $scope,
            $scopeSpecified,
            $manager,
        ): void {
            if (is_string($permission)) {
                $reference = $scopeSpecified
                    ? new PermissionReference($permission, $scope)
                    : $permission;

                try {
                    $resolved = $manager->resolvePermissionReference($reference, true, $this->getConnection());
                } catch (ModelNotFoundException $exception) {
                    if (! Config::get('role-permission-manager.auto_create_permissions', false)) {
                        throw $exception;
                    }

                    $resolved = $manager->createPermission(
                        $permission,
                        $permission,
                        scope: $scopeSpecified ? $scope : null,
                        connection: $this->getConnection(),
                    );
                }
            } else {
                $resolved = $manager->resolvePermissionReference($permission, true, $this->getConnection());
            }

            $this->permissions()->syncWithoutDetaching([$resolved->getKey()]);
            $this->unsetRelation('permissions');
            app(AuthorizationCache::class)->invalidate($this);
        });
    }

    /**
     * @param array<string, mixed> $options
     */
    public function save(array $options = [])
    {
        if (! $this->parentAssignmentNeedsTransaction()) {
            if ($this->isDirty('parent_id') && $this->getConnection()->transactionLevel() > 0) {
                $this->acquireHierarchyMutationLock();
                $this->guardAcyclicParentAssignment();
            }

            return parent::save($options);
        }

        return $this->getConnection()->transaction(function () use ($options): bool {
            $this->acquireHierarchyMutationLock();
            $this->guardAcyclicParentAssignment();

            return parent::save($options);
        });
    }

    /**
     * Serialize hierarchy edits on this connection.
     *
     * SQLite has no row locks, so a no-op update takes the writer lock before the
     * tree is read. Other drivers lock the lowest role row inside the transaction;
     * that lock is released on commit or rollback.
     */
    public function acquireHierarchyMutationLock(): void
    {
        $connection = $this->getConnection();
        if ($connection->transactionLevel() < 1) {
            throw new LogicException('Role hierarchy mutations must run inside a transaction.');
        }

        if ($connection->getDriverName() === 'sqlite') {
            $this->reserveSqliteHierarchyLock();

            return;
        }

        $this->newQuery()->orderBy($this->getKeyName())->lockForUpdate()->first([$this->getKeyName()]);
    }

    /**
     * @param list<int|string> $keys
     */
    public function lockRoleKeys(array $keys): void
    {
        $orderedKeys = array_values(array_unique(array_map(strval(...), $keys)));
        sort($orderedKeys, SORT_STRING);

        foreach ($orderedKeys as $key) {
            $this->newQuery()->whereKey($key)->lockForUpdate()->firstOrFail();
        }
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

            if ($role->isDirty('parent_id') && $role->parent_id !== null) {
                $role->assertAcyclicParent((int) $role->parent_id);
            }
        });

        static::saved(static function (self $role): void {
            if (app()->bound(AuthorizationCache::class)) {
                app(AuthorizationCache::class)->invalidate($role);
            }
        });

        static::deleted(static function (self $role): void {
            if (app()->bound(AuthorizationCache::class)) {
                app(AuthorizationCache::class)->invalidate($role);
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

    private function parentAssignmentNeedsTransaction(): bool
    {
        return $this->isDirty('parent_id')
            && $this->getConnection()->transactionLevel() === 0;
    }

    private function guardAcyclicParentAssignment(): void
    {
        if ($this->isDirty('parent_id') && $this->parent_id !== null) {
            $this->assertAcyclicParent((int) $this->parent_id);
        }
    }

    private function permissionModelMatchesConnection(Permission $permission): bool
    {
        return $permission->exists
            && $permission->getKey() !== null
            && app(AuthorizationCache::class)->onConnection($permission, $this->getConnection());
    }

    /**
     * @return Collection<int, self>
     */
    private function collectDescendants(bool $lock): Collection
    {
        if (! $this->exists) {
            return collect();
        }

        $visited = [(string) $this->getKey() => true];
        $queue = [$this->getKey()];
        $descendants = collect();
        $maxNodes = max(1, (int) config('role-permission-manager.hierarchy_max_nodes', 10000));
        $keyName = $this->getKeyName();

        while ($queue !== []) {
            $parentId = array_shift($queue);
            $childrenQuery = $this->newQuery()
                ->where('parent_id', $parentId)
                ->orderBy($keyName);
            if ($lock) {
                $childrenQuery->lockForUpdate();
            }

            foreach ($childrenQuery->get() as $child) {
                $key = (string) $child->getKey();
                if (isset($visited[$key])) {
                    throw new RoleHierarchyCycleException('A cycle exists in the persisted role hierarchy.');
                }

                $visited[$key] = true;
                $descendants->push($child);
                $queue[] = $child->getKey();

                if ($descendants->count() > $maxNodes) {
                    throw new RoleHierarchyCycleException('Role hierarchy traversal exceeded the configured limit.');
                }
            }
        }

        return $descendants;
    }

    private function assertAcyclicParent(int $parentId): void
    {
        if ($this->exists && (string) $parentId === (string) $this->getKey()) {
            throw new RoleHierarchyCycleException('A role cannot be its own parent.');
        }

        $this->reserveSqliteHierarchyLock();
        if ($this->exists) {
            $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->first();
        }

        $visited = [];
        $currentId = $parentId;
        $maxNodes = max(1, (int) config('role-permission-manager.hierarchy_max_nodes', 10000));

        while ($currentId !== null) {
            $key = (string) $currentId;
            if (isset($visited[$key])) {
                throw new RoleHierarchyCycleException('A cycle exists in the persisted role hierarchy.');
            }

            if ($this->exists && $key === (string) $this->getKey()) {
                throw new RoleHierarchyCycleException('The selected parent is a descendant of this role.');
            }

            $visited[$key] = true;
            if (count($visited) > $maxNodes) {
                throw new RoleHierarchyCycleException('Role hierarchy traversal exceeded the configured limit.');
            }

            $currentId = $this->newQuery()->lockForUpdate()->whereKey($currentId)->value('parent_id');
        }
    }

    private function reserveSqliteHierarchyLock(): void
    {
        $connection = $this->getConnection();
        if ($connection->getDriverName() !== 'sqlite' || $connection->transactionLevel() < 1) {
            return;
        }

        $key = $connection->getQueryGrammar()->wrap($this->getKeyName());
        $connection->table($this->getTable())
            ->whereRaw('0 = 1')
            ->update([$this->getKeyName() => $connection->raw($key)]);
    }
}
