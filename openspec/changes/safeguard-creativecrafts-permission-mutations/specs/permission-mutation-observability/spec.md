## ADDED Requirements

### Requirement: Commands report completion truthfully
Mutation commands SHALL return success only after the requested operation commits, SHALL return failure for invalid/partial input, ambiguity, cancellation, or transaction error, and SHALL not emit a success line after failure.

#### Scenario: Valid idempotent command succeeds
- **GIVEN** all command identifiers resolve and the target state already matches
- **WHEN** the user confirms
- **THEN** the command returns success and reports no changes

#### Scenario: Partially invalid list fails
- **GIVEN** a command list has valid and invalid identifiers
- **WHEN** the command runs
- **THEN** it returns failure, mutates nothing, and does not print synchronization success

### Requirement: Mutation diagnostics avoid sensitive payloads
Normal logs and command output SHALL identify operation type and non-sensitive permission descriptors only and SHALL NOT log user payloads or full permission metadata collections.

#### Scenario: Successful sync output is bounded
- **GIVEN** synchronization attaches and detaches permissions
- **WHEN** results are displayed
- **THEN** output contains deterministic IDs/slugs/scopes and counts without user data

#### Scenario: Resolution error does not dump records
- **GIVEN** an ambiguous slug matches multiple rows
- **WHEN** the command reports the error
- **THEN** it identifies the ambiguous input without dumping descriptions, users, or full row payloads

### Requirement: Cancellation is non-completion
Declining command confirmation SHALL leave state unchanged and return a non-success exit code.

#### Scenario: Confirmed operation continues
- **GIVEN** valid resolved input
- **WHEN** the operator confirms
- **THEN** the transaction executes

#### Scenario: Declined operation stops
- **GIVEN** valid resolved input
- **WHEN** the operator declines
- **THEN** no transaction mutation occurs and the command returns failure
