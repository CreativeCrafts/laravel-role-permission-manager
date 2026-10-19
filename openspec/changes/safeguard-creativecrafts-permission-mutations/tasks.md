## 1. Resolution and synchronization

- [x] 1.1 Add PermissionReference and explicit unknown, ambiguous, invalid, and accidental-empty exceptions
- [x] 1.2 Implement complete transactional resolution for Permission model, ID, unique slug, and structured scope references
- [x] 1.3 Implement explicit clear semantics and deterministic attached/detached/unchanged descriptors
- [x] 1.4 Add manager tests for mixed forms, duplicates, malformed/unknown/ambiguous input, empty/clear, idempotency, and rollback

## 2. Commands

- [x] 2.1 Refactor sync command to one complete resolution contract with ID, scope, and explicit clear modes
- [x] 2.2 Refactor assign/remove commands for exact scope, complete failure, transaction, and truthful exit/output behavior
- [x] 2.3 Verify role/permission creation and role assignment commands for ambiguity, scope `"0"`, missing input, idempotency, and failures
- [x] 2.4 Add command tests for valid, partial invalid, scoped duplicate, cancellation, clear, idempotent, and transaction-failure paths

## 3. Hierarchy and propagation

- [x] 3.1 Prevent self/direct/indirect parent cycles under transaction
- [x] 3.2 Replace recursive ancestor/descendant traversal with deterministic visited traversal and legacy-cycle failure
- [x] 3.3 Resolve concrete permission identity once for subtree grant/revoke and visit descendants once
- [x] 3.4 Normalize/deduplicate `hasAllRoles` inputs and align case/slug semantics
- [x] 3.5 Test deep branching hierarchy, reparenting, inherited revoke, legacy cycles, unique traversal, and scoped propagation

## 4. Concurrency, observability, and evidence

- [x] 4.1 Apply row locks/unique-pivot idempotency where supported and publish cache invalidation only after commit
- [x] 4.2 Remove sensitive mutation/read payload logging and add bounded deterministic operation results
- [ ] 4.3 Execute SQLite concurrency comparison and isolated MySQL duplicate/concurrent mutation tests
  SQLite and isolated MySQL suites both pass, including uniqueness and rollback. Two-process duplicate races were not executed.
- [x] 4.4 Document input/result/CLI migration, preflight, transaction guarantees, and rollback
- [x] 4.5 Map G, L, R, JX, KO, KP, and KQ to implementation/tests and run strict OpenSpec validation
