<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelRolePermissionManager\Cache;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use JsonException;

final class AuthorizationCache
{
    private const NAMESPACE = 'creativecrafts:lprm:v2';

    private int $pendingInvalidations = 0;

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
        if (! $this->enabled() || $this->pendingInvalidations > 0) {
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
            $this->pendingInvalidations++;
            $connection->afterCommit(function () use ($principal): void {
                $this->publishGeneration();
                $this->forgetLegacyPrincipal($principal);
                $this->releasePendingInvalidation();
            });
            $connection->afterRollBack(function (): void {
                $this->releasePendingInvalidation();
            });

            return;
        }

        $this->publishGeneration();
        $this->forgetLegacyPrincipal($principal);
        $this->pendingInvalidations = 0;
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
            'scope' => $this->scopeIdentity($scope, $scopeSpecified),
        ];
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
        $value = $repository->get($this->generationKey());
        if (is_int($value) && $value > 0) {
            return $value;
        }

        $repository->forever($this->generationKey(), 1);

        return 1;
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

        if (! is_int($next) || $next < 1) {
            $repository->forever($key, max(2, $this->currentGeneration() + 1));
        }
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

    private function releasePendingInvalidation(): void
    {
        $this->pendingInvalidations = max(0, $this->pendingInvalidations - 1);
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
