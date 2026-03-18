<?php

namespace HasinHayder\Tyro\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Privilege extends Model {
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description'];

    protected $hidden = ['pivot', 'created_at', 'updated_at'];

    public function getTable() {
        return config('tyro.tables.privileges', parent::getTable());
    }

    public function roles(): BelongsToMany {
        return $this->belongsToMany(
            Role::class,
            config('tyro.tables.role_privilege', 'privilege_role')
        )->using(RolePrivilege::class)->withTimestamps();
    }
}
