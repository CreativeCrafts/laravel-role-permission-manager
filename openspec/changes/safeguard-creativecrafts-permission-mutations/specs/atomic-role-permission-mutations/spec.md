## ADDED Requirements

### Requirement: Supported mutations are transactional
Role assignment/removal, permission assignment/removal/sync, subtree propagation, and hierarchy reparenting SHALL complete atomically on the relevant database connection.

#### Scenario: Multi-role propagation commits fully
- **GIVEN** a valid role subtree and concrete permission
- **WHEN** propagation succeeds
- **THEN** every intended role receives the permission in one committed operation

#### Scenario: Mid-operation failure rolls back
- **GIVEN** a failure occurs after some mutation statements execute
- **WHEN** the transaction aborts
- **THEN** no partial pivot or hierarchy change remains

### Requirement: Concurrent duplicate assignment is idempotent
Supported assignment SHALL rely on unique pivot identity and SHALL not create duplicate logical assignments under repeated or concurrent calls.

#### Scenario: Repeated assignment is unchanged
- **GIVEN** a role already has a permission
- **WHEN** the same assignment runs again
- **THEN** one pivot remains and the result is unchanged

#### Scenario: Concurrent assignment does not duplicate
- **GIVEN** two workers assign the same persisted identity
- **WHEN** both operations complete
- **THEN** at most one logical pivot exists

### Requirement: Cache publication follows transaction outcome
Mutation code SHALL schedule authorization generation publication after commit and SHALL NOT publish successful invalidation/result state after rollback.

#### Scenario: Commit publishes invalidation
- **GIVEN** a mutation changes effective authority
- **WHEN** its outer transaction commits
- **THEN** a new authorization generation is published

#### Scenario: Rollback publishes no success
- **GIVEN** the outer transaction rolls back
- **WHEN** mutation handling completes
- **THEN** no success message/result or committed-generation publication occurs
