<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Cache;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use JsonException;

final class AuthorizationCache
{
    private const NAMESPACE = 'creativecrafts:lprm:v2';

    /**
     * Open invalidations keyed by connection name.
     *
     * Laravel 10 database connections do not implement afterRollBack(). A rolled-back
     * transaction therefore leaves its count in place until the next read observes
     * that the connection is no longer inside a transaction.
     *
     * @var array<string, int>
     */
    private array $pendingInvalidations = [];

    public function __construct(private readonly CacheFactory $cache)
    {
    }

    public function enabled(): bool
    {
        return (bool) config('role-permission-manager.use_cache', true)
            && $this->ttlMinutes() > 0;
    }

    /**
     * @template TValue
     *
     * @param array<int|string, mixed> $identity
     * @param Closure(): TValue $loader
     * @return TValue
     *
     * @throws JsonException
     */
    public function remember(string $type, array $identity, Closure $loader): mixed
    {
        if (! $this->enabled() || $this->hasPendingInvalidation()) {
            return $loader();
        }

        return $this->repository()->remember(
            $this->key($type, $identity),
            now()->addMinutes($this->ttlMinutes()),
            $loader,
        );
    }

    /**
     * Publish an authorization generation after the model's outer transaction commits.
     */
    public function invalidate(?Model $model = null, ?Authenticatable $principal = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        $connection = $model?->getConnection();
        if ($connection instanceof Connection && $connection->transactionLevel() > 0) {
            $connectionName = $connection->getName();
            $this->pendingInvalidations[$connectionName] = ($this->pendingInvalidations[$connectionName] ?? 0) + 1;
            $connection->afterCommit(function () use ($principal, $connectionName): void {
                $this->publishGeneration();
                $this->forgetLegacyPrincipal($principal);
                $this->releasePendingInvalidation($connectionName);
            });

            return;
        }

        $this->publishGeneration();
        $this->forgetLegacyPrincipal($principal);
        $this->forgetInactivePendingInvalidations();
    }

    public function principalIdentity(
        Authenticatable $principal,
        ?string $guard = null,
        ?string $scope = null,
        bool $scopeSpecified = false,
    ): array {
        $modelIdentity = $principal instanceof Model
            ? $principal->getMorphClass()
            : $principal::class;

        return [
            'guard' => $guard ?? (string) config('role-permission-manager.default_guard', 'web'),
            'model' => $modelIdentity,
            'id' => [
                'type' => get_debug_type($principal->getAuthIdentifier()),
                'value' => (string) $principal->getAuthIdentifier(),
            ],
            'database' => $this->databaseIdentity($this->principalConnection($principal)),
            'scope' => $this->scopeIdentity($scope, $scopeSpecified),
        ];
    }

    public function principalConnection(Authenticatable $principal): Connection
    {
        return $principal instanceof Model ? $principal->getConnection() : DB::connection();
    }

    /**
     * @return array{connection: string, database: string}
     */
    public function databaseIdentity(Connection $connection): array
    {
        return [
            'connection' => $connection->getName(),
            'database' => (string) $connection->getDatabaseName(),
        ];
    }

    public function sameDatabase(Model $left, Model $right): bool
    {
        return $left->getTable() === $right->getTable()
            && $this->databaseIdentity($left->getConnection()) === $this->databaseIdentity($right->getConnection());
    }

    public function sameDatabaseRecord(Model $left, Model $right): bool
    {
        return $left->getKey() === $right->getKey()
            && $this->sameDatabase($left, $right);
    }

    public function onConnection(Model $model, Connection $connection): bool
    {
        return $this->databaseIdentity($model->getConnection()) === $this->databaseIdentity($connection);
    }

    public function scopeIdentity(?string $scope, bool $specified): array
    {
        if (! $specified) {
            return ['type' => 'omitted'];
        }

        return $scope === null
            ? ['type' => 'null']
            : ['type' => 'string', 'value' => $scope];
    }

    public function currentGeneration(): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $repository = $this->repository();
        $key = $this->generationKey();
        $repository->add($key, 1, now()->addYears(10));
        $generation = $this->normalizeGeneration($repository->get($key));

        return $generation > 0 ? $generation : 1;
    }

    /**
     * Explicitly rotate the package namespace after deployment or raw host mutation.
     */
    public function flushAuthorizationState(): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->publishGeneration();
    }

    /**
     * @param array<int|string, mixed> $identity
     *
     * @throws JsonException
     */
    private function key(string $type, array $identity): string
    {
        $payload = json_encode(
            ['type' => $type, 'identity' => $identity],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return implode(':', [
            self::NAMESPACE,
            (string) $this->currentGeneration(),
            $type,
            hash('sha256', $payload),
        ]);
    }

    private function publishGeneration(): void
    {
        $repository = $this->repository();
        $key = $this->generationKey();
        $repository->add($key, 1, now()->addYears(10));
        $next = $repository->increment($key);

        if ($this->normalizeGeneration($next) >= 1 && is_numeric($next)) {
            return;
        }

        $current = $this->normalizeGeneration($repository->get($key));
        $repository->add($key, $current + 1, now()->addYears(10));
    }

    private function normalizeGeneration(mixed $value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return 0;
    }

    private function forgetLegacyPrincipal(?Authenticatable $principal): void
    {
        if ($principal === null) {
            return;
        }

        $id = $principal->getAuthIdentifier();
        foreach ([
            "user_permissions_{$id}",
            "user_roles_{$id}",
            "user_role_names_{$id}",
            "user_role_slugs_{$id}",
        ] as $legacyKey) {
            $this->repository()->forget($legacyKey);
        }
    }

    private function hasPendingInvalidation(): bool
    {
        $this->forgetInactivePendingInvalidations();

        return $this->pendingInvalidations !== [];
    }

    private function forgetInactivePendingInvalidations(): void
    {
        foreach (array_keys($this->pendingInvalidations) as $connectionName) {
            $pending = $this->pendingInvalidations[$connectionName] ?? 0;
            if ($pending <= 0 || DB::connection($connectionName)->transactionLevel() === 0) {
                unset($this->pendingInvalidations[$connectionName]);
            }
        }
    }

    private function releasePendingInvalidation(string $connectionName): void
    {
        $remaining = ($this->pendingInvalidations[$connectionName] ?? 0) - 1;
        if ($remaining > 0) {
            $this->pendingInvalidations[$connectionName] = $remaining;

            return;
        }

        unset($this->pendingInvalidations[$connectionName]);
    }

    private function generationKey(): string
    {
        return self::NAMESPACE . ':generation';
    }

    private function ttlMinutes(): int
    {
        return max(0, (int) config('role-permission-manager.cache_expiration_time', 60));
    }

    private function repository(): Repository
    {
        $store = config('role-permission-manager.cache_store');

        return $this->cache->store(is_string($store) && $store !== '' ? $store : null);
    }
}
