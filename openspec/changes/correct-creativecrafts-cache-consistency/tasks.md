## 1. Cache service and key contract

- [x] 1.1 Implement and bind the package AuthorizationCache service using configured enablement/store
- [x] 1.2 Add versioned typed keys for catalog, role, effective permission, guard, model, principal, and scope identities
- [x] 1.3 Store stable scalar identity arrays and rehydrate public model collections
- [x] 1.4 Convert documented minute TTLs to absolute expirations and bypass non-positive TTLs
- [x] 1.5 Remove all direct package authorization Cache facade paths, including Inertia sharing

## 2. Commit-aware invalidation

- [x] 2.1 Implement shared generation initialization/increment and bounded legacy principal-key cleanup
- [x] 2.2 Defer generation publication to outer commit and bypass cache while mutation is pending
- [x] 2.3 Wire direct permission, role assignment, role permission, hierarchy, privileged, and catalog mutations to invalidation
- [x] 2.4 Clear affected loaded Eloquent relationships without refreshing unrelated model state

## 3. Regression evidence

- [x] 3.1 Test cache disabled versus enabled and named/default store selection with zero-I/O assertions
- [x] 3.2 Test class/ID, guard, scope, and frontend/internal value collision resistance
- [x] 3.3 Test direct, role, privileged, role-permission, and hierarchy revocation with loaded/fresh/two model instances
- [ ] 3.4 Test successful commit, failed rollback, pending-transaction read, and controlled concurrent read/revoke behavior
  Commit, rollback, and pending-read behavior is covered. No multi-worker harness was available for the concurrent case.
- [ ] 3.5 Test persistent shared-store cross-request behavior and old/new namespace transition
  Legacy-key isolation is covered in-process. No shared Redis or database cache service was available for cross-process verification.
- [x] 3.6 Test TTL units with frozen time

## 4. Upgrade and evidence

- [x] 4.1 Document shared-store requirements, commit-bound consistency, raw-mutation invalidation, rollout, and rollback
- [x] 4.2 Map D, E, JV, KF, and KW to implementation and executed tests
- [x] 4.3 Run strict OpenSpec validation and leave unavailable multi-worker infrastructure tasks unchecked
