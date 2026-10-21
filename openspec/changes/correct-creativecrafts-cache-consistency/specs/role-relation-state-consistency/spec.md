## ADDED Requirements

### Requirement: Security checks do not trust stale loaded relationships
Role and permission authorization checks SHALL query authoritative supported state or clear affected loaded relations after package mutations.

#### Scenario: Loaded role object remains accurate after removal
- **GIVEN** a user's roles relationship was loaded and contains a Role model
- **WHEN** that role is removed through the package API
- **THEN** `hasRole(Role)` returns false without requiring consumer refresh

#### Scenario: Unrelated loaded state is preserved
- **GIVEN** the user has unrelated loaded attributes or relations
- **WHEN** a role mutation clears authorization relation state
- **THEN** the package does not discard unrelated unsaved consumer state

### Requirement: Independent model instances converge
Separate instances representing the same principal SHALL observe committed authorization mutations through generation invalidation and fresh relationship queries.

#### Scenario: Second instance sees revocation
- **GIVEN** two User model instances for one database row and one has cached authority
- **WHEN** the other instance revokes the role and commits
- **THEN** a subsequent check on the first instance denies the role-derived permission

#### Scenario: Failed mutation does not create false state
- **GIVEN** two model instances and a mutation transaction that rolls back
- **WHEN** either instance checks authority afterward
- **THEN** both observe the unchanged committed state
