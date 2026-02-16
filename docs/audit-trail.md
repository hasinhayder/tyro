# Audit Trail

The Audit Trail feature in Tyro provides a database-backed log of all administrative actions. It is designed to help you track "Who did what, to whom, and when," which is essential for security compliance and troubleshooting.

## Configuration

You can configure the audit trail in `config/tyro.php`. If you haven't published your config yet, run `php artisan tyro:publish-config`.

```php
'audit' => [
    'enabled' => env('TYRO_AUDIT_ENABLED', true),
    'retention_days' => env('TYRO_AUDIT_RETENTION_DAYS', 30),
],
```

- **`enabled`**: Set to `false` to completely disable audit logging.
- **`retention_days`**: Number of days to keep logs before they are eligible for purging via the `tyro:audit-purge` command.

## Recorded Events

Tyro automatically captures the following events:

### User Management
- `user.suspended`: When a user is suspended (includes the reason).
- `user.unsuspended`: When a user's suspension is lifted.
- `role.assigned`: When a role is attached to a user.
- `role.removed`: When a role is detached from a user.

### Role Management
- `role.created`: When a new role is created.
- `role.updated`: When a role's name or slug is modified (logs old vs. new values).
- `role.deleted`: When a role is deleted.

### Privilege Management
- `privilege.created`: When a new privilege is created.
- `privilege.updated`: When a privilege is modified.
- `privilege.deleted`: When a privilege is deleted.

## Data Captured

Each audit log entry includes:
- **User ID**: The ID of the authenticated user who performed the action (or "System" for CLI).
- **Event**: The type of action performed.
- **Auditable**: A polymorphic relation to the object affected (e.g., the User or Role).
- **Old Values**: A JSON representation of the data before the change.
- **New Values**: A JSON representation of the data after the change.
- **Metadata**: Includes IP Address, User-Agent, and whether the action was performed via Console (CLI).

## CLI Commands

### Viewing Logs
List recent audit logs with the `tyro:audit` command:

```bash
# Show last 20 logs
php artisan tyro:audit

# Show last 50 logs
php artisan tyro:audit --limit=50

# Filter by event type
php artisan tyro:audit --event=user.suspended

# Filter by date range
php artisan tyro:audit --from=2024-01-01 --to=2024-01-31
```

### Purging Logs
Clean up old records to keep your database lean:

```bash
# Purge logs older than the configured retention days (default 30)
php artisan tyro:audit-purge

# Override retention for a one-time purge
php artisan tyro:audit-purge --days=7

# Force purge without confirmation prompt
php artisan tyro:audit-purge --force
```

## REST API

If the Tyro API is enabled, admins can access logs via:

`GET /api/audit-logs`

### Query Parameters:
- `event`: Filter by event slug (e.g., `role.assigned`).
- `user_id`: Filter by the actor's user ID.
- `from`: Filter by start date (YYYY-MM-DD).
- `to`: Filter by end date (YYYY-MM-DD).
- `per_page`: Control pagination (default 20).

**Note:** This endpoint requires the authenticated user to have administrative privileges as defined in your Tyro config.
