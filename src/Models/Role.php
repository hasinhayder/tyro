<?php

namespace HasinHayder\Tyro\Models;

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
}
