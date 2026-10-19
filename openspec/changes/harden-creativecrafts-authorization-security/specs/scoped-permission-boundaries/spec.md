## ADDED Requirements

### Requirement: Authorization scope equality is explicit
An authorization check without scope SHALL match only a null-scope grant. A check with scope SHALL match only the exact scope, preserving null, empty string, and `"0"` as distinct values.

#### Scenario: Explicit scope grant succeeds
- **GIVEN** a user holds `jobs.update` in scope `"0"`
- **WHEN** authorization checks `jobs.update` with scope `"0"`
- **THEN** authorization returns true

#### Scenario: Scoped grant cannot satisfy omitted scope
- **GIVEN** the user holds only scoped `jobs.update`
- **WHEN** authorization checks `jobs.update` without scope
- **THEN** authorization returns false

### Requirement: Enumeration scope semantics are separate
Permission enumeration with omitted scope SHALL return all authorized scopes, while an explicitly supplied scope SHALL filter exact scope including empty string and `"0"`.

#### Scenario: Omitted enumeration returns all scopes
- **GIVEN** effective permissions in null and multiple explicit scopes
- **WHEN** the all-permissions enumeration API is called without scope
- **THEN** all effective permission identities are returned

#### Scenario: Empty scope does not mean all
- **GIVEN** permissions with null, empty, and `"0"` scopes
- **WHEN** enumeration requests empty-string scope
- **THEN** only empty-string scoped permissions are returned

### Requirement: Wildcard and case matching is deterministic
Only `*` SHALL act as a wildcard; matching SHALL be anchored, scope-aware, and use the configured case mode. `%`, `_`, and `.` SHALL be literal characters.

#### Scenario: Anchored wildcard grant matches
- **GIVEN** a grant `posts.*` in the requested scope
- **WHEN** `posts.update` is checked in that scope
- **THEN** the grant matches

#### Scenario: SQL wildcard characters remain literal
- **GIVEN** a grant containing `%` or `_`
- **WHEN** a different permission string is checked
- **THEN** those characters do not expand and the check is denied

### Requirement: Authorization evaluation is observational
A permission check SHALL NOT create, update, or delete permission records, even when historical auto-create configuration is enabled.

#### Scenario: Existing grant is observed without writes
- **GIVEN** a matching existing grant
- **WHEN** authorization is evaluated
- **THEN** it returns true and the permission table is unchanged

#### Scenario: Missing permission remains missing
- **GIVEN** `auto_create_permissions=true` and no matching permission row
- **WHEN** authorization is evaluated
- **THEN** it returns false and no permission row is inserted
