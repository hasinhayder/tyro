<?php

namespace HasinHayder\Tyro\Console\Commands;

use Illuminate\Support\Facades\Hash;

class LoginCommand extends BaseTyroCommand {
    protected $signature = 'tyro:login
        {--user= : User ID or email address}
        {--email= : [Deprecated] Email address}
        {--password= : The plain text password}
        {--name=Tyro CLI Token : Token name}';

    protected $description = 'Mint a Sanctum token for a user via the CLI';

    public function handle(): int {
        $identifier = $this->option('user')
            ?? $this->option('email')
            ?? $this->ask('User ID or email');
        $password = $this->option('password') ?? $this->secret('Password');
        $tokenName = $this->option('name') ?: 'Tyro CLI Token';

        if (!$identifier || !$password) {
            $this->error('A user identifier and password are required.');

            return self::FAILURE;
        }

        $user = $this->findUser($identifier);

        if (!$user || !Hash::check($password, $user->password)) {
            $this->error('Invalid credentials.');

            return self::FAILURE;
        }

        $isSuspended = method_exists($user, 'isSuspended')
            ? $user->isSuspended()
            : (bool) ($user->suspended_at ?? false);

        if ($isSuspended) {
            $reason = method_exists($user, 'getSuspensionReason')
                ? $user->getSuspensionReason()
                : ($user->suspension_reason ?? null);

            $message = 'User is suspended.';
            if ($reason) {
                $message .= ' Reason: ' . $reason;
            }

            $this->error($message);

            return self::FAILURE;
        }

        if (config('tyro.delete_previous_access_tokens_on_login', false)) {
            $user->tokens()->delete();
        }

        $abilities = $this->abilitiesForUser($user);
        $token = $user->createToken($tokenName ?: 'Tyro CLI Token', $abilities)->plainTextToken;

        $this->info('Token: ' . $token);
        $this->line(sprintf('User #%s (%s) now has a new token named "%s".', $user->id, $user->email, $tokenName));

        return self::SUCCESS;
    }
}
