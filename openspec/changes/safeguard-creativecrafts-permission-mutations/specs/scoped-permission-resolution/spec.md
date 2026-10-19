## ADDED Requirements

### Requirement: Slug-only mutation input must be unambiguous
A slug string SHALL resolve only when exactly one permission row has that slug. Multiple scoped rows SHALL cause an ambiguity error rather than first-row selection.

#### Scenario: Unique slug resolves
- **GIVEN** exactly one permission has slug `posts.edit`
- **WHEN** a mutation resolves that slug
- **THEN** it resolves the existing Permission identity

#### Scenario: Duplicate scoped slug is rejected
- **GIVEN** `posts.edit` exists in null and `"0"` scopes
- **WHEN** slug-only resolution runs
- **THEN** it fails without mutation and requires scope or ID

### Requirement: Structured scope preserves all scope values
Structured references SHALL distinguish null, empty string, `"0"`, and other strings and SHALL resolve exact equality only.

#### Scenario: Scope zero resolves exact row
- **GIVEN** same-slug permissions in empty and `"0"` scopes
- **WHEN** a structured reference requests `"0"`
- **THEN** only the `"0"` row resolves

#### Scenario: Missing exact scope fails
- **GIVEN** a slug exists only in scope A
- **WHEN** a structured reference requests scope B
- **THEN** resolution fails and no fallback occurs

### Requirement: CLI scope and ID modes are explicit
Commands SHALL treat numeric strings as slugs unless `--use-ids` is supplied, SHALL apply an explicit `--scope` exactly, and SHALL reject unsupported mixed per-item scope intent.

#### Scenario: ID mode resolves IDs
- **GIVEN** `--use-ids` and valid numeric identifiers
- **WHEN** the command runs
- **THEN** role and permission rows resolve by ID

#### Scenario: Numeric slug is not coerced
- **GIVEN** slug mode and a permission slug `"123"`
- **WHEN** the command runs
- **THEN** it resolves slug `"123"` rather than permission ID 123
