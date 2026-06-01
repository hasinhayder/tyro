# Multi-Tenancy

## Why It Matters

Tyro is designed as a single-tenant authorization framework by default, but multi-tenancy is a natural extension requirement for SaaS applications. The architecture provides extension points (configurable user model, role-based scoping, polymorphic audit trails) that can support tenant isolation without modifying core source files. At this stage, Tyro does not ship tenant-scoped role or privilege queries — roles and privileges are global by design. Building tenant isolation on top of Tyro requires understanding where scoping hooks exist and where they must be introduced.

## Incorrect

```php
// Assuming roles are tenant-scoped — they are not by default
$tenantRoles = Role::where('tenant_id', $tenantId)->get();
// The roles table has no tenant_id column

// Adding tenant_id directly to pivot tables without config
$user->roles()->attach($roleId, ['tenant_id' => $tenantId]);
// The user_roles pivot table has no tenant_id column

// Filtering privileges by tenant — privileges are global
$privileges = Privilege::where('tenant_id', $tenantId)->get();

// Using the configurable user model to bypass tenant isolation
// The user model is configurable, but the role/privilege tables are fixed
$user = $this->findUser($identifier); // Could be any user across tenants
```

## Correct

```php
// Extend Tyro for multi-tenancy: scope the user query
// In your application's User model (config('tyro.models.user')):
public function scopeForTenant($query, $tenantId) {
    return $query->where('tenant_id', $tenantId);
}

// Context propagation: store tenant in request attributes
// In middleware:
$request->attributes->set('tenant_id', $request->user()->tenant_id);

// Custom role scoping via a tenant-aware Role model
// Extend HasinHayder\Tyro\Models\Role and add tenant relationship:
class TenantRole extends Role {
    public function scopeForTenant($query, $tenantId) {
        return $query->where('tenant_id', $tenantId);
    }
}
// Then configure: config(['tyro.models.role' => TenantRole::class]);

// Tenant-aware user resolution in commands — override findUser
protected function findUser(?string $identifier): ?Model {
    $user = parent::findUser($identifier);
    if ($user && $user->tenant_id !== $this->option('tenant')) {
        return null; // User doesn't belong to this tenant
    }
    return $user;
}

// Scoped cache keys — include tenant in cache key
// (Requires extending TyroCache)
public static function rolesKey($userId, $tenantId = null): string {
    $tenant = $tenantId ? "t{$tenantId}." : '';
    return "tyro:{$tenant}user-{$userId}:roles";
}

// Audit logs already support tenant context via metadata
TyroAudit::log('role.created', $role, null, $role->toArray(), [
    'tenant_id' => $tenantId,
]);

// Use the abilities config for tenant-level gating
// config/tyro.php:
'abilities' => [
    'tenant_admin' => ['tenant-'.$tenantId.'-admin'],
],
```

## Notes

- The `roles` and `privileges` tables have no tenant column by default — they are application-global.
- The `user_roles` and `privilege_role` pivot tables also lack tenant columns.
- Configurable user model (`config('tyro.models.user')`) is the primary tenancy extension point — your User model can implement `HasTyroRoles` and add tenant scoping.
- Role and privilege slugs are global — a role named `editor` in tenant A is the same role in tenant B. For tenant-scoped slugs, use prefixing: `tenant-{id}-editor`.
- `TyroCache` methods use flat keys with no tenant namespace. Extend `TyroCache` or override key generation for tenant-isolated caching.
- The `config('tyro.tables.*')` values point to fixed table names. For true tenant-isolated tables (separate databases or schemas), override these config values per-tenant at runtime.
- Audit logs via `TyroAudit::log()` support arbitrary `metadata` — pass `tenant_id` here for tenant-scoped audit queries.
- Token abilities (`$user->createToken('name', $abilities)`) are role-slug-based and global. For tenant-scoped tokens, include tenant context in the ability string.
- Commands receive no tenant context by default. For tenant-aware CLI commands, add a `--tenant` option and filter queries accordingly.
- Middleware (`role`, `roles`, `privilege`, `privileges`) does not perform tenant scoping — it checks only slug membership against the user's roles/privileges.

## Cross References

- caching.md (cache key isolation for tenants)
- api-design.md (route-level tenant scoping)
- security.md (tenant boundary enforcement)
