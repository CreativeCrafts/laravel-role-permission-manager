# JTC read-only adoption report

## Checkout evidence

- Repository: `/Users/prince/Projects/jtc-new-readonly-aa944491-9f8c2b`
- Commit: `aa94449121adeb278ff9a03c00696b1d55877479`
- Historical comparison: `b52e5f5cce0962a3942348a2f8fcaa329320e52a`
- Branch reference: `origin/471-task-role-and-permission-optimisation`
- Review mode: read-only
- Final status: detached checkout clean; no JTC files changed
- Package baseline used by JTC: 1.0.3 at `7718f71c55eb23874e5e82951b70903897986860`

The package changes in this remediation branch are not yet released. JTC must not adopt the new contracts until a
major package release exists and the application migrations below are complete.

## Call-site and adoption mapping

| JTC call site | Current behavior at package 1.0.3 | Hardened package behavior | JTC action and regression |
|---|---|---|---|
| `config/role-permission-manager.php` | Gate hook enabled, package routes enabled, cache disabled | Prefixed Gate, authorized metadata, authoritative cache disable | Keep cache disabled if desired; disable unused package routes; test package config in production mode |
| Package `Gate::before` | Returns a bool for every ability and can swallow policies | Handles only `role-permission:*`; otherwise returns `null` | Until upgrade, set `register_permission_check_method=false`; test `TeamPolicy`, `UserPolicy`, and Telescope Gate grants/denials |
| `app/Policies/TeamPolicy.php` and Jetstream checks | Compete with the package global Gate hook | Host policy remains authoritative | Test owner/non-owner decisions if teams are enabled; teams are currently disabled |
| `UserPolicy::create` | The `id === 1` policy may never execute | Unrelated Gate abilities abstain | Test user 1 and non-user-1 policy outcomes after upgrade |
| `TelescopeServiceProvider` | `viewTelescope` can be short-circuited | Host Gate runs normally | Test allowlisted and non-allowlisted users |
| `RegisterUserRequest::authorize()` | Returns true independently of package permission state | Unchanged host responsibility | Require a host permission such as `create-user`; test 403 without it |
| `routes/web.php` `role:super-admin` | Name-or-slug compatibility matching | Exact slug middleware matching | Existing `super-admin` slug is compatible; test a shadow-name role is denied |
| `modules/JobMatchAi/routes/web.php` | Backend requires super-admin while UI checks `access-ai-module` | Exact role/permission semantics expose mismatch | Choose one server contract, preferably explicit module permission; test URL and menu consistently |
| 39 permission middleware attachments | Omitted scope can be satisfied by a scoped grant | Omitted scope means null scope only | Add explicit scope support or create unscoped grants before package upgrade; test each module route |
| `GetAssignedUserPermissions` | Returns all effective permission `{slug, scope}` pairs | Same enumeration, exact authorization scopes | Keep scope values intact; test guest, ordinary, and privileged responses |
| `GetAssignedUserRoles` | Calls `getUserRoleSlugs(null)` | Getter argument is optional; role slugs remain exact | Remove the null workaround when convenient; test guest and authenticated users |
| Package `HandleInertiaRequests` plus JTC `ShareInertiaData` | Both can write `auth` props | Package shares lazy current-request permissions without replacing host props | Choose one owner for each prop and test login, logout, navigation, and revocation |
| Published `usePermissions.ts` | Hardcoded `super-admin`; unscoped check crosses scopes | No frontend bypass; exact null/empty/`"0"` scope; current props | Republish/manual-merge the helper and run component tests |
| `Layout.tsx` and `usePagePermissions.ts` | UI mostly uses scoped checks | Exact checks continue to work when seed data matches | Test menu visibility and server access together |
| `SecondaryNavigation.tsx` | Five Factor menu uses `access-talent-module` | Package does not mask wrong application permission | Change to `access-five-factor-module` with the intended scope; test secondary navigation |
| JobMatchFive routes | Authenticated only | Package cannot infer missing host route policy | Add server-side permission middleware and test direct URL access |
| `PermissionRoleSeeder` | Creates scoped permissions but does not attach them to ordinary roles | No implicit assignment or auto-create during checks | Seed missing permissions and explicitly sync role permissions |
| `UserSeeder` | Creates name `Super Admin`, slug `super-admin` | Exact configured name plus slug-only middleware | Existing identity is compatible; test privilege revocation |

## Confirmed adoption blockers

1. `create-user` is used by routes but is not seeded.
2. `access-five-factor-module` is used by the frontend but is not seeded; JobMatchFive routes lack permission
   middleware.
3. `PermissionRoleSeeder` does not assign permissions to ordinary roles.
4. The package Gate hook remains enabled on package 1.0.3 and can short-circuit host policies.
5. All permission middleware calls omit scope while JTC seed data is scoped; tightening scope first would deny
   non-super-admin users.
6. Package 1.0.3 still performs manager-level cache I/O even though JTC config sets `use_cache=false`.
7. Package and host Inertia middleware both populate authorization props.
8. JobMatch AI uses a role on the backend and a permission on the frontend.
9. Five Factor secondary navigation checks a Talent permission.
10. Existing published TypeScript files are not replaced by Composer and must be republished or manually merged.
11. No secure package version is released yet.

## Adoption order

1. On the current package, disable the global package Gate hook and unused package routes.
2. Complete permission seed data, role-permission assignments, and backend guards.
3. Decide and migrate every scoped middleware contract.
4. Release and install the hardened major package version; run package preflight and migrations.
5. Republish or manually merge frontend helpers.
6. Align AI/Five Factor route and navigation checks.
7. Run policy, middleware, Inertia, metadata, revocation, and direct-URL regression tests.

No package release and no JTC mutation were performed as part of this review.

