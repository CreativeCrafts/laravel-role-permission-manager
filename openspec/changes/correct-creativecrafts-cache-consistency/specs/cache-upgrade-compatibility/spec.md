## ADDED Requirements

### Requirement: New code never reads legacy authorization values
The new cache schema SHALL use a versioned namespace and SHALL NOT deserialize or authorize from legacy role/permission cache keys.

#### Scenario: Legacy privileged value is ignored
- **GIVEN** a legacy key contains a privileged permission snapshot
- **WHEN** new code evaluates the principal
- **THEN** it computes or reads only the new namespace

#### Scenario: Bounded legacy principal keys are forgotten
- **GIVEN** a supported mutation identifies one principal
- **WHEN** invalidation commits
- **THEN** known legacy keys for that principal are forgotten without being read

### Requirement: Rolling deployment has a safe prerequisite
The upgrade guide SHALL require cache disablement or complete old-node drainage before relying on new revocation semantics and SHALL NOT claim safe mixed-version authorization while old nodes read legacy keys.

#### Scenario: Safe rollout order
- **GIVEN** an application currently runs old nodes
- **WHEN** operators follow the documented rollout
- **THEN** old authorization caching is disabled/drained before new caching is enabled

#### Scenario: Mixed nodes remain a blocker
- **GIVEN** old nodes continue serving and reading legacy cache
- **WHEN** a privilege is revoked by new code
- **THEN** release evidence marks global revocation unverified/unsafe rather than complete

### Requirement: Rollback clears privileged representations
Rollback SHALL disable package caching and clear both legacy and new authorization namespaces before old behavior resumes.

#### Scenario: Rollback after new cache population
- **GIVEN** v2 entries include a formerly privileged principal
- **WHEN** operators roll back
- **THEN** both namespaces are cleared before old nodes serve authorization

#### Scenario: Cache values are not translated backward
- **GIVEN** new typed cache values exist
- **WHEN** rollback occurs
- **THEN** they are not copied into legacy keys
