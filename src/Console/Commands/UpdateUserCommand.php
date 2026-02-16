<?php

namespace HasinHayder\Tyro\Console\Commands;

use HasinHayder\Tyro\Support\PasswordRules;
use HasinHayder\Tyro\Support\TyroAudit;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UpdateUserCommand extends BaseTyroCommand {
    protected $signature = 'tyro:user-update {--user=} {--name=} {--email=} {--password=}';

    protected $aliases = ['tyro:update-user'];

    protected $description = "Update a user's name, email and password";

    public function handle(): int {
        $identifier = $this->option('user') ?? $this->ask('User ID or email');

        if (!$identifier) {
            $this->error('A user identifier is required.');

            return self::FAILURE;
        }

        $user = $this->findUser($identifier);

        if (!$user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $nameInput = $this->option('name');
        if ($nameInput === null) {
            $nameInput = $this->ask('Name', (string) ($user->name ?? ''));
        }
        $name = trim((string) ($nameInput ?? ''));
        if ($name === '') {
            $name = (string) ($user->name ?? '');
        }

        $emailInput = $this->option('email');
        if ($emailInput === null) {
            $emailInput = $this->ask('Email', (string) $user->email);
        }
        $email = trim((string) ($emailInput ?? ''));

        if ($email === '') {
            $this->error('Email is required.');

            return self::FAILURE;
        }

        $validator = Validator::make([
            'email' => $email,
        ], [
            'email' => 'required|email|unique:' . $user->getTable() . ',email,' . $user->id,
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first('email'));

            return self::FAILURE;
        }

        while (true) {
            $passwordInput = $this->option('password');

            if ($passwordInput === null && $this->input->isInteractive() && !app()->runningUnitTests()) {
                $passwordInput = $this->secret('Password (leave blank to keep current)');
            }

            $passwordInput = $passwordInput !== null ? trim($passwordInput) : '';
            $password = $passwordInput === '' ? null : $passwordInput;

            if ($password === null) {
                break;
            }

            $pwValidator = Validator::make(['password' => $password], [
                'password' => PasswordRules::get(['name' => $name, 'email' => $email]),
            ]);

            if ($pwValidator->passes()) {
                break;
            }

            foreach ($pwValidator->errors()->all() as $error) {
                $this->error($error);
            }

            if ($this->option('password') !== null || !$this->input->isInteractive()) {
                return self::FAILURE;
            }

            $this->info('Please try again.');
        }

        if ($name === (string) ($user->name ?? '') && $email === $user->email && $password === null) {
            $this->info('No changes detected.');

            return self::SUCCESS;
        }

        $payload = [
            'name' => $name,
            'email' => $email,
        ];

        if ($password !== null) {
            $payload['password'] = Hash::make($password);
        }

        $oldValues = $user->only(['name', 'email']);
        $user->forceFill($payload)->save();

        TyroAudit::log('user.updated', $user, $oldValues, $user->only(['name', 'email']));

        $this->info(sprintf('User %s updated.', $user->email));

        return self::SUCCESS;
    }
}
