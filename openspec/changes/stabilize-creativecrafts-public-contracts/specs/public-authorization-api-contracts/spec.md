## ADDED Requirements

### Requirement: Declared PHP contracts match runtime implementations
The authenticatable interface, trait, manager contract, manager implementation, and facade-visible service SHALL declare compatible Permission/string/scope types, return types, and exceptions.

#### Scenario: Permission model authorization input works
- **GIVEN** a persisted Permission model granted to a user
- **WHEN** the interface-typed user calls `hasPermissionTo($permission)`
- **THEN** the implementation evaluates that exact Permission identity without a type error

#### Scenario: Invalid permission input fails predictably
- **GIVEN** a caller supplies an unsupported array/object to a typed authorization method
- **WHEN** PHP invokes the method
- **THEN** it raises the documented type/domain error without mutation

### Requirement: Null-to-self role getters have optional arguments
Trait role getter methods SHALL declare `mixed $user = null`, SHALL select `$this` for omitted/null input, and SHALL accept an explicitly supplied compatible principal.

#### Scenario: Omitted getter uses self
- **GIVEN** an assigned user model using the trait
- **WHEN** `getUserRoleSlugs()` is called with no argument
- **THEN** it returns that model's role slugs

#### Scenario: Invalid supplied principal is rejected
- **GIVEN** an incompatible supplied object
- **WHEN** a role getter is called
- **THEN** it raises the documented error rather than querying an arbitrary object

### Requirement: Empty and duplicate collection inputs are defined
Role/permission any/all methods SHALL normalize duplicates consistently, SHALL avoid invalid SQL for empty inputs, and SHALL document vacuous all/false any semantics.

#### Scenario: Duplicate all-role input succeeds once
- **GIVEN** a user has role slug `editor`
- **WHEN** `hasAllRoles(['editor', 'editor'])` is called
- **THEN** it returns true after deduplication

#### Scenario: Empty any-role input is false
- **GIVEN** any user
- **WHEN** `hasAnyRole([])` is called
- **THEN** it returns false without issuing invalid SQL
