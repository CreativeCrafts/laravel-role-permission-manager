## Why

At pinned commit `7718f71c55eb23874e5e82951b70903897986860`, package authorization can override unrelated Laravel policies, expose another user's authorization metadata to any authenticated caller, confuse role names with slugs at protected boundaries, and cross permission scopes. These are source-confirmed defects or conditional escalation paths that require secure defaults before a consumer such as JTC can safely upgrade.

## Verified Existing Behavior

- A global `Gate::before` callback returns `true` or `false` for every ability. Laravel treats both as final, so a package permission can bypass an ownership policy and a missing package permission can suppress a legitimate policy grant (H, V).
- The four default metadata GET routes require authentication but have no catalog or target-user authorization. The scoped user endpoint calls a trait path that ignores its scope (J, JS).
- `RolePolicy` and `PermissionPolicy` allow create/update/delete fallback based on possession of the target role or permission (JN, JO).
- Permission checks treat omitted scope as any scope, string mutation resolution can select the first same-slug record, SQL `LIKE` treats `%` and `_` as wildcards, and null/empty/`"0"` scope semantics differ by entry point (F, JW, JX, JY, JZ).
- `hasRole(string)` OR-matches names and slugs. Role middleware relies on that ambiguous lookup, while privileged identity is name-based in model/manager paths and ambiguous in the trait (KX).
- With `auto_create_permissions=true`, a permission check writes to the catalog (JU).

No default package mutation route makes JN or JO a confirmed remote exploit at the pinned revision. KX is a conditional source-confirmed path, not evidence that a production shadow role exists.

## What Changes

- **BREAKING** Replace catch-all Gate behavior with a collision-resistant package ability prefix. The callback returns `null` for abilities the package does not own.
- **BREAKING** Require independent catalog authority and deny cross-user metadata inspection by default. Preserve authenticated self-inspection and expose a host-replaceable metadata authorizer.
- Resolve the canonical privileged role by one exact configured identifier across Role, manager, trait, middleware-relevant checks, Blade behavior, and serialized frontend state.
- **BREAKING** Make route role arguments explicit slug identities; an unrelated role name can no longer satisfy a slug-protected route.
- Require explicit, action-specific role/permission definition management authority; possession is not management.
- Define exact scope and case semantics, including distinct null, empty, and `"0"` scopes and unscoped-only authorization when scope is omitted.
- Define anchored `*` wildcard matching and literal `%`, `_`, and period handling without SQL `LIKE`.
- Make authorization evaluation observational; it never creates a permission.
- Add real HTTP, Gate/policy, middleware, and Eloquent integration tests with positive and adversarial negative cases.

## New Guarantees

- Unrelated Laravel policies retain final authority.
- Only explicitly prefixed package Gate abilities receive package decisions.
- Guests cannot read metadata; ordinary users can inspect only themselves; target-user and catalog access require separately authorized capabilities.
- A shadow role cannot impersonate the canonical privileged identity or a slug-protected route.
- Scope A cannot authorize scope B, and omitted scope cannot consume a scoped grant.
- Definition management requires explicit create/edit/delete authority.
- Authorization checks perform no writes.

## Capabilities

### New Capabilities

- `permission-metadata-access`: Authentication, self-inspection, catalog authority, cross-user extension points, status/payload semantics, and scoped metadata filtering.
- `gate-policy-composition`: Package-owned Gate namespace, abstention, resource-policy composition, and disabled-integration behavior.
- `privileged-role-identity`: Canonical privileged identity and explicit role name/slug resolution at security boundaries.
- `role-permission-management-authority`: Distinct view/list/create/update/delete authority without possession fallbacks.
- `scoped-permission-boundaries`: Scope, case, wildcard, exact-comparison, and observational authorization contracts.

### Modified Capabilities

None; the pinned repository has no canonical OpenSpec capabilities.

## Compatibility Consequences

- Applications using unprefixed Gate abilities as package permission checks must migrate to the configured prefix.
- Applications relying on authenticated-only catalog or cross-user metadata access must bind/configure explicit authority.
- Middleware strings are slug identifiers, not name-or-slug aliases.
- Unscoped permission checks no longer match scoped grants. This is intentionally breaking and may affect JTC's unscoped middleware calls.
- Existing `auto_create_permissions` configuration remains readable for mutation compatibility but is ignored by authorization evaluation.
- Existing roles and permissions are never renamed, deleted, or reassigned automatically.

## Impact

Affected code includes the package service provider, metadata controller/routes, policies, role/permission models, user trait, manager, middleware, Blade helpers, configuration, tests, README/upgrade guidance, and frontend serialization contract. JTC's TeamPolicy Gate calls, `role:super-admin` routes, scoped seed data, and metadata/Inertia consumers require read-only compatibility review.

## Dependencies

- Cache reads and invalidation use the contracts introduced by `correct-creativecrafts-cache-consistency`.
- Exact permission resolution and hierarchy-safe model behavior use `safeguard-creativecrafts-permission-mutations`.
- Public signatures, relationships, migration fixtures, and frontend assets are finalized in `stabilize-creativecrafts-public-contracts`.

## Explicit Exclusions

- No generic global Super Admin bypass of host policies.
- No new role metadata route.
- No JTC source/configuration changes.
- No production data mutation, release publication, Laravel 13 claim, or unrelated policy architecture rewrite.

## Risks and Rollback

The secure Gate, scope, metadata, and middleware semantics are intentionally incompatible with unsafe historical behavior and require a major release. Rollout must preflight role identity collisions and scoped consumers, deploy configuration before enabling metadata access, disable old cache behavior during mixed-version operation, and run JTC policy regressions. Rollback must flush both legacy and versioned authorization caches; it must not restore broad metadata exposure or stale privileged grants. A code rollback cannot safely be treated as an authorization rollback without those cache and configuration steps.
