## ADDED Requirements

### Requirement: Privileged authority uses one exact canonical identity
`Role::isSuperAdmin()`, manager `isSuperAdmin()`, trait `hasSuperAdminRole()`, and permission overrides SHALL resolve the same exact configured role name and SHALL NOT OR-match another field or case variant.

#### Scenario: Canonical role is privileged
- **GIVEN** the configured canonical name is `Super Admin` and a persisted role has name `Super Admin` and slug `super-admin`
- **WHEN** model, manager, and trait privileged checks run
- **THEN** all checks return true

#### Scenario: Cross-field shadow role is ordinary
- **GIVEN** a distinct role whose name is `super-admin` or whose slug is `Super Admin`
- **WHEN** that role or its assigned user is checked for privileged authority
- **THEN** every privileged check returns false

### Requirement: Security boundaries use explicit role identifier types
Role middleware SHALL interpret route role tokens as exact slugs. Public APIs SHALL expose explicit name and slug checks, and ambiguous compatibility lookup SHALL NOT be used by middleware or privileged checks.

#### Scenario: Canonical slug protects route
- **GIVEN** a user assigned the canonical role with slug `super-admin`
- **WHEN** the user enters a route protected by `role:super-admin`
- **THEN** middleware permits the request

#### Scenario: Shadow name cannot satisfy slug middleware
- **GIVEN** a user assigned only an ordinary role named `super-admin` whose slug differs
- **WHEN** the user enters the same route
- **THEN** middleware returns HTTP 403

### Requirement: Privileged revocation is authoritative
After supported canonical-role revocation commits, subsequent permission, manager, trait, middleware, and role-object checks SHALL no longer grant privileged authority.

#### Scenario: Revoked canonical role loses override
- **GIVEN** a cached privileged user
- **WHEN** the canonical role is removed and the transaction commits
- **THEN** a subsequent permission check returns only ordinary effective authority

#### Scenario: Loaded relationship does not retain privilege
- **GIVEN** the user's roles relationship was loaded before canonical-role removal
- **WHEN** privileged status is checked after committed removal
- **THEN** the stale loaded collection does not grant authority
