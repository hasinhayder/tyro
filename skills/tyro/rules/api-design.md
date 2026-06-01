# API Design

## Why It Matters

Tyro exposes a full REST API for managing users, roles, privileges, assignments, suspensions, and audit logs. These endpoints are consumed programmatically by CLIs, dashboards, and integrations. Inconsistent error handling, poor resource nesting, missing authorization checks, or rigid versioning create maintenance burden and break clients. The API surface is defined in `routes/api.php` and served under a configurable prefix (`config('tyro.route_prefix', 'api')`) with Sanctum token authentication and configurable middleware stacks.

## Incorrect

```php
// Mixing authorization logic into controllers inconsistently
public function destroy($id) {
    $role = Role::findOrFail($id);
    // One controller checks auth, another doesn't
    $role->delete();
    return response()->json(['message' => 'deleted']);
}

// Returning raw Eloquent models with hidden fields exposed
public function index() {
    return AuditLog::all(); // Exposes all columns including internal metadata
}

// Inconsistent error response shapes
// Some return ['error' => 1, 'message' => '...']
// Others throw exceptions that become HTML errors
public function store(Request $request) {
    if ($exists) {
        abort(409, 'role already exists'); // No JSON structure
    }
}

// Using different route naming conventions within the same group
Route::post('users/suspend', ...)->name('suspend-user');
Route::post('users/unsuspend', ...)->name('users.unsuspend'); // inconsistent
```

## Correct

```php
// Consistent JSON error responses — always { error: int, message: string }
public function store(Request $request) {
    $data = $request->validate([
        'name' => 'required|string',
        'slug' => 'required|string|unique:roles,slug',
    ]);
    return Role::create($data);
}

// Standard validation for existing resources
public function store(Request $request) {
    $existing = Role::where('slug', $data['slug'])->first();
    if ($existing) {
        return response(['error' => 1, 'message' => 'role already exists'], 409);
    }
    return Role::create($data);
}

// Nested resources follow Laravel conventions
// users/{user}/roles — nested under user resource
// roles/{role}/privileges — nested under role resource
public function index($user) {
    $user = $this->resolveUser($user);
    return $user->load('roles');
}

// All routes use tyro.{resource}.{action} naming
// Route definition in TyroServiceProvider:
Route::group([
    'prefix' => trim(config('tyro.route_prefix', 'api'), '/'),
    'middleware' => config('tyro.route_middleware', ['api']),
    'as' => config('tyro.route_name_prefix', 'tyro.'),
], function () {
    $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
});
```

## Notes

- The `disable_api` config option (`TYRO_DISABLE_API=true`) completely skips route registration in `TyroServiceProvider::registerRoutes()`.
- Custom route prefixes are set via `TYRO_ROUTE_PREFIX` env (default `api`), so routes become `api/tyro`, `api/roles`, etc.
- Route middleware is configurable via `tyro.route_middleware` (default `['api']`). The guard middleware is hardcoded to `auth:config('tyro.guard')`.
- Three auth tiers exist: public (login, register, tyro info), user (update own profile), and admin (CRUD roles/privileges/users/suspensions/audit-logs).
- Controllers return raw models or collections — Laravel's Eloquent serialization handles field filtering via `$hidden` properties.
- `Role` and `Privilege` models hide `pivot`, `created_at`, `updated_at` by default.
- Pagination for audit logs: `GET audit-logs?per_page=20&event=role.&user_id=1&from=2024-01-01&to=2024-12-31`.
- The `AuditLogController` extends `TyroController` and adds the `summary` computed attribute to each paginated result.
- Resource binding: `Role` and `Privilege` use `Route::model()`, while `User` uses a custom `Route::bind()` that queries the configurable user model.
- No explicit API version prefix — stability relies on additive changes to the existing endpoint set.

### Controller Reference

| Controller | Routes | Auth Tier |
|---|---|---|
| `TyroController` | `GET /` (info), `GET /version` | Public |
| `UserController` | CRUD + `POST /login` + `GET /me` | Mixed (public login, admin CRUD, user self-update) |
| `RoleController` | Full CRUD | Admin |
| `PrivilegeController` | Full CRUD | Admin |
| `UserRoleController` | List/attach/detach user roles | Admin |
| `RolePrivilegeController` | List/attach/detach role privileges | Admin |
| `UserSuspensionController` | Suspend/unsuspend | Admin |
| `AuditLogController` | `GET /audit-logs` (paginated, filterable) | Admin |

```php
// TyroController::tyro() returns a welcome payload
// GET /api/  →  { error: 0, message: "Hello Tyro!", version: "..." }

// TyroController::version() returns version info
// GET /api/version  →  { error: 0, version: "..." }

// UserController::update() uses tokenCan() for self-update authorization
public function update(Request $request, $user) {
    $authUser = $request->user();
    if ($authUser->tokenCan('user') && $authUser->id !== $user->id) {
        return response(['error' => 1, 'message' => 'unauthorized'], 403);
    }
    // Sanctum token abilities control self-update vs admin-update
    // Token abilities are set during login from config('tyro.abilities.*')
}
```

## Cross References

- policies.md (route-level middleware for authorization)
- security.md (suspension endpoint checks, admin protection)
- artisan-commands.md (CLI alternatives to every API endpoint)
- multi-tenancy.md (scoping considerations)
