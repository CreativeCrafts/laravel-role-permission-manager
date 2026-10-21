## ADDED Requirements

### Requirement: Supported mutations invalidate effective authority after commit
Direct permission, role assignment, role permission, privileged role, and hierarchy mutations SHALL publish a new authorization generation only after successful outer transaction commit.

#### Scenario: Direct grant revocation is visible
- **GIVEN** a user's direct grant has been cached
- **WHEN** the grant is revoked and the transaction commits
- **THEN** a subsequent authorization check denies that grant

#### Scenario: Failed revocation rollback preserves grant
- **GIVEN** a cached and committed grant
- **WHEN** a revocation occurs inside a transaction that rolls back
- **THEN** subsequent authorization still reflects the committed grant and no committed-generation invalidation is published

### Requirement: Indirect authority changes invalidate all affected principals
Changing a role's permissions or parent hierarchy SHALL invalidate effective authority through a generation change without enumerating users assigned to that role.

#### Scenario: Role permission removal affects assigned users
- **GIVEN** multiple users retain a role whose permission is cached
- **WHEN** that permission is removed from the role and committed
- **THEN** subsequent checks for all those users deny it

#### Scenario: Reparenting removes inherited authority
- **GIVEN** a child role inherits a cached parent permission
- **WHEN** the child is reparented and commit succeeds
- **THEN** subsequent checks omit the former parent's permission

### Requirement: Consistency is commit-bound
A check beginning after successful generation publication SHALL not read the prior generation. The package SHALL document that a read already in flight at commit can finish with its captured snapshot.

#### Scenario: Post-commit reader sees revocation
- **GIVEN** revocation and generation publication completed
- **WHEN** another worker begins authorization
- **THEN** it uses the new generation

#### Scenario: In-flight race is not overstated
- **GIVEN** a reader captured the old generation before revocation commit
- **WHEN** it finishes after the commit
- **THEN** the documented contract permits that one in-flight result but not a newly started read
