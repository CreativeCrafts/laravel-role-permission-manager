## ADDED Requirements

### Requirement: Synchronization resolves the complete target set
Synchronization SHALL resolve and validate every input before changing the pivot. Supported references SHALL include persisted Permission models, positive integer IDs, unambiguous slug strings, and structured slug/scope references.

#### Scenario: Mixed valid references synchronize
- **GIVEN** a target list containing a model, ID, unique slug, and scoped reference
- **WHEN** synchronization runs
- **THEN** duplicates are normalized and the exact resolved permission IDs become the role's assignments

#### Scenario: One unknown reference aborts all mutation
- **GIVEN** existing role assignments and a target list containing valid plus unknown references
- **WHEN** synchronization runs
- **THEN** it fails and leaves every existing assignment unchanged

### Requirement: Clearing all permissions is explicit
An empty target SHALL fail unless the caller supplies explicit clear intent or calls the dedicated clear API.

#### Scenario: Explicit clear succeeds
- **GIVEN** a role with permissions
- **WHEN** synchronization receives an empty target with explicit clear intent
- **THEN** all assignments are detached atomically

#### Scenario: Accidental empty target is rejected
- **GIVEN** a role with permissions
- **WHEN** synchronization receives an empty target without clear intent
- **THEN** it fails and detaches nothing

### Requirement: Synchronization returns an auditable result
Successful synchronization SHALL return deterministic `attached`, `detached`, and `unchanged` permission descriptors containing ID, slug, and exact scope.

#### Scenario: Changed sync reports each category
- **GIVEN** current and target sets overlap but differ
- **WHEN** synchronization commits
- **THEN** each resolved permission appears exactly once in the correct sorted result category

#### Scenario: Failed sync reports no success
- **GIVEN** a transaction failure
- **WHEN** synchronization rolls back
- **THEN** no successful mutation result is returned or logged
