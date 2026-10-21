## Context

Authorization caching is currently spread across manager methods and Inertia middleware using the global Cache facade. Configuration, store selection, key identity, TTL, type shape, mutation invalidation, and Eloquent loaded state disagree. Role/hierarchy mutations can affect many principals, so enumerating all users is not an acceptable invalidation strategy.

The consistency boundary is the successful outer database transaction commit. Multiple processes are consistent only when they share the configured cache store. JTC currently configures `use_cache=false`, which must become a true bypass.

## Goals / Non-Goals

**Goals:**

- One package-owned cache abstraction with stable typed keys/values.
- Real disable/store/TTL behavior.
- Commit-aware revocation after every supported mutation without user enumeration.
- Separation of distributed cache freshness from in-process relationship freshness.
- Safe namespace transition and precise consistency claims.

**Non-Goals:**

- Cancellation of reads already in flight at commit time.
- Cross-worker guarantees with local/non-shared stores.
- Detection of raw SQL or unsupported direct pivot writes.
- Safe mixed-version operation while old nodes continue reading legacy privilege keys.

## Decisions

### 1. Inject a package AuthorizationCache service

The service provider binds a singleton using Laravel's configured cache factory/repository. Every manager/model/trait/Inertia package cache path calls this service. When `use_cache=false`, `remember()` evaluates the loader directly and invalidation methods perform no cache I/O.

Alternative rejected: continue facade calls plus conditionals; it remains easy to bypass configuration. Alternative rejected: the existing nested Repository binding; it supplies a Repository where a Store is expected.

### 2. Versioned typed key format

Namespace format is conceptually:

`creativecrafts:lprm:v2:<generation>:<type>:<guard>:<model-hash>:<identifier-hash>:<scope-tag>`

Model morph class, guard, auth identifier, and tagged scope are independently encoded. Null, empty, and `"0"` have distinct tags. Catalog, role IDs, role names, role slugs, effective permission IDs, and frontend serialization never share a type token. Values are scalar arrays of IDs/strings, not Eloquent models.

Alternative rejected: raw class/ID concatenation; delimiters and numeric IDs collide. Alternative rejected: cache serialized Eloquent collections; model/table/config state can drift and frontend paths require a different type.

### 3. Global authorization generation

All authorization-affecting mutations increment a shared generation after commit. Effective permission/role keys include the generation, making prior entries unreachable without enumerating principals. Catalog-only creation also advances generation for simple correctness. A bounded principal mutation additionally forgets known legacy keys during upgrade.

Alternative rejected: enumerate all users with a changed role; it is unbounded. Alternative rejected: tags; not all supported Laravel stores provide tags. Alternative rejected: only per-user keys; hierarchy/role permission changes cannot cheaply identify all affected principals.

### 4. Transaction-aware publication

If the relevant database connection has no open transaction, invalidation advances generation immediately. Inside a transaction, the service marks authorization state pending for this process, bypasses package cache for subsequent reads, and registers `afterCommit` publication. Laravel discards commit callbacks on rollback; the request continues bypassing stale cache if needed.

Alternative rejected: invalidate before commit; another worker can cache uncommitted state and a rollback leaves false invalidation/state. Alternative rejected: wait for TTL; it violates revocation.

### 5. Commit-bound consistency

After database commit and successful generation publication, a newly starting authorization check on nodes using the shared store cannot read the old generation. A read that captured the generation before commit can finish with old authority. This bounded race is documented rather than described as instantaneous global revocation.

If generation publication fails after database commit, mutation code surfaces the cache publication failure where possible and emits a non-sensitive operational error; deployments must prefer fail-closed cache bypass/retry. The explicit cache flush command/API can advance the namespace.

### 6. Eloquent state is invalidated separately

Supported trait/manager mutations call `unsetRelation('roles')`, `unsetRelation('permissions')`, and affected Role parent/children/permissions relations. Security checks query authoritative relationships or reconstructed ID sets, not a previously loaded collection. Two model instances converge through generation invalidation and fresh queries.

Alternative rejected: call `refresh()` automatically; it can discard unrelated unsaved consumer changes.

### 7. TTL is always an absolute expiration

`cache_expiration_time` remains minutes for compatibility. The service converts it to `now()->addMinutes(...)` for all cache types. Non-positive values bypass caching. This avoids Laravel integer-seconds ambiguity.

## Data and Cache Consistency

Effective permission computation loads direct permission IDs, role IDs, direct role permission IDs, and cycle-safe ancestor permissions. A canonical privileged principal materializes current catalog permission IDs but privilege decisions themselves query exact canonical role identity. Cached IDs are rehydrated through configured Permission models/tables before public enumeration.

Generation values are integers initialized atomically where the store supports `add`/`increment`. The design does not require cache tags. Store errors do not silently fall back to an unrelated store.

## Transaction and Concurrency Behavior

- Direct grant/revoke, role attach/detach, role permission changes, hierarchy reparenting, and role/permission identity changes schedule generation publication on their model connection.
- Duplicate concurrent publications are safe; they only skip additional generations.
- A concurrent reader that starts after publication sees the new generation.
- Failed outer transactions do not publish the pending generation.
- Persistent shared-store tests use separate manager/model instances to avoid in-memory false positives.

## Public API Migration

- New `AuthorizationCache` contract exposes cache read/bypass, principal identity, generation invalidation, and explicit flush.
- `cache_store=null` means Laravel default; a named store is honored exactly.
- Hosts performing raw mutations must call the public invalidator after commit.
- Legacy keys are never read. Bounded per-principal legacy keys are forgotten where known.

## Migration Plan

1. Inventory/disable old package authorization caching.
2. Drain old application nodes; safe mixed versions are not claimed.
3. Deploy new namespace code and compatible mutation hooks.
4. Clear known legacy keys or flush the dedicated legacy namespace/store.
5. Advance v2 generation, then enable shared caching.
6. Verify revocation across two processes/requests.

Rollback: disable cache, drain new nodes, clear both namespaces (including privileged entries), restore old code/config, and keep cache disabled until the known old invalidation defects are explicitly accepted.

## Risks / Trade-offs

- [Generation increment unavailable/non-atomic] → Validate supported store behavior; fail/bypass rather than switch stores silently.
- [Cache memory retains unreachable generations until TTL] → Bounded TTL and operational namespace cleanup.
- [Hot global generation invalidates more than necessary] → Simplicity/security chosen over complex dependency graphs; mutation frequency is expected far below read frequency.
- [Read already in flight returns old grant] → Document commit-bound start-time guarantee; do not claim instantaneous cancellation.
- [After-commit cache failure] → Surface/monitor failure and fail-safe cache bypass.

## Test Strategy

- Enabled/disabled and named/default stores with assertions of zero I/O when disabled.
- Key separation for user classes sharing IDs, guards, scope tags, and value types.
- Direct/role/privileged revoke, role permission change, hierarchy reparent, loaded/fresh/two instances.
- Shared persistent cache across simulated requests.
- Successful transaction commit, failed rollback, read while mutation pending, and a controlled concurrent read/revoke interleaving.
- Legacy v1 value present while v2 code runs and documented old/new rolling transition.
- TTL using frozen time and minute conversion.

## Open Questions

MySQL/Redis multi-process evidence depends on locally available services. Missing infrastructure remains an unchecked verification task rather than a claimed pass.
