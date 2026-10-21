## ADDED Requirements

### Requirement: Inverse relationships use configured pivot tables
Role-to-user, Permission-to-user, and Permission-to-role relationships SHALL name the configured pivot table and foreign/related keys explicitly and SHALL use `using()` only with a Pivot subclass.

#### Scenario: Default inverse traversal succeeds
- **GIVEN** assignments created through default package pivots
- **WHEN** roles load users and permissions load users/roles
- **THEN** inverse collections contain the assigned models

#### Scenario: Table name is not treated as a class
- **GIVEN** a configured pivot table string
- **WHEN** an inverse relation is queried
- **THEN** Laravel never attempts to instantiate that table string as a Pivot class

### Requirement: Custom table names work bidirectionally
Configured role, permission, and pivot table names SHALL be honored by direct, inverse, eager-loaded, attach, and detach operations.

#### Scenario: Custom schema traverses both directions
- **GIVEN** valid custom table names and assignments
- **WHEN** user-to-role and role-to-user relations are eager loaded
- **THEN** both directions return the same persisted identities

#### Scenario: Default conventional pivot is not queried
- **GIVEN** the configured role-permission table differs from `permission_role`
- **WHEN** `Permission::roles()` is read
- **THEN** only the configured table is queried

### Requirement: Mutation clears affected loaded relationship state
Supported attach/detach operations SHALL unset only affected authorization relationships so subsequent property access is fresh.

#### Scenario: Detach refreshes inverse observation
- **GIVEN** relevant relationships are loaded
- **WHEN** a supported detach commits
- **THEN** subsequent access no longer contains the detached model

#### Scenario: Unrelated relations remain loaded
- **GIVEN** an unrelated relation is loaded
- **WHEN** authorization relations are invalidated
- **THEN** the unrelated relation is not unset
