---
name: tyro
version: 1.0.0
description: Framework-maintainer skill for the Tyro Laravel authorization package
---

# Tyro Authorization Framework Skill

## Description

This skill provides engineering rules for maintaining and extending **Tyro**, a reusable Laravel authorization framework providing role-based access control (RBAC), permissions, privileges, suspensions, authorization caching, audit logging, and Artisan tooling.

Tyro is the authorization layer of the Tyro ecosystem. Related packages (Tyro Login, Tyro Dashboard) depend on Tyro's public APIs.

This skill treats every rule as a framework contract. Changes must preserve backward compatibility, avoid privilege escalation, and follow established conventions.

---

## Activation Triggers

Activate this skill when working on:

- Tyro package source code under `src/`
- Tyro configuration in `config/tyro.php`
- Tyro migrations in `database/migrations/`
- Tyro seeders in `database/seeders/`
- Tyro tests in `tests/`
- Tyro Artisan commands
- Tyro middleware, controllers, or Blade directives
- Tyro authorization logic, permission resolution, or caching
- Any code that imports `HasinHayder\Tyro\` namespace

---

## Consistency First

Before introducing any new pattern, check whether an established convention already exists in the Tyro codebase.

- Follow existing package conventions (`HasinHayder\Tyro` namespace, PSR-4 autoloading)
- Follow existing command conventions (`tyro:{entity}-{action}`, `tyro:sys-{action}`, `tyro:auth-{action}`)
- Follow existing event conventions (`{entity}.{action}` dot notation for audit events)
- Follow existing cache conventions (`tyro:user-{userId}:roles`, `tyro:user-{userId}:privileges`)
- Follow existing naming conventions (PascalCase classes, kebab-case Artisan signatures, snake_case config keys)
- Follow existing extension-point conventions (configurable table names, configurable model classes)

Consistency is more important than theoretical perfection.

---

## Quick Reference

| Concern | Rule File |
|---|---|
| Package structure, dependency direction | [rules/architecture.md](rules/architecture.md) |
| Authorization contracts, services, boundaries | [rules/authorization.md](rules/authorization.md) |
| Permission registration, naming, lifecycle | [rules/permissions.md](rules/permissions.md) |
| Role registration, assignment, hierarchy | [rules/roles.md](rules/roles.md) |
| System-level privileges, break-glass access | [rules/privileges.md](rules/privileges.md) |
| User/role/permission/tenant suspensions | [rules/suspensions.md](rules/suspensions.md) |
| Role/permission/privilege inheritance | [rules/inheritance.md](rules/inheritance.md) |
| Resolution pipelines, conflict handling | [rules/permission-resolution.md](rules/permission-resolution.md) |
| Policy integration, discovery, boundaries | [rules/policies.md](rules/policies.md) |
| Public/internal APIs, REST endpoints, versioning | [rules/api-design.md](rules/api-design.md) |
| Command architecture, naming, UX, safety | [rules/artisan-commands.md](rules/artisan-commands.md) |
| Audit logging, immutable trails, compliance | [rules/audit-logs.md](rules/audit-logs.md) |
| Permission/role/event/command/cache naming | [rules/naming-conventions.md](rules/naming-conventions.md) |
| Tenant isolation, scoped permissions | [rules/multi-tenancy.md](rules/multi-tenancy.md) |
| Cache invalidation, warming, consistency | [rules/caching.md](rules/caching.md) |
| Privilege escalation prevention, secure defaults | [rules/security.md](rules/security.md) |
| Role/permission/privilege/suspension/cache events | [rules/events.md](rules/events.md) |
| Package, cache, driver, tenant configuration | [rules/configuration.md](rules/configuration.md) |
| Custom providers, drivers, resolvers | [rules/extensibility.md](rules/extensibility.md) |
| Permission, authorization, security testing | [rules/testing.md](rules/testing.md) |
| Lookup optimization, query tuning, scaling | [rules/performance.md](rules/performance.md) |
| Migrations, seeding, synchronization | [rules/migrations-seeding.md](rules/migrations-seeding.md) |
| Public API stability, deprecation, upgrade paths | [rules/backward-compatibility.md](rules/backward-compatibility.md) |
| Public API docs, extension docs, upgrade guides | [rules/documentation.md](rules/documentation.md) |
| Middleware architecture, resolution, wildcards | [rules/middleware.md](rules/middleware.md) |
| Blade directives, aliases, registration | [rules/blade-directives.md](rules/blade-directives.md) |
| Model conventions, pivots, observers, factories | [rules/models.md](rules/models.md) |
| Error responses, HTTP status codes, exit codes | [rules/error-handling.md](rules/error-handling.md) |

---

## How To Apply

1. **Identify the concern** you are working on (roles, permissions, caching, commands, etc.)
2. **Read the corresponding rule file** from the Quick Reference table above
3. **Check cross-referenced files** listed at the bottom of each rule file
4. **Follow existing codebase conventions** before introducing new patterns (Consistency First)
5. **Run the full test suite** after changes: `vendor/bin/pest`
6. **Run the linter** after changes: `vendor/bin/pint --test`

Every rule file follows this structure:

- **Why It Matters** — architectural reasoning
- **Incorrect** — realistic bad examples
- **Correct** — preferred implementation
- **Notes** — additional practical guidance
