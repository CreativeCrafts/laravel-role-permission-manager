## ADDED Requirements

### Requirement: Declared framework support has executable evidence
Every Laravel and PHP combination claimed by Composer SHALL have a compatible CI dependency set and passing package tests, or the support claim SHALL remain explicitly blocked/unverified.

#### Scenario: Laravel 10 matrix executes
- **GIVEN** Composer declares Illuminate 10 support
- **WHEN** CI runs the compatible Testbench 8/Pest 2/Larastan 2 job
- **THEN** install and tests pass before release readiness

#### Scenario: Missing environment is not a pass
- **GIVEN** a claimed combination cannot be executed
- **WHEN** the test report is produced
- **THEN** it records the exact blocker and does not mark the combination verified

### Requirement: Quality gates cover package and frontend contracts
CI SHALL run Composer validation, Pest integration tests, PHPStan/Larastan, PHP syntax, non-mutating Pint style check, strict OpenSpec validation, and TypeScript checks where applicable.

#### Scenario: All quality gates pass
- **GIVEN** the completed change on a supported matrix job
- **WHEN** quality commands execute
- **THEN** each exits zero and exact totals/versions are recorded

#### Scenario: Failure is not suppressed
- **GIVEN** any quality command exits nonzero
- **WHEN** completion status is evaluated
- **THEN** the corresponding task remains incomplete and the failure is reported

### Requirement: Database evidence distinguishes engines
SQLite migration/behavior tests SHALL NOT be represented as MySQL uniqueness or concurrency evidence. Claimed MySQL guarantees SHALL require isolated MySQL execution.

#### Scenario: Both engines pass
- **GIVEN** isolated SQLite and MySQL environments
- **WHEN** migration, uniqueness, and concurrency tests run
- **THEN** results are reported separately by engine

#### Scenario: MySQL unavailable
- **GIVEN** no isolated MySQL service is available
- **WHEN** verification completes
- **THEN** MySQL-specific tasks remain blocked and release readiness is false

### Requirement: Laravel 13 is not implicitly claimed
Dependency and CI changes SHALL NOT add Laravel 13 support without a separate validated compatibility change.

#### Scenario: Existing majors remain explicit
- **GIVEN** this remediation
- **WHEN** Composer and CI are inspected
- **THEN** only Laravel 10, 11, and 12 are claimed

#### Scenario: Accidental broad constraint is rejected
- **GIVEN** a dependency edit would admit Laravel 13
- **WHEN** compatibility review runs
- **THEN** validation fails until separate evidence exists
