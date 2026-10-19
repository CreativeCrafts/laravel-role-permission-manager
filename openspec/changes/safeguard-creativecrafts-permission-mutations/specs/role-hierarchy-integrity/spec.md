## ADDED Requirements

### Requirement: Parent assignment prevents cycles
The package SHALL reject self-parenting, direct cycles, and indirect cycles before saving hierarchy changes.

#### Scenario: Valid reparenting succeeds
- **GIVEN** two acyclic persisted role trees
- **WHEN** a role is assigned a parent that is not itself or its descendant
- **THEN** the new parent commits

#### Scenario: Indirect cycle is rejected
- **GIVEN** A is an ancestor of C
- **WHEN** A is assigned parent C
- **THEN** the operation fails and the original hierarchy remains unchanged

### Requirement: Traversal is cycle-safe and unique
Ancestor and descendant traversal SHALL use visited persisted identities, SHALL visit each role at most once, and SHALL fail safely on a legacy cycle or configured defensive bound.

#### Scenario: Deep branching tree is complete
- **GIVEN** a finite deep hierarchy with branches
- **WHEN** descendants or inherited permissions are enumerated
- **THEN** every reachable role is included once in deterministic order

#### Scenario: Legacy cycle cannot recurse forever
- **GIVEN** historical rows already contain a cycle
- **WHEN** hierarchy traversal runs
- **THEN** it raises a hierarchy integrity error within a finite number of steps

### Requirement: Inheritance direction remains child from parent
A child role SHALL inherit direct permissions from its ancestors; a parent SHALL NOT inherit a child's direct permissions.

#### Scenario: Child receives parent grant
- **GIVEN** a parent has a direct permission and its child does not
- **WHEN** the child's effective permissions are calculated
- **THEN** the parent permission is included

#### Scenario: Parent excludes child-only grant
- **GIVEN** only the child has a direct permission
- **WHEN** the parent's effective permissions are calculated
- **THEN** the child-only permission is excluded
