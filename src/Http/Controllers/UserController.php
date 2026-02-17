<?php

namespace HasinHayder\Tyro\Http\Controllers;

use HasinHayder\Tyro\Models\Role;
use HasinHayder\Tyro\Support\PasswordRules;
use HasinHayder\Tyro\Support\TyroAudit;
use HasinHayder\Tyro\Support\TyroCache;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Exceptions\MissingAbilityException;

class UserController extends Controller {
    public function index() {
        return $this->userQuery()->get();
    }

    public function store(Request $request) {
        $creds = $request->validate([
            'email' => 'required|email',
            'password' => array_merge(['required'], PasswordRules::get(['name' => $request->name, 'email' => $request->email])),
            'name' => 'nullable|string',
        ]);

        $userClass = $this->userClass();
        $existing = $userClass::query()->where('email', $creds['email'])->first();

        if ($existing) {
            return response(['error' => 1, 'message' => 'user already exists'], 409);
        }

        /** @var \Illuminate\Database\Eloquent\Model $user */
        $user = $userClass::create([
            'email' => $creds['email'],
            'password' => Hash::make($creds['password']),
            'name' => $creds['name'],
        ]);

        TyroAudit::log('user.created', $user, null, $user->only(['name', 'email', 'id']));

        if (method_exists($user, 'assignRole')) {
            $defaultRoleSlug = config('tyro.default_user_role_slug', 'user');
            $user->assignRole(Role::where('slug', $defaultRoleSlug)->first());
        } else {
            $defaultRoleSlug = config('tyro.default_user_role_slug', 'user');
            $user->roles()->attach(Role::where('slug', $defaultRoleSlug)->first());
            TyroCache::forgetUser($user);
        }

        return $user;
    }

    public function login(Request $request) {
        $creds = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $userClass = $this->userClass();
        $user = $userClass::where('email', $creds['email'])->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response(['error' => 1, 'message' => 'invalid credentials'], 401);
        }

        if ($this->userIsSuspended($user)) {
            return response(['error' => 1, 'message' => 'user is suspended'], 423);
        }

        if (config('tyro.delete_previous_access_tokens_on_login', false)) {
            $user->tokens()->delete();
        }

        $roles = $user->roles->pluck('slug')->all();
        $token = $user->createToken('tyro-api-token', $roles)->plainTextToken;

        return response(['error' => 0, 'id' => $user->id, 'name' => $user->name, 'token' => $token], 200);
    }

    public function show($user) {
        return $this->resolveUser($user);
    }

    public function update(Request $request, $user) {
        $user = $this->resolveUser($user);

        if ($request->password) {
            $request->validate([
                'password' => PasswordRules::get([
                    'name' => $request->name ?? $user->name,
                    'email' => $request->email ?? $user->email
                ]),
            ]);
        }

        $oldValues = [
            'name' => $user->name,
            'email' => $user->email,
        ];
        $passwordChanged = (bool) $request->password;

        $user->name = $request->name ?? $user->name;
        $user->email = $request->email ?? $user->email;
        $user->password = $passwordChanged ? Hash::make($request->password) : $user->password;

        $loggedInUser = $request->user();
        $authorized = $loggedInUser->id === $user->id
            || $loggedInUser->tokenCan('admin')
            || $loggedInUser->tokenCan('super-admin');

        if ($authorized) {
            $user->save();

            if ($oldValues['email'] !== $user->email) {
                TyroAudit::log('user.email_changed', $user, ['email' => $oldValues['email']], ['email' => $user->email]);
            }

            return $user;
        }

        throw new MissingAbilityException('Not Authorized');
    }

    public function destroy($user) {
        $user = $this->resolveUser($user);
        $adminRole = Role::where('slug', 'admin')->first();

        if ($adminRole && $user->roles->contains($adminRole)) {
            $adminCount = $adminRole->users()->count();
            if ($adminCount === 1) {
                return response(['error' => 1, 'message' => 'Create another admin before deleting this only admin user'], 409);
            }
        }

        TyroAudit::log('user.deleted', $user, $user->only(['name', 'email', 'id']));

        $user->delete();
        TyroCache::forgetUser($user);

        return response(['error' => 0, 'message' => 'user deleted']);
    }

    public function me(Request $request) {
        return $request->user();
    }

    protected function userClass(): string {
        return config('tyro.models.user', config('auth.providers.users.model', 'App\\Models\\User'));
    }

    protected function userQuery() {
        $class = $this->userClass();

        return $class::query();
    }

    protected function resolveUser($user) {
        if ($user instanceof \Illuminate\Contracts\Auth\Authenticatable) {
            return $user;
        }

        return $this->userQuery()->findOrFail($user);
    }

    protected function userIsSuspended($user): bool {
        if (method_exists($user, 'isSuspended')) {
            return $user->isSuspended();
        }

        return (bool) ($user->suspended_at ?? false);
    }
}
