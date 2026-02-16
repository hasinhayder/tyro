<?php

namespace HasinHayder\Tyro\Models\Observers;

use HasinHayder\Tyro\Models\Role;
use HasinHayder\Tyro\Support\TyroAudit;

class RoleObserver
{
    public function created(Role $role)
    {
        TyroAudit::log('role.created', $role, null, $role->toArray());
    }

    public function updated(Role $role)
    {
        TyroAudit::log('role.updated', $role, $role->getOriginal(), $role->getChanges());
    }

    public function deleted(Role $role)
    {
        TyroAudit::log('role.deleted', $role, $role->toArray());
    }
}
