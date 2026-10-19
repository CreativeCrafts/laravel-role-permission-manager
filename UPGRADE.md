# Upgrade guide

The authorization hardening described here changes security-sensitive behavior and public return values. It requires a
major-version release. Do not deploy it as a patch release.

## Before upgrading

1. Back up the authorization tables and the configured cache store.
2. Put every application node on the same package version during a maintenance or drained deployment.
3. Run the read-only data check:

   ```bash
   php artisan role-permission:preflight
   php artisan role-permission:preflight --json
   ```

   The command exits with a failure status when it finds duplicate permission identities, role name/slug collisions,
   hierarchy cycles, or duplicate pivots. It does not change rows.
4. Resolve every reported finding and run the preflight again.
5. Review all unscoped permission checks, package metadata routes, Gate calls, and published frontend helpers as
   described below.

## Database migration

Publish the new migrations and run the normal additive migration path:

```bash
php artisan vendor:publish --tag="role-permission-manager-migrations"
php artisan migrate
```

Do not rerun the original create-tables migration and do not reset production authorization tables. The
`add_permission_identity_keys` migration checks historical permission identities before changing the schema, then adds
portable hash keys that distinguish a null scope, an empty scope, `"0"`, and every other string. Exact duplicate
identities fail instead of being selected arbitrarily.

If migration fails, leave the application on the old package version, correct the reported data, and retry. The
migration rollback removes only the additive identity columns and indexes. It does not remove authorization data.

## Breaking authorization contracts

### Gate abilities are prefixed

The package now handles only abilities beginning with `gate_permission_prefix`, which defaults to
`role-permission:`:

```php
Gate::allows('role-permission:posts.edit');
Gate::allows('role-permission:posts.edit', ['tenant-42']);
```

For every other ability the package returns `null`, allowing host gates and model policies to run. Replace old
unprefixed package Gate calls or set `register_permission_check_method` to `false` while migrating them. Never choose an
empty prefix.

### Metadata routes deny by default

The four optional routes are still protected by the configured route middleware and now also enforce resource
authorization:

- `/roles` requires the unscoped `view roles` permission.
- `/permissions` requires the unscoped `view permissions` permission.
- A user may inspect their own effective permissions.
- Cross-user inspection is denied by default.
- `getUserRoles()` is not exposed as a package route.

For host-specific cross-user access, bind an implementation of
`CreativeCrafts\LaravelRolePermissionManager\Contracts\MetadataAccessAuthorizer`. Keep
`use_package_routes=false` when the application does not need these endpoints.

### Role identities are explicit

`super_admin_role` is one exact role **name**. A role whose slug matches that name, or whose name matches another
protected slug, is not privileged. Role middleware compares exact role **slugs**. Use `hasRoleByName()` or
`hasRoleBySlug()` at security boundaries instead of relying on the compatibility `hasRole()` method.

### Scopes are exact

For authorization checks, an omitted scope means a null-scope permission only. Empty string, `"0"`, and other strings
are distinct:

```php
$user->hasPermissionTo('jobs.edit');       // null scope only
$user->hasPermissionTo('jobs.edit', '');   // empty scope only
$user->hasPermissionTo('jobs.edit', '0');  // "0" scope only
```

Enumeration remains separate: `getAllPermissions()` and `getAllPermissionsForScope(null)` return all scopes, while a
non-null scope filters exactly. Published TypeScript helpers follow the authorization rule.

### Wildcards use only `*`

Wildcard matching is anchored to the complete permission name or slug. Only `*` is special. SQL wildcard characters
`%` and `_`, and periods, are literal. Matching follows `case_sensitive_permissions` in application code and no longer
depends on database collation.

### Authorization checks do not create records

`hasPermissionTo()` is read-only even when `auto_create_permissions` is enabled. Create missing permissions through an
explicit mutation API or the assignment command's `--create-permission` option.

## Mutation API and CLI changes

Permission mutation references may be a persisted `Permission`, an ID, a unique slug, or a structured
`PermissionReference`/`['slug' => ..., 'scope' => ...]`. A slug shared by multiple scopes is rejected unless its exact
scope is supplied.

`syncPermissions()` resolves the complete target before writing, executes in a transaction, and returns deterministic
`attached`, `detached`, and `unchanged` permission descriptors. Empty input is rejected unless clear intent is explicit:

```php
$manager->syncPermissions($role, [$permission, 42, 'unique-slug']);
$manager->syncPermissions($role, [
    ['slug' => 'jobs.edit', 'scope' => '0'],
]);
$manager->clearPermissions($role);
```

Command equivalents:

```bash
php artisan role-permission:sync editor read write
php artisan role-permission:sync editor jobs.edit --scope=0
php artisan role-permission:sync 7 12 13 --use-ids
php artisan role-permission:sync editor --clear
php artisan role-permission:assign editor jobs.edit --scope=0
php artisan role-permission:remove editor jobs.edit --scope=0
```

A declined confirmation, unknown/ambiguous reference, partial invalid list, or invalid ID returns a failure exit code and
does not report completion.

## Cache deployment contract

All package authorization caches use a versioned namespace, stable scalar identities, the configured store, and the
documented TTL in minutes. `use_cache=false` performs no package cache I/O. Supported package mutations publish a new
authorization generation only after their outer transaction commits.

For more than one PHP process or application node, configure a cache store shared by every node. Array, file, and other
process-local stores provide only local consistency. Direct SQL, query-builder pivot writes, and host mutations that
bypass package APIs must rotate package state after commit:

```php
app(\CreativeCrafts\LaravelRolePermissionManager\Cache\AuthorizationCache::class)
    ->flushAuthorizationState();
```

Do not run old and new package versions concurrently: old nodes can repopulate legacy keys that new code intentionally
ignores. Drain old nodes, deploy the new code everywhere, migrate, and then restore traffic. Before rollback, drain the
new nodes and clear the selected authorization cache store, including legacy `user_permissions_*` and `user_roles_*`
entries.

## Published frontend assets

Composer does not overwrite files already published into an application. Republish to a temporary location or compare
the vendor files manually, then merge local changes:

```bash
php artisan vendor:publish \
  --tag="laravel-role-permission-manager-typescript" \
  --force
```

The helpers now read current props for every principal, have no hardcoded super-admin bypass, and distinguish null,
empty, and `"0"` scopes. They are display helpers only; server-side authorization remains authoritative.

## Application adoption checklist

- Prefix package Gate abilities and regression-test unrelated model policies.
- Seed `view roles` and `view permissions` only for intended metadata readers.
- Audit every permission middleware call that previously relied on a scoped grant satisfying an omitted scope.
- Verify the configured privileged role name and every role-middleware slug.
- Replace ambiguous mutation slugs with exact scoped references.
- Run the preflight and migrations on both the primary database engine and a production-like copy.
- Republish or manually merge the TypeScript helpers.
- Exercise login, logout, principal switching, revocation, metadata endpoints, and host policies.
- Use one shared cache store before claiming cross-node revocation consistency.

