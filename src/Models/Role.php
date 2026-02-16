<?php

namespace HasinHayder\Tyro\Models;

use HasinHayder\Tyro\Support\TyroAudit;
use HasinHayder\Tyro\Support\TyroCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model {
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    protected $hidden = ['pivot', 'created_at', 'updated_at'];

    protected $table = 'roles';

    public function users() {
        $userClass = config('tyro.models.user', config('auth.providers.users.model', 'App\\Models\\User'));

        return $this->belongsToMany($userClass, config('tyro.tables.pivot', 'user_roles'));
    }

    public function privileges(): BelongsToMany {
        return $this->belongsToMany(
            Privilege::class,
            config('tyro.tables.role_privilege', 'privilege_role')
        )->using(RolePrivilege::class)->withTimestamps();
    }

    /**
     * Check if the role has a specific privilege by slug.
     *
     * @param string $privilegeSlug
     * @return bool
     */
    public function hasPrivilege(string $privilegeSlug): bool {
        // Use eager-loaded data if available to avoid N+1 queries
        if ($this->relationLoaded('privileges')) {
            return $this->privileges->contains('slug', $privilegeSlug);
        }
        
        return $this->privileges()->where('slug', $privilegeSlug)->exists();
    }

    /**
     * Check if the role has all of the specified privileges by slug.
     *
     * @param array $privilegeSlugs
     * @return bool
     */
    public function hasPrivileges(array $privilegeSlugs): bool {
        $rolePrivilegeSlugs = $this->privileges()->pluck('slug')->toArray();

        foreach ($privilegeSlugs as $slug) {
            if (!in_array($slug, $rolePrivilegeSlugs)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Attach a privilege to the role.
     */
    public function attachPrivilege(Privilege $privilege): void {
        $this->privileges()->syncWithoutDetaching($privilege);
        TyroCache::forgetUsersByRole($this);
        TyroAudit::log('privilege.attached', $this, null, [
            'privilege_id' => $privilege->id,
            'privilege_slug' => $privilege->slug,
        ]);
    }

    /**
     * Detach a privilege from the role.
     */
    public function detachPrivilege(Privilege $privilege): void {
        $this->privileges()->detach($privilege);
        TyroCache::forgetUsersByRole($this);
        TyroAudit::log('privilege.detached', $this, null, [
            'privilege_id' => $privilege->id,
            'privilege_slug' => $privilege->slug,
        ]);
    }
}
