## ADDED Requirements

### Requirement: Configured cache repository is honored
When caching is enabled, every package-owned entry and generation SHALL use exactly the configured Laravel cache store; a null store SHALL use Laravel's default.

#### Scenario: Named persistent store is used
- **GIVEN** `cache_store` names a configured persistent store
- **WHEN** package authorization caches data
- **THEN** the entry and generation are written to that store

#### Scenario: Invalid store does not silently fall back
- **GIVEN** `cache_store` names an unavailable store
- **WHEN** caching is attempted
- **THEN** the configuration error is surfaced and no unrelated store is used

### Requirement: Cache TTL uses documented minutes
Positive `cache_expiration_time` values SHALL be interpreted consistently as minutes via an absolute expiration. Non-positive values SHALL bypass package caching.

#### Scenario: Sixty means sixty minutes
- **GIVEN** frozen time and `cache_expiration_time=60`
- **WHEN** an entry is written
- **THEN** its expiration is sixty minutes from the frozen time

#### Scenario: Zero does not retain authority
- **GIVEN** `cache_expiration_time=0`
- **WHEN** effective authority is read
- **THEN** the loader executes without retaining a cache entry

### Requirement: Shared-store claims are conditional
Cross-worker consistency SHALL be claimed only when workers use the same configured store and atomic generation operations.

#### Scenario: Shared store propagates generation
- **GIVEN** two workers using one persistent store
- **WHEN** one publishes revocation generation
- **THEN** a new read by the other uses the new generation

#### Scenario: Local stores are documented as local
- **GIVEN** workers use independent array/file-local stores
- **WHEN** one mutates authority
- **THEN** the package does not claim immediate cross-worker invalidation
