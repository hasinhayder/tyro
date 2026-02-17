<?php

namespace HasinHayder\Tyro\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function getTable()
    {
        return config('tyro.tables.audit_logs', 'tyro_audit_logs');
    }

    public function user(): BelongsTo
    {
        $userClass = config('tyro.models.user', 'App\Models\User');
        return $this->belongsTo($userClass, 'user_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getSummaryAttribute(): string
    {
        $target = $this->auditable_type ? basename($this->auditable_type) : 'System';
        $new = $this->new_values ?? [];
        $old = $this->old_values ?? [];

        switch ($this->event) {
            case 'role.assigned':
                return "Assigned role \"{$new['role_slug']}\" to user #{$this->auditable_id}";
            case 'role.removed':
                return "Removed role \"{$new['role_slug']}\" from user #{$this->auditable_id}";
            case 'role.created':
                return "Created role \"{$new['slug']}\" ({$new['name']})";
            case 'role.updated':
                $changes = array_keys($new);
                return "Updated role \"{$this->auditable_id}\": " . implode(', ', $changes);
            case 'role.deleted':
                return "Deleted role \"{$old['slug']}\"";
            case 'privilege.created':
                return "Created privilege \"{$new['slug']}\"";
            case 'privilege.updated':
                return "Updated privilege \"{$this->auditable_id}\"";
            case 'privilege.deleted':
                return "Deleted privilege \"{$old['slug']}\"";
            case 'privilege.attached':
                return "Attached privilege \"{$new['privilege_slug']}\" to role #{$this->auditable_id}";
            case 'privilege.detached':
                return "Detached privilege \"{$new['privilege_slug']}\" from role #{$this->auditable_id}";
            case 'user.suspended':
                return "Suspended user #{$this->auditable_id}" . ($new['suspension_reason'] ? " reason: {$new['suspension_reason']}" : "");
            case 'user.unsuspended':
                return "Unsuspended user #{$this->auditable_id}";
            case 'user.created':
                return "Created user \"{$new['email']}\"";
            case 'user.updated':
                return "Updated user \"{$new['email']}\"";
            case 'user.email_changed':
                $oldEmail = $old['email'] ?? 'unknown';
                $newEmail = $new['email'] ?? 'unknown';
                return "Changed user email from \"{$oldEmail}\" to \"{$newEmail}\"";
            case 'user.deleted':
                return "Deleted user \"{$old['email']}\"";
            case 'user.token_created':
                return "Created token \"{$new['token_name']}\" for user #{$this->auditable_id}";
            case 'user.token_revoked':
                return "Revoked token \"{$new['token_name']}\" for user #{$this->auditable_id}";
            case 'user.tokens_revoked':
                return "Revoked all ({$new['token_count']}) tokens for user #{$this->auditable_id}";
            case 'system.tokens_purged':
                return "Purged all ({$new['token_count']}) tokens in system";
            case 'system.seeded':
                return "Seeded system: {$new['type']}";
            case 'system.installed':
                return "Installed Tyro";
            case 'roles.flushed':
                return "Flushed all roles and assignments";
            case 'privileges.purged':
                return "Purged all ({$new['deleted_count']}) privileges";
            default:
                return $this->event;
        }
    }
}
