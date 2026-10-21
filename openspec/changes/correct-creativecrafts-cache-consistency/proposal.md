## Why

The pinned package advertises configurable authorization caching, but production paths bypass the package cache binding, collide across principals and value types, and omit invalidation after privilege mutations. Revoked direct, role, inherited, or privileged grants can therefore remain effective in a worker or shared cache (D, E, JV, KF, KW).

## Verified Existing Behavior

- Manager and Inertia paths call the global Cache facade directly regardless of `use_cache`; the configured `role-permission.cache` binding is unused and incorrectly wraps an already-created repository.
- `user_permissions_{id}` is shared by manager Eloquent collections and Inertia arrays, and omits model class and guard. Role-name/slug caches have the same numeric-ID collision pattern.
- TTL integers documented as minutes are interpreted by Laravel as seconds, while another path uses an absolute 60-minute time.
- User role assignment clears role-list keys but not effective permissions; role removal and direct permission mutations omit necessary invalidation; role permission and hierarchy changes do not invalidate affected users.
- `hasRole(Role)` can answer from a loaded Eloquent relationship after supported pivot mutation.
- In-process loaded relationships and distributed cache are separate stale-state sources.

## What Changes

- Route every package-owned cache read/write through one injected cache service that honors `use_cache`, `cache_store`, and documented minute TTLs.
- Introduce a versioned, typed namespace whose principal keys include model morph identity, authentication identifier, guard, scope/mode, and cache generation.
- Cache stable permission/role identity lists rather than mixing Eloquent collections with frontend arrays.
- Use generation invalidation for role permission/hierarchy changes so mutation cost does not enumerate an unbounded user set.
- Invalidate only after successful outer transaction commit; bypass package cache in a transaction with pending authorization mutation.
- Clear affected loaded relationships after supported assignment/removal operations and query authoritative pivot state for security decisions.
- Write/forget legacy per-user keys during the transition where a bounded principal is known, while new code never reads legacy values.
- Define commit-bound revocation consistency and document the unavoidable in-flight read race.
- Add persistent-store, cache-disabled, model/guard collision, rollback, multi-instance, and rolling-upgrade tests.

## New Guarantees

- `use_cache=false` performs no package-owned authorization cache reads or writes.
- The configured store is the only store used by package caching.
- Cache values and namespaces are type stable and do not collide across user classes, guards, identities, scopes, or frontend representation.
- Every supported authorization mutation becomes visible to subsequent checks after the successful outer transaction commit.
- A failed transaction does not publish uncommitted authorization state or invalidate committed state as though the mutation succeeded.
- Loaded Eloquent relationships do not preserve authority after supported revocation.
- New code cannot consume a legacy privileged cache value.

## Capabilities

### New Capabilities

- `effective-permission-cache`: Typed principal/cache identity, cache bypass, configured store, and effective-permission materialization.
- `authorization-revocation-consistency`: Commit-bound invalidation for direct, role, inherited, hierarchy, and privileged grants.
- `role-relation-state-consistency`: In-process Eloquent relation invalidation and authoritative security queries.
- `cache-store-and-ttl-contract`: Store selection, enable/disable behavior, TTL units, and stable value types.
- `cache-upgrade-compatibility`: Versioned namespace, legacy key handling, mixed-version limitations, deployment, and rollback.

### Modified Capabilities

None; the pinned repository has no canonical OpenSpec capabilities.

## Compatibility Consequences

- Cache keys and values change completely; existing entries are intentionally ignored by new code.
- `cache_store` must name a Laravel-configured shared store when cross-worker consistency is required. Null selects Laravel's default store.
- TTL remains configured in minutes but is now applied consistently, so entries historically expiring after seconds live for the documented interval unless invalidated sooner.
- Direct database or raw pivot writes remain outside supported invalidation; hosts must use package APIs or invoke the explicit invalidator.
- Instantaneous cancellation of a read already in flight is not promised. The guarantee begins after mutation commit and generation publication.

## Impact

Affected code includes service-provider bindings, manager reads, trait mutations, Role/Permission model events, Inertia sharing, configuration, transaction hooks, tests, and upgrade/rollback docs. Persistent-store verification requires an isolated test cache; production cache access is excluded.

## Dependencies

- Mutation entry points and hierarchy operations from `safeguard-creativecrafts-permission-mutations` must call the cache invalidator.
- Authorization identity/scope semantics come from `harden-creativecrafts-authorization-security`.
- Public cache configuration and frontend contracts are documented by `stabilize-creativecrafts-public-contracts`.

## Explicit Exclusions

- No unbounded enumeration of every user on role mutation.
- No claim that mixed old and new application nodes provide safe revocation while old nodes continue reading legacy caches.
- No production cache flush, infrastructure change, or distributed lock service.
- No guarantee for raw SQL or unsupported direct relationship mutation.

## Risks and Rollback

Generation counters require a shared, atomic cache backend for multi-worker behavior; non-shared stores provide only worker-local consistency and are documented as such. Deployments must disable authorization caching or drain old nodes, deploy new code, publish a new generation, and then re-enable caching. Rollback must disable caching, remove/rotate the new namespace, clear legacy privilege keys, restore old code only after the security risk is accepted, and never repopulate old values from new serialized entries.
