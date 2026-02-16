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
}
