## Why

At the pinned revision, permission synchronization can destructively apply a partially or incorrectly resolved target set, role hierarchy cycles can recurse without bound, and subtree propagation can duplicate work or select the wrong scope. These defects make routine CLI or manager mutations capable of revoking valid authority or corrupting effective authorization (G, L, R, JX, KO, KP, KQ).

## Verified Existing Behavior

- The sync command resolves Permission models, skips unknown inputs, and passes models to a manager that queries them as slug strings before calling destructive `sync()`.
- Manager sync silently ignores unknown slugs; an empty resolved set detaches all assignments without an explicit clear intent.
- Slug-only resolution is ambiguous when the same slug exists in multiple scopes.
- Assignment/removal/sync paths are not consistently transactional and report success after partial resolution.
- `setRoleParent()` permits self-parenting and indirect cycles; recursive ancestor/descendant traversal has no visited set.
- `getSubRoles()` re-traverses descendants already returned recursively, and subtree propagation loses scope when given a string.
- `hasAllRoles()` compares database count with raw duplicate inputs and applies different case semantics from `hasAnyRole()`.

## What Changes

- **BREAKING** Define accepted manager sync references: persisted Permission model, integer ID, unambiguous slug string, and structured `{slug, scope}` reference. Reject invalid models, unknown values, malformed arrays, and ambiguous slug-only values.
- Resolve and validate the complete target set inside one database transaction before any pivot mutation.
- Require an explicit clear operation/flag for an empty target; empty accidental input fails.
- Return deterministic attached, detached, and unchanged permission descriptors for observability.
- Make CLI sync, assign, and remove fail on any unresolved or ambiguous input, cancellation, or transaction failure; never print success for partial work.
- Add explicit CLI scope selection and preserve ID mode. Mixed per-item scopes unsupported by a command are rejected with actionable guidance.
- Prevent self/indirect hierarchy cycles under a transaction and lock the changed hierarchy rows where supported.
- Replace recursive hierarchy traversal with visited, deterministic traversal that detects legacy cycles and visits each role once.
- Resolve one concrete Permission identity before subtree propagation.
- Normalize/deduplicate role-set input for `hasAllRoles`.
- Publish commit-aware authorization invalidation only after successful mutation.

## New Guarantees

- Unresolved, ambiguous, or partially valid sync input causes zero pivot mutation.
- Clearing all permissions is explicit and distinguishable from failed lookup.
- Supported permission mutation is atomic and returns auditable change sets.
- Self-parenting and direct/indirect cycles are rejected; legacy cycles cannot cause infinite execution.
- Descendants are unique and permission propagation cannot cross scope by slug accident.
- Commands return failure when the requested operation did not complete.

## Capabilities

### New Capabilities

- `safe-permission-synchronization`: Accepted references, complete resolution, explicit clear, atomic sync, result shape, and CLI behavior.
- `scoped-permission-resolution`: Exact scope identity, ambiguity errors, ID/model/string/structured inputs, and command scope handling.
- `role-hierarchy-integrity`: Direction of inheritance, cycle prevention/detection, unique traversal, depth/branch behavior, and reparenting.
- `atomic-role-permission-mutations`: Transaction, concurrency, idempotency, assignment/removal, rollback, and cache publication.
- `permission-mutation-observability`: Deterministic results and non-sensitive CLI/diagnostic output.

### Modified Capabilities

None; the pinned repository has no canonical OpenSpec capabilities.

## Compatibility Consequences

- `syncPermissions($role, [])` no longer means implicit clear; callers use an explicit clear method/flag.
- Ambiguous slug-only operations that historically selected an arbitrary row now fail.
- Sync result values become structured descriptors and include `unchanged`.
- Commands gain scope/clear options and stricter nonzero exits.
- Legacy cyclic data is not silently rewritten; preflight reports it and runtime traversal fails safely.
- Existing direct and inherited grant direction remains child-inherits-parent.

## Impact

Affected code includes manager mutation methods, Role hierarchy traversal, trait helpers, assignment/removal/sync/create commands, exceptions/value objects, cache invalidation hooks, integration tests, and upgrade docs. Historical permission/role rows are read for preflight but are not automatically deleted, merged, renamed, or reassigned.

## Dependencies

- Exact authorization scope semantics are defined by `harden-creativecrafts-authorization-security`.
- Commit-aware invalidation is supplied by `correct-creativecrafts-cache-consistency`.
- Public signatures, schema preflight, and migration compatibility are finalized by `stabilize-creativecrafts-public-contracts`.

## Explicit Exclusions

- No production mutation or automatic historical-data cleanup.
- No unbounded recursive traversal or user enumeration.
- No attempt to infer which duplicate historical permission is canonical.
- No JTC command execution or source modification.

## Risks and Rollback

Stricter resolution will surface previously hidden duplicate/scoped data and may block automation until callers provide scope or ID. Deployments must run read-only duplicate/cycle preflight and update command invocations before enabling writes. Schema constraints, if later applied, must follow clean preflight. Rollback cannot safely replay a partially specified historical sync; disable mutation commands, complete/rollback the database transaction, flush authorization cache generations, then restore code. No irreversible data transform is part of this change.
