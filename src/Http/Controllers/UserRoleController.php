<?php

namespace HasinHayder\Tyro\Http\Controllers;

use HasinHayder\Tyro\Models\Role;
use HasinHayder\Tyro\Support\TyroCache;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UserRoleController extends Controller
{
    public function index($user)
    {
        $user = $this->resolveUser($user);

        return $user->load('roles');
    }

    public function store(Request $request, $user)
    {
        $user = $this->resolveUser($user);
        $data = $request->validate([
            'role_id' => 'required|integer',
        ]);

        $role = Role::findOrFail($data['role_id']);
        if (! $user->roles()->find($role->id)) {
            $user->assignRole($role);
        }

        return $user->load('roles');
    }

    public function destroy($user, Role $role)
    {
        $user = $this->resolveUser($user);
        $user->removeRole($role);

        return $user->load('roles');
    }

    protected function resolveUser($user)
    {
        $class = config('tyro.models.user', config('auth.providers.users.model', 'App\\Models\\User'));

        if ($user instanceof $class) {
            return $user;
        }

        return $class::query()->findOrFail($user);
    }
}
