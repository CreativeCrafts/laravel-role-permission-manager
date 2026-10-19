# Security and Correctness Remediation Evidence Register

## Baseline

- Authoritative package repository: `https://github.com/CreativeCrafts/laravel-role-permission-manager`
- Pinned starting commit: `7718f71c55eb23874e5e82951b70903897986860`
- Working branch: `remediation/comprehensive-security-7718f71`
- Original workspace preserved at `main` with its untracked `.claude/`, `.codex/`, `.junie/`, and `openspec/` paths unchanged.
- Package `main` at investigation start: `f8cf4cdf21b0b561fe831111dc96b0ba21b9c4e8`
- Divergence: pinned commit is one commit behind `main`; the only changed tracked file is `CHANGELOG.md`.
- Local toolchain: PHP 8.3.33, Composer 2.10.2, OpenSpec 1.3.1.
- Composer lock policy: `composer.lock` is ignored and is not present at the pinned revision. The resolved dependency baseline is recorded in the test report after installation.
- Declared compatibility: PHP `^8.2|^8.3`; Illuminate 10, 11, and 12.
- Existing commands: `composer test`, `composer analyse`, `composer format`, and direct Pest/PHPStan/Pint commands.
- Existing CI: PHP 8.2/8.3 against Laravel 11/12 only; Laravel 10 is declared but omitted.

This register records source behavior before remediation. Line references identify the pinned source and may move during implementation. OpenSpec, code, and test mappings are filled as executable evidence is created.

## Historical findings

| ID | Classification | Pinned evidence and existing execution | Impact / prerequisites | Planned disposition |
|---|---|---|---|---|
| D | Source-confirmed correctness/security defect | `LaravelRolePermissionManager::{getAllPermissionsForUser,clearPermissionCache,clearRoleCache}` and mutation methods; `HasRolesAndPermissions::{assignRole,removeRole,givePermissionTo,revokePermissionTo}`. Effective-permission entries are not invalidated by most supported mutations. | Previously cached grants can survive direct, role, or inherited revocation. | Centralize cache access and commit-aware invalidation; add revocation tests. |
| E | Source-confirmed correctness/security defect | `LaravelRolePermissionManager::getAllPermissionsForUser()` and `HandleInertiaRequests::getUserPermissions()` both use `user_permissions_{numeric-id}` but store different value types. User class and guard are absent from all per-user keys. | Cross-model IDs collide; Inertia arrays can be read where Eloquent collections are expected. | Versioned, typed, model/guard-aware keys and a single cache service. |
| F | Source-confirmed correctness/security defect | `LaravelRolePermissionManager::hasPermissionTo()` and `Permission::wildcardMatch()` treat omitted scope as matching scoped grants. | A same-slug permission in scope A can authorize an unscoped check. | Unscoped-only authorization when scope is omitted; exact explicit scopes. |
| G | Source-confirmed correctness defect | `SyncPermissionsCommand::getPermissions()` returns `Permission[]`; `LaravelRolePermissionManager::syncPermissions()` queries those objects as slug strings. | Valid CLI input can resolve to an empty target set and detach assignments. | Resolve all references once and pass typed models/IDs through an atomic sync. |
| H | Source-confirmed security defect | `LaravelRolePermissionManagerServiceProvider::registerGateCheck()` registers a global `Gate::before` returning `bool` for every ability. | A package grant bypasses resource policy checks and package denial blocks legitimate policy grants. | Abstain for unrelated abilities and own only a prefixed package Gate namespace. |
| J | Source-confirmed security defect | Four default GET routes in `registerRoutes()` call controller actions without target-user or catalog authorization beyond `auth`. | Any authenticated user can enumerate catalogs and another user's effective permissions when package routes are enabled (default). | HTTP authorization with self-inspection, independent catalog authority, and deny-by-default cross-user authority. |
| K | Source-confirmed compatibility defect | `Role::users()` passes a pivot table name to `BelongsToMany::using()`, which expects a custom Pivot model class. | Inverse role-to-user traversal fails at runtime. | Configure the pivot table in `belongsToMany()` and remove invalid `using()`. |
| L | Source-confirmed security/correctness defect | `syncPermissions()` silently ignores unresolved slugs then calls destructive `sync()`; CLI also continues after individual resolution failures. | Typos or ambiguity can revoke valid assignments. | Fail complete resolution before mutation; explicit clear operation only. |
| R | Source-confirmed correctness/security defect | `setRoleParent()` has no self/ancestor check; recursive Role traversal has no visited set. | Cycles can cause unbounded recursion and authorization failure. | Reject cycles and use iterative, visited traversal. |
| V | Source-confirmed security/correctness defect | Same global `Gate::before` path as H returns `false` on a missing package permission. | Laravel model-policy grants never execute for unrelated abilities. | Same Gate abstention contract as H. |
| JN | Source-confirmed security defect | `RolePolicy::{create,update,delete}` grants fallback authority based on possessing the role being managed. | Role membership is confused with definition-management authority; default package exposes no mutation HTTP route. | Require explicit action-specific management permissions. |
| JO | Source-confirmed security defect | `PermissionPolicy::{create,update,delete}` grants fallback authority based on possessing the target permission. | Permission possession is confused with definition-management authority; default package exposes no mutation HTTP route. | Require explicit action-specific management permissions. |
| JP | Source-confirmed compatibility defect | `Role::users()` uses `using(<table-name>)`. | Same runtime failure as K. | Same relationship correction as K. |
| JQ | Source-confirmed compatibility defect | `Permission::users()` uses `using(<table-name>)`. | Inverse permission-to-user traversal fails at runtime. | Explicit configured pivot table and keys. |
| JR | Source-confirmed compatibility defect | `Permission::roles()` relies on Laravel's conventional `permission_role`, while the package migration/config use `role_has_permissions`. | Inverse permission-to-role traversal reads the wrong table. | Explicit configured role-permission pivot table. |
| JS | Source-confirmed correctness/security defect | Trait `getAllPermissions($scope)` passes an extra argument to manager `getAllPermissionsForUser($user)`, whose implementation ignores scope. | Scoped user enumeration returns permissions from other scopes. | Add an exact-scope manager contract and HTTP regression coverage. |
| JT | Source-confirmed public-contract defect | Trait accepts `Permission|string`; manager contract/implementation accepts only `string`. | Passing a Permission model through the advertised API raises a runtime type error. | Align interface, trait, manager, models, and callers with explicit unions. |
| JU | Conditional exploitable security risk | `LaravelRolePermissionManager::hasPermissionTo()` creates a permission whenever `auto_create_permissions=true`. | A read-only authorization check mutates the permission catalog under a compatibility option. | Make checks observational; retain creation only in explicit mutation APIs. |
| JV | Source-confirmed correctness defect | Provider binds `role-permission.cache`, but production code uses the global Cache facade. The binding also wraps a cache Repository as though it were a Store. | `use_cache=false` and configured store selection are not honored consistently. | Inject/use one package cache service and bypass it completely when disabled. |
| JW | Source-confirmed security/correctness defect | `Role::hasPermissionTo()` uses SQL `LIKE` for case-insensitive matching. | `%` and `_` are wildcards rather than literal permission characters; database collation changes behavior. | Exact application-level comparison and explicit `*` wildcard grammar. |
| JX | Source-confirmed security/correctness defect | Several string lookups select first slug match without exact scope or ambiguity detection. | Same slug in multiple scopes can attach, remove, or authorize the wrong identity. | Exact scoped references; reject ambiguous slug-only mutation input. |
| JY | Compatibility/security problem | Creation lowercases names/slugs, wildcard checks use regex flags, Role uses database `LIKE`, and role methods use separate case rules. | Results vary by entry point and database collation. | One configured comparison strategy across manager, model, trait, middleware, and CLI. |
| JZ | Source-confirmed correctness/security defect | `getAllPermissionsForScope()` treats `null`, `''`, and `'0'` as “all”; TypeScript uses truthiness for scope checks. | Explicit scope `"0"` or empty scope crosses scope boundaries. | Preserve null, empty, and `"0"` as distinct values in explicit APIs. |
| KA | Source-confirmed migration defect | Migration `down()` drops permissions before `model_has_permissions`. | Rollback violates the remaining foreign key dependency. | Correct new-install rollback order and test actual published migration. |
| KB | Runtime/database-dependent compatibility defect | Published migration uses nullable `(name, scope)` and `(slug, scope)` unique indexes. | MySQL permits duplicate logical null-scope rows; SQLite behavior differs. | Add read-only duplicate preflight and a portable identity/upgrade strategy before any constraint. |
| KC | Source-confirmed information-disclosure defect | `getAllPermissionsForUser()` logs each role's full permission records during normal reads. | Permission metadata is copied into application logs. | Remove payload logging; retain only opt-in aggregate diagnostics if needed. |
| KD | Source-confirmed frontend defect | `usePermissions.ts` hardcodes role slug `super-admin`. | Frontend behavior disagrees with configurable backend privileged identity. | Derive effective permissions from authorized server props; no frontend role bypass. |
| KE | Source-confirmed frontend consistency defect | `permissions.ts` memoizes the first `window.Laravel.permissions` value forever. | Login/logout, principal switch, navigation, and revocation can display stale authority. | Read current principal props/snapshot on each check or explicit state input. |
| KF | Source-confirmed correctness defect | Documentation calls `cache_expiration_time` minutes, while integer TTLs passed to Laravel cache are seconds; other paths pass a DateTime at 60 minutes. | Catalog/role and effective-permission entries have inconsistent lifetimes. | Convert documented minutes to an absolute expiration consistently. |
| KG | Design decision / coverage gap | `RolePermissionController::getUserRoles()` is public but absent from the four registered package routes. | There is no HTTP exposure at the pinned revision; adding one would increase attack surface. | Keep it unregistered unless an explicit authorized contract is approved; characterize separately. |
| KO | Source-confirmed correctness/performance defect | `getSubRoles()` recursively traverses each member returned by already-recursive `getAllChildren()`. | Descendants are repeated and propagation work grows unnecessarily. | Single visited traversal with unique descendants. |
| KP | Source-confirmed correctness defect | `hasAllRoles()` compares matching-row count to raw input count and ignores configured case handling. | Duplicate requested roles cause false negatives and behavior differs from `hasAnyRole()`. | Normalize/deduplicate inputs and use exact slug semantics. |
| KQ | Source-confirmed security/correctness defect | Subtree propagation methods accept strings but do not carry scope into `givePermissionToRole()`/revoke resolution. | A same-slug permission from another scope may be propagated. | Resolve a concrete Permission identity before traversing. |
| KU | Test/coverage gap | Composer declares Laravel 10/11/12; workflow tests only 11/12. | Laravel 10 compatibility is an unverified release claim. | Add Laravel 10 compatible matrix coverage and execute locally where possible. |
| KV | Compatibility/design constraint | TypeScript assets are vendor-published copies; Composer updates do not overwrite consumer files. | Security fixes are not automatically adopted by existing applications. | Version/document frontend contract and explicit republish/manual diff procedure. |
| KW | Source-confirmed authorization-consistency defect | `hasRole(Role)` uses the possibly loaded `$this->roles` collection; pivot mutation helpers do not consistently unset loaded relations. | One in-memory model can retain revoked role authority. | Query authoritative pivot state and clear affected loaded relations after mutations. |
| KX | Conditional source-confirmed authorization-escalation risk | `hasRole(string)` OR-matches role name and slug; role middleware uses it. Privileged identity is name-based elsewhere. | A distinct role whose name equals a protected slug can satisfy `role:<slug>` if assigned. This is not evidence of production exploitation. | Middleware and privileged checks use explicit identifier types; keep ambiguous compatibility only outside security boundaries. |
| KY | Source-confirmed public-contract defect | Trait `getUserRoles`, `getUserRoleNames`, and `getUserRoleSlugs` implement null-to-self but require an argument. | Documented no-argument usage fails; JTC explicitly passes null as a workaround. | Add `mixed $user = null` defaults and interface coverage. |

## Preserved historical identities

- CN and DL remain rejected historical findings and are not renumbered.
- FF remains reserved; its historical description is unavailable and is not reused.
- The next identifier for a genuinely distinct package defect is KZ.

## Candidate directly related defects requiring reproduction

| Candidate | Evidence to verify | Status |
|---|---|---|
| KZ | `HandleInertiaRequests::handle()` calls `Inertia::share()` after the downstream response has already been generated, so current-request props may not be included. | Pending real Inertia response characterization. |
| KAA | Empty role arrays can produce an invalid raw `IN ()` expression in `hasAnyRole()` on some database engines. | Pending SQLite/MySQL characterization. |
| KAB | `AssignRoleCommand` performs ambiguous name-or-slug resolution and mutates the pivot directly, bypassing supported cache/relation invalidation. | Source-confirmed execution; determine whether to map into D/KX or retain as distinct. |

## Evidence completion gates

- No finding is marked resolved until its positive and adversarial negative test executes.
- Runtime-dependent MySQL and rolling-deployment claims remain blocked unless the relevant environment is actually exercised.
- OpenSpec mappings, modified-code mappings, compatibility classification, and test evidence will be appended after strict specification validation and implementation.

## Final implementation and executable evidence mapping

| Finding IDs | Implementation | Positive and adversarial evidence | Disposition |
|---|---|---|---|
| D, E, JV, KF, KW | `src/Cache/AuthorizationCache.php`, manager and trait mutation paths, Inertia sharing | `CacheConsistencyTest`, cache cases in `LaravelRolePermissionManagerTest`, MySQL suite | Resolved for supported package mutations; cross-node claims require a shared store |
| F, JW, JY, JZ | `Permission::wildcardMatch`, manager authorization, exact frontend checks | `SecurityIntegrationTest` scope/wildcard cases, model tests, three frontend behavior tests | Resolved |
| G, L, JX, KQ | `PermissionReference`, atomic manager sync, assign/remove/sync commands | `MutationSafetyTest`, `PermissionMutationCommandsTest`, negative partial/ambiguous/empty cases | Resolved |
| H, V | Prefixed nullable `Gate::before` in the service provider | `SecurityIntegrationTest` owner/non-owner/same-name/prefixed checks, provider tests | Resolved |
| J, KG | `MetadataAccessAuthorizer`, controller target checks, four-route registration | `MetadataRoutesSecurityTest` guest/self/cross-user/catalog/custom-authorizer/missing-route cases | Resolved; user-role action remains unregistered |
| JN, JO | Action-specific role and permission policy permissions | Positive administrator and negative holder cases in policy tests | Resolved |
| K, JP, JQ, JR | Explicit configured pivot tables and keys in both relationship directions | `SchemaAndRelationshipsTest` default and custom table traversal | Resolved |
| JS, JT, KY | Aligned interfaces, manager, trait, policy, middleware, and command signatures | `PublicContractsTest`, reflection/runtime model input and optional getter tests | Resolved |
| JU, KC | Read-only authorization path; removed permission-record logging | `SecurityIntegrationTest` no-write case plus source/static review for removed logging | Resolved |
| R, KO, KP | Transactional cycle rejection; iterative visited traversal; normalized role sets | `MutationSafetyTest` self/indirect/legacy cycles, unique traversal, duplicate and empty sets | Resolved |
| KA, KB | Foreign-key-safe down order, read-only preflight, additive scope identity keys | `SchemaAndRelationshipsTest` install/down/reinstall, duplicate preflight, null/empty/zero uniqueness; SQLite and MySQL suites | Resolved for SQLite/MySQL schema behavior; multi-process races remain a separate environment task |
| KD, KE, KV | Current-state Vue/React helpers with no hardcoded privileged role | TypeScript typecheck and frontend login/logout/switch/scope tests; `UPGRADE.md` republish procedure | Resolved; consumers must manually adopt published assets |
| KU | Laravel 10 added to the PHP 8.2/8.3 CI matrix | Six local framework/runtime lanes listed below | Resolved |
| KX | Exact role-name and role-slug APIs; slug-only role middleware | `SecurityIntegrationTest` canonical, shadow-name, shadow-slug, case, revocation cases; JTC report | Resolved |

## Candidate outcomes

| Candidate | Outcome |
|---|---|
| KZ | Source-confirmed: Inertia data was shared after the downstream response. Sharing now occurs before `$next`; a real rendered Inertia response test remains an explicit completion item. |
| KAA | Folded into KP rather than assigned a new identity. Empty `hasAnyRole([])` is `false`, empty `hasAllRoles([])` is `true`, and no raw `IN ()` expression is built. |
| KAB | Folded into D/KX rather than assigned a new identity. `AssignRoleCommand` resolves an exact slug and delegates to `assignRole()`, which clears relation/cache state. |

## Local verification record

All commands exited zero unless a failure is explicitly described.

| Environment / gate | Result |
|---|---|
| Pinned baseline suite | 27 failures before remediation |
| PHP 8.2.33, Laravel 10.50.3, Testbench 8.39.0, Pest 2.36.1, SQLite | 205 passed, 519 assertions |
| PHP 8.3.33, Laravel 10.50.3, Testbench 8.39.0, Pest 2.36.1, SQLite | 205 passed, 519 assertions |
| PHP 8.2.33, Laravel 11.57.0, Testbench 9.18.0, Pest 3.8.7, SQLite | 208 passed, 563 assertions |
| PHP 8.3.33, Laravel 11.57.0, Testbench 9.18.0, Pest 3.8.7, SQLite | 208 passed, 563 assertions |
| PHP 8.2.33, Laravel 12.70.0, Testbench 10.12.0, Pest 3.8.7, SQLite | 208 passed, 563 assertions |
| PHP 8.3.33, Laravel 12.70.0, Testbench 10.12.0, Pest 3.8.7, SQLite | 208 passed, 563 assertions |
| MySQL 8.0, PHP 8.3.33, Laravel 12.70.0 | 208 passed, 563 assertions in 112.96 seconds |
| Composer metadata | `composer validate --strict --no-check-publish`: valid |
| PHP syntax | 77 PHP files passed `php -l` |
| Larastan/PHPStan | Level 5 configured paths: no errors |
| Pint | `vendor/bin/pint --test --verbose`: passed |
| Frontend | TypeScript typecheck passed; 3 behavior tests passed |
| OpenSpec 1.3.1 | Strict validation: 4 passed, 0 failed |
| Diff hygiene | `git diff --check`: passed |
| JTC compatibility review | Read-only report completed at `aa944491`; checkout confirmed clean |

The MySQL container was isolated on local port 33306 with an ephemeral test database and was removed after verification.
No package release and no JTC modification were performed.

## Remaining environment-bound evidence

- No multi-worker shared-cache service was available, so cross-process generation propagation is documented but not
  claimed as locally executed.
- The suite verifies transaction rollback and database uniqueness, but it does not yet execute two independent
  processes racing the same mutation.
- A real rendered Inertia response characterization is still required for KZ.
- Rolling old/new application nodes were not deployed; mixed-version operation remains prohibited by the upgrade guide.

## Adversarial review follow-up

- `Role::givePermissionTo()` no longer selects the first name-or-slug match. A string is a unique slug unless an exact scope is supplied, and ambiguous input fails before any pivot write. Regression coverage is in `RoleTest`.
- `AuthorizationCache` counts transaction-scoped invalidations and releases that count on rollback, so a failed transaction cannot leave a long-lived worker bypassing cache. The rollback regression asserts the pending count returns to zero.
- `role-permission:assign-role` resolves a numeric identifier by primary key and any other identifier by email, avoiding an `id OR email` match.
- `role-permission:create-permission` calls the concrete manager service, and the facade declares a mixin for the manager contract.
- OpenSpec tasks are reconciled. Four environment-bound tasks remain open: cache concurrency and shared-store cross-process behavior, two-process mutation races, and the concurrent portion of the MySQL schema task. Those changes are intentionally not archived.
