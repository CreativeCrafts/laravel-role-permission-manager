## 1. Gate and identity foundations

- [x] 1.1 Add prefixed nullable Gate composition and preserve disabled integration
- [x] 1.2 Add real Gate/policy integration tests for owner, non-owner, grant, denial, unknown ability, and same-named package permission
- [x] 1.3 Implement exact canonical privileged-role resolution across Role, manager, trait, and permission overrides
- [x] 1.4 Add explicit role-name/role-slug APIs and switch role middleware to exact slug resolution
- [x] 1.5 Test canonical, ordinary, shadow-name, shadow-slug, case, custom designation, Role/string, custom User, middleware, and revocation cases

## 2. Metadata and management authorization

- [x] 2.1 Add the replaceable metadata authorizer with self allow, independent catalog authority, and cross-user deny defaults
- [x] 2.2 Authorize all four registered metadata routes without registering `getUserRoles`
- [x] 2.3 Add real HTTP tests for guests, self, another user, authorized inspector, catalogs, missing targets, payloads, and exact scopes
- [x] 2.4 Remove role/permission possession fallback from create, update, and delete policy actions
- [x] 2.5 Replace historical permissive policy tests with positive administrator and negative holder tests

## 3. Permission boundary semantics

- [x] 3.1 Implement exact null/empty/`"0"` scope behavior for authorization and separate enumeration behavior
- [x] 3.2 Implement anchored `*` wildcard matching with literal `%`, `_`, and periods plus consistent case mode
- [x] 3.3 Remove SQL `LIKE` permission equality and any implicit cross-scope fallback
- [x] 3.4 Remove permission creation side effects from every authorization evaluation path
- [x] 3.5 Add direct, inherited, wildcard, case, special-character, scope, and no-write adversarial tests

## 4. Compatibility evidence

- [x] 4.1 Document Gate prefix, metadata authorizer, role identity, scope, and auto-create migration
- [x] 4.2 Map and test JTC TeamPolicy, `role:super-admin`, scoped/unscoped middleware, and metadata implications without modifying JTC
- [x] 4.3 Run strict OpenSpec validation and map H, J, V, JN, JO, F, JS, JU, JW, JX, JY, JZ, and KX to code/tests
