<?php

namespace HasinHayder\Tyro\Http\Controllers;

use HasinHayder\Tyro\Models\Privilege;
use HasinHayder\Tyro\Support\TyroCache;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class PrivilegeController extends Controller {
    public function index() {
        return Privilege::with('roles:id,name,slug')->get();
    }

    public function store(Request $request) {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'slug' => ['required', 'string', 'max:255', Rule::unique(config('tyro.tables.privileges', 'privileges'), 'slug')],
            'description' => ['nullable', 'string'],
        ]);

        $privilege = Privilege::create($data);

        return $privilege->fresh('roles:id,name,slug');
    }

    public function show(Privilege $privilege) {
        return $privilege->load('roles:id,name,slug');
    }

    public function update(Request $request, Privilege $privilege) {
        $data = $request->validate([
            'name' => ['sometimes', 'string'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique(config('tyro.tables.privileges', 'privileges'), 'slug')->ignore($privilege->id)],
            'description' => ['nullable', 'string'],
        ]);

        $privilege->fill($data);
        $dirty = $privilege->isDirty('slug');
        $privilege->save();

        if ($dirty) {
            TyroCache::forgetUsersByPrivilege($privilege);
        }

        return $privilege->fresh('roles:id,name,slug');
    }

    public function destroy(Privilege $privilege) {
        TyroCache::forgetUsersByPrivilege($privilege);
        $privilege->roles()->detach();
        $privilege->delete();

        return response(['error' => 0, 'message' => 'privilege has been deleted']);
    }
}
