## ADDED Requirements

### Requirement: Metadata endpoints enforce actor and resource authority
The package SHALL require authentication for all registered metadata endpoints, SHALL permit an actor to inspect its own effective permissions, and SHALL deny cross-user inspection by default. A host SHALL be able to replace the metadata authorizer to grant explicit target-user authority.

#### Scenario: Authenticated self-inspection succeeds
- **GIVEN** an authenticated actor and a route user identifier for that same model type and auth identifier
- **WHEN** the actor requests its effective permissions
- **THEN** the endpoint returns only that actor's current effective permissions with HTTP 200

#### Scenario: Guessed target identifier is denied
- **GIVEN** an ordinary authenticated actor without cross-user metadata authority
- **WHEN** the actor requests another user's permission endpoint by identifier
- **THEN** the endpoint returns HTTP 403 without disclosing target metadata or whether the target exists

### Requirement: Catalog authority is independent
The package SHALL authorize role-catalog and permission-catalog reads independently and SHALL NOT infer catalog authority from authentication or self-inspection authority.

#### Scenario: Explicit permission catalog reader succeeds
- **GIVEN** an actor with permission-catalog read authority but no role-catalog authority
- **WHEN** the actor requests the permission catalog
- **THEN** the permission catalog returns HTTP 200

#### Scenario: Role catalog remains denied
- **GIVEN** the same actor without role-catalog authority
- **WHEN** the actor requests the role catalog
- **THEN** the endpoint returns HTTP 403

### Requirement: Scoped metadata is exact
The scoped user endpoint SHALL return only effective permissions whose scope exactly equals the requested route scope, including empty string and `"0"`.

#### Scenario: Scope zero is filtered
- **GIVEN** a user with same-slug permissions in scopes `"0"` and `"1"`
- **WHEN** an authorized actor requests scope `"0"`
- **THEN** only the scope `"0"` permission is returned

#### Scenario: Cross-scope data is excluded
- **GIVEN** a user with scoped and unscoped permissions
- **WHEN** an authorized actor requests one explicit scope
- **THEN** no unscoped or differently scoped permission is returned
