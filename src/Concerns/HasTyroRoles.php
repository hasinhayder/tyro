<?php

namespace HasinHayder\Tyro\Concerns;

use HasinHayder\Tyro\Models\Role;
use HasinHayder\Tyro\Models\UserRole;
use HasinHayder\Tyro\Support\TyroCache;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

trait HasTyroRoles {
    protected ?array $tyroRoleSlugsCache = null;

    protected ?array $tyroPrivilegeSlugsCache = null;

    protected ?int $tyroRoleSlugsVersion = null;

    protected ?int $tyroPrivilegeSlugsVersion = null;

    /**
     * Get the roles relationship for the user.
     */
    public function roles(): BelongsToMany {
        return $this->belongsToMany(
            Role::class,
            config('tyro.tables.pivot', 'user_roles')
        )->using(UserRole::class)->withTimestamps();
    }

    /**
     * Assign a role to the user.
     */
    public function assignRole(Role $role): void {
        $this->roles()->syncWithoutDetaching($role);
        TyroCache::forgetUser($this->getKey());
        $this->flushTyroRuntimeCache();
    }

    /**
     * Remove a role from the user.
     */
    public function removeRole(Role $role): void {
        $this->roles()->detach($role);
        TyroCache::forgetUser($this->getKey());
        $this->flushTyroRuntimeCache();
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $role): bool {
        $userRoles = $this->tyroRoleSlugs();
        return in_array($role, $userRoles, true) || in_array('*', $userRoles, true);
    }

    /**
     * Check if the user has all of the given roles.
     */
    public function hasRoles(array $roles): bool {
        $userRoles = $this->tyroRoleSlugs();
        if (in_array('*', $userRoles, true)) {
            return true;
        }
        return empty(array_diff($roles, $userRoles));
    }

    /**
     * Get all privileges for the user (flattened from all roles).
     * Eager-load privileges if missing to avoid N+1 queries.
     */
    public function privileges(): Collection {
        if ($this->relationLoaded('roles')) {
            $roles = $this->roles;
            if ($roles->isNotEmpty() && !$roles->first()->relationLoaded('privileges')) {
                $roles->load('privileges');
            }
        } else {
            $roles = $this->roles()->with('privileges')->get();
        }
        return $roles
            ->flatMap(fn(Role $role) => $role->privileges)
            ->unique('id')
            ->values();
    }

    /**
     * Check if the user has all of the given privileges.
     */
    public function hasPrivileges(array $privileges): bool {
        $userPrivileges = $this->tyroPrivilegeSlugs();
        if (in_array('*', $userPrivileges, true)) {
            return true;
        }
        return empty(array_diff($privileges, $userPrivileges));
    }

    /**
     * Check if the user can perform the given ability.
     * Checks privilege, then role, then falls back to Gate.
     */
    public function can($ability, $arguments = []): bool {
        if (is_string($ability) && $this->hasPrivilege($ability)) {
            return true;
        }
        if (is_string($ability) && $this->hasRole($ability)) {
            return true;
        }
        return Gate::forUser($this)->check($ability, $arguments);
    }

    /**
     * Check if the user has a specific privilege.
     */
    public function hasPrivilege(string $ability): bool {
        $userPrivileges = $this->tyroPrivilegeSlugs();
        return in_array($ability, $userPrivileges, true) || in_array('*', $userPrivileges, true);
    }

    /**
     * Get all role slugs for the user (cached).
     */
    public function tyroRoleSlugs(): array {
        $userId = $this->getKey();
        $runtimeVersion = TyroCache::runtimeVersion($userId);
        if ($this->tyroRoleSlugsCache !== null && $this->tyroRoleSlugsVersion === $runtimeVersion) {
            return $this->tyroRoleSlugsCache;
        }

        $slugs = $this->getTyroSlugsData($userId, 'roles');

        $this->tyroRoleSlugsCache = $slugs;
        $this->tyroRoleSlugsVersion = $runtimeVersion;
        return $slugs;
    }

    /**
     * Get all privilege slugs for the user (cached).
     */
    public function tyroPrivilegeSlugs(): array {
        $userId = $this->getKey();
        $runtimeVersion = TyroCache::runtimeVersion($userId);
        if ($this->tyroPrivilegeSlugsCache !== null && $this->tyroPrivilegeSlugsVersion === $runtimeVersion) {
            return $this->tyroPrivilegeSlugsCache;
        }

        $slugs = $this->getTyroSlugsData($userId, 'privileges');

        $this->tyroPrivilegeSlugsCache = $slugs;
        $this->tyroPrivilegeSlugsVersion = $runtimeVersion;
        return $slugs;
    }

    /**
     * Get Tyro slugs data with optimized caching and relation handling.
     */
    protected function getTyroSlugsData(int $userId, string $type): array {
        if ($type === 'roles') {
            // Handle role slugs
            if ($this->relationLoaded('roles')) {
                $slugs = $this->roles->pluck('slug')->all();
            } else {
                $slugs = TyroCache::rememberRoleSlugs($userId, function () {
                    return $this->roles()->pluck('slug')->all();
                });
            }
        } else {
            // Handle privilege slugs
            if ($this->relationLoaded('roles') && $this->roles->every(fn($role) => $role->relationLoaded('privileges'))) {
                $slugs = $this->roles
                    ->flatMap(fn(Role $role) => $role->privileges)
                    ->pluck('slug')
                    ->all();
            } else {
                $slugs = TyroCache::rememberPrivilegeSlugs($userId, function () {
                    return $this->roles()
                        ->with('privileges:id,slug')
                        ->get()
                        ->flatMap(fn(Role $role) => $role->privileges)
                        ->pluck('slug')
                        ->all();
                });
            }
        }

        // Filter and deduplicate
        return array_values(array_unique(array_filter($slugs)));
    }

    /**
     * Flush the runtime cache for role and privilege slugs.
     */
    protected function flushTyroRuntimeCache(): void {
        $this->tyroRoleSlugsCache = null;
        $this->tyroPrivilegeSlugsCache = null;
        $this->tyroRoleSlugsVersion = null;
        $this->tyroPrivilegeSlugsVersion = null;
    }

    /**
     * Suspend the user and revoke all tokens.
     */
    public function suspend(?string $reason = null): void {
        $this->suspended_at = now();
        $this->suspension_reason = $reason;
        $this->save();
        // Revoke all active tokens
        $this->tokens()->delete();
    }

    /**
     * Unsuspend the user.
     */
    public function unsuspend(): void {
        $this->suspended_at = null;
        $this->suspension_reason = null;
        $this->save();
    }

    /**
     * Check if the user is suspended.
     */
    public function isSuspended(): bool {
        return (bool) ($this->suspended_at ?? false);
    }

    /**
     * Get the suspension reason for the user.
     */
    public function getSuspensionReason(): ?string {
        $reason = $this->suspension_reason ?? null;
        return $reason !== null ? (string) $reason : null;
    }
}
