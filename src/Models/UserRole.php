<?php

namespace HasinHayder\Tyro\Models;

use HasinHayder\Tyro\Support\TyroCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserRole extends Pivot {
    use HasFactory;

    protected $table = 'user_roles';

    protected $fillable = ['user_id', 'role_id'];

    public $timestamps = true;

    protected static function booted(): void {
        static::saved(function (self $pivot): void {
            TyroCache::forgetUser($pivot->user_id);
        });

        static::deleted(function (self $pivot): void {
            TyroCache::forgetUser($pivot->user_id);
        });
    }

    public function getTable() {
        return config('tyro.tables.pivot', parent::getTable());
    }
}
