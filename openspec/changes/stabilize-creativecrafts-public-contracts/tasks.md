## 1. PHP contracts and relationships

- [x] 1.1 Align authenticatable interface, trait, manager contract/implementation, facade callers, models, policies, middleware, and commands
- [x] 1.2 Add optional null-to-self role getter defaults, explicit role identity methods, and empty/duplicate input semantics
- [x] 1.3 Correct Role/Permission inverse relationships with configured pivot tables and keys
- [x] 1.4 Add reflection/runtime signature tests and direct/inverse/eager/custom-table relationship tests

## 2. Published schema and upgrade safety

- [x] 2.1 Replace simplified global test schema with representative users table plus actual published migration
- [x] 2.2 Correct new-install rollback order and verify clean install/down/reinstall
- [x] 2.3 Add read-only duplicate, cross-field collision, pivot duplicate, and hierarchy-cycle preflight
- [x] 2.4 Design/implement additive exact scope-identity uniqueness only if clean historical-data and engine evidence supports it
- [x] 2.5 Test custom table names, nullable null/empty/`"0"` identities, representative upgrade data, and safe rollback on SQLite
- [ ] 2.6 Execute isolated MySQL install, rollback, nullable uniqueness, preflight, and concurrent duplicate tests
  The isolated MySQL suite executed install, rollback, nullable uniqueness, and preflight. Concurrent duplicate processes were not executed.

## 3. Frontend contracts

- [x] 3.1 Make Inertia sharing current-request and non-destructive to host auth props
- [x] 3.2 Remove standalone helper principal memoization and React hardcoded privileged slug
- [x] 3.3 Implement exact frontend null/empty/`"0"` scope behavior from current authorized props
- [x] 3.4 Add TypeScript config/tests for login, logout, switch, navigation, revocation, empty sets, and scopes
- [x] 3.5 Document manual republish/merge and frontend non-authoritative behavior

## 4. Framework and release assurance

- [x] 4.1 Add Laravel 10 compatible CI matrix alongside Laravel 11/12 without Laravel 13
- [x] 4.2 Add Composer validation, Pest, PHPStan/Larastan, PHP syntax, Pint check, frontend type check, migration, and strict OpenSpec jobs
- [x] 4.3 Execute locally available PHP/Laravel matrix combinations and record exact versions/totals/exits
- [x] 4.4 Complete public API compatibility, breaking-change, SemVer, upgrade, changelog, release, and rollback documentation
- [x] 4.5 Complete read-only JTC call-site/adoption report and confirm its checkout remains clean
- [x] 4.6 Map K, JP, JQ, JR, JT, JU, JW, KA, KB, KC, KD, KE, KG, KU, KV, and KY to code/tests
- [x] 4.7 Run strict OpenSpec validation and leave unavailable environment tasks unchecked
