<?php

namespace HasinHayder\Tyro\Database\Seeders;

use HasinHayder\Tyro\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder {
    public function run(): void {
        $userClass = config('tyro.models.user', config('auth.providers.users.model', 'App\\Models\\User'));

        /** @var \Illuminate\Database\Eloquent\Model $user */
        $user = $userClass::updateOrCreate(
            ['email' => 'admin@tyro.project'],
            [
                'password' => Hash::make('tyro'),
                'name' => 'Tyro Admin',
            ]
        );

        $adminRole = Role::where('slug', 'admin')->first();
        if ($adminRole) {
            $user->roles()->attach($adminRole);
        }
    }
}
