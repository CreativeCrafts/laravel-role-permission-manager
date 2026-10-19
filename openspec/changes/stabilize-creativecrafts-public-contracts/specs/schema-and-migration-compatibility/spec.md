## ADDED Requirements

### Requirement: Published migration installs and rolls back cleanly
Tests SHALL run the actual published migration after a representative users table exists, SHALL verify all foreign keys/indexes/pivots, and SHALL drop dependent pivots before referenced tables on rollback.

#### Scenario: Clean install and traversal
- **GIVEN** an empty supported test database with users table
- **WHEN** the published migration runs
- **THEN** all configured role/permission tables and constraints support direct/inverse assignments

#### Scenario: Clean rollback respects dependencies
- **GIVEN** the installed package schema
- **WHEN** migration down runs
- **THEN** dependent pivots are dropped first and no package table remains

### Requirement: Historical duplicates are preflighted without mutation
Before adding identity constraints, the package SHALL report exact duplicate role/permission/pivot/cycle/cross-field groups and SHALL stop without schema/data mutation if blockers exist.

#### Scenario: Clean preflight permits additive migration
- **GIVEN** representative existing data has no logical duplicate or cycle
- **WHEN** read-only preflight runs
- **THEN** it reports clean status and the separately invoked additive migration can proceed

#### Scenario: Null-scope duplicates block safely
- **GIVEN** MySQL data contains duplicate null-scope permission identity rows
- **WHEN** preflight or upgrade migration runs
- **THEN** it reports the conflicting IDs and performs no deletion, merge, rename, or constraint addition

### Requirement: Scope uniqueness is portable and exact
The upgrade design SHALL distinguish null, empty, and `"0"` while protecting concurrent duplicate logical permission identity on every claimed database engine.

#### Scenario: Distinct tagged scopes coexist
- **GIVEN** same slug with null, empty, and `"0"` scopes
- **WHEN** valid rows are created
- **THEN** all three identities can coexist

#### Scenario: Concurrent exact duplicate is rejected
- **GIVEN** two workers create the same exact slug/scope identity
- **WHEN** writes race on a supported engine
- **THEN** at most one logical identity commits

### Requirement: Existing installations use an additive path
Correcting the historical migration SHALL apply to new installs only; installed applications SHALL receive a separately named, preflighted, rollback-documented upgrade migration.

#### Scenario: New install gets corrected schema
- **GIVEN** no package migration has run
- **WHEN** current migrations install
- **THEN** the corrected new-install schema is created

#### Scenario: Existing install is not reset
- **GIVEN** package 1.0.3 tables and data exist
- **WHEN** upgrade instructions are followed
- **THEN** no historical migration is rerun and no destructive reset occurs
