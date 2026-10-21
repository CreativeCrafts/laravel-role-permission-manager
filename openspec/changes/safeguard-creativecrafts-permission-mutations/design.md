## Context

Permission sync currently combines permissive lookup with destructive pivot replacement. The CLI performs one resolution pass and the manager performs an incompatible second pass. Hierarchy operations are recursive and accept cycles. The package must preserve child-inherits-parent behavior while making mutation identity, atomicity, scope, command results, and cache publication explicit.

## Goals / Non-Goals

**Goals:**

- Resolve every requested permission identity before changing pivots.
- Distinguish explicit clear from absent/invalid input.
- Make manager and CLI mutations atomic, idempotent, scoped, and observable.
- Prevent new hierarchy cycles and safely detect legacy cycles.
- Visit each descendant once and publish cache invalidation after commit.

**Non-Goals:**

- Automatic merge/deletion of duplicate historical permissions.
- A free-form string grammar that silently guesses IDs/scopes.
- Production command execution or JTC mutation.
- Recursive traversal without a finite visited bound.

## Decisions

### 1. PermissionReference normalizes supported input

Manager mutation methods accept:

- persisted `Permission`;
- positive integer ID;
- non-empty slug string, only when exactly one row has that slug;
- structured `PermissionReference` (and documented array compatibility) containing slug plus explicit scope, where scope can be null, `''`, or `'0'`.

Numeric strings remain slugs unless a CLI uses `--use-ids`; this avoids changing a valid numeric slug into an ID. A model must exist and use the configured connection/table. Duplicate equivalent references deduplicate by resolved ID. Invalid types throw a domain exception before pivot mutation.

Alternative rejected: `first()` by slug; it is nondeterministic across scopes. Alternative rejected: infer `scope:slug` using punctuation; both fields can contain punctuation and escaping becomes a hidden grammar.

### 2. Resolver and sync execute in one transaction

`syncPermissions()` opens/reuses the role connection transaction, resolves all rows, locks permission rows when supported, calculates current/target IDs, then performs one `sync()`. Any unknown/ambiguous/malformed input aborts. It returns `attached`, `detached`, and `unchanged` arrays of `{id, slug, scope}` sorted by ID.

Alternative rejected: resolve outside the transaction; rows can change before sync. Alternative rejected: partial success; destructive operations cannot safely infer user intent.

### 3. Empty input requires explicit clear

`syncPermissions($role, [], false)` fails. `syncPermissions($role, [], true)` or `clearPermissions($role)` intentionally clears. CLI requires `--clear`, rejects combining it with permission identifiers, and confirms the exact effect.

Alternative rejected: historical implicit clear; a missing shell expansion or failed resolver becomes destructive.

### 4. CLI performs one complete resolution contract

`role-permission:sync`, `assign`, and `remove` support slug mode plus optional exact `--scope`, or ID mode. Every identifier must resolve; repeated identifiers are idempotent. Ambiguous slug without scope fails and lists no sensitive record payload. Cancellation returns failure/non-completion and makes no mutation. Exceptions/rollback return `Command::FAILURE`; success is emitted only after commit.

Mixed scopes in one CLI invocation are explicitly rejected; callers use IDs or separate scoped invocations. The manager remains capable of mixed structured references.

### 5. Hierarchy integrity uses iterative graph traversal

Before assigning parent P to role R:

- both must be persisted on the configured role table/connection;
- R cannot equal P;
- walking P's ancestor IDs cannot encounter R;
- a repeated ancestor indicates legacy cycle and fails safely.

Traversal uses a queue/stack plus visited IDs, deterministic ID ordering, and a configurable defensive node limit. `getAllPermissions()` walks ancestors once; descendants are breadth-first and unique. Existing direction remains: a child inherits direct grants from ancestors.

Alternative rejected: recursive Eloquent properties; cycles exhaust stack and loaded relations become stale. Alternative rejected: database recursive CTE only; support and syntax differ across declared database engines.

### 6. Propagation resolves once

Subtree grant/revoke resolves a concrete Permission before traversal. Every role receives/removes that same ID in one transaction. Scope is therefore intrinsic and cannot drift. Each role is visited once.

### 7. Concurrency and invalidation

Role and relevant pivot rows are locked where supported. Database unique pivot indexes make duplicate concurrent attachment idempotent. Cache generation publication is registered only after successful outer commit through the cache service. Rollback publishes no success/result.

## Data and Cache Consistency

Mutation result descriptors contain identifiers only and are safe to log at normal verbosity; user payloads and full permission metadata are excluded. The affected role/model loaded relations are unset after mutation. Generation invalidation makes all principals using a changed role/hierarchy re-evaluate without enumerating them.

## Public API Migration

- New explicit `PermissionReference` and resolution exceptions.
- `syncPermissions(Role, array, bool $clear = false)` rejects accidental empty targets.
- `clearPermissions(Role)` is the intention-revealing clear API.
- Sync results add `unchanged` and use descriptors rather than names.
- Propagation accepts an optional scope for string input or a concrete Permission.
- Commands document ID/slug/scope modes and nonzero cancellation/failure.

## Migration Plan

1. Run read-only duplicate permission and hierarchy-cycle diagnostics.
2. Update callers relying on implicit empty clear or ambiguous slug selection.
3. Update automation to pass scope/ID and handle structured results/nonzero exits.
4. Deploy cache/transaction support before enabling mutation commands.
5. Exercise idempotent dry-run/preflight in an isolated copy; do not mutate production as part of package release.

Rollback: stop mutation commands, allow/rollback open transactions, clear new/legacy authorization caches, restore callers and code together. Structured results do not mutate schema and require no data reverse transform.

## Risks / Trade-offs

- [Historical duplicate data blocks operations] → Actionable preflight; no arbitrary canonical selection.
- [Lock behavior differs by SQLite/MySQL] → Test both; correctness relies on transaction plus unique pivots, not SQLite lock claims.
- [Deep valid hierarchy cost] → Iterative O(nodes+edges), unique visits, defensive limit.
- [Major API result change] → Major release and before/after examples.
- [Cancellation traditionally maps to success in some CLIs] → Security semantics choose failure/non-completion and document it.

## Test Strategy

- Manager sync for model, ID, slug, structured scope, mixed forms, duplicates, unknown/ambiguous, malformed, empty/explicit clear, idempotency, and rollback.
- CLI sync/assign/remove for slug, ID, scope, partial invalid, ambiguity, cancellation, clear, output, and exit code.
- Direct/indirect/self cycle rejection, legacy cycle detection, deep/branching hierarchy, unique descendants, reparenting, inherited revoke.
- Scope propagation with duplicate slugs in null/empty/`"0"`/other scopes.
- Concurrent attach/create/sync on MySQL where available and SQLite comparison.

## Open Questions

No destructive duplicate remediation is selected. Operators must decide which historical duplicate is canonical outside this package change after independent data review.
