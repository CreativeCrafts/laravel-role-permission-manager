## Context

The package currently merges its permission namespace with every Laravel Gate ability, exposes metadata routes under authentication alone, and resolves security-sensitive role/permission strings ambiguously. Laravel Gate `before` callbacks are terminal whenever they return non-null; therefore the existing callback changes host policy semantics. JTC uses real Jetstream TeamPolicy ownership checks, `role:super-admin` route middleware, scoped permissions, and Inertia authorization props. The design must secure package defaults without altering JTC.

Actors are authenticated models implementing `AuthenticatableWithRolesAndPermissions`. Resources are package Role/Permission definitions, a target authenticatable principal, or host application resources governed by host policies. Role identities have separate name and slug fields. A permission identity is `(model id, slug, scope)`; scope preserves null, empty string, and `"0"`.

## Goals / Non-Goals

**Goals:**

- Compose with Laravel Gate/policies without granting or denying unrelated abilities.
- Enforce self, target-user, and catalog metadata authority independently.
- Use one exact privileged-role identity and explicit role identifier types at boundaries.
- Make scope, wildcard, case, and observational-check behavior deterministic across entry points.
- Keep create/update/delete definition authority separate from possession.

**Non-Goals:**

- A global Super Admin bypass of host policies.
- New package mutation or user-role HTTP routes.
- Automatic role/permission data cleanup or JTC modification.
- Preserving insecure unprefixed Gate or any-scope defaults.

## Decisions

### 1. Package Gate abilities use an explicit prefix and abstain otherwise

`registerGateCheck()` registers a nullable callback. It returns `null` unless the ability starts with configured `gate_permission_prefix` (default `role-permission:`). For owned abilities it strips the prefix, validates a non-empty permission token, takes optional scope from the first Gate argument, and returns the package permission result.

Examples:

- `Gate::allows('update', $team)` is untouched and reaches TeamPolicy.
- `Gate::allows('role-permission:posts.update')` checks an unscoped package permission.
- `Gate::allows('role-permission:posts.update', ['0'])` checks exact scope `"0"`.

Alternative rejected: define every permission dynamically with `Gate::define`. It requires catalog enumeration at boot, becomes stale, and cannot cover newly registered permissions. Alternative rejected: return `false` for unrelated abilities; Laravel treats it as terminal and reproduces V. Alternative rejected: generic privileged bypass; it defeats resource ownership.

### 2. Metadata authorization is a replaceable service

Introduce `MetadataAccessAuthorizer` with separate methods for role catalog, permission catalog, and target-principal inspection. The default:

- denies guests through route middleware;
- permits self-inspection by exact model type plus auth identifier;
- requires `view roles` for role catalog and `view permissions` for permission catalog;
- denies cross-user inspection.

Hosts can replace the binding with policy/callback-backed logic. Controller authorization runs before target lookup for a cross-user request unless the authorizer declares cross-user authority, avoiding existence disclosure to ordinary users. Authorized inspectors receive normal 404 behavior for missing targets. The scoped endpoint filters exact scope.

Alternative rejected: treat every authenticated user as an administrator. Alternative rejected: register a permissive package Gate definition. Alternative rejected: add `getUserRoles` as a fifth route without a reviewed contract.

### 3. Canonical privileged identity is exact configured role name

The existing `super_admin_role` value remains the canonical role **name** for compatibility. `Role::isSuperAdmin()`, manager `isSuperAdmin()`, and trait `hasSuperAdminRole()` query exact name equality with no name/slug OR and no case folding. A Role object is privileged only when its persisted name equals this value. Route middleware resolves each argument as an exact slug via `hasRoleBySlug()`.

General `hasRole(string)` remains a deprecated name-or-slug convenience wrapper only for non-boundary compatibility. New explicit `hasRoleByName()` and `hasRoleBySlug()` methods remove ambiguity.

Alternative rejected: infer canonical identity from whichever name/slug matches first; that preserves KX. Alternative rejected: automatically rename/collapse collisions; it mutates historical authority. A future immutable role ID is possible but cannot be configured portably before roles exist.

### 4. Permission matching is application-level and exact by scope

Permission model matching uses:

- exact ID when the caller supplies a Permission model;
- exact slug or name comparison after configured case normalization for a string;
- exact scope equality, including tagged distinctions among null, `''`, and `'0'`;
- unscoped-only matching when authorization scope is omitted;
- anchored wildcard grammar where only `*` is special and `%`, `_`, and `.` are literals.

Enumeration is separate: omitted scope returns all permissions; supplied scope, including `''` or `'0'`, filters exactly. SQL `LIKE` is never used for equality.

Alternative rejected: omitted authorization scope means any scope; this is F. Alternative rejected: database collation as policy; MySQL/SQLite differ. Alternative rejected: hidden fallback from explicit scope to null; it crosses boundaries.

### 5. Authorization checks are read-only

`hasPermissionTo()` never calls permission creation regardless of `auto_create_permissions`. Explicit assignment/registration APIs can retain documented creation compatibility until deprecation. This separates authorization observation from catalog mutation and removes JU.

### 6. Management policies use action-specific authority

Role policy uses `view roles`, `create roles`, `edit roles`, and `delete roles`; Permission policy uses corresponding permission authority. Possession fallback is removed from create/update/delete. View/list behavior remains separately testable and can permit self-related inspection only through the metadata authorizer, not definition management.

## Execution Flow

1. Middleware authenticates the request.
2. Role middleware resolves route tokens as slugs; permission middleware calls exact unscoped authorization unless scope is explicitly encoded by its public contract.
3. For metadata, controller asks the authorizer before querying/disclosing a target and invokes manager enumeration with explicit scope state.
4. For Gate, Laravel invokes the package before-callback. Unowned ability returns `null`; owned ability delegates to manager.
5. Manager checks canonical privileged identity, loads effective permission identities through the cache service, and performs exact matcher evaluation.
6. Host resource policy runs normally for every unowned ability.

## Data and Cache Consistency

This change consumes versioned effective-permission caching from `correct-creativecrafts-cache-consistency`. Security comparisons never depend on a loaded Role collection. Metadata serialization uses the same current effective set and never shares cache values with frontend arrays. Scope and case normalization occur before key/match generation.

## Transaction and Concurrency Behavior

Authorization performs no writes and opens no mutation transaction. Concurrent revocation has commit-bound semantics: a read already in flight can finish with the old snapshot, but checks beginning after commit/generation publication use the new state. Metadata authorization and target retrieval can race with deletion; authorized callers receive standard not-found behavior without broadening access.

## Public API Migration

- Replace `Gate::allows('permission-slug')` package usage with `Gate::allows('role-permission:permission-slug')`.
- Use `hasRoleBySlug()` for middleware/security boundaries and `hasRoleByName()` when a display name is intended.
- Treat omitted authorization scope as null-scope only; use explicit scope or intentional enumeration APIs.
- Bind `MetadataAccessAuthorizer` for administrative cross-user inspection.
- Keep `register_permission_check_method` as the enable switch, but its semantics become prefixed/abstaining.

## Migration Plan

1. Run read-only role name/slug collision and scoped-slug diagnostics.
2. Update host Gate call sites and metadata authorizer configuration.
3. Review every unscoped permission middleware call.
4. Deploy with package cache disabled while old nodes drain.
5. Deploy new code/config, publish a new cache generation, then optionally enable caching.
6. Execute package and JTC Gate/route regression suites before traffic adoption.

Rollback: disable package Gate integration and metadata routes first; disable cache and clear legacy plus versioned namespaces; restore compatible host Gate calls/config; only then restore old code. Rolling back reintroduces known unsafe semantics and requires explicit risk acceptance.

## Risks / Trade-offs

- [Breaking unprefixed Gate callers] → Major release, migration examples, static source search, and explicit prefixed tests.
- [Existing scoped grants stop satisfying unscoped checks] → Preflight call sites and seed data; require explicit scope.
- [Exact configured role name can be renamed by hosts] → Preflight canonical role existence/collisions; never auto-rename.
- [Custom metadata administration differs by host] → Replaceable authorizer with deny-safe default.
- [Frontend may display stale UI between responses] → Fresh server props and backend enforcement; frontend is never authoritative.

## Test Strategy

- Real Laravel Gate tests with owner/non-owner Team-like policies, grants, denials, unknown abilities, disabled integration, and prefixed abilities.
- HTTP tests for all four registered routes: guest, self, other user, authorized inspector, independent catalogs, missing target, invalid/exact scopes, and no target disclosure.
- Privileged identity matrix: canonical, ordinary, cross-field shadow collisions, case variants, custom config, Role/string, middleware, revocation, and custom User model.
- Permission matrix: null/empty/`"0"`/other scopes, direct/inherited/wildcard grants, `%`, `_`, `*`, periods, case modes, and no database writes.
- Policy tests proving possession cannot create/edit/delete definitions while explicit administrators can.

## Open Questions

No unresolved implementation decision is allowed to weaken the safe defaults. A consumer-specific cross-user authorization policy remains a host decision through the authorizer binding.
