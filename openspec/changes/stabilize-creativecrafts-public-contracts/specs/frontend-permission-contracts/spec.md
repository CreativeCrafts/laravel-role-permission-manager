## ADDED Requirements

### Requirement: Frontend checks use current principal props
React/Inertia and standalone TypeScript helpers SHALL evaluate the current authorized server permission snapshot and SHALL NOT retain another principal's snapshot across login, logout, navigation, or user switching.

#### Scenario: Principal switch updates checks
- **GIVEN** principal A can edit and principal B cannot
- **WHEN** page/global props switch from A to B
- **THEN** subsequent frontend `can('edit')` returns false

#### Scenario: Logout clears displayed authority
- **GIVEN** an authenticated permission snapshot
- **WHEN** logout supplies empty/absent current props
- **THEN** every permission helper denies rather than using the old snapshot

### Requirement: Frontend scope handling is exact
Helpers SHALL treat null, empty string, `"0"`, and other scopes distinctly and SHALL not use truthiness to decide whether scope was supplied.

#### Scenario: Scope zero succeeds exactly
- **GIVEN** current props contain a permission in scope `"0"`
- **WHEN** `can` requests that permission and scope `"0"`
- **THEN** it returns true

#### Scenario: Omitted scope cannot display scoped authority
- **GIVEN** current props contain only scoped permission
- **WHEN** `can` is called without scope
- **THEN** it returns false under the backend authorization contract

### Requirement: Frontend has no hardcoded privileged bypass
Helpers SHALL derive display authority from current effective permissions and SHALL NOT hardcode a privileged role name or slug.

#### Scenario: Custom privileged designation works through props
- **GIVEN** a host uses a custom canonical role and server returns its effective permissions
- **WHEN** the frontend checks one of those permissions
- **THEN** the result follows props without knowing the role slug

#### Scenario: Shadow role cannot bypass UI check
- **GIVEN** current roles include a string resembling a historical privileged slug but effective permission is absent
- **WHEN** the frontend checks the permission
- **THEN** it returns false

### Requirement: Published asset upgrades are explicit
Documentation SHALL state that Composer does not replace consumer-published files and SHALL provide republish/manual-merge steps and backend-authority warnings.

#### Scenario: Consumer follows upgrade guide
- **GIVEN** an application has committed old published helpers
- **WHEN** it upgrades the Composer package
- **THEN** the guide requires an explicit helper diff/republish and frontend tests

#### Scenario: Old helper remains an adoption blocker
- **GIVEN** the consumer has not updated its helper
- **WHEN** release readiness is assessed
- **THEN** frontend adoption is marked blocked rather than assumed fixed
