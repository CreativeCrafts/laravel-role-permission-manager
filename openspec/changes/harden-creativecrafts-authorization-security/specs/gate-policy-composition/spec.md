## ADDED Requirements

### Requirement: Package Gate integration owns only a prefixed namespace
When Gate integration is enabled, the package SHALL decide only abilities beginning with the configured package prefix and SHALL return null for every unrelated ability.

#### Scenario: Explicit package ability is granted
- **GIVEN** an actor with unscoped permission `reports.view`
- **WHEN** Laravel Gate evaluates `role-permission:reports.view`
- **THEN** the package callback returns true

#### Scenario: Unknown host ability is not denied
- **GIVEN** an actor without a package permission named `update`
- **WHEN** Laravel Gate evaluates host ability `update` for a resource
- **THEN** the package callback returns null and Laravel continues to the resource policy

### Requirement: Resource policies retain authority
The package SHALL NOT grant or deny unrelated ownership-sensitive resource abilities and SHALL NOT apply a generic privileged-role bypass to host policies.

#### Scenario: Resource owner policy grant survives
- **GIVEN** a resource policy that grants `update` to its owner and the owner lacks a package permission named `update`
- **WHEN** the owner invokes Gate for that resource
- **THEN** Gate grants according to the policy

#### Scenario: Same-named permission cannot bypass ownership
- **GIVEN** a non-owner with package permission `update`
- **WHEN** the non-owner invokes Gate `update` for the resource
- **THEN** the resource policy denial remains effective

### Requirement: Gate integration can be disabled
When package Gate integration is disabled, the package SHALL register no before callback.

#### Scenario: Disabled integration leaves host Gate unchanged
- **GIVEN** `register_permission_check_method=false`
- **WHEN** the provider boots
- **THEN** host Gate abilities execute without a package callback

#### Scenario: Disabled integration does not expose package prefix
- **GIVEN** Gate integration is disabled and no host Gate definition exists for a package-prefixed ability
- **WHEN** that ability is evaluated
- **THEN** the package does not grant it
