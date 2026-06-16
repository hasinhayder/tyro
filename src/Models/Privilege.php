<?php

namespace HasinHayder\Tyro\Models;

use HasinHayder\Tyro\Support\TyroAudit;
use HasinHayder\Tyro\Support\TyroCache;
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

    public function attachRole(Role $role): void {
        $this->roles()->syncWithoutDetaching($role);
        TyroCache::forgetUsersByRole($role);
        TyroAudit::log('role.attached', $this, null, ['role_id' => $role->id, 'role_slug' => $role->slug]);
    }

    public function detachRole(Role $role): void {
        $this->roles()->detach($role);
        TyroCache::forgetUsersByRole($role);
        TyroAudit::log('role.detached', $this, null, ['role_id' => $role->id, 'role_slug' => $role->slug]);
    }

    public function roles(): BelongsToMany {
        return $this->belongsToMany(
            Role::class,
            config('tyro.tables.role_privilege', 'privilege_role')
        )->using(RolePrivilege::class)->withTimestamps();
    }

    /**
     * Find a privilege by its slug.
     */
    public static function findPrivilege(string $slug): ?self {
        return self::where('slug', $slug)->first();
    }
}
