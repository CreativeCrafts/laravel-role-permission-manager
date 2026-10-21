## Why

The pinned package's interfaces, implementations, Eloquent relationships, published migration, frontend helpers, and declared framework matrix disagree in executable ways. Consumers can satisfy a PHP interface yet fail at runtime, inverse relations target incorrect pivots, rollback ordering is invalid, frontend state becomes stale, and Laravel 10 support is claimed without CI evidence (K, JP, JQ, JR, JT, JU, JW, KA, KB, KC, KD, KE, KG, KU, KV, KY).

## Verified Existing Behavior

- The authenticatable interface accepts only `string` for `hasPermissionTo`, while the trait advertises `Permission|string`; manager accepts only `string`. Three role getter bodies handle null-to-self but their signatures require an argument.
- Role/Permission inverse user relationships pass table names to `using()`, which expects a Pivot model class. Permission-to-role relies on a conventional table that differs from package config/migration.
- Test setup creates simplified tables that omit published constraints, hierarchy, descriptions, timestamps, and some pivots; it does not faithfully exercise the published schema.
- Published rollback drops permissions before the direct user-permission pivot. Nullable composite unique indexes do not enforce one logical null-scope permission on MySQL.
- Normal permission reads log complete role permission payloads.
- The React hook hardcodes `super-admin`; standalone TypeScript memoizes the first global permission snapshot and treats `"0"` as absent.
- Published TypeScript assets are copies and are not replaced by Composer update.
- `getUserRoles()` is not one of the four routes; registering it without an access policy would create new exposure.
- Composer declares Laravel 10/11/12, while CI covers only 11/12.

## What Changes

- Align public interfaces, trait, manager, facade-facing methods, models, middleware, policies, and command inputs with explicit Permission/string/scope contracts and null defaults.
- **BREAKING** Add explicit role name/slug query methods and structured permission references where ambiguity is unsafe; preserve safe compatibility wrappers with deprecation guidance.
- Correct all inverse relationships using configured pivot tables and foreign keys; test direct/inverse traversal, eager loading, attach/detach, and custom table names.
- Repair new-install migration rollback order and add a separate upgrade/preflight path for existing installations.
- Add portable duplicate/collision diagnostics before any uniqueness enforcement; never infer or delete historical duplicates.
- Test the actual published migration on SQLite and an isolated MySQL service when available.
- Remove permission payload logging from reads.
- Replace hardcoded/stale frontend authority behavior with current authorized props and exact scope handling; document that frontend checks are non-authoritative.
- Keep `getUserRoles()` unregistered and characterize it separately.
- Add type checks and package scripts for TypeScript assets without forcing React/Inertia as runtime Composer dependencies.
- Add Laravel 10 to CI with compatible Testbench/Pest/Larastan dependency combinations and verify PHP support.
- Publish a major-version upgrade, compatibility, JTC adoption, schema preflight, cache transition, and rollback guide.

## New Guarantees

- Public PHP declarations accept exactly the values implementations support and reject invalid values predictably.
- Configured pivot/table names work in both relationship directions.
- Clean install and rollback exercise the package's published migration rather than a substitute fixture.
- Existing duplicate/collision data is diagnosed read-only before schema enforcement.
- Frontend permission state follows the current principal and exact scope, without a hardcoded privileged role.
- Framework support claims correspond to executable matrix jobs or are explicitly marked unverified/blocked.

## Capabilities

### New Capabilities

- `public-authorization-api-contracts`: Interface/trait/manager/facade signatures, null/empty/invalid inputs, compatibility wrappers, and deprecations.
- `eloquent-relationship-contracts`: Configured direct/inverse role, permission, user, hierarchy, and pivot behavior.
- `schema-and-migration-compatibility`: Published clean install/rollback, custom names, duplicate preflight, nullable uniqueness, and additive upgrade safety.
- `frontend-permission-contracts`: Principal-fresh Inertia/global props, exact scope, configurable privilege representation, publishing, and non-authoritative UI semantics.
- `framework-support-assurance`: Composer constraints, PHP/Laravel dependency matrix, static analysis, frontend checks, and evidence reporting.

### Modified Capabilities

None; the pinned repository has no canonical OpenSpec capabilities.

## Compatibility Consequences

- This work belongs in a major release, not a `1.0.3` patch: Gate/scope/sync behavior and some public signatures intentionally change.
- Relationship fixes are behavior-preserving for intended pivots but may expose applications that depended on incorrect conventional tables.
- Existing databases do not receive destructive cleanup. Upgrade constraints remain blocked until duplicate preflight is clean.
- Consumers must manually republish or merge TypeScript assets after Composer upgrade.
- Laravel 10 remains supported only if its matrix passes; Laravel 13 is not claimed.
- JTC must review unscoped middleware calls, Gate migration, canonical role config, and its committed helper before adoption.

## Impact

Affected code includes both public contracts, manager, trait, facade-visible API, Role/Permission models, commands, policies/middleware callers, package migrations/config, tests/fixtures, CI, frontend resources, README/changelog/upgrade reports, and JTC read-only compatibility analysis.

## Dependencies

- Authorization meaning comes from `harden-creativecrafts-authorization-security`.
- Cache configuration/public transition comes from `correct-creativecrafts-cache-consistency`.
- Mutation reference/result contracts come from `safeguard-creativecrafts-permission-mutations`.

## Explicit Exclusions

- No automatic registration of `getUserRoles()` as a fifth endpoint.
- No JTC edit, dependency update, seeder change, deployment, or migration execution.
- No production database access or duplicate cleanup.
- No automatic overwrite of consumer-published assets.
- No Laravel 13 compatibility claim and no dependency upgrade solely to hide a failing matrix.

## Risks and Rollback

Schema behavior differs between SQLite and MySQL, so SQLite-only success is insufficient. Upgrade migrations must stop before mutation when duplicates/collisions exist and provide checked read-only diagnostics. A frontend rollback requires restoring the consumer copy separately from Composer. A package rollback must restore compatible config, disable/flush both cache namespaces, and account for any additive schema objects; no rollback may silently reactivate stale privileged values. Release recommendation and unresolved environment evidence remain explicit in the final report.
