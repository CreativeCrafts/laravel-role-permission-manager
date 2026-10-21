## ADDED Requirements

### Requirement: Package cache enablement is authoritative
All package-owned authorization reads and writes SHALL pass through the package cache service. With `use_cache=false`, the service SHALL execute loaders directly and perform zero cache store I/O.

#### Scenario: Disabled cache reads database
- **GIVEN** caching is disabled and a stale cache entry exists
- **WHEN** effective permissions are requested
- **THEN** current database authority is returned without reading the cache entry

#### Scenario: Disabled mutation performs no cache write
- **GIVEN** caching is disabled
- **WHEN** a supported authorization mutation commits
- **THEN** no package cache key is written, incremented, or forgotten

### Requirement: Principal cache identity cannot collide
Effective authorization keys SHALL include cache schema version, value type, guard, user model identity, authentication identifier, and scope mode using unambiguous encoding.

#### Scenario: Different user classes share numeric ID safely
- **GIVEN** two authenticatable model classes each have identifier `1`
- **WHEN** each principal's permissions are cached
- **THEN** each receives only its own effective grants

#### Scenario: Different guards do not share grants
- **GIVEN** the same model identifier is evaluated under two guards
- **WHEN** their cache entries are populated
- **THEN** the entries use distinct keys and cannot authorize one another

### Requirement: Cached values have stable types
Internal permission cache entries SHALL store documented scalar identity arrays and SHALL NOT share keys with frontend serialization or Eloquent collection values.

#### Scenario: Internal cache rehydrates permissions
- **GIVEN** a cached array of permission IDs
- **WHEN** public effective permissions are requested
- **THEN** the manager returns a Permission collection rehydrated from configured models

#### Scenario: Frontend array cannot poison manager cache
- **GIVEN** Inertia permission props are cached or serialized
- **WHEN** manager permissions are read
- **THEN** the manager never reads the frontend representation key
