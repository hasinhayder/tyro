<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Support\PasswordRules;
use HasinHayder\Tyro\Support\TyroAudit;
use HasinHayder\Tyro\Support\TyroCache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateUserCommand extends BaseTyroCommand {
    protected $signature = 'tyro:user-create {--name=} {--email=} {--password=}';

    protected $aliases = ['tyro:create-user'];

    protected $description = 'Create a new user and attach Tyro\'s default role';

    public function handle(): int {
        $name = $this->option('name') ?? $this->ask('Name (optional)', null);
        $email = $this->option('email') ?? $this->ask('Email');

        if (!$email) {
            $this->error('Email is required.');

            return self::FAILURE;
        }

        $validator = Validator::make([
            'email' => $email,
        ], [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first('email'));

            return self::FAILURE;
        }

        $userClass = $this->userClass();

        if ($userClass::where('email', $email)->exists()) {
            $this->error('A user with that email already exists.');

            return self::FAILURE;
        }

        $password = $this->option('password') ?? $this->secret('Password (leave blank to auto-generate)');
        $generatedPassword = false;

        if (!$password) {
            $password = Str::random(16);
            $generatedPassword = true;
        } else {
            $validator = Validator::make(['password' => $password], [
                'password' => PasswordRules::get(['name' => $name, 'email' => $email]),
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }

                return self::FAILURE;
            }
        }

        /** @var \Illuminate\Database\Eloquent\Model $user */
        $user = $userClass::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        TyroAudit::log('user.created', $user, null, $user->only(['name', 'email', 'id']));

        if (method_exists($user, 'assignRole')) {
            $defaultRole = $this->defaultRole();
            if ($defaultRole) {
                $user->assignRole($defaultRole);
            }
        }

        $this->info(sprintf('User %s created (ID: %s)', $user->email, $user->id));

        if ($generatedPassword) {
            $this->warn(sprintf('Generated password: %s', $password));
        }

        return self::SUCCESS;
    }
}
