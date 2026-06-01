# Migrations and Seeding

## Why It Matters

Tyro ships with 6 migrations and 4 seeders that establish the complete database schema for role-based access control, privilege management, user suspension, and audit logging. Every table name is configurable, allowing coexistence with existing database schemas. The sync pattern (updateOrCreate in seeders) ensures that roles and privileges defined in code are aligned with the database without destructive operations.

## Incorrect

Hardcoding table names in migrations:

```php
// Do NOT hardcode table names in migrations
Schema::create('roles', function (Blueprint $table) {
    // Hardcoded — breaks if config('tyro.tables.roles') is customized
});
```

Using destructive `create()` instead of `updateOrCreate()` in seeders:

```php
// Do NOT — this duplicates or crashes on re-seeding
Role::create(['name' => 'Administrator', 'slug' => 'admin']);
Role::create(['name' => 'Administrator', 'slug' => 'admin']); // Duplicate slug!
```

Omitting `--force` safety checks in destructive operations:

```php
// Do NOT — allow users to accidentally truncate production data
DB::table('roles')->truncate();
```

## Correct

Use config values in migrations so table names are customizable:

```php
// database/migrations/2022_05_17_181447_create_roles_table.php
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void {
        Schema::create(config('tyro.tables.roles', 'roles'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists(config('tyro.tables.roles', 'roles'));
    }
};
```

All 6 migrations and their purposes:

```php
// 1. create_roles_table
// Columns: id, name (string), slug (unique string), timestamps
// Table: config('tyro.tables.roles', 'roles')

// 2. create_user_roles_table (pivot)
// Columns: id, user_id (FK), role_id (FK), timestamps
// Table: config('tyro.tables.pivot', 'user_roles')

// 3. create_privileges_table
// Columns: id, name (string), slug (unique string), description (nullable text), timestamps
// Table: config('tyro.tables.privileges', 'privileges')

// 4. create_privilege_role_table (pivot)
// Columns: id, privilege_id (FK), role_id (FK), timestamps
// Table: config('tyro.tables.role_privilege', 'privilege_role')

// 5. add_suspension_columns_to_users_table
// Columns: suspended_at (nullable timestamp), suspension_reason (nullable string)
// Table: config('tyro.tables.users', 'users')

// 6. create_tyro_audit_logs_table
// Columns: id, user_id (nullable FK), event (string), auditable_type (nullable string),
//          auditable_id (nullable integer), old_values (nullable json), new_values (nullable json),
//          metadata (nullable json), created_at (timestamp)
// Table: config('tyro.tables.audit_logs', 'tyro_audit_logs')
```

Use `updateOrCreate` in seeders for idempotent seeding (database/seeders/RoleSeeder.php):

```php
class RoleSeeder extends Seeder {
    public function run(): void {
        $roles = [
            ['name' => 'Administrator', 'slug' => 'admin'],
            ['name' => 'User', 'slug' => 'user'],
            ['name' => 'Customer', 'slug' => 'customer'],
            ['name' => 'Editor', 'slug' => 'editor'],
            ['name' => 'All', 'slug' => '*'],
            ['name' => 'Super Admin', 'slug' => 'super-admin'],
        ];

        collect($roles)->each(fn ($role) => Role::updateOrCreate(
            ['slug' => $role['slug']],
            $role
        ));
    }
}
```

Sync pattern for privileges with role assignments (database/seeders/PrivilegeSeeder.php):

```php
class PrivilegeSeeder extends Seeder {
    public function run(): void {
        $definitions = [
            [
                'name' => 'Generate Reports',
                'slug' => 'report.generate',
                'description' => 'Allows generating system-wide reports.',
                'roles' => ['admin', 'super-admin'],
            ],
            [
                'name' => 'Manage Users',
                'slug' => 'users.manage',
                'description' => 'Allows creating, editing, and deleting users.',
                'roles' => ['admin', 'super-admin'],
            ],
            [
                'name' => 'Manage Roles',
                'slug' => 'roles.manage',
                'description' => 'Allows editing Tyro roles.',
                'roles' => ['super-admin'],
            ],
            [
                'name' => 'View Billing',
                'slug' => 'billing.view',
                'description' => 'Allows viewing billing statements.',
                'roles' => ['admin', 'user'],
            ],
            [
                'name' => 'Wildcard',
                'slug' => '*',
                'description' => 'Grants every privilege.',
                'roles' => ['*'],
            ],
        ];

        $roleMap = Role::query()->whereIn('slug',
            collect($definitions)->flatMap(fn ($d) => $d['roles'])->unique()->all()
        )->get()->keyBy('slug');

        collect($definitions)->each(function (array $definition) use ($roleMap): void {
            $privilege = Privilege::updateOrCreate(
                ['slug' => $definition['slug']],
                \Illuminate\Support\Arr::only($definition, ['name', 'description'])
            );

            $roleIds = collect($definition['roles'])
                ->map(fn ($slug) => $roleMap->get($slug)?->id)
                ->filter()->unique()->values()->all();

            if (! empty($roleIds)) {
                $privilege->roles()->sync($roleIds);
            }
        });
    }
}
```

Safe seed command usage with `--force` guard:

```php
// Commands check for --force before destructive operations
// Example from PurgePrivilegesCommand:
if (! $this->option('force') && ! $this->confirm('Are you sure you want to delete all privileges?')) {
    return self::FAILURE;
}
```

Available seed-related Artisan commands:

```php
'tyro:seed-all'         // Runs TyroSeeder (roles, privileges, users)
'tyro:seed-roles'       // Runs RoleSeeder only
'tyro:seed-privileges'  // Runs PrivilegeSeeder only
```

## Notes

- Migrations are loaded automatically via `$this->loadMigrationsFrom()` in TyroServiceProvider when running in console.
- Users can publish migrations via `php artisan vendor:publish --tag=tyro-migrations` for manual customization.
- The `TyroSeeder` orchestrates all three seeders in the correct order: RoleSeeder -> PrivilegeSeeder -> UsersSeeder.
- Seeder classes are autoloaded via classmap in composer.json: `"classmap": ["database/seeders", "database/factories"]`.
- The `--force` flag pattern is used consistently across all destructive commands to prevent accidental data loss.
- When adding new default roles or privileges in a package update, always use `updateOrCreate` to avoid breaking existing installations that may have customized these records.
- The `protected_role_slugs` config (`['admin', 'super-admin']`) is enforced in delete commands but NOT in seeders — seeders should always be able to recreate these.

## Cross References

- [configuration.md](configuration.md) — table name config keys
- [backward-compatibility.md](backward-compatibility.md) — safe migration upgrade patterns
- [extensibility.md](extensibility.md) — adding custom seeders
- [testing.md](testing.md) — seeding in test setUp()
