<?php

namespace HasinHayder\Tyro\Models\Observers;

use HasinHayder\Tyro\Models\Privilege;
use HasinHayder\Tyro\Support\TyroAudit;

class PrivilegeObserver
{
    public function created(Privilege $privilege)
    {
        TyroAudit::log('privilege.created', $privilege, null, $privilege->toArray());
    }

    public function updated(Privilege $privilege)
    {
        TyroAudit::log('privilege.updated', $privilege, $privilege->getOriginal(), $privilege->getChanges());
    }

    public function deleted(Privilege $privilege)
    {
        TyroAudit::log('privilege.deleted', $privilege, $privilege->toArray());
    }
}
