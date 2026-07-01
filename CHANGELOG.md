# Changelog

## [1.10.1] - 2026-06-18

- Removed `is()` and `isNot()` from `HasTyroRoles` trait to resolve compatibility issues

## [1.10.0] - 2026-06-18

- Added `is()`, `isNot()`, `syncRoles()`, `hasNoRoles()`, and `rolesCount()` helpers to `HasTyroRoles` trait

## [1.9.0] - 2026-06-16

- Added `findRole()` and `findPrivilege()` static methods to `Role` and `Privilege` models

## [1.8.0] - 2026-06-15

- Added `attachRole()` / `detachRole()` to `HasTyroRoles` trait
- Added role check helpers: `isAdmin()`, `isSuperAdmin()`, `isEditor()`, `isCustomer()`
- Refactored `assignRole()` / `removeRole()` as wrapper methods

## [1.7.0] - 2026-06-01

- Added AI skill repository system
- Registered 4 missing console commands
- Fixed installer paths in `SetupAiSkillCommand`

## [1.6.0] - 2026-04-14

- Added `tyro:update-config` command

## [1.5.0] - 2026-03-18

- Added Laravel 13 support
- Audit trail now logs user login and logout events

## [1.4.0] - 2026-03-02

- Added `hasAnyRole()` support — check if a user has any of the given roles with a single method call

## [1.3.1] - 2026-02-17

- Audit trail now logs user email address changes

## [1.3.0] - 2026-02-17

- Added comprehensive audit trail system
- Consistent naming for console commands with backward compatibility aliases
- Audit trail filtering by date
- Audit trail reports now show context

## [1.2.8] - 2026-02-09

- Fixed issue #6: authenticated users can no longer update their own email verification timestamp

## [1.2.7] - 2026-02-06

- Minor middleware improvement (merged PR #5)

## [1.2.6] - 2026-02-06

- Blade directives standardization with camelCase aliases (merged PR #4)

## [1.2.5] - 2026-02-06

- Performance improvement: reduced memory usage in middleware to avoid N+1 query issues (merged PR #5)

## [1.2.4] - 2026-01-29

- Added null safety checks to `HasTyroRoles` trait methods
- Fixed null user ID handling in `HasTyroRoles` trait

## [1.2.3] - 2026-01-18

- In `--no-interaction` mode, `tyro:install` seeds only roles and privileges (no users)
- Seeders no longer create duplicate roles, users, or privileges

## [1.2.2] - 2026-01-18

- In `--no-interaction` mode, `tyro:install` command skips seeding entirely

## [1.2.0] - 2025-12-06

- Added password complexity enforcement

## [1.1.3] - 2025-12-01

- Namespace resolution fix and seeding fix for Linux environments

## [1.1.2] - 2025-12-01

- Namespace resolution fix and seeding fix for Linux environments

## [1.1.1] - 2025-11-29

- Documentation update and branding improvements
- New Blade directives

## [1.1.0] - 2025-11-29

- Blade directives for role and privilege checks
- `hasPrivilege()` and `hasPrivileges()` methods in `Role` model

## [1.0.1] - 2025-11-23

- Minor improvements and bug fixes

## [1.0.0] - 2025-11-22

- Initial release
