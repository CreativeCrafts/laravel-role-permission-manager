## Context

This package is a public Composer dependency, so source fixes must align interface declarations, runtime behavior, database upgrades, published assets, and support claims. The pinned test fixture masks parts of the real migration and the current dependency matrix omits Laravel 10. Existing installations may contain logical duplicates permitted by MySQL nullable uniqueness and consumer-published TypeScript files that Composer cannot replace.

## Goals / Non-Goals

**Goals:**

- Make public PHP signatures truthful and migration paths explicit.
- Correct configured direct/inverse Eloquent relationships.
- Test the actual published schema, clean rollback, custom names, and safe upgrade preflight.
- Keep frontend state principal-fresh and backend-authoritative.
- Verify every declared Laravel/PHP combination or report the exact blocker.
- Provide major-release, JTC adoption, and rollback documentation.

**Non-Goals:**

- Destructive duplicate cleanup or production migration.
- Automatic overwrite of consumer-published frontend files.
- A fifth metadata endpoint.
- Laravel 13 support.
- JTC edits or dependency changes.

## Decisions

### 1. Public contracts use explicit supported unions and defaults

`AuthenticatableWithRolesAndPermissions`, trait, and manager agree on `Permission|string` authorization input plus optional exact scope. Role getters use `mixed $user = null`, with null selecting `$this`. Role identity helpers expose name/slug methods. Manager mutation methods use the reference/result contract from the mutation change. Invalid types fail with domain/type exceptions rather than hidden coercion.

Compatibility wrappers remain where safe, are marked deprecated, and delegate to explicit methods. The facade resolves the same container singleton; commands never instantiate a separate unmanaged manager.

Alternative rejected: remove type declarations to hide mismatches. Alternative rejected: keep interface narrower than implementation.

### 2. Relationships name pivot table and keys explicitly

- Role→users uses configured `user_role_table`, `role_id`, and configurable user foreign key.
- Permission→users uses configured `user_permission_table`, `permission_id`, and user key.
- Permission↔roles uses configured `role_permission_table`.
- User trait relationships mirror the inverse keys.

`using()` is used only if a configured class extends Laravel Pivot; table names are never passed as classes. Custom table-name tests exercise both directions and eager loading.

Alternative rejected: Laravel conventions; package defaults intentionally use non-conventional names.

### 3. Published migration is the test fixture

Testbench creates a realistic users table first, then runs the exact published migration. Individual tests no longer substitute simplified role/permission tables except isolated custom-schema tests. Clean rollback drops direct user-permission and role-permission pivots before referenced tables.

Historical migration may be corrected for new installs. Existing installs receive a separately named additive/preflight migration or command; no edited historical file is assumed to rerun.

### 4. Duplicate safety uses preflight before additive identity constraints

The package provides read-only diagnostics grouped by exact tagged `(slug, scope)` and `(name, scope)`, role cross-field collisions, cycles, and pivot duplicates. Null, empty, and `"0"` remain distinct in application identity.

No additive unique identity constraint runs when duplicate groups exist. If a portable identity-key migration is included, it backfills deterministic tagged hashes only after preflight and adds non-null unique columns/indexes transactionally where the engine supports it. Otherwise the task remains explicitly blocked rather than pretending nullable indexes suffice.

Alternative rejected: auto-delete/merge duplicates. Alternative rejected: convert null scope to empty, because the authorization contract distinguishes them. Alternative rejected: trust SQLite as MySQL evidence.

### 5. Creation is concurrency-safe

Permission creation normalizes case/scope, writes deterministic identity fields where available, relies on a database unique constraint, catches a unique-race, and re-reads the exact identity only for explicitly idempotent registration. A normal `createPermission` still reports duplicate creation rather than silently selecting a different record. Assignment uses resolved existing models.

### 6. Frontend helpers accept current state

The React hook reads current `auth.permissions` from each Inertia page and performs exact scope checks using `scope !== null`, not truthiness. It has no privileged-role bypass. The standalone helper reads the current `window.Laravel.permissions` for each call or accepts an explicit snapshot; it does not retain a principal across navigation.

The package shares props before the downstream Inertia response is built and avoids overwriting unrelated host `auth` props. Frontend decisions are display-only; server middleware/Gate remains authoritative.

Alternative rejected: hardcode configurable role slug. Alternative rejected: global memoization without a principal/version key.

### 7. Framework matrix is explicit

CI includes:

- PHP versions allowed by Composer and each Laravel major;
- Laravel 10/Testbench 8/Pest 2/Larastan 2 compatible set;
- Laravel 11/Testbench 9 and Laravel 12/Testbench 10 sets;
- Composer validation, Pest, PHPStan, Pint check, PHP syntax, and frontend type check.

Dependency combinations are constrained per job rather than force-upgrading the package manifest. Laravel 13 is absent.

## Data and Cache Consistency

Public enumeration rehydrates cached IDs from configured models. Relationship mutations clear loaded relations and call commit-aware invalidation. Frontend props serialize fresh authorized server results and never share cache value keys/types with manager internals.

## Transaction and Concurrency Behavior

Schema preflight is read-only. Additive migration validates before changing schema and stops on duplicates. Supported DDL transaction differences are documented per SQLite/MySQL. Creation/sync uses database constraints and transaction locks where supported. No test runs against production.

## Public API Migration

Upgrade documentation contains old/new signatures, Gate prefix, role identifier methods, scope semantics, explicit sync clear, cache config/key transition, metadata authorizer binding, frontend republish steps, schema preflight, and JTC-specific notes. `getUserRoles()` remains a callable controller method but is not registered as an endpoint.

## Migration Plan

1. Pin/backup application code and database; run read-only duplicate/cycle/collision queries.
2. Resolve data findings through separately reviewed application work.
3. Update config/API/Gate/scope call sites and manually merge frontend assets.
4. Disable old cache and drain old nodes.
5. Run additive schema migration only after clean preflight.
6. Deploy major package version and execute application regression suites.
7. Re-enable shared cache only after generation verification.

Rollback reverses additive indexes/columns only where safe, keeps historical data untouched, restores consumer frontend files separately, disables/clears both cache namespaces, and restores matching application code/config. Irreversible operator-selected duplicate cleanup is outside this package and must have its own backup plan.

## Risks / Trade-offs

- [Major release adoption burden] → Complete compatibility table and before/after examples.
- [MySQL unavailable locally] → Leave MySQL tasks unchecked and release blocked; never infer from SQLite.
- [Published assets drift] → Version banner/changelog and explicit `vendor:publish --force` warning plus manual merge path.
- [Correct inverse pivots reveal latent data assumptions] → Custom/default table integration tests.
- [Additive uniqueness blocked by dirty data] → Fail before mutation and provide exact read-only groups.

## Test Strategy

- Reflection/runtime contract tests for every interface/trait/manager method and invalid input.
- Direct/inverse/eager/custom-table relationship tests using actual migration.
- Clean install/down/reinstall and representative upgrade schema/data tests.
- SQLite nullable behavior plus isolated MySQL duplicate/concurrency/rollback evidence.
- TypeScript compilation and behavior tests for login/logout/principal switch/navigation/revocation/null/empty/`"0"`.
- Laravel 10/11/12 and compatible PHP matrix, Composer validate, PHPStan, Pint, syntax.
- Read-only JTC source mapping and documented application regression requirements.

## Open Questions

Whether the local environment provides a MySQL service and all PHP runtimes is verification-dependent. Missing environments block those tasks and release readiness; they do not justify narrowing support or manufacturing passing evidence.
